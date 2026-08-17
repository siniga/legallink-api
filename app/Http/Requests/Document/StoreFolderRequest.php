<?php

namespace App\Http\Requests\Document;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFolderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->firm_id;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $firmId = $this->user()?->firm_id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists('documents', 'id')->where(fn ($query) => $query
                    ->where('firm_id', $firmId)
                    ->where('is_folder', true)
                    ->whereNull('deleted_at')),
            ],
            'visibility' => ['required', Rule::in(['private', 'firm', 'restricted'])],
            'client_id' => [
                'nullable',
                'integer',
                Rule::exists('clients', 'id')->where(fn ($query) => $query->where('firm_id', $firmId)),
            ],
            'case_id' => [
                'nullable',
                'integer',
                Rule::exists('cases', 'id')->where(fn ($query) => $query->where('firm_id', $firmId)),
            ],
            'allowed_user_ids' => ['nullable', 'array'],
            'allowed_user_ids.*' => [
                'integer',
                Rule::exists('users', 'id')->where(fn ($query) => $query->where('firm_id', $firmId)),
            ],
            'access_roles' => ['nullable', 'array'],
        ];
    }
}
