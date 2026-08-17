<?php

namespace App\Http\Requests\Team;

use Illuminate\Validation\Rule;

class UpdateTeamMemberRequest extends StoreTeamMemberRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = parent::rules();
        $memberId = $this->route('member')?->id;

        $rules['first_name'] = ['sometimes', 'required', 'string', 'max:100'];
        $rules['last_name'] = ['sometimes', 'required', 'string', 'max:100'];
        $rules['email'] = ['sometimes', 'required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($memberId)];
        $rules['job_role'] = ['sometimes', 'required', Rule::in([
            'managing_partner',
            'partner',
            'senior_associate',
            'associate',
            'paralegal',
            'legal_assistant',
            'finance_admin',
            'intern',
        ])];
        $rules['access_role'] = ['sometimes', 'required', 'string', Rule::exists('roles', 'slug')->where(
            fn ($query) => $query->where('firm_id', $this->user()?->firm_id),
        )];

        return $rules;
    }
}
