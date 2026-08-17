<?php

namespace App\Http\Requests\Document;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $document = $this->route('document');

        return $document && $this->user() && $document->isEditableBy($this->user());
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $firmId = $this->user()?->firm_id;

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'parent_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('documents', 'id')->where(fn ($query) => $query
                    ->where('firm_id', $firmId)
                    ->where('is_folder', true)
                    ->whereNull('deleted_at')),
            ],
            'visibility' => ['sometimes', Rule::in(['private', 'firm', 'restricted'])],
            'allowed_user_ids' => ['nullable', 'array'],
            'allowed_user_ids.*' => [
                'integer',
                Rule::exists('users', 'id')->where(fn ($query) => $query->where('firm_id', $firmId)),
            ],
            'access_roles' => ['nullable', 'array'],
        ];
    }
}
