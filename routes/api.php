<?php

use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\ClientSatisfactionController;
use App\Http\Controllers\CommunicationController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EscalationRuleController;
use App\Http\Controllers\LeadController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OpportunityController;
use App\Http\Controllers\PublicSurveyController;
use App\Http\Controllers\ReminderController;
use App\Http\Controllers\SalesRepresentativeController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WinOpportunityController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::get('/me', function () {
    return Auth::check();
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('leads/mine', [LeadController::class, 'mine']);

    Route::apiResource('leads', LeadController::class)
        ->except(['destroy']);

    Route::patch('leads/{lead}/status', [LeadController::class, 'updateStatus']);
    Route::patch('leads/{lead}/reassign', [LeadController::class, 'reassign']);

    Route::get(
        'sales-representatives',
        [SalesRepresentativeController::class, 'index']
    );

    Route::get('companies', [CompanyController::class, 'index']);

    Route::get('companies/mine', [CompanyController::class, 'mine']);

    Route::get('clients/mine', [ClientController::class, 'mine']);

    Route::apiResource('clients', ClientController::class)
        ->only(['index', 'show', 'update']);

    Route::patch('clients/{client}/status', [ClientController::class, 'updateStatus']);
    Route::patch('clients/{client}/reassign', [ClientController::class, 'reassign']);

    Route::get('opportunities/mine', [OpportunityController::class, 'mine']);

    Route::apiResource('opportunities', OpportunityController::class)
        ->except(['destroy']);

    Route::patch('opportunities/{opportunity}/stage', [OpportunityController::class, 'updateStage']);

    Route::post('opportunities/{opportunity}/win', [WinOpportunityController::class, 'win']);

    Route::get('communications/mine', [CommunicationController::class, 'mine']);

    Route::apiResource('communications', CommunicationController::class)
        ->only(['index', 'show', 'store', 'update', 'destroy']);

    Route::get('reminders/mine', [ReminderController::class, 'mine']);
    Route::get('reminders/team', [ReminderController::class, 'team']);

    Route::apiResource('reminders', ReminderController::class)
        ->only(['index', 'show', 'store', 'update', 'destroy']);

    Route::patch('reminders/{reminder}/complete', [ReminderController::class, 'complete']);
    Route::patch('reminders/{reminder}/incomplete', [ReminderController::class, 'markIncomplete']);
    Route::patch('reminders/{reminder}/snooze', [ReminderController::class, 'snooze']);

    Route::get('satisfaction', [ClientSatisfactionController::class, 'index']);
    Route::get('satisfaction/mine', [ClientSatisfactionController::class, 'mine']);
    Route::get('satisfaction/{client}', [ClientSatisfactionController::class, 'show']);
    Route::post('satisfaction/{client}/surveys', [ClientSatisfactionController::class, 'store']);
    Route::delete('satisfaction/{client}/surveys/{survey}', [ClientSatisfactionController::class, 'destroy']);

    Route::apiResource('contacts', ContactController::class)
        ->only(['store', 'update', 'destroy']);

    Route::get('audit-logs', [AuditLogController::class, 'index']);
    Route::get('audit-logs/export', [AuditLogController::class, 'export']);

    Route::apiResource('users', UserController::class)
        ->except(['create', 'edit']);
    Route::post('users/{user}/deactivate', [UserController::class, 'deactivate']);
    Route::post('users/{user}/activate', [UserController::class, 'activate']);
    Route::post('users/{user}/reset-password', [UserController::class, 'resetPassword']);
    Route::get('users-export', [UserController::class, 'export']);

    Route::apiResource('teams', TeamController::class)
        ->only(['index', 'store', 'update', 'destroy']);
    Route::get('teams/{team}/members', [TeamController::class, 'members']);

    Route::apiResource('escalation-rules', EscalationRuleController::class)
        ->only(['index', 'store', 'update', 'destroy']);

    Route::get('dashboard', [DashboardController::class, 'index']);

    Route::get('notifications', [NotificationController::class, 'index']);
    Route::get('notifications/unread-count', [NotificationController::class, 'unreadCount']);
    Route::post('notifications/mark-all-read', [NotificationController::class, 'markAllRead']);
    Route::patch('notifications/{id}/read', [NotificationController::class, 'markAsRead']);
    Route::delete('notifications/{id}', [NotificationController::class, 'destroy']);
});

Route::get('surveys/{token}', [PublicSurveyController::class, 'show']);
Route::post('surveys/{token}/submit', [PublicSurveyController::class, 'submit']);
