<?php

use App\Http\Controllers\AcademicProgramController;
use App\Http\Controllers\AcademicRecordController;
use App\Http\Controllers\ActivationController;
use App\Http\Controllers\ActivationSeederController;
use App\Http\Controllers\AdminStudentIdSampleController;
use App\Http\Controllers\AuditEventController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BatchActivationNotificationController;
use App\Http\Controllers\BatchController;
use App\Http\Controllers\BillingReportController;
use App\Http\Controllers\ChangelogController;
use App\Http\Controllers\CollaboratorController;
use App\Http\Controllers\DatabaseController;
use App\Http\Controllers\DeveloperServicesController;
use App\Http\Controllers\DistributionReportController;
use App\Http\Controllers\DocumentFileController;
use App\Http\Controllers\DocumentSubmissionController;
use App\Http\Controllers\EligibilityController;
use App\Http\Controllers\FaceReviewController;
use App\Http\Controllers\FaqController;
use App\Http\Controllers\FileManagerController;
use App\Http\Controllers\FormController;
use App\Http\Controllers\FormResponseController;
use App\Http\Controllers\FormSecurityLogController;
use App\Http\Controllers\GranteeController;
use App\Http\Controllers\GranteeFormController;
use App\Http\Controllers\IdentityOnboardingController;
use App\Http\Controllers\MasterlistImportController;
use App\Http\Controllers\OnboardingCenterController;
use App\Http\Controllers\PolicySettingsController;
use App\Http\Controllers\PublicFormController;
use App\Http\Controllers\RbacController;
use App\Http\Controllers\RequirementVaultController;
use App\Http\Controllers\StudentDocumentOcrController;
use App\Http\Controllers\StudentFaceVerificationController;
use App\Http\Controllers\StudentKycController;
use App\Http\Controllers\StudentNotificationController;
use App\Http\Controllers\StudentSettingsController;
use App\Http\Controllers\StudentSubmissionWindowController;
use App\Http\Controllers\SupportTicketController;
use App\Http\Controllers\SystemHealthController;
use App\Http\Controllers\TccUnifastStudentsController;
use App\Http\Controllers\TccUnifastSyncController;
use App\Http\Controllers\TermController;
use App\Http\Middleware\FormSecurityHeaders;
use App\Support\VaultFileStorage;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;

// Public auth routes (session for CSRF; cookies via api middleware stack)
Route::middleware([
    StartSession::class,
])->group(function (): void {
    Route::post('/auth/login', [AuthController::class, 'login'])
        ->middleware('throttle:'.config('services.auth.login_throttle_per_minute', 5).',1');
    Route::post('/auth/refresh', [AuthController::class, 'refresh'])
        ->middleware('throttle:'.config('services.auth.refresh_throttle_per_minute', 30).',1');
    Route::get('/activation/{token}', [ActivationController::class, 'show'])->middleware('throttle:20,1');
    Route::post('/activation/{token}', [ActivationController::class, 'activate'])->middleware('throttle:10,1');
});

// Public content (for login page)
Route::get('/terms/active', [TermController::class, 'active']);
Route::get('/faqs', [FaqController::class, 'index']);

// Time-limited signed file access (no bearer token; HMAC signature required)
Route::get('/signed/document-files/{submission}/{variant}', [DocumentFileController::class, 'showSigned'])
    ->middleware(['signed', 'throttle:120,1'])
    ->where('variant', 'primary|secondary')
    ->name('signed.document-files.show');
Route::get('/signed/identity-photos/{grantee}/{filename}', [DocumentFileController::class, 'showIdentityPhoto'])
    ->middleware(['signed', 'throttle:120,1'])
    ->where('filename', VaultFileStorage::identityFilenameRoutePattern())
    ->name('signed.identity-photos.show');

