<?php

namespace App\Http\Requests\Payroll;

use App\Enums\PaymentStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePayrollRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'employee_id' => ['sometimes', 'exists:employees,id'],
            'month' => ['sometimes', 'string', 'max:20'],
            'gross_salary' => ['sometimes', 'numeric', 'min:0'],
            'allowances' => ['sometimes', 'numeric', 'min:0'],
            'deductions' => ['sometimes', 'numeric', 'min:0'],
            'net_salary' => ['sometimes', 'numeric', 'min:0'],
            'payment_status' => ['sometimes', Rule::in(PaymentStatus::values())],
            'paid_at' => ['nullable', 'date'],
        ];
    }
}
