<?php

use App\Http\Controllers\AcademicProgramController;
use App\Http\Controllers\AcademicRecordController;
use App\Http\Controllers\ActivationController;
use App\Http\Controllers\AdminStudentIdSampleController;
use App\Http\Controllers\AuditEventController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AuthCaptchaController;
use App\Http\Controllers\BatchActivationNotificationController;
use App\Http\Controllers\BatchController;
use App\Http\Controllers\BillingReportController;
use App\Http\Controllers\ChangelogController;
use App\Http\Controllers\CollaboratorController;
use App\Http\Controllers\DatabaseController;
use App\Http\Controllers\DistributionReportController;
use App\Http\Controllers\DocumentSubmissionController;
use App\Http\Controllers\EligibilityController;
use App\Http\Controllers\FaqController;
use App\Http\Controllers\GranteeController;
use App\Http\Controllers\IdentityOnboardingController;
use App\Http\Controllers\MasterlistImportController;
use App\Http\Controllers\PolicySettingsController;
use App\Http\Controllers\RbacController;
use App\Http\Controllers\RequirementVaultController;
use App\Http\Controllers\SupportTicketController;
use App\Http\Controllers\SystemHealthController;
use App\Http\Controllers\TermController;
use App\Http\Controllers\StudentDocumentOcrController;
use App\Http\Controllers\StudentFaceVerificationController;
use App\Http\Controllers\StudentKycController;
use App\Http\Controllers\StudentNotificationController;
use App\Http\Controllers\StudentSubmissionWindowController;
use App\Http\Controllers\TccUnifastStudentsController;
use App\Http\Controllers\TccUnifastSyncController;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;

// Public routes (no auth required)
Route::middleware([
    EncryptCookies::class,
    AddQueuedCookiesToResponse::class,
    StartSession::class,
])->group(function (): void {
    Route::get('/auth/captcha', AuthCaptchaController::class)->middleware('throttle:30,1');
    Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
});
Route::get('/activation/{token}', [ActivationController::class, 'show'])->middleware('throttle:20,1');
Route::post('/activation/{token}', [ActivationController::class, 'activate'])->middleware('throttle:10,1');

// Public content (for login page)
Route::get('/terms/active', [TermController::class, 'active']);
Route::get('/faqs', [FaqController::class, 'index']);

