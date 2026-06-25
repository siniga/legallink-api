<?php

namespace App\Http\Controllers;

use App\Enums\EmployeeStatus;
use App\Http\Requests\Employee\StoreEmployeeRequest;
use App\Http\Requests\Employee\UpdateEmployeeRequest;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;

class EmployeeController extends Controller
{
    public function index(): JsonResponse
    {
        $employees = Employee::with('user')->latest()->paginate(15);

        return $this->success($employees);
    }

    public function store(StoreEmployeeRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['status'] = $data['status'] ?? EmployeeStatus::Active->value;

        $employee = Employee::create($data);
        $employee->load('user');

        return $this->success($employee, 'Employee created successfully', 201);
    }

    public function show(Employee $employee): JsonResponse
    {
        $employee->load(['user', 'payrolls']);

        return $this->success($employee);
    }

    public function update(UpdateEmployeeRequest $request, Employee $employee): JsonResponse
    {
        $employee->update($request->validated());
        $employee->load('user');

        return $this->success($employee, 'Employee updated successfully');
    }

    public function destroy(Employee $employee): JsonResponse
    {
        $employee->delete();

        return $this->success(null, 'Employee deleted successfully');
    }
}
