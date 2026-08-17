<?php

namespace App\Http\Requests\Document;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDocumentsRequest extends FormRequest
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
            'files' => ['required', 'array', 'min:1', 'max:20'],
            'files.*' => ['file', 'max:25600', 'mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png'],
            'relative_paths' => ['nullable', 'array'],
            'relative_paths.*' => ['nullable', 'string', 'max:500'],
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
            'notes' => ['nullable', 'string'],
            'allowed_user_ids' => ['nullable', 'array'],
            'allowed_user_ids.*' => [
                'integer',
                Rule::exists('users', 'id')->where(fn ($query) => $query->where('firm_id', $firmId)),
            ],
        ];
    }
}
