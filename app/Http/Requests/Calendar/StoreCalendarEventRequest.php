<?php

namespace App\Http\Requests\Calendar;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCalendarEventRequest extends FormRequest
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
            'type' => ['required', Rule::in(['hearing', 'court_mention', 'meeting', 'deadline', 'other'])],
            'status' => ['nullable', Rule::in(['scheduled', 'completed', 'adjourned', 'cancelled', 'rescheduled'])],
            'date' => ['required', 'date'],
            'start_time' => ['nullable', 'regex:/^\d{2}:\d{2}(:\d{2})?$/'],
            'end_time' => ['nullable', 'regex:/^\d{2}:\d{2}(:\d{2})?$/'],
            'all_day' => ['nullable', 'boolean'],
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
            'assigned_user_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where(fn ($query) => $query->where('firm_id', $firmId)),
            ],
            'location' => ['nullable', 'string', 'max:255'],
            'purpose' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'reminder' => ['nullable', 'string', 'max:20'],
            'reschedule' => ['nullable', 'boolean'],
        ];
    }
}
