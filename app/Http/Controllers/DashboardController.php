<?php

namespace App\Http\Controllers;

use App\Enums\CaseStatus;
use App\Enums\TaskStatus;
use App\Models\Client;
use App\Models\CaseTask;
use App\Models\LegalCase;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function index(): JsonResponse
    {
        $today = now()->toDateString();

        $data = [
            'total_cases' => LegalCase::count(),
            'open_cases' => LegalCase::where('case_status', CaseStatus::Open)->count(),
            'pending_cases' => LegalCase::where('case_status', CaseStatus::Pending)->count(),
            'closed_cases' => LegalCase::where('case_status', CaseStatus::Closed)->count(),
            'total_clients' => Client::count(),
            'today_court_cases' => LegalCase::with(['client', 'assignee'])
                ->whereDate('court_date', $today)
                ->orderBy('court_date')
                ->get(),
            'upcoming_court_dates' => LegalCase::with(['client', 'assignee'])
                ->whereDate('court_date', '>', $today)
                ->orderBy('court_date')
                ->limit(10)
                ->get(),
            'pending_tasks' => CaseTask::with(['legalCase', 'assignee'])
                ->where('status', TaskStatus::Pending)
                ->orderBy('due_date')
                ->limit(10)
                ->get(),
        ];

        return $this->success($data);
    }
}
