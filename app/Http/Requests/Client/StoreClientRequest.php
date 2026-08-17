<?php

namespace App\Http\Requests\Client;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreClientRequest extends FormRequest
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
            'type' => ['required', Rule::in(['individual', 'company'])],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
            'first_name' => ['required_if:type,individual', 'nullable', 'string', 'max:100'],
            'last_name' => ['required_if:type,individual', 'nullable', 'string', 'max:100'],
            'name' => ['required_if:type,company', 'nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:255'],
            'id_number' => ['nullable', 'string', 'max:100'],
            'occupation' => ['nullable', 'string', 'max:100'],
            'registration_number' => ['nullable', 'string', 'max:100'],
            'industry' => ['nullable', 'string', 'max:100'],
            'tin' => ['nullable', 'string', 'max:100'],
            'primary_contact' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'assigned_user_ids' => ['nullable', 'array'],
            'assigned_user_ids.*' => [
                'integer',
                Rule::exists('users', 'id')->where(fn ($query) => $query->where('firm_id', $firmId)),
            ],
        ];
    }
}
