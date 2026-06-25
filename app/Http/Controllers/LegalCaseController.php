<?php

namespace App\Http\Controllers;

use App\Enums\CaseStatus;
use App\Http\Requests\Case\StoreCaseRequest;
use App\Http\Requests\Case\UpdateCaseRequest;
use App\Models\LegalCase;
use Illuminate\Http\JsonResponse;

class LegalCaseController extends Controller
{
    public function index(): JsonResponse
    {
        $cases = LegalCase::with(['client', 'assignee', 'creator'])
            ->latest()
            ->paginate(15);

        return $this->success($cases);
    }

    public function store(StoreCaseRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['created_by'] = $request->user()->id;
        $data['case_status'] = $data['case_status'] ?? CaseStatus::Open->value;

        $case = LegalCase::create($data);
        $case->load(['client', 'assignee', 'creator']);

        return $this->success($case, 'Case created successfully', 201);
    }

    public function show(LegalCase $case): JsonResponse
    {
        $case->load(['client', 'assignee', 'creator', 'documents', 'tasks']);

        return $this->success($case);
    }

    public function update(UpdateCaseRequest $request, LegalCase $case): JsonResponse
    {
        $case->update($request->validated());
        $case->load(['client', 'assignee', 'creator']);

        return $this->success($case, 'Case updated successfully');
    }

    public function destroy(LegalCase $case): JsonResponse
    {
        $case->delete();

        return $this->success(null, 'Case deleted successfully');
    }
}
