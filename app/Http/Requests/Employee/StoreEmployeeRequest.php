<?php

namespace App\Http\Requests\Employee;

use App\Enums\EmployeeStatus;
use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'user_id' => ['nullable', 'exists:users,id'],
            'full_name' => ['required', 'string', 'max:255'],
            'role' => ['required', Rule::in(UserRole::values())],
            'salary' => ['required', 'numeric', 'min:0'],
            'phone' => ['nullable', 'string', 'max:50'],
            'status' => ['sometimes', Rule::in(EmployeeStatus::values())],
        ];
    }
}
