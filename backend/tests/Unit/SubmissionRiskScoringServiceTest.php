<?php

namespace Tests\Unit;

use App\Models\Grantee;
use App\Services\SubmissionRiskScoringService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SubmissionRiskScoringServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_adds_qr_risk_when_gradeslip_qr_is_invalid(): void
    {
        $service = $this->app->make(SubmissionRiskScoringService::class);
        $grantee = new Grantee(['student_id' => '2024-000123', 'full_name' => 'Juan Dela Cruz']);

        $signals = $service->collectSignals(
            identityFailed: false,
            ocrSummary: [],
            authenticityStatus: 'disabled',
            grantee: $grantee,
            gradeslipQr: [
                'status' => 'invalid',
                'success' => false,
                'found' => false,
                'domain_valid' => false,
                'error_code' => 'qr_not_found',
            ],
        );

        $this->assertSame(30, $signals['qr_code_invalid_or_domain_mismatch'] ?? null);
        $this->assertSame(30, $service->score($signals));
    }

    #[Test]
    public function it_skips_qr_risk_when_pyzbar_dependency_missing(): void
    {
        $service = $this->app->make(SubmissionRiskScoringService::class);
        $grantee = new Grantee(['student_id' => '2024-000123', 'full_name' => 'Juan Dela Cruz']);

        $signals = $service->collectSignals(
            identityFailed: false,
            ocrSummary: [],
            authenticityStatus: 'disabled',
            grantee: $grantee,
            gradeslipQr: [
                'status' => 'unavailable',
                'success' => false,
                'found' => false,
                'domain_valid' => false,
                'error_code' => 'dependency_missing',
            ],
        );

        $this->assertArrayNotHasKey('qr_code_invalid_or_domain_mismatch', $signals);
        $this->assertSame(0, $service->score($signals));
    }

    #[Test]
    public function it_prefers_course_history_source_for_eligibility(): void
    {
        $service = $this->app->make(SubmissionRiskScoringService::class);
        $grantee = new Grantee(['student_id' => '2024-000123', 'full_name' => 'Julius', 'program' => 'BSIT']);

        $eligibility = $service->evaluateEligibility($grantee, [
            'grade_slip' => [
                'raw_text' => 'Grade Slip',
                'courses' => [
                    ['code' => 'A', 'description' => 'Ok', 'units' => '3', 'grade' => '1.0', 'remarks' => 'Passed'],
                ],
            ],
            'course_history' => [
                'raw_text' => 'Course History',
                'courses' => [
                    ['code' => 'B', 'description' => 'Fail', 'units' => '3', 'grade' => '5.0', 'remarks' => 'Failed'],
                ],
            ],
        ]);

        $this->assertSame('course_history', $eligibility['document_type']);
        $this->assertSame(1, $eligibility['failed_count']);
    }
}
