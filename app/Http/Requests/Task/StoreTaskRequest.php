<?php

namespace App\Http\Requests\Task;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTaskRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'case_id' => [
                'nullable',
                'integer',
                Rule::exists('cases', 'id')->where(fn ($query) => $query
                    ->where('firm_id', $firmId)
                    ->whereNull('deleted_at')),
            ],
            'client_id' => [
                'nullable',
                'integer',
                Rule::exists('clients', 'id')->where(fn ($query) => $query
                    ->where('firm_id', $firmId)
                    ->whereNull('deleted_at')),
            ],
            'assignee_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where(fn ($query) => $query->where('firm_id', $firmId)),
            ],
            'due_date' => ['nullable', 'date'],
            'due_time' => ['nullable', 'regex:/^\d{2}:\d{2}(:\d{2})?$/'],
            'priority' => ['nullable', Rule::in(['low', 'medium', 'high'])],
            'status' => ['nullable', Rule::in(['pending', 'in_progress', 'submitted', 'completed', 'cancelled'])],
            'reminder_offset' => ['nullable', Rule::in(['1h', '3h', '1d', '2d'])],
        ];
    }
}
