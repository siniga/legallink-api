<?php

namespace App\Http\Requests\LegalCase;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCaseRequest extends FormRequest
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
            'case_number' => [
                'required',
                'string',
                'max:100',
                Rule::unique('cases', 'case_number')
                    ->where(fn ($query) => $query->where('firm_id', $firmId)->whereNull('deleted_at')),
            ],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'client_id' => [
                'required',
                'integer',
                Rule::exists('clients', 'id')->where(fn ($query) => $query->where('firm_id', $firmId)),
            ],
            'case_type_id' => [
                'nullable',
                'integer',
                Rule::exists('case_types', 'id')->where(fn ($query) => $query->where('firm_id', $firmId)),
            ],
            'case_status_id' => [
                'nullable',
                'integer',
                Rule::exists('case_statuses', 'id')->where(fn ($query) => $query->where('firm_id', $firmId)),
            ],
            'court' => ['nullable', 'string', 'max:255'],
            'hearing_at' => ['nullable', 'date'],
            'assigned_user_ids' => ['nullable', 'array'],
            'assigned_user_ids.*' => [
                'integer',
                Rule::exists('users', 'id')->where(fn ($query) => $query->where('firm_id', $firmId)),
            ],
        ];
    }
}
