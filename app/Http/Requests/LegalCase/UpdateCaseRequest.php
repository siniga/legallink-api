<?php

namespace App\Http\Requests\LegalCase;

use Illuminate\Validation\Rule;

class UpdateCaseRequest extends StoreCaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = parent::rules();
        $firmId = $this->user()?->firm_id;
        $caseId = $this->route('legalCase')?->id;

        $rules['case_number'] = [
            'required',
            'string',
            'max:100',
            Rule::unique('cases', 'case_number')
                ->where(fn ($query) => $query->where('firm_id', $firmId)->whereNull('deleted_at'))
                ->ignore($caseId),
        ];

        return $rules;
    }
}