// Authenticated routes (Sanctum token required)
Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    // Student routes
    Route::middleware('role:student')->group(function (): void {
        Route::get('/student/kyc', [StudentKycController::class, 'show']);
        Route::post('/student/kyc', [StudentKycController::class, 'store'])->middleware('throttle:20,1');
        Route::get('/student/identity-onboarding', [IdentityOnboardingController::class, 'show']);
        Route::post('/student/identity-onboarding/id-scan', [IdentityOnboardingController::class, 'storeIdScan'])->middleware('throttle:10,1');
        Route::post('/student/identity-onboarding/liveness', [IdentityOnboardingController::class, 'storeLiveness'])->middleware('throttle:10,1');
        Route::get('/student/identity-onboarding/references', [IdentityOnboardingController::class, 'referenceFace']);
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

    // Developer/Admin/Staff routes (includes legacy head role)
    Route::middleware('role:developer,admin,head,staff')->group(function (): void {
        Route::get('/notifications', [StudentNotificationController::class, 'index']);
        Route::post('/notifications/{notification}/read', [StudentNotificationController::class, 'markRead']);
        Route::post('/notifications/read-all', [StudentNotificationController::class, 'markAllRead']);

        Route::get('/eligibility', [EligibilityController::class, 'index']);
        Route::get('/eligibility/{grantee}', [EligibilityController::class, 'show']);
        Route::post('/eligibility/{grantee}/notify', [EligibilityController::class, 'notify'])->middleware('throttle:20,1');

        Route::get('/academic-programs', [AcademicProgramController::class, 'index']);
        Route::post('/academic-programs', [AcademicProgramController::class, 'store'])->middleware('throttle:20,1');
        Route::patch('/academic-programs/{academicProgram}', [AcademicProgramController::class, 'update'])->middleware('throttle:20,1');
        Route::delete('/academic-programs/{academicProgram}', [AcademicProgramController::class, 'destroy'])->middleware('throttle:20,1');

        Route::get('/policy-settings', [PolicySettingsController::class, 'show']);
        Route::put('/policy-settings', [PolicySettingsController::class, 'update'])->middleware('throttle:20,1');

        Route::get('/grantees', [GranteeController::class, 'index']);
        Route::get('/grantees/{grantee}', [GranteeController::class, 'show']);
        Route::post('/students/{student}/id-sample', AdminStudentIdSampleController::class)->middleware('throttle:20,1');
        Route::post('/batches/{batch}/activation-notifications', BatchActivationNotificationController::class)->middleware('throttle:5,1');
        Route::get('/batches', [BatchController::class, 'index']);
        Route::get('/batches/{batch}', [BatchController::class, 'show']);
        Route::get('/masterlist/imports', [MasterlistImportController::class, 'index']);
        Route::post('/masterlist/imports/preview', [MasterlistImportController::class, 'preview'])->middleware('throttle:10,1');
        Route::get('/masterlist/imports/{import}', [MasterlistImportController::class, 'show']);
        Route::delete('/masterlist/imports/{import}', [MasterlistImportController::class, 'destroy']);
        Route::get('/academic-records', [AcademicRecordController::class, 'index']);
        Route::get('/academic-records/{record}', [AcademicRecordController::class, 'show']);
        Route::get('/document-submissions', [DocumentSubmissionController::class, 'index']);
        Route::get('/files', [\App\Http\Controllers\FileManagerController::class, 'index']);
        Route::get('/document-submissions/{submission}', [DocumentSubmissionController::class, 'show']);
        Route::get('/audit-logs', [AuditEventController::class, 'index']);

        Route::get('/billing-reports', [BillingReportController::class, 'index']);
        Route::post('/billing-reports', [BillingReportController::class, 'store'])->middleware('throttle:10,1');
        Route::get('/billing-reports/{report}', [BillingReportController::class, 'show']);
        Route::get('/billing-reports/{report}/download', [BillingReportController::class, 'download']);

        Route::get('/distribution-reports', [DistributionReportController::class, 'index']);
        Route::post('/distribution-reports', [DistributionReportController::class, 'store'])->middleware('throttle:10,1');
        Route::get('/distribution-reports/{report}', [DistributionReportController::class, 'show']);
        Route::get('/distribution-reports/{report}/download', [DistributionReportController::class, 'download']);
    });

    // Developer/Admin/Head/Staff routes (for batch ops and masterlists)
    Route::middleware('role:developer,admin,head,staff')->group(function (): void {
        Route::post('/batches', [BatchController::class, 'store'])->middleware('throttle:20,1');
        Route::patch('/batches/{batch}', [BatchController::class, 'update'])->middleware('throttle:20,1');
        Route::post('/batches/{batch}/activate', [BatchController::class, 'activate'])->middleware('throttle:10,1');
        Route::post('/batches/{batch}/deactivate', [BatchController::class, 'deactivate'])->middleware('throttle:10,1');
        Route::post('/batches/{batch}/extend-deadline', [BatchController::class, 'extendDeadline'])->middleware('throttle:10,1');
        Route::post('/masterlist/imports/{import}/confirm', [MasterlistImportController::class, 'confirm'])->middleware('throttle:10,1');
    });

    // RBAC + database viewer (developer/admin)
    Route::middleware('role:developer,admin')->group(function (): void {
        Route::get('/changelogs', [ChangelogController::class, 'index']);
        Route::get('/rbac/roles', [RbacController::class, 'index']);
        Route::post('/rbac/roles', [RbacController::class, 'store']);
        Route::get('/rbac/roles/{role}', [RbacController::class, 'show']);
        Route::put('/rbac/roles/{role}', [RbacController::class, 'update']);
        Route::delete('/rbac/roles/{role}', [RbacController::class, 'destroy']);
        Route::get('/rbac/permissions', [RbacController::class, 'permissions']);
        Route::post('/rbac/permissions', [RbacController::class, 'storePermission']);
        Route::delete('/rbac/permissions/{permission}', [RbacController::class, 'destroyPermission']);
        Route::get('/rbac/users/{user}/roles', [RbacController::class, 'userRoles']);
        Route::post('/rbac/users/{user}/roles', [RbacController::class, 'assignUserRole']);
        Route::delete('/rbac/users/{user}/roles/{role}', [RbacController::class, 'removeUserRole']);
        Route::put('/rbac/users/{user}/roles', [RbacController::class, 'syncUserRoles']);
        Route::post('/rbac/check-permission', [RbacController::class, 'checkPermission']);

        Route::get('/database/tables', [DatabaseController::class, 'tables']);
        Route::get('/database/stats', [DatabaseController::class, 'stats']);
        Route::get('/database/tables/{table}', [DatabaseController::class, 'table']);
        Route::get('/database/tables/{table}/rows', [DatabaseController::class, 'rows']);

        // Terms & Conditions management
        Route::get('/terms', [TermController::class, 'index']);
        Route::post('/terms', [TermController::class, 'store']);
        Route::get('/terms/{term}', [TermController::class, 'show']);
        Route::put('/terms/{term}', [TermController::class, 'update']);
        Route::delete('/terms/{term}', [TermController::class, 'destroy']);

        // FAQ management
        Route::get('/faqs/all', [FaqController::class, 'all']);
        Route::post('/faqs', [FaqController::class, 'store']);
        Route::get('/faqs/{faq}', [FaqController::class, 'show']);
        Route::put('/faqs/{faq}', [FaqController::class, 'update']);
        Route::delete('/faqs/{faq}', [FaqController::class, 'destroy']);
        Route::post('/faqs/reorder', [FaqController::class, 'reorder']);

        // Support tickets management
        Route::get('/support-tickets', [SupportTicketController::class, 'index']);
        Route::post('/support-tickets', [SupportTicketController::class, 'store']);
        Route::patch('/support-tickets/{supportTicket}', [SupportTicketController::class, 'update']);

        // Collaborators management
        Route::get('/collaborators', [CollaboratorController::class, 'index']);
        Route::post('/collaborators/invite', [CollaboratorController::class, 'invite']);
        Route::delete('/collaborators/{user}', [CollaboratorController::class, 'destroy']);

        // System health telemetry
        Route::get('/system/health', [SystemHealthController::class, 'show']);
    });

    // Document submission review (developer/admin/staff)
    Route::post('/document-submissions/{submission}/review', [DocumentSubmissionController::class, 'review'])->middleware('role:developer,admin,head,staff');

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
