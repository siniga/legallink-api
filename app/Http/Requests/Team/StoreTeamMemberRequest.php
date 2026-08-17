<?php

namespace App\Http\Requests\Team;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTeamMemberRequest extends FormRequest
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
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:50'],
            'job_role' => ['required', Rule::in([
                'managing_partner',
                'partner',
                'senior_associate',
                'associate',
                'paralegal',
                'legal_assistant',
                'finance_admin',
                'intern',
            ])],
            'access_role' => [
                'required',
                'string',
                Rule::exists('roles', 'slug')->where(fn ($query) => $query->where('firm_id', $firmId)),
            ],
            'status' => ['nullable', Rule::in(['active', 'away', 'inactive'])],
        ];
    }
}
