<?php

namespace App\Http\Requests\Case;

use App\Enums\CaseStatus;
use App\Enums\ClaimStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'case_number' => ['required', 'string', 'max:100', 'unique:cases,case_number'],
            'client_id' => ['required', 'exists:clients,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'court_name' => ['nullable', 'string', 'max:255'],
            'court_date' => ['nullable', 'date'],
            'claim_status' => ['required', Rule::in(ClaimStatus::values())],
            'case_status' => ['sometimes', Rule::in(CaseStatus::values())],
            'assigned_to' => ['nullable', 'exists:users,id'],
        ];
    }
}
