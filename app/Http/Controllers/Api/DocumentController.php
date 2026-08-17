<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Document\StoreDocumentsRequest;
use App\Http\Requests\Document\StoreFolderRequest;
use App\Http\Requests\Document\UpdateDocumentRequest;
use App\Http\Resources\DocumentResource;
use App\Models\Client;
use App\Models\Document;
use App\Models\LegalCase;
use App\Models\User;
use App\Services\Auditor;
use App\Services\Notifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user?->firm_id) {
            return response()->json([
                'data' => [],
                'breadcrumbs' => [],
                'recent' => [],
                'roots' => ['my_docs' => null, 'clients' => null, 'cases' => null, 'firm' => null],
                'lookups' => ['clients' => [], 'cases' => [], 'users' => [], 'folders' => []],
            ]);
        }

        $roots = $this->ensureRoots($user);
        $scope = $request->string('scope')->toString();
        $parentId = $request->filled('parent_id') ? $request->integer('parent_id') : null;

        $query = Document::query()
            ->with(['owner', 'client', 'legalCase', 'accessUsers'])
            ->visibleTo($user);

        $breadcrumbs = [];

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $query->where(function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhereHas('client', fn ($client) => $client->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('legalCase', fn ($case) => $case->where('title', 'like', "%{$search}%"));
            });
        } elseif ($scope === 'recent') {
            $query->where('is_folder', false)->latest('updated_at')->limit(20);
        } elseif ($scope === 'shared') {
            $sharedIds = Document::query()
                ->where('owner_id', '!=', $user->id)
                ->whereHas('accessUsers', fn ($access) => $access->where('users.id', $user->id))
                ->pluck('id');

            $sharedFolderIds = Document::query()
                ->where('is_folder', true)
                ->whereIn('id', $sharedIds)
                ->pluck('id')
                ->all();

            $query->whereIn('id', $sharedIds->merge($this->descendantIds($sharedFolderIds))->unique()->values());
        } elseif ($parentId) {
            $parent = Document::query()->visibleTo($user)->findOrFail($parentId);
            $query->where('parent_id', $parent->id);
            $breadcrumbs = $this->breadcrumbs($parent);
        } else {
            $query->whereNull('parent_id');
        }

        if ($request->filled('type')) {
            $query->where('kind', $request->input('type'));
        }
        if ($request->filled('owner')) {
            $owner = $request->input('owner');
            if (is_numeric($owner)) {
                $query->where('owner_id', (int) $owner);
            } else {
                $query->whereHas('owner', fn ($member) => $member->where('name', $owner));
            }
        }
        if ($request->filled('visibility')) {
            $query->where('visibility', $request->input('visibility'));
        }

        $this->applySort($query, $request);

        $items = $query->limit(200)->get();

        $recent = Document::query()
            ->with(['owner', 'client', 'legalCase', 'accessUsers'])
            ->visibleTo($user)
            ->where('is_folder', false)
            ->latest('updated_at')
            ->limit(8)
            ->get();

        return response()->json([
            'data' => DocumentResource::collection($items),
            'breadcrumbs' => $breadcrumbs,
            'recent' => DocumentResource::collection($recent),
            'roots' => $roots,
            'lookups' => $this->lookups($user),
        ]);
    }

    public function show(Request $request, Document $document): DocumentResource
    {
        $this->authorizeView($request, $document);

        return new DocumentResource($document->load(['owner', 'client', 'legalCase', 'accessUsers']));
    }

    public function store(StoreDocumentsRequest $request): JsonResponse
    {
        $user = $request->user();
        $parent = $this->parentFolder($request->integer('parent_id') ?: null);
        $created = [];
        $files = $request->file('files', []);
        $paths = $request->input('relative_paths', []);

        foreach ($files as $index => $file) {
            $relative = is_array($paths) ? (string) ($paths[$index] ?? $file->getClientOriginalName()) : $file->getClientOriginalName();
            $relative = str_replace('\\', '/', $relative);
            $segments = array_values(array_filter(explode('/', $relative)));
            $fileName = array_pop($segments) ?: $file->getClientOriginalName();
            $folder = $parent;

            foreach ($segments as $segment) {
                $folder = $this->findOrCreateFolder($folder, $segment, $request, $user);
            }

            $storedName = Str::uuid().'_'.Str::slug(pathinfo($fileName, PATHINFO_FILENAME)).'.'.$file->getClientOriginalExtension();
            $path = $file->storeAs('documents/'.$user->firm_id, $storedName, 'local');

            $document = Document::query()->create([
                'parent_id' => $folder?->id,
                'is_folder' => false,
                'name' => $fileName,
                'kind' => $this->kindFromName($fileName),
                'client_id' => $request->input('client_id') ?: $folder?->client_id,
                'case_id' => $request->input('case_id') ?: $folder?->case_id,
                'owner_id' => $user->id,
                'visibility' => $request->input('visibility'),
                'disk' => 'local',
                'path' => $path,
                'original_name' => $fileName,
                'mime_type' => $file->getMimeType(),
                'size_bytes' => $file->getSize(),
                'notes' => $request->input('notes'),
                'created_by' => $user->id,
            ]);

            $this->syncAccess($document, $request->input('allowed_user_ids', []), $request->input('access_roles', []));
            $created[] = $document->load(['owner', 'client', 'legalCase', 'accessUsers']);
            Auditor::record(
                action: 'upload',
                module: 'documents',
                subject: $document,
                resourceName: $document->name,
                details: $document->legalCase?->title ?: $document->client?->name,
            );
            Notifier::documentUploaded($document, $user);
        }

        return response()->json([
            'message' => 'Files uploaded.',
            'data' => DocumentResource::collection($created),
        ], 201);
    }

    public function storeFolder(StoreFolderRequest $request): JsonResponse
    {
        $user = $request->user();
        $parent = $this->parentFolder($request->integer('parent_id') ?: null);

        $folder = Document::query()->create([
            'parent_id' => $parent?->id,
            'is_folder' => true,
            'name' => $request->validated('name'),
            'kind' => 'folder',
            'client_id' => $request->input('client_id') ?: $parent?->client_id,
            'case_id' => $request->input('case_id') ?: $parent?->case_id,
            'owner_id' => $user->id,
            'visibility' => $request->input('visibility'),
            'created_by' => $user->id,
        ]);

        $this->syncAccess($folder, $request->input('allowed_user_ids', []), $request->input('access_roles', []));

        Auditor::record(
            action: 'create',
            module: 'documents',
            subject: $folder,
            resourceName: $folder->name,
            details: 'Folder created',
        );

        return response()->json([
            'message' => 'Folder created.',
            'data' => new DocumentResource($folder->load(['owner', 'client', 'legalCase', 'accessUsers'])),
        ], 201);
    }

    public function update(UpdateDocumentRequest $request, Document $document): JsonResponse
    {
        $data = $request->validated();

        if (array_key_exists('parent_id', $data)) {
            $this->assertNotMovingIntoSelf($document, $data['parent_id']);
        }

        $previousAccess = $document->accessUsers()->pluck('users.id')->all();
        $oldName = $document->name;
        $oldVisibility = $document->visibility;
        $document->fill(array_intersect_key($data, array_flip(['name', 'parent_id', 'visibility'])));
        $document->save();

        if ($request->has('allowed_user_ids') || $request->has('visibility')) {
            $this->syncAccess($document, $request->input('allowed_user_ids', []), $request->input('access_roles', []));
        }

        $action = $request->has('allowed_user_ids')
            ? 'share'
            : (array_key_exists('visibility', $data) && $oldVisibility !== $document->visibility ? 'permission_change' : 'update');

        Auditor::record(
            action: $action,
            module: 'documents',
            subject: $document,
            resourceName: $document->name,
            details: match ($action) {
                'share' => 'Sharing updated',
                'permission_change' => 'Visibility changed',
                default => $oldName !== $document->name ? 'Renamed' : 'Document updated',
            },
            oldValue: $oldName !== $document->name ? $oldName : ($oldVisibility !== $document->visibility ? $oldVisibility : null),
            newValue: $oldName !== $document->name ? $document->name : ($oldVisibility !== $document->visibility ? $document->visibility : null),
        );

        if ($action === 'share') {
            $document->load('accessUsers');
            $added = $document->accessUsers->whereNotIn('id', $previousAccess);
            Notifier::documentShared($document, $added, $request->user());
        }

        return response()->json([
            'message' => 'Document updated.',
            'data' => new DocumentResource($document->load(['owner', 'client', 'legalCase', 'accessUsers'])),
        ]);
    }

    public function copy(Request $request, Document $document): JsonResponse
    {
        $this->authorizeView($request, $document);
        $document->load(['accessUsers', 'children']);
        $copy = $this->copyTree($document, $document->parent_id, $request->user());

        Auditor::record(
            action: 'create',
            module: 'documents',
            subject: $copy,
            resourceName: $copy->name,
            details: 'Copied from '.$document->name,
        );

        return response()->json([
            'message' => 'Copied.',
            'data' => new DocumentResource($copy->load(['owner', 'client', 'legalCase', 'accessUsers'])),
        ], 201);
    }

    public function archive(Request $request, Document $document): JsonResponse
    {
        $this->authorizeEdit($request, $document);
        Auditor::record(
            action: 'delete',
            module: 'documents',
            subject: $document,
            resourceName: $document->name,
            details: 'Document archived',
        );
        Notifier::documentDeleted($document, $request->user());
        $this->deleteTree($document);

        return response()->json(['message' => 'Document archived.']);
    }

    public function destroy(Request $request, Document $document): JsonResponse
    {
        $this->authorizeEdit($request, $document);
        Auditor::record(
            action: 'delete',
            module: 'documents',
            subject: $document,
            resourceName: $document->name,
            details: 'Document deleted',
        );
        Notifier::documentDeleted($document, $request->user());
        $this->deleteTree($document);

        return response()->json(['message' => 'Document deleted.']);
    }

    public function download(Request $request, Document $document): StreamedResponse|JsonResponse
    {
        $this->authorizeView($request, $document);

        if ($document->is_folder || ! $document->hasStoredFile()) {
            return response()->json(['message' => 'This file is not available for download.'], 404);
        }

        Auditor::record(
            action: 'download',
            module: 'documents',
            subject: $document,
            resourceName: $document->name,
            details: $document->legalCase?->title ?: $document->client?->name,
        );

        return Storage::disk($document->disk ?: 'local')->download(
            $document->path,
            $document->original_name ?: $document->name,
        );
    }

    /**
     * @return array{my_docs: int, clients: int, cases: int, firm: int}
     */
    private function ensureRoots(User $user): array
    {
        $firmFolders = [
            'clients' => ['Clients', 'firm'],
            'cases' => ['Cases', 'firm'],
            'firm' => ['Firm Documents', 'firm'],
        ];

        $roots = [];
        foreach ($firmFolders as $key => [$name, $visibility]) {
            $folder = Document::query()
                ->whereNull('parent_id')
                ->where('is_folder', true)
                ->where('name', $name)
                ->first();

            if (! $folder) {
                $folder = Document::query()->create([
                    'parent_id' => null,
                    'is_folder' => true,
                    'name' => $name,
                    'kind' => 'folder',
                    'owner_id' => $user->id,
                    'visibility' => $visibility,
                    'created_by' => $user->id,
                ]);
            }
            $roots[$key] = $folder->id;
        }

        $mine = Document::query()
            ->whereNull('parent_id')
            ->where('is_folder', true)
            ->where('name', 'My Documents')
            ->where('owner_id', $user->id)
            ->first();

        if (! $mine) {
            $mine = Document::query()->create([
                'parent_id' => null,
                'is_folder' => true,
                'name' => 'My Documents',
                'kind' => 'folder',
                'owner_id' => $user->id,
                'visibility' => 'private',
                'created_by' => $user->id,
            ]);
        }
        $roots['my_docs'] = $mine->id;

        return $roots;
    }

    private function parentFolder(?int $parentId): ?Document
    {
        if (! $parentId) {
            return null;
        }

        return Document::query()->where('is_folder', true)->findOrFail($parentId);
    }

    private function findOrCreateFolder(?Document $parent, string $name, StoreDocumentsRequest $request, User $user): Document
    {
        $existing = Document::query()
            ->where('parent_id', $parent?->id)
            ->where('is_folder', true)
            ->where('name', $name)
            ->first();

        if ($existing) {
            return $existing;
        }

        $folder = Document::query()->create([
            'parent_id' => $parent?->id,
            'is_folder' => true,
            'name' => $name,
            'kind' => 'folder',
            'client_id' => $request->input('client_id') ?: $parent?->client_id,
            'case_id' => $request->input('case_id') ?: $parent?->case_id,
            'owner_id' => $user->id,
            'visibility' => $request->input('visibility'),
            'created_by' => $user->id,
        ]);
        $this->syncAccess($folder, $request->input('allowed_user_ids', []), $request->input('access_roles', []));

        return $folder;
    }

    /**
     * @param  list<int|string>  $userIds
     * @param  array<string, string>  $roles
     */
    private function syncAccess(Document $document, array $userIds, array $roles): void
    {
        if ($document->visibility === 'private') {
            $document->accessUsers()->sync([]);

            return;
        }

        $sync = [];
        foreach ($userIds as $userId) {
            $id = (int) $userId;
            if ($id < 1 || $id === (int) $document->owner_id) {
                continue;
            }
            $access = $roles[(string) $userId] ?? $roles[$userId] ?? $roles[(string) $id] ?? 'viewer';
            if ($access === 'owner') {
                $access = 'editor';
            }
            $sync[$id] = ['access' => in_array($access, ['viewer', 'editor'], true) ? $access : 'viewer'];
        }
        $document->accessUsers()->sync($sync);
    }

    /**
     * @param  list<int>  $folderIds
     * @return list<int>
     */
    private function descendantIds(array $folderIds): array
    {
        $found = [];
        $queue = array_values(array_unique(array_filter(array_map('intval', $folderIds))));

        while ($queue) {
            $children = Document::query()->whereIn('parent_id', $queue)->pluck('id')->all();
            $queue = [];
            foreach ($children as $id) {
                $id = (int) $id;
                if (! isset($found[$id])) {
                    $found[$id] = true;
                    $queue[] = $id;
                }
            }
        }

        return array_keys($found);
    }

    private function applySort($query, Request $request): void
    {
        $direction = $request->input('dir') === 'desc' ? 'desc' : 'asc';
        $sort = $request->string('sort')->toString();

        $query->orderByRaw('is_folder desc');

        match ($sort) {
            'modified' => $query->orderBy('updated_at', $direction),
            'size' => $query->orderBy('size_bytes', $direction),
            'owner' => $query->orderBy(
                User::query()->select('name')->whereColumn('users.id', 'documents.owner_id'),
                $direction,
            ),
            default => $query->orderBy('name', $direction),
        };
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    private function breadcrumbs(Document $folder): array
    {
        $trail = [];
        $current = $folder;
        while ($current) {
            array_unshift($trail, ['id' => $current->id, 'name' => $current->name]);
            $current = $current->parent_id ? Document::query()->find($current->parent_id) : null;
        }

        return $trail;
    }

    /**
     * @return array<string, mixed>
     */
    private function lookups(User $user): array
    {
        $folders = Document::query()
            ->visibleTo($user)
            ->where('is_folder', true)
            ->orderBy('name')
            ->get();

        return [
            'clients' => Client::query()->where('status', '!=', 'archived')->orderBy('name')->get(['id', 'name'])
                ->map(fn (Client $client) => ['id' => $client->id, 'name' => $client->name])
                ->values()
                ->all(),
            'cases' => LegalCase::query()->with('client')->orderBy('title')->get(['id', 'title', 'client_id'])
                ->map(fn (LegalCase $case) => [
                    'id' => $case->id,
                    'title' => $case->title,
                    'client_id' => $case->client_id,
                    'client' => $case->client?->name,
                ])
                ->values()
                ->all(),
            'users' => User::query()
                ->where('firm_id', $user->firm_id)
                ->where('status', '!=', 'inactive')
                ->whereNull('deactivated_at')
                ->orderBy('name')
                ->get()
                ->map(fn (User $member) => [
                    'id' => $member->id,
                    'name' => $member->name,
                    'initials' => $member->initials,
                    'role' => $member->job_role ?: 'Team member',
                ])
                ->values()
                ->all(),
            'folders' => $folders->map(fn (Document $folder) => [
                'id' => $folder->id,
                'label' => collect($this->breadcrumbs($folder))->pluck('name')->implode(' / '),
            ])->values()->all(),
        ];
    }

    private function copyTree(Document $source, ?int $parentId, User $user): Document
    {
        $copy = $source->replicate(['path', 'original_name']);
        $copy->parent_id = $parentId;
        $copy->owner_id = $user->id;
        $copy->created_by = $user->id;
        $copy->name = $parentId === $source->parent_id ? $source->name.' copy' : $source->name;

        if (! $source->is_folder && $source->hasStoredFile()) {
            $extension = pathinfo((string) $source->path, PATHINFO_EXTENSION);
            $newPath = 'documents/'.$user->firm_id.'/'.Str::uuid().($extension ? '.'.$extension : '');
            Storage::disk($source->disk ?: 'local')->copy($source->path, $newPath);
            $copy->path = $newPath;
            $copy->original_name = $source->original_name;
            $copy->disk = $source->disk;
        }

        $copy->save();
        if ($source->relationLoaded('accessUsers')) {
            $copy->accessUsers()->sync(
                $source->accessUsers->mapWithKeys(fn ($accessUser) => [
                    $accessUser->id => ['access' => $accessUser->pivot->access],
                ])->all()
            );
        }

        $children = $source->relationLoaded('children') ? $source->children : $source->children()->get();
        foreach ($children as $child) {
            $child->load(['accessUsers', 'children']);
            $this->copyTree($child, $copy->id, $user);
        }

        return $copy;
    }

    private function deleteTree(Document $document): void
    {
        foreach ($document->children()->get() as $child) {
            $this->deleteTree($child);
        }

        if ($document->path && $document->hasStoredFile()) {
            Storage::disk($document->disk ?: 'local')->delete($document->path);
        }

        $document->delete();
    }

    private function assertNotMovingIntoSelf(Document $document, mixed $parentId): void
    {
        if (! $parentId || (int) $parentId === (int) $document->id) {
            if ((int) $parentId === (int) $document->id) {
                abort(422, 'A folder cannot be moved into itself.');
            }

            return;
        }

        $current = Document::query()->find($parentId);
        while ($current) {
            if ((int) $current->id === (int) $document->id) {
                abort(422, 'A folder cannot be moved into itself.');
            }
            $current = $current->parent_id ? Document::query()->find($current->parent_id) : null;
        }
    }

    private function kindFromName(string $name): string
    {
        $ext = strtolower((string) pathinfo($name, PATHINFO_EXTENSION));

        return match ($ext) {
            'pdf' => 'pdf',
            'doc', 'docx' => 'word',
            'xls', 'xlsx' => 'excel',
            'jpg', 'jpeg', 'png', 'gif', 'webp' => 'image',
            default => 'other',
        };
    }

    private function authorizeView(Request $request, Document $document): void
    {
        if (! $request->user() || ! $document->isVisibleTo($request->user())) {
            abort(404);
        }
    }

    private function authorizeEdit(Request $request, Document $document): void
    {
        if (! $request->user() || ! $document->isEditableBy($request->user())) {
            abort(403, 'You cannot change this document.');
        }
    }
}
