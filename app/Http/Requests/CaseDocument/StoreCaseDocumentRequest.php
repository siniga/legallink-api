<?php

namespace App\Http\Requests\CaseDocument;

use Illuminate\Foundation\Http\FormRequest;

class StoreCaseDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'case_id' => ['required', 'exists:cases,id'],
            'title' => ['required', 'string', 'max:255'],
            'file' => ['required', 'file', 'mimes:pdf,doc,docx,jpg,jpeg,png', 'max:10240'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
