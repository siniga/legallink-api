<?php

namespace App\Http\Requests\CaseTask;

use App\Enums\TaskStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCaseTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'case_id' => ['nullable', 'exists:cases,id'],
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'assigned_to' => ['sometimes', 'exists:users,id'],
            'status' => ['sometimes', Rule::in(TaskStatus::values())],
            'due_date' => ['nullable', 'date'],
        ];
    }
}
