<?php

namespace Tests\Unit;

use App\Models\Grantee;
use App\Services\SubmissionRiskScoringService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SubmissionRiskScoringServiceTest extends TestCase
{
    #[Test]
    public function it_adds_qr_risk_when_gradeslip_qr_is_invalid(): void
    {
        $service = $this->app->make(SubmissionRiskScoringService::class);
        $grantee = new Grantee(['student_id' => '2024-000123', 'full_name' => 'Juan Dela Cruz']);

        $signals = $service->collectSignals(
            identityFailed: false,
            ocrSummary: [],
            authenticityStatus: 'stubbed',
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
            authenticityStatus: 'stubbed',
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
}
