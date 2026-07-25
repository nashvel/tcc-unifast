<?php

use App\Http\Controllers\TccUnifastSyncController;
use App\Http\Controllers\TccUnifastStudentsController;
use App\Http\Controllers\AdminStudentIdSampleController;
use App\Http\Controllers\AuditEventController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ActivationController;
use App\Http\Controllers\AcademicRecordController;
use App\Http\Controllers\BatchActivationNotificationController;
use App\Http\Controllers\BatchController;
use App\Http\Controllers\DocumentSubmissionController;
use App\Http\Controllers\GranteeController;
use App\Http\Controllers\MasterlistImportController;
use App\Http\Controllers\RequirementVaultController;
use App\Http\Controllers\StudentDocumentOcrController;
use App\Http\Controllers\StudentFaceVerificationController;
use App\Http\Controllers\StudentNotificationController;
use App\Http\Controllers\StudentSubmissionWindowController;
use App\Http\Controllers\StudentKycController;
use Illuminate\Support\Facades\Route;

// Public routes (no auth required)
Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:20,1');
Route::get('/activation/{token}', [ActivationController::class, 'show'])->middleware('throttle:20,1');
Route::post('/activation/{token}', [ActivationController::class, 'activate'])->middleware('throttle:10,1');

// Authenticated routes (Sanctum token required)
Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    // Student routes
    Route::middleware('role:student')->group(function (): void {
        Route::get('/student/kyc', [StudentKycController::class, 'show']);
        Route::post('/student/kyc', [StudentKycController::class, 'store'])->middleware('throttle:20,1');
        Route::get('/student/submission-window', StudentSubmissionWindowController::class);
        Route::get('/student/requirement-vault', [RequirementVaultController::class, 'show']);
        Route::post('/student/requirement-vault/id', [RequirementVaultController::class, 'storeId'])->middleware('throttle:20,1');
        Route::post('/student/requirement-vault/document', [RequirementVaultController::class, 'storeDocument'])->middleware('throttle:20,1');
        Route::post('/student/requirement-vault/identity-check', [RequirementVaultController::class, 'storeIdentityCheck'])->middleware('throttle:10,1');
        Route::post('/student/requirement-vault/confirm', [RequirementVaultController::class, 'confirm'])->middleware('throttle:10,1');
        Route::get('/student/notifications', [StudentNotificationController::class, 'index']);
        Route::post('/student/notifications/{notification}/read', [StudentNotificationController::class, 'markRead']);
        Route::post('/student/notifications/read-all', [StudentNotificationController::class, 'markAllRead']);
        Route::post('/student/submissions/ocr', StudentDocumentOcrController::class)->middleware('throttle:20,1');
        Route::post('/student/identity/face-verify', StudentFaceVerificationController::class)->middleware('throttle:10,1');
    });

    // Developer/Admin/Staff routes
    Route::middleware('role:developer,admin,staff')->group(function (): void {
        Route::get('/grantees', [GranteeController::class, 'index']);
        Route::get('/grantees/{grantee}', [GranteeController::class, 'show']);
        Route::post('/students/{student}/id-sample', AdminStudentIdSampleController::class)->middleware('throttle:20,1');
        Route::post('/batches/{batch}/activation-notifications', BatchActivationNotificationController::class)->middleware('throttle:5,1');
        Route::get('/batches', [BatchController::class, 'index']);
        Route::get('/batches/{batch}', [BatchController::class, 'show']);
        Route::post('/masterlist/imports/preview', [MasterlistImportController::class, 'preview'])->middleware('throttle:10,1');
        Route::get('/masterlist/imports/{import}', [MasterlistImportController::class, 'show']);
        Route::get('/academic-records', [AcademicRecordController::class, 'index']);
        Route::get('/academic-records/{record}', [AcademicRecordController::class, 'show']);
        Route::get('/document-submissions', [DocumentSubmissionController::class, 'index']);
        Route::get('/document-submissions/{submission}', [DocumentSubmissionController::class, 'show']);
        Route::get('/audit-logs', [AuditEventController::class, 'index']);
    });

    // Developer/Admin only routes
    Route::middleware('role:developer,admin')->group(function (): void {
        Route::post('/batches', [BatchController::class, 'store'])->middleware('throttle:20,1');
        Route::patch('/batches/{batch}', [BatchController::class, 'update'])->middleware('throttle:20,1');
        Route::post('/batches/{batch}/activate', [BatchController::class, 'activate'])->middleware('throttle:10,1');
        Route::post('/batches/{batch}/deactivate', [BatchController::class, 'deactivate'])->middleware('throttle:10,1');
        Route::post('/batches/{batch}/extend-deadline', [BatchController::class, 'extendDeadline'])->middleware('throttle:10,1');
        Route::post('/masterlist/imports/{import}/confirm', [MasterlistImportController::class, 'confirm'])->middleware('throttle:10,1');
    });

    // Document submission review (developer/admin/staff)
    Route::post('/document-submissions/{submission}/review', [DocumentSubmissionController::class, 'review'])->middleware('role:developer,admin,staff');

    // Audit events (any authenticated user)
    Route::post('/audit-events', [AuditEventController::class, 'store'])->middleware('throttle:240,1');
});

// External integrations (no auth, webhook-style)
Route::post('/integrations/n8n/tcc-unifast/sync', TccUnifastSyncController::class)
    ->middleware('throttle:30,1')
    ->name('integrations.n8n.tcc-unifast.sync');

Route::get('/integrations/n8n/tcc-unifast/students', TccUnifastStudentsController::class)
    ->middleware('throttle:120,1')
    ->name('integrations.n8n.tcc-unifast.students');
