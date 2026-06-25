<?php

namespace App\Http\Controllers;

use App\Enums\CaseStatus;
use App\Enums\TaskStatus;
use App\Models\CaseTask;
use App\Models\LegalCase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function search(Request $request): JsonResponse
    {
        $query = LegalCase::with(['client', 'assignee', 'creator']);

        if ($request->filled('case_number')) {
            $query->where('case_number', 'like', '%'.$request->case_number.'%');
        }

        if ($request->filled('client_name')) {
            $query->whereHas('client', function ($q) use ($request) {
                $q->where('full_name', 'like', '%'.$request->client_name.'%');
            });
        }

        if ($request->filled('court_date')) {
            $query->whereDate('court_date', $request->court_date);
        }

        if ($request->filled('assigned_to')) {
            $query->where('assigned_to', $request->assigned_to);
        }

        if ($request->filled('assigned_name')) {
            $query->whereHas('assignee', function ($q) use ($request) {
                $q->where('name', 'like', '%'.$request->assigned_name.'%');
            });
        }

        if ($request->filled('claim_status')) {
            $query->where('claim_status', $request->claim_status);
        }

        if ($request->filled('case_status')) {
            $query->where('case_status', $request->case_status);
        }

        $cases = $query->latest()->paginate(15);

        return $this->success($cases);
    }
}
