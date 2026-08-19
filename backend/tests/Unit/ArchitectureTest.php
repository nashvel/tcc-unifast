<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ArchitectureTest extends TestCase
{
    public function test_the_application_uses_the_expected_php_runtime(): void
    {
        $this->assertGreaterThanOrEqual(80300, PHP_VERSION_ID);
    }

    public function test_database_viewer_routes_keep_auth_and_permission_middleware(): void
    {
        $routes = collect(Route::getRoutes())->filter(
            fn ($route) => str_starts_with($route->uri(), 'api/database/')
        );

        $this->assertNotEmpty($routes);

        foreach ($routes as $route) {
            $middleware = $route->gatherMiddleware();

            $this->assertContains('auth:sanctum', $middleware, $route->uri());
            $this->assertContains('permission:view_database', $middleware, $route->uri().' must require view_database.');
        }
    }

    public function test_database_viewer_controller_keeps_policy_and_audit_split_out(): void
    {
        $controller = app_path('Http/Controllers/DatabaseController.php');
        $policy = app_path('Services/DatabaseViewerPolicy.php');
        $auditLogger = app_path('Services/DatabaseViewerAuditLogger.php');

        $this->assertLessThanOrEqual(250, count(file($controller)));
        $this->assertLessThanOrEqual(100, count(file($policy)));
        $this->assertLessThanOrEqual(50, count(file($auditLogger)));
    }

    public function test_requirement_vault_controller_does_not_regress_past_current_size_limit(): void
    {
        $path = app_path('Http/Controllers/RequirementVaultController.php');

        $this->assertLessThanOrEqual(
            450,
            count(file($path)),
            'RequirementVaultController should keep shrinking as use-case services are extracted.'
        );
    }

    public function test_identity_onboarding_controller_stays_under_controller_size_limit(): void
    {
        $path = app_path('Http/Controllers/IdentityOnboardingController.php');

        $this->assertLessThanOrEqual(
            500,
            count(file($path)),
            'IdentityOnboardingController should keep identity business rules in services.'
        );
    }

    public function test_document_submission_controller_keeps_presentation_split_out(): void
    {
        $controller = app_path('Http/Controllers/DocumentSubmissionController.php');
        $presenter = app_path('Services/DocumentSubmissionPresenter.php');

        $this->assertLessThanOrEqual(275, count(file($controller)));
        $this->assertLessThanOrEqual(175, count(file($presenter)));
    }

    public function test_eligibility_controller_keeps_detail_rules_split_out(): void
    {
        $controller = app_path('Http/Controllers/EligibilityController.php');
        $presenter = app_path('Services/EligibilityPresenter.php');

        $this->assertLessThanOrEqual(150, count(file($controller)));
        $this->assertLessThanOrEqual(275, count(file($presenter)));
    }

    public function test_masterlist_import_controller_keeps_response_shapes_split_out(): void
    {
        $controller = app_path('Http/Controllers/MasterlistImportController.php');
        $presenter = app_path('Services/MasterlistImportPresenter.php');
        $rowValidator = app_path('Services/MasterlistImportRowValidator.php');

        $this->assertLessThanOrEqual(240, count(file($controller)));
        $this->assertLessThanOrEqual(100, count(file($presenter)));
        $this->assertLessThanOrEqual(100, count(file($rowValidator)));
    }

    public function test_requirement_submission_pipeline_job_stays_thin(): void
    {
        $path = app_path('Jobs/ProcessRequirementSubmissionPipeline.php');

        $this->assertLessThanOrEqual(
            100,
            count(file($path)),
            'ProcessRequirementSubmissionPipeline should only dispatch to the pipeline service.'
        );
    }

    public function test_requirement_submission_pipeline_services_stay_split_by_responsibility(): void
    {
        $processor = app_path('Services/SubmissionPipeline/ProcessRequirementSubmissionService.php');
        $externalChecks = app_path('Services/SubmissionPipeline/PipelineExternalChecksService.php');
        $academicOcr = app_path('Services/SubmissionPipeline/PipelineAcademicOcrService.php');

        $this->assertLessThanOrEqual(225, count(file($processor)));
        $this->assertLessThanOrEqual(175, count(file($externalChecks)));
        $this->assertLessThanOrEqual(325, count(file($academicOcr)));
    }

    public function test_submission_risk_scoring_keeps_eligibility_split_out(): void
    {
        $scoring = app_path('Services/SubmissionRiskScoringService.php');
        $eligibility = app_path('Services/SubmissionEligibilityEvaluator.php');

        $this->assertLessThanOrEqual(150, count(file($scoring)));
        $this->assertLessThanOrEqual(300, count(file($eligibility)));
    }

    public function test_academic_grade_parser_keeps_text_extraction_split_out(): void
    {
        $parser = app_path('Services/AcademicGradeParser.php');
        $textParser = app_path('Services/AcademicGradeTextParser.php');
        $termAnalyzer = app_path('Services/AcademicTermAnalyzer.php');
        $courseSummarizer = app_path('Services/AcademicCourseSummarizer.php');

        $this->assertLessThanOrEqual(600, count(file($parser)));
        $this->assertLessThanOrEqual(300, count(file($textParser)));
        $this->assertLessThanOrEqual(400, count(file($termAnalyzer)));
        $this->assertLessThanOrEqual(300, count(file($courseSummarizer)));
    }

    public function test_id_card_ocr_service_keeps_parsing_and_matching_split_out(): void
    {
        $ocr = app_path('Services/IdCardOcrService.php');
        $backParser = app_path('Services/IdCardBackParser.php');
        $identityMatcher = app_path('Services/IdCardIdentityMatcher.php');

        $this->assertLessThanOrEqual(250, count(file($ocr)));
        $this->assertLessThanOrEqual(150, count(file($backParser)));
        $this->assertLessThanOrEqual(350, count(file($identityMatcher)));
    }

    public function test_requirement_vault_services_do_not_depend_on_http_request(): void
    {
        $paths = glob(app_path('Services/RequirementVault/*.php')) ?: [];

        $this->assertNotEmpty($paths);

        foreach ($paths as $path) {
            $contents = file_get_contents($path);

            $this->assertStringNotContainsString('Illuminate\Http\Request', $contents, basename($path));
        }
    }

    public function test_identity_onboarding_services_do_not_depend_on_http_request(): void
    {
        $paths = glob(app_path('Services/IdentityOnboarding/*.php')) ?: [];

        $this->assertNotEmpty($paths);

        foreach ($paths as $path) {
            $contents = file_get_contents($path);

            $this->assertStringNotContainsString('Illuminate\Http\Request', $contents, basename($path));
        }
    }
}
