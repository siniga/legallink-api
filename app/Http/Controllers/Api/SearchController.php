<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Document;
use App\Models\LegalCase;
use App\Models\Task;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = trim((string) $request->input('q', $request->input('search', '')));
        $limit = min(max((int) $request->integer('limit', 8), 1), 25);

        if ($query === '') {
            return response()->json($this->emptyPayload($query));
        }

        $user = $request->user();
        $like = '%'.$query.'%';

        $cases = $this->group(
            LegalCase::query()
                ->with(['client', 'caseStatus'])
                ->where(function (Builder $builder) use ($like) {
                    $builder->where('case_number', 'like', $like)
                        ->orWhere('title', 'like', $like)
                        ->orWhere('court', 'like', $like)
                        ->orWhereHas('client', fn (Builder $client) => $client->where('name', 'like', $like));
                })
                ->latest('updated_at'),
            $limit,
            fn (LegalCase $case) => [
                'id' => (string) $case->id,
                'type' => 'case',
                'title' => $case->title,
                'subtitle' => collect([$case->case_number, $case->client?->name, $case->caseStatus?->name])
                    ->filter()
                    ->implode(' · '),
                'href' => '/cases/'.$case->id,
            ],
        );

        $clients = $this->group(
            Client::query()
                ->where(function (Builder $builder) use ($like) {
                    $builder->where('name', 'like', $like)
                        ->orWhere('email', 'like', $like)
                        ->orWhere('phone', 'like', $like)
                        ->orWhere('registration_number', 'like', $like)
                        ->orWhereHas('contacts', fn (Builder $contacts) => $contacts->where('name', 'like', $like));
                })
                ->latest('updated_at'),
            $limit,
            fn (Client $client) => [
                'id' => (string) $client->id,
                'type' => 'client',
                'title' => $client->name,
                'subtitle' => collect([
                    $client->type === 'company' ? 'Company' : 'Individual',
                    $client->email ?: $client->phone,
                    $client->status === 'archived' ? 'Archived' : null,
                ])->filter()->implode(' · '),
                'href' => '/clients?client='.$client->id,
            ],
        );

        $documentsQuery = Document::query()
            ->with(['client', 'legalCase'])
            ->where(function (Builder $builder) use ($like) {
                $builder->where('name', 'like', $like)
                    ->orWhere('original_name', 'like', $like)
                    ->orWhereHas('client', fn (Builder $client) => $client->where('name', 'like', $like))
                    ->orWhereHas('legalCase', function (Builder $case) use ($like) {
                        $case->where(function (Builder $inner) use ($like) {
                            $inner->where('title', 'like', $like)
                                ->orWhere('case_number', 'like', $like);
                        });
                    });
            })
            ->latest('updated_at');

        if ($user) {
            $documentsQuery->visibleTo($user);
        }

        $documents = $this->group(
            $documentsQuery,
            $limit,
            function (Document $document) {
                $related = $document->legalCase?->title
                    ?: $document->client?->name
                    ?: ($document->is_folder ? 'Folder' : 'Document');

                return [
                    'id' => (string) $document->id,
                    'type' => 'document',
                    'title' => $document->name,
                    'subtitle' => collect([$document->is_folder ? 'Folder' : strtoupper((string) $document->kind), $related])
                        ->filter()
                        ->implode(' · '),
                    'href' => $document->is_folder
                        ? '/documents?folder='.$document->id
                        : '/documents?id='.$document->id,
                ];
            },
        );

        $tasks = $this->group(
            Task::query()
                ->with(['client', 'legalCase', 'assignee'])
                ->where(function (Builder $builder) use ($like) {
                    $builder->where('title', 'like', $like)
                        ->orWhere('description', 'like', $like)
                        ->orWhereHas('client', fn (Builder $client) => $client->where('name', 'like', $like))
                        ->orWhereHas('legalCase', function (Builder $case) use ($like) {
                            $case->where(function (Builder $inner) use ($like) {
                                $inner->where('title', 'like', $like)
                                    ->orWhere('case_number', 'like', $like);
                            });
                        });
                })
                ->latest('updated_at'),
            $limit,
            fn (Task $task) => [
                'id' => (string) $task->id,
                'type' => 'task',
                'title' => $task->title,
                'subtitle' => collect([
                    $task->legalCase?->case_number ?: $task->client?->name,
                    $task->assignee?->name,
                    $task->status ? str_replace('_', ' ', $task->status) : null,
                ])->filter()->implode(' · '),
                'href' => '/tasks?task='.$task->id,
            ],
        );

        return response()->json([
            'query' => $query,
            'total' => $cases['total'] + $clients['total'] + $documents['total'] + $tasks['total'],
            'cases' => $cases,
            'clients' => $clients,
            'documents' => $documents,
            'tasks' => $tasks,
        ]);
    }

    /**
     * @param  callable(mixed): array<string, mixed>  $map
     * @return array{total: int, data: array<int, array<string, mixed>>}
     */
    private function group(Builder $query, int $limit, callable $map): array
    {
        $total = (clone $query)->count();

        return [
            'total' => $total,
            'data' => $query->limit($limit)->get()->map($map)->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyPayload(string $query): array
    {
        $empty = ['total' => 0, 'data' => []];

        return [
            'query' => $query,
            'total' => 0,
            'cases' => $empty,
            'clients' => $empty,
            'documents' => $empty,
            'tasks' => $empty,
        ];
    }
}
