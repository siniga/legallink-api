<?php

namespace App\Http\Controllers;

use App\Enums\PaymentStatus;
use App\Http\Requests\Payroll\StorePayrollRequest;
use App\Http\Requests\Payroll\UpdatePayrollRequest;
use App\Models\Payroll;
use Illuminate\Http\JsonResponse;

class PayrollController extends Controller
{
    public function index(): JsonResponse
    {
        $payrolls = Payroll::with('employee')->latest()->paginate(15);

        return $this->success($payrolls);
    }

    public function store(StorePayrollRequest $request): JsonResponse
    {
        $data = $request->validated();

        $allowances = $data['allowances'] ?? 0;
        $deductions = $data['deductions'] ?? 0;
        $data['allowances'] = $allowances;
        $data['deductions'] = $deductions;
        $data['net_salary'] = $data['net_salary'] ?? ($data['gross_salary'] + $allowances - $deductions);
        $data['payment_status'] = $data['payment_status'] ?? PaymentStatus::Pending->value;

        if (($data['payment_status'] ?? PaymentStatus::Pending->value) === PaymentStatus::Paid->value && empty($data['paid_at'])) {
            $data['paid_at'] = now();
        }

        $payroll = Payroll::create($data);
        $payroll->load('employee');

        return $this->success($payroll, 'Payroll created successfully', 201);
    }

    public function show(Payroll $payroll): JsonResponse
    {
        $payroll->load('employee');

        return $this->success($payroll);
    }

    public function update(UpdatePayrollRequest $request, Payroll $payroll): JsonResponse
    {
        $data = $request->validated();

        if (isset($data['gross_salary']) || isset($data['allowances']) || isset($data['deductions'])) {
            $gross = $data['gross_salary'] ?? $payroll->gross_salary;
            $allowances = $data['allowances'] ?? $payroll->allowances;
            $deductions = $data['deductions'] ?? $payroll->deductions;
            $data['net_salary'] = $gross + $allowances - $deductions;
        }

        if (isset($data['payment_status']) && $data['payment_status'] === PaymentStatus::Paid->value && empty($data['paid_at'])) {
            $data['paid_at'] = now();
        }

        $payroll->update($data);
        $payroll->load('employee');

        return $this->success($payroll, 'Payroll updated successfully');
    }

    public function destroy(Payroll $payroll): JsonResponse
    {
        $payroll->delete();

        return $this->success(null, 'Payroll deleted successfully');
    }
}
