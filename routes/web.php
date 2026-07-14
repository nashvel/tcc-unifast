<?php

use App\Http\Controllers\AdminStudentIdSampleController;
use App\Http\Controllers\AuditEventController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BatchActivationNotificationController;
use App\Http\Controllers\DocumentSubmissionController;
use App\Http\Controllers\StudentDocumentOcrController;
use App\Http\Controllers\StudentFaceVerificationController;
use Illuminate\Support\Facades\Route;

Route::post('/api/auth/login', [AuthController::class, 'login'])->middleware('throttle:20,1');
Route::get('/api/auth/me', [AuthController::class, 'me']);
Route::post('/api/auth/logout', [AuthController::class, 'logout']);

Route::middleware('auth')->group(function (): void {
    Route::post('/api/student/submissions/ocr', StudentDocumentOcrController::class)->middleware(['role:student', 'throttle:20,1']);
    Route::post('/api/student/identity/face-verify', StudentFaceVerificationController::class)->middleware(['role:student', 'throttle:10,1']);
    Route::post('/api/students/{student}/id-sample', AdminStudentIdSampleController::class)->middleware(['role:admin,head,staff', 'throttle:20,1']);
    Route::post('/api/batches/{batch}/activation-notifications', BatchActivationNotificationController::class)->middleware(['role:admin,head,staff', 'throttle:5,1']);
    Route::get('/api/document-submissions', [DocumentSubmissionController::class, 'index']);
    Route::get('/api/document-submissions/{submission}', [DocumentSubmissionController::class, 'show']);
    Route::post('/api/document-submissions/{submission}/review', [DocumentSubmissionController::class, 'review'])->middleware('role:admin,head,staff');
    Route::get('/api/audit-logs', [AuditEventController::class, 'index'])->middleware('role:admin,head,staff');
    Route::post('/api/audit-events', [AuditEventController::class, 'store'])->middleware('throttle:240,1');
});

Route::view('/{path?}', 'app')->where('path', '.*');
