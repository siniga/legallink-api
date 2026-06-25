<?php

namespace App\Http\Controllers;

use App\Enums\TaskStatus;
use App\Http\Requests\CaseTask\StoreCaseTaskRequest;
use App\Http\Requests\CaseTask\UpdateCaseTaskRequest;
use App\Models\CaseTask;
use Illuminate\Http\JsonResponse;

class CaseTaskController extends Controller
{
    public function index(): JsonResponse
    {
        $tasks = CaseTask::with(['legalCase', 'assignee', 'assigner'])
            ->latest()
            ->paginate(15);

        return $this->success($tasks);
    }

    public function store(StoreCaseTaskRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['assigned_by'] = $request->user()->id;
        $data['status'] = $data['status'] ?? TaskStatus::Pending->value;

        $task = CaseTask::create($data);
        $task->load(['legalCase', 'assignee', 'assigner']);

        return $this->success($task, 'Task created successfully', 201);
    }

    public function show(CaseTask $caseTask): JsonResponse
    {
        $caseTask->load(['legalCase', 'assignee', 'assigner']);

        return $this->success($caseTask);
    }

    public function update(UpdateCaseTaskRequest $request, CaseTask $caseTask): JsonResponse
    {
        $caseTask->update($request->validated());
        $caseTask->load(['legalCase', 'assignee', 'assigner']);

        return $this->success($caseTask, 'Task updated successfully');
    }

    public function destroy(CaseTask $caseTask): JsonResponse
    {
        $caseTask->delete();

        return $this->success(null, 'Task deleted successfully');
    }
}