// Authenticated routes (Sanctum token required)
Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    // Student routes
    Route::middleware('role:student')->group(function (): void {
        Route::get('/student/kyc', [StudentKycController::class, 'show'])->middleware('throttle:30,1');
        Route::post('/student/kyc', [StudentKycController::class, 'store'])->middleware('throttle:60,1');
        Route::get('/student/identity-onboarding', [IdentityOnboardingController::class, 'show']);
        Route::get('/student/identity-onboarding/ocr-health', [IdentityOnboardingController::class, 'ocrHealth'])->middleware('throttle:30,1');
        Route::post('/student/identity-onboarding/id-scan/ocr-front', [IdentityOnboardingController::class, 'validateFrontIdOcr'])->middleware('throttle:30,1');
        Route::post('/student/identity-onboarding/id-scan', [IdentityOnboardingController::class, 'storeIdScan'])->middleware('throttle:30,1');
        Route::post('/student/identity-onboarding/liveness', [IdentityOnboardingController::class, 'storeLiveness'])->middleware('throttle:10,1');
        Route::get('/student/identity-onboarding/references', [IdentityOnboardingController::class, 'referenceFace']);
        Route::get('/student/identity-onboarding/photos/{filename}', [DocumentFileController::class, 'showOwnIdentityPhoto'])
            ->where('filename', VaultFileStorage::identityFilenameRoutePattern())
            ->middleware('throttle:60,1');
        Route::get('/student/submission-window', StudentSubmissionWindowController::class);
        Route::get('/student/requirement-vault', [RequirementVaultController::class, 'show']);
        Route::post('/student/requirement-vault/document', [RequirementVaultController::class, 'storeDocument'])->middleware('throttle:20,1');
        Route::post('/student/requirement-vault/confirm', [RequirementVaultController::class, 'confirm'])
            ->middleware('throttle:'.config('services.requirement_vault.confirm_throttle_per_minute', 20).',1');
        Route::post('/student/requirement-vault/resubmit-slot', [RequirementVaultController::class, 'resubmitSlot'])->middleware('throttle:10,1');
        Route::get('/student/requirement-vault/files/{submission}/{variant?}', [DocumentFileController::class, 'showAuthenticated'])
            ->where('variant', 'primary|secondary')
            ->middleware('throttle:60,1');
        Route::get('/student/notifications', [StudentNotificationController::class, 'index']);
        Route::post('/student/notifications/{notification}/read', [StudentNotificationController::class, 'markRead']);
        Route::post('/student/notifications/read-all', [StudentNotificationController::class, 'markAllRead']);
        Route::post('/student/submissions/ocr', StudentDocumentOcrController::class)->middleware('throttle:20,1');
        Route::post('/student/identity/face-verify', StudentFaceVerificationController::class)->middleware('throttle:10,1');
        Route::post('/student/settings/pin', [StudentSettingsController::class, 'updateSecurityPin'])->middleware('throttle:10,1');
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
        Route::get('/document-submission-packages', [DocumentSubmissionController::class, 'packages']);
        Route::get('/document-submission-packages/{granteeId}/{batchId}', [DocumentSubmissionController::class, 'packageShow'])
            ->whereNumber('granteeId')
            ->whereNumber('batchId');
        Route::get('/face-reviews', [FaceReviewController::class, 'index']);
        Route::get('/face-reviews/{faceReview}', [FaceReviewController::class, 'show']);
        Route::post('/face-reviews/{faceReview}/approve', [FaceReviewController::class, 'approve'])->middleware('throttle:30,1');
        Route::post('/face-reviews/{faceReview}/reject', [FaceReviewController::class, 'reject'])->middleware('throttle:30,1');
        Route::get('/grantees/{grantee}/identity-photos/{filename}', [DocumentFileController::class, 'showStaffIdentityPhoto'])
            ->where('filename', VaultFileStorage::identityFilenameRoutePattern())
            ->middleware('throttle:60,1');
        Route::get('/files', [FileManagerController::class, 'index']);
        Route::get('/files/imports/{import}/download', [FileManagerController::class, 'downloadImport'])
            ->middleware('throttle:60,1');
        Route::get('/document-submissions/{submission}', [DocumentSubmissionController::class, 'show']);
        Route::get('/document-submissions/{submission}/file/{variant?}', [DocumentFileController::class, 'showAuthenticated'])
            ->where('variant', 'primary|secondary')
            ->middleware('throttle:60,1');
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

        Route::get('/onboarding-center/batches/{batch}/stats', [OnboardingCenterController::class, 'stats']);
        Route::get('/onboarding-center/batches/{batch}/grantees', [OnboardingCenterController::class, 'grantees']);
        Route::post('/onboarding-center/batches/{batch}/blast-invites', [OnboardingCenterController::class, 'blastInvites'])->middleware('throttle:5,1');
        Route::post('/onboarding-center/grantees/{grantee}/resend-invite', [OnboardingCenterController::class, 'resendInvite'])->middleware('throttle:20,1');
    });

    // RBAC + database viewer (developer/admin)
    Route::middleware('role:developer,admin')->group(function (): void {
        // Activation Seeder — create activation-ready grantees from the browser
        Route::get('/activation-seeder/batches', [ActivationSeederController::class, 'batches']);
        Route::get('/activation-seeder/history', [ActivationSeederController::class, 'history']);
        Route::post('/activation-seeder', [ActivationSeederController::class, 'seed'])->middleware('throttle:30,1');
        Route::post('/activation-seeder/regenerate/{grantee}', [ActivationSeederController::class, 'regenerate'])->middleware('throttle:10,1');

        // Service Manager
        Route::get('/services/status', [DeveloperServicesController::class, 'status']);
        Route::post('/services/start-ocr', [DeveloperServicesController::class, 'startOcr'])->middleware('throttle:20,1');
        Route::post('/services/start-cloudflare', [DeveloperServicesController::class, 'startCloudflare'])->middleware('throttle:20,1');

        Route::get('/changelogs', [ChangelogController::class, 'index']);
        Route::get('/rbac/roles', [RbacController::class, 'index'])->middleware('throttle:60,1');
        Route::post('/rbac/roles', [RbacController::class, 'store'])->middleware('throttle:30,1');
        Route::get('/rbac/roles/{role}', [RbacController::class, 'show'])->middleware('throttle:60,1');
        Route::put('/rbac/roles/{role}', [RbacController::class, 'update'])->middleware('throttle:30,1');
        Route::delete('/rbac/roles/{role}', [RbacController::class, 'destroy'])->middleware('throttle:30,1');
        Route::get('/rbac/permissions', [RbacController::class, 'permissions'])->middleware('throttle:60,1');
        Route::post('/rbac/permissions', [RbacController::class, 'storePermission'])->middleware('throttle:30,1');
        Route::delete('/rbac/permissions/{permission}', [RbacController::class, 'destroyPermission'])->middleware('throttle:30,1');
        Route::get('/rbac/users/{user}/roles', [RbacController::class, 'userRoles'])->middleware('throttle:60,1');
        Route::post('/rbac/users/{user}/roles', [RbacController::class, 'assignUserRole'])->middleware('throttle:30,1');
        Route::delete('/rbac/users/{user}/roles/{role}', [RbacController::class, 'removeUserRole'])->middleware('throttle:30,1');
        Route::put('/rbac/users/{user}/roles', [RbacController::class, 'syncUserRoles'])->middleware('throttle:30,1');
        Route::post('/rbac/check-permission', [RbacController::class, 'checkPermission'])->middleware('throttle:60,1');

        Route::get('/database/tables', [DatabaseController::class, 'tables']);
        Route::get('/database/stats', [DatabaseController::class, 'stats']);
        Route::get('/database/tables/{table}', [DatabaseController::class, 'table']);
        Route::get('/database/tables/{table}/rows', [DatabaseController::class, 'rows']);

        // Terms & Conditions management
        Route::get('/terms', [TermController::class, 'index']);
        Route::post('/terms', [TermController::class, 'store'])->middleware('throttle:20,1');
        Route::get('/terms/{term}', [TermController::class, 'show']);
        Route::put('/terms/{term}', [TermController::class, 'update'])->middleware('throttle:20,1');
        Route::delete('/terms/{term}', [TermController::class, 'destroy'])->middleware('throttle:20,1');

        // FAQ management
        Route::get('/faqs/all', [FaqController::class, 'all']);
        Route::post('/faqs', [FaqController::class, 'store'])->middleware('throttle:20,1');
        Route::get('/faqs/{faq}', [FaqController::class, 'show']);
        Route::put('/faqs/{faq}', [FaqController::class, 'update'])->middleware('throttle:20,1');
        Route::delete('/faqs/{faq}', [FaqController::class, 'destroy'])->middleware('throttle:20,1');
        Route::post('/faqs/reorder', [FaqController::class, 'reorder'])->middleware('throttle:20,1');

        // Support tickets management
        Route::get('/support-tickets', [SupportTicketController::class, 'index']);
        Route::post('/support-tickets', [SupportTicketController::class, 'store'])->middleware('throttle:20,1');
        Route::patch('/support-tickets/{supportTicket}', [SupportTicketController::class, 'update'])->middleware('throttle:20,1');

        // Collaborators management
        Route::get('/collaborators', [CollaboratorController::class, 'index'])->middleware('throttle:60,1');
        Route::post('/collaborators/invite', [CollaboratorController::class, 'invite'])->middleware('throttle:20,1');
        Route::delete('/collaborators/{user}', [CollaboratorController::class, 'destroy'])->middleware('throttle:20,1');

        // System health telemetry
        Route::get('/system/health', [SystemHealthController::class, 'show']);
    });

    // Document submission review (developer/admin/staff)
    Route::post('/document-submissions/{submission}/review', [DocumentSubmissionController::class, 'review'])
        ->middleware(['role:developer,admin,head,staff', 'throttle:60,1']);

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

