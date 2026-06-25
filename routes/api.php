<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CaseDocumentController;
use App\Http\Controllers\CaseTaskController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\LegalCaseController;
use App\Http\Controllers\PayrollController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'me']);

    Route::get('/dashboard', [DashboardController::class, 'index']);
    Route::get('/search', [SearchController::class, 'search']);

    Route::apiResource('clients', ClientController::class);
    Route::apiResource('cases', LegalCaseController::class);
    Route::apiResource('case-documents', CaseDocumentController::class);
    Route::apiResource('case-tasks', CaseTaskController::class);

    Route::middleware('admin')->group(function () {
        Route::apiResource('users', UserController::class);
        Route::apiResource('employees', EmployeeController::class);
        Route::apiResource('payrolls', PayrollController::class);
    });
});
