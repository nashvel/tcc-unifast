<?php

use App\Http\Controllers\AdminStudentIdSampleController;
use App\Http\Controllers\AuditEventController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ActivationController;
use App\Http\Controllers\AcademicRecordController;
use App\Http\Controllers\BatchActivationNotificationController;
use App\Http\Controllers\BatchController;
use App\Http\Controllers\DocumentSubmissionController;
use App\Http\Controllers\MasterlistImportController;
use App\Http\Controllers\RequirementVaultController;
use App\Http\Controllers\StudentDocumentOcrController;
use App\Http\Controllers\StudentFaceVerificationController;
use App\Http\Controllers\StudentNotificationController;
use App\Http\Controllers\StudentSubmissionWindowController;
use App\Http\Controllers\StudentKycController;
use Illuminate\Support\Facades\Route;

Route::post('/api/auth/login', [AuthController::class, 'login'])->middleware('throttle:20,1');
Route::get('/api/auth/me', [AuthController::class, 'me']);
Route::post('/api/auth/logout', [AuthController::class, 'logout']);
Route::get('/api/activation/{token}', [ActivationController::class, 'show'])->middleware('throttle:20,1');
Route::post('/api/activation/{token}', [ActivationController::class, 'activate'])->middleware('throttle:10,1');

Route::middleware('auth')->group(function (): void {
    Route::get('/api/student/kyc', [StudentKycController::class, 'show'])->middleware('role:student');
    Route::post('/api/student/kyc', [StudentKycController::class, 'store'])->middleware(['role:student', 'throttle:20,1']);
    Route::get('/api/student/submission-window', StudentSubmissionWindowController::class)->middleware('role:student');
    Route::get('/api/student/requirement-vault', [RequirementVaultController::class, 'show'])->middleware('role:student');
    Route::post('/api/student/requirement-vault/id', [RequirementVaultController::class, 'storeId'])->middleware(['role:student', 'throttle:20,1']);
    Route::post('/api/student/requirement-vault/document', [RequirementVaultController::class, 'storeDocument'])->middleware(['role:student', 'throttle:20,1']);
    Route::post('/api/student/requirement-vault/identity-check', [RequirementVaultController::class, 'storeIdentityCheck'])->middleware(['role:student', 'throttle:10,1']);
    Route::post('/api/student/requirement-vault/confirm', [RequirementVaultController::class, 'confirm'])->middleware(['role:student', 'throttle:10,1']);
    Route::get('/api/student/notifications', [StudentNotificationController::class, 'index'])->middleware('role:student');
    Route::post('/api/student/submissions/ocr', StudentDocumentOcrController::class)->middleware(['role:student', 'throttle:20,1']);
    Route::post('/api/student/identity/face-verify', StudentFaceVerificationController::class)->middleware(['role:student', 'throttle:10,1']);
    Route::post('/api/students/{student}/id-sample', AdminStudentIdSampleController::class)->middleware(['role:admin,head,staff', 'throttle:20,1']);
    Route::post('/api/batches/{batch}/activation-notifications', BatchActivationNotificationController::class)->middleware(['role:admin,head,staff', 'throttle:5,1']);
    Route::get('/api/batches', [BatchController::class, 'index'])->middleware('role:admin,head,staff');
    Route::post('/api/batches', [BatchController::class, 'store'])->middleware(['role:admin,head', 'throttle:20,1']);
    Route::get('/api/batches/{batch}', [BatchController::class, 'show'])->middleware('role:admin,head,staff');
    Route::patch('/api/batches/{batch}', [BatchController::class, 'update'])->middleware(['role:admin,head', 'throttle:20,1']);
    Route::post('/api/batches/{batch}/activate', [BatchController::class, 'activate'])->middleware(['role:admin,head', 'throttle:10,1']);
    Route::post('/api/batches/{batch}/deactivate', [BatchController::class, 'deactivate'])->middleware(['role:admin,head', 'throttle:10,1']);
    Route::post('/api/batches/{batch}/extend-deadline', [BatchController::class, 'extendDeadline'])->middleware(['role:admin,head', 'throttle:10,1']);
    Route::post('/api/masterlist/imports/preview', [MasterlistImportController::class, 'preview'])->middleware(['role:admin,head,staff', 'throttle:10,1']);
    Route::get('/api/masterlist/imports/{import}', [MasterlistImportController::class, 'show'])->middleware('role:admin,head,staff');
    Route::post('/api/masterlist/imports/{import}/confirm', [MasterlistImportController::class, 'confirm'])->middleware(['role:admin,head', 'throttle:10,1']);
    Route::get('/api/academic-records', [AcademicRecordController::class, 'index'])->middleware('role:admin,head,staff');
    Route::get('/api/academic-records/{record}', [AcademicRecordController::class, 'show'])->middleware('role:admin,head,staff');
    Route::get('/api/document-submissions', [DocumentSubmissionController::class, 'index']);
    Route::get('/api/document-submissions/{submission}', [DocumentSubmissionController::class, 'show']);
    Route::post('/api/document-submissions/{submission}/review', [DocumentSubmissionController::class, 'review'])->middleware('role:admin,head,staff');
    Route::get('/api/audit-logs', [AuditEventController::class, 'index'])->middleware('role:admin,head,staff');
    Route::post('/api/audit-events', [AuditEventController::class, 'store'])->middleware('throttle:240,1');
});

Route::view('/{path?}', 'app')->where('path', '.*');
