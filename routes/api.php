<?php

use App\Http\Controllers\Api\ActivityController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CalendarEventController;
use App\Http\Controllers\Api\CaseController;
use App\Http\Controllers\Api\ClientController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DocumentController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\Platform\DashboardController as PlatformDashboardController;
use App\Http\Controllers\Api\Platform\FirmController as PlatformFirmController;
use App\Http\Controllers\Api\Platform\UserController as PlatformUserController;
use App\Http\Controllers\Api\SearchController;
use App\Http\Controllers\Api\SettingsController;
use App\Http\Controllers\Api\TaskController;
use App\Http\Controllers\Api\TeamController;
use Illuminate\Support\Facades\Route;

Route::post('/auth/login', [AuthController::class, 'login']);
Route::post('/auth/logout', [AuthController::class, 'logout']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::get('/dashboard', [DashboardController::class, 'show']);

    Route::prefix('platform')->group(function () {
        Route::get('/dashboard', [PlatformDashboardController::class, 'show']);
        Route::get('/firms', [PlatformFirmController::class, 'index']);
        Route::post('/firms', [PlatformFirmController::class, 'store']);
        Route::get('/firms/{firm}', [PlatformFirmController::class, 'show']);
        Route::put('/firms/{firm}', [PlatformFirmController::class, 'update']);
        Route::post('/firms/{firm}/deactivate', [PlatformFirmController::class, 'deactivate']);
        Route::post('/firms/{firm}/activate', [PlatformFirmController::class, 'activate']);
        Route::post('/firms/{firm}/users', [PlatformFirmController::class, 'storeUser']);
        Route::get('/users', [PlatformUserController::class, 'index']);
        Route::post('/users/{member}/deactivate', [PlatformUserController::class, 'deactivate']);
        Route::post('/users/{member}/activate', [PlatformUserController::class, 'activate']);
    });

    Route::get('/search', [SearchController::class, 'index']);
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead']);
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markRead']);

    Route::get('/clients', [ClientController::class, 'index']);
    Route::post('/clients', [ClientController::class, 'store']);
    Route::get('/clients/{client}', [ClientController::class, 'show']);
    Route::put('/clients/{client}', [ClientController::class, 'update']);
    Route::post('/clients/{client}/archive', [ClientController::class, 'archive']);
    Route::post('/clients/{client}/notes', [ClientController::class, 'storeNote']);

    Route::get('/cases', [CaseController::class, 'index']);
    Route::post('/cases', [CaseController::class, 'store']);
    Route::get('/cases/{legalCase}', [CaseController::class, 'show']);
    Route::put('/cases/{legalCase}', [CaseController::class, 'update']);
    Route::post('/cases/{legalCase}/archive', [CaseController::class, 'archive']);

    Route::get('/documents', [DocumentController::class, 'index']);
    Route::post('/documents', [DocumentController::class, 'store']);
    Route::post('/documents/folders', [DocumentController::class, 'storeFolder']);
    Route::get('/documents/{document}', [DocumentController::class, 'show']);
    Route::put('/documents/{document}', [DocumentController::class, 'update']);
    Route::post('/documents/{document}/copy', [DocumentController::class, 'copy']);
    Route::post('/documents/{document}/archive', [DocumentController::class, 'archive']);
    Route::delete('/documents/{document}', [DocumentController::class, 'destroy']);
    Route::get('/documents/{document}/download', [DocumentController::class, 'download']);

    Route::get('/tasks', [TaskController::class, 'index']);
    Route::post('/tasks', [TaskController::class, 'store']);
    Route::get('/tasks/{task}', [TaskController::class, 'show']);
    Route::put('/tasks/{task}', [TaskController::class, 'update']);
    Route::delete('/tasks/{task}', [TaskController::class, 'destroy']);
    Route::post('/tasks/{task}/checklist/{checklistItem}', [TaskController::class, 'toggleChecklist']);

    Route::get('/calendar', [CalendarEventController::class, 'index']);
    Route::post('/calendar', [CalendarEventController::class, 'store']);
    Route::get('/calendar/{calendarEvent}', [CalendarEventController::class, 'show']);
    Route::put('/calendar/{calendarEvent}', [CalendarEventController::class, 'update']);
    Route::delete('/calendar/{calendarEvent}', [CalendarEventController::class, 'destroy']);

    Route::get('/team', [TeamController::class, 'index']);
    Route::post('/team', [TeamController::class, 'store']);
    Route::get('/team/{member}', [TeamController::class, 'show']);
    Route::put('/team/{member}', [TeamController::class, 'update']);
    Route::post('/team/{member}/deactivate', [TeamController::class, 'deactivate']);
    Route::post('/team/{member}/permissions', [TeamController::class, 'updatePermission']);

    Route::get('/activity', [ActivityController::class, 'index']);
    Route::get('/activity/export', [ActivityController::class, 'export']);
    Route::get('/activity/{auditLog}', [ActivityController::class, 'show']);

    Route::get('/settings', [SettingsController::class, 'show']);
    Route::put('/settings/profile', [SettingsController::class, 'updateProfile']);
    Route::put('/settings/password', [SettingsController::class, 'updatePassword']);
    Route::put('/settings/firm', [SettingsController::class, 'updateFirm']);
    Route::put('/settings/documents', [SettingsController::class, 'updateDocuments']);
    Route::put('/settings/cases', [SettingsController::class, 'updateCases']);
    Route::post('/settings/cases/statuses', [SettingsController::class, 'storeCaseStatus']);
    Route::put('/settings/cases/status-order', [SettingsController::class, 'reorderCaseStatuses']);
    Route::put('/settings/cases/statuses/{status}', [SettingsController::class, 'updateCaseStatus']);
    Route::delete('/settings/cases/statuses/{status}', [SettingsController::class, 'destroyCaseStatus']);
    Route::post('/settings/cases/types', [SettingsController::class, 'storeCaseType']);
    Route::delete('/settings/cases/types/{type}', [SettingsController::class, 'destroyCaseType']);
    Route::put('/settings/notifications', [SettingsController::class, 'updateNotifications']);
    Route::put('/settings/appearance', [SettingsController::class, 'updateAppearance']);
    Route::put('/settings/security', [SettingsController::class, 'updateSecurity']);
    Route::put('/settings/roles/{role}', [SettingsController::class, 'updateRole']);
    Route::delete('/settings/sessions/{session}', [SettingsController::class, 'revokeSession']);
    Route::post('/settings/sessions/revoke-others', [SettingsController::class, 'revokeOtherSessions']);
    Route::put('/settings/audit', [SettingsController::class, 'updateAudit']);
    Route::get('/settings/export', [SettingsController::class, 'export']);
    Route::post('/settings/workspace/deactivate', [SettingsController::class, 'deactivateWorkspace']);
    Route::post('/settings/workspace/delete', [SettingsController::class, 'deleteWorkspace']);
});
