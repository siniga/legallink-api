<?php

namespace App\Http\Requests\Employee;

use App\Enums\EmployeeStatus;
use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'user_id' => ['nullable', 'exists:users,id'],
            'full_name' => ['sometimes', 'string', 'max:255'],
            'role' => ['sometimes', Rule::in(UserRole::values())],
            'salary' => ['sometimes', 'numeric', 'min:0'],
            'phone' => ['nullable', 'string', 'max:50'],
            'status' => ['sometimes', Rule::in(EmployeeStatus::values())],
        ];
    }
}