// ══════════════════════════════════════════════════════════════
// Dynamic Form Creation Module
// ══════════════════════════════════════════════════════════════

// Public form endpoints — no authentication required
Route::middleware(['throttle:30,1', FormSecurityHeaders::class])->group(function (): void {
    Route::get('/forms/public/{token}', [PublicFormController::class, 'show']);
    Route::post('/forms/public/{token}/responses', [PublicFormController::class, 'store'])
        ->middleware('throttle:5,1');
});

// Authenticated form endpoints
Route::middleware(['auth:sanctum', FormSecurityHeaders::class])->group(function (): void {

    // ── Grantee portal ───────────────────────────────────────────────
    Route::middleware('role:student')->group(function (): void {
        Route::get('/forms/assigned', [GranteeFormController::class, 'assigned']);
        Route::get('/forms/{id}/schema', [GranteeFormController::class, 'schema'])
            ->whereNumber('id');
        Route::post('/forms/{id}/responses', [GranteeFormController::class, 'submit'])
            ->whereNumber('id')
            ->middleware('throttle:10,1');
    });

    // ── Staff — response viewing only ────────────────────────────────
    Route::middleware('role:developer,admin,head,staff')->group(function (): void {
        Route::get('/forms/{id}/responses', [FormResponseController::class, 'index'])
            ->whereNumber('id');
        Route::get('/forms/{id}/responses/{rid}', [FormResponseController::class, 'show'])
            ->whereNumber('id')->whereNumber('rid');
    });

    // ── Form management (admin / staff) ──────────────────────────────
    Route::middleware('role:developer,admin,head,staff')->prefix('forms')->group(function (): void {
        // Form CRUD
        Route::get('/', [FormController::class, 'index'])->middleware('throttle:60,1');
        Route::post('/', [FormController::class, 'store'])->middleware('throttle:60,1');
        Route::get('/{id}', [FormController::class, 'show'])->whereNumber('id');
        Route::put('/{id}', [FormController::class, 'update'])->whereNumber('id')->middleware('throttle:60,1');
        Route::delete('/{id}', [FormController::class, 'destroy'])->whereNumber('id')->middleware('throttle:60,1');
        Route::patch('/{id}/toggle', [FormController::class, 'toggle'])->whereNumber('id')->middleware('throttle:60,1');
        Route::patch('/{id}/regenerate-token', [FormController::class, 'regenerateToken'])->whereNumber('id')->middleware('throttle:20,1');

        // Publish workflow
        Route::patch('/{id}/publish', [FormController::class, 'publish'])->whereNumber('id')->middleware('throttle:20,1');
        Route::patch('/{id}/close', [FormController::class, 'close'])->whereNumber('id')->middleware('throttle:20,1');

        // Analytics
        Route::get('/{id}/analytics', [FormController::class, 'analytics'])->whereNumber('id');

        // Sections
        Route::post('/{id}/sections', [FormController::class, 'storeSections'])->whereNumber('id')->middleware('throttle:60,1');
        Route::put('/{id}/sections/{sid}', [FormController::class, 'updateSection'])->whereNumber('id')->whereNumber('sid')->middleware('throttle:60,1');
        Route::delete('/{id}/sections/{sid}', [FormController::class, 'destroySection'])->whereNumber('id')->whereNumber('sid')->middleware('throttle:60,1');
        Route::patch('/{id}/sections/reorder', [FormController::class, 'reorderSections'])->whereNumber('id')->middleware('throttle:60,1');

        // Field management (via FormController)
        Route::post('/{id}/fields', [FormController::class, 'storeField'])->whereNumber('id')->middleware('throttle:60,1');
        Route::put('/{id}/fields/{fid}', [FormController::class, 'updateField'])->whereNumber('id')->whereNumber('fid')->middleware('throttle:60,1');
        Route::delete('/{id}/fields/{fid}', [FormController::class, 'destroyField'])->whereNumber('id')->whereNumber('fid')->middleware('throttle:60,1');
        Route::patch('/{id}/fields/reorder', [FormController::class, 'reorderFields'])->whereNumber('id')->middleware('throttle:60,1');

        // Response export (admin only)
        Route::get('/{id}/responses/export', [FormResponseController::class, 'export'])->whereNumber('id');

        // Security logs (admin only)
        Route::get('/{id}/security-logs', [FormSecurityLogController::class, 'index'])->whereNumber('id');
    });
});
