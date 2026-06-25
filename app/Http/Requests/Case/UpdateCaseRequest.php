<?php

namespace App\Http\Requests\Case;

use App\Enums\CaseStatus;
use App\Enums\ClaimStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $caseId = $this->route('case')?->id ?? $this->route('case');

        return [
            'case_number' => ['sometimes', 'string', 'max:100', Rule::unique('cases', 'case_number')->ignore($caseId)],
            'client_id' => ['sometimes', 'exists:clients,id'],
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'court_name' => ['nullable', 'string', 'max:255'],
            'court_date' => ['nullable', 'date'],
            'claim_status' => ['sometimes', Rule::in(ClaimStatus::values())],
            'case_status' => ['sometimes', Rule::in(CaseStatus::values())],
            'assigned_to' => ['nullable', 'exists:users,id'],
        ];
    }
}
