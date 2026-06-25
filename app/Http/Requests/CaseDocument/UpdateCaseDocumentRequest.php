<?php

namespace App\Http\Requests\CaseDocument;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCaseDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'case_id' => ['sometimes', 'exists:cases,id'],
            'title' => ['sometimes', 'string', 'max:255'],
            'file' => ['sometimes', 'file', 'mimes:pdf,doc,docx,jpg,jpeg,png', 'max:10240'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
