<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\EmployeeController;
use App\Http\Controllers\Api\FollowUpController;
use App\Http\Controllers\Api\ImportController;
use App\Http\Controllers\Api\LeadController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\SettingsController;
use App\Http\Controllers\Api\WhatsAppController;
use Illuminate\Support\Facades\Route;

// Public routes
Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
});

// Protected routes (Sanctum)
Route::middleware('auth:sanctum')->group(function () {
    // Auth & Profile
    Route::prefix('auth')->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::put('/profile', [AuthController::class, 'updateProfile']);
        Route::post('/logout', [AuthController::class, 'logout']);
    });

    // Dashboard
    Route::get('/dashboard/metrics', [DashboardController::class, 'metrics']);

    // Leads
    Route::get('/leads/export', [LeadController::class, 'export']);
    Route::apiResource('leads', LeadController::class);
    Route::post('/leads/{lead}/assign', [LeadController::class, 'assign']);
    Route::post('/leads/{lead}/call', [LeadController::class, 'logCall']);
    Route::post('/leads/{lead}/note', [LeadController::class, 'addNote']);

    // Follow-ups
    Route::get('/follow-ups', [FollowUpController::class, 'index']);
    Route::post('/leads/{lead}/follow-ups', [FollowUpController::class, 'store']);
    Route::put('/follow-ups/{followUp}', [FollowUpController::class, 'update']);
    Route::post('/follow-ups/{followUp}/complete', [FollowUpController::class, 'complete']);

    // Lead Import (Admin only enforced in controller)
    Route::post('/import/upload-preview', [ImportController::class, 'uploadAndPreview']);
    Route::post('/import/process', [ImportController::class, 'process']);
    Route::get('/import/history', [ImportController::class, 'history']);
    Route::get('/import/history/{history}/failed-report', [ImportController::class, 'downloadFailedReport']);

    // WhatsApp
    Route::get('/whatsapp/templates', [WhatsAppController::class, 'getTemplates']);
    Route::post('/leads/{lead}/whatsapp/preview', [WhatsAppController::class, 'previewMessage']);
    Route::post('/leads/{lead}/whatsapp/log', [WhatsAppController::class, 'logMessageDispatch']);

    // Notifications
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markAsRead']);
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);
    Route::get('/notifications/delivery-logs', [NotificationController::class, 'deliveryLogs']);

    // Employees (Admin only)
    Route::get('/employees', [EmployeeController::class, 'index']);
    Route::post('/employees', [EmployeeController::class, 'store']);
    Route::put('/employees/{user}', [EmployeeController::class, 'update']);
    Route::post('/employees/{user}/toggle-status', [EmployeeController::class, 'toggleStatus']);

    // CRM Settings & Templates (Admin only)
    Route::get('/settings', [SettingsController::class, 'getSettings']);
    Route::post('/settings', [SettingsController::class, 'updateSettings']);
    Route::post('/settings/templates', [SettingsController::class, 'storeTemplate']);
    Route::put('/settings/templates/{template}', [SettingsController::class, 'updateTemplate']);
    Route::delete('/settings/templates/{template}', [SettingsController::class, 'deleteTemplate']);
});
