<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class ClientDetailResource extends ClientResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return array_merge(parent::toArray($request), [
            'contacts' => $this->whenLoaded('contacts', fn () => $this->contacts->map(fn ($contact) => [
                'id' => $contact->id,
                'client_id' => $contact->client_id,
                'name' => $contact->name,
                'title' => $contact->title,
                'phone' => $contact->phone,
                'email' => $contact->email,
                'primary' => $contact->is_primary,
            ])->values()),
            'notes' => $this->whenLoaded('clientNotes', fn () => $this->clientNotes->map(fn ($note) => [
                'id' => $note->id,
                'client_id' => $note->client_id,
                'text' => $note->body,
                'created_at' => optional($note->created_at)?->toIso8601String(),
            ])->values()),
            'cases' => $this->whenLoaded('cases', fn () => $this->cases->map(function ($case) {
                $next = $case->relationLoaded('calendarEvents')
                    ? $case->calendarEvents
                        ->where('status', 'scheduled')
                        ->whereIn('type', ['hearing', 'court_mention'])
                        ->sortBy('starts_at')
                        ->first()
                    : null;

                return [
                    'id' => $case->id,
                    'client_id' => $case->client_id,
                    'title' => $case->title,
                    'case_number' => $case->case_number,
                    'status' => $case->caseStatus?->name ?? 'Open',
                    'next_hearing' => $next?->starts_at?->format('d M Y, g:i A') ?? '—',
                ];
            })->values()),
            'documents' => $this->whenLoaded('documents', fn () => $this->documents
                ->where('is_folder', false)
                ->values()
                ->map(fn ($document) => [
                    'id' => $document->id,
                    'client_id' => $document->client_id,
                    'name' => $document->name,
                    'category' => $document->kind === 'folder' ? 'Folder' : ucfirst($document->kind),
                    'modified' => optional($document->updated_at)?->diffForHumans(),
                    'kind' => in_array($document->kind, ['pdf', 'word', 'excel', 'image'], true) ? $document->kind : 'other',
                ])),
            'activity' => $this->activity_items ?? [],
        ]);
    }
}
