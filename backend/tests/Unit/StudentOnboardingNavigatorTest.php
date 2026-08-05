<?php

namespace Tests\Unit;

use App\Models\Batch;
use App\Models\Grantee;
use App\Models\GranteeIdentityProfile;
use App\Models\User;
use App\Services\StudentOnboardingNavigator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class StudentOnboardingNavigatorTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function pending_identity_with_stale_completed_profile_resumes_liveness(): void
    {
        $batch = Batch::query()->create([
            'name' => 'Navigator Test Batch',
            'academic_year' => '2026-2027',
            'semester' => '1st Semester',
            'status' => 'active',
            'window_status' => 'active',
            'is_active' => true,
        ]);

        $user = User::factory()->create([
            'role' => 'student',
            'account_status' => 'pending_identity',
            'student_id' => '20231909',
            'name' => 'Rafael Balacuit',
        ]);

        $grantee = Grantee::query()->create([
            'user_id' => $user->id,
            'batch_id' => $batch->id,
            'student_id' => '20231909',
            'student_number' => '20231909',
            'full_name' => 'Rafael Balacuit',
            'email' => $user->email,
            'program' => 'BSIT',
            'year_level' => '1',
            'status' => 'unverified',
            'submission_status' => 'not_submitted',
        ]);

        GranteeIdentityProfile::query()->create([
            'grantee_id' => $grantee->id,
            'user_id' => $user->id,
            'status' => 'completed',
            'id_scan_completed_at' => now(),
            'onboarding_completed_at' => null,
        ]);

        $step = (new StudentOnboardingNavigator)->nextStep($user->fresh());

        $this->assertSame('liveness', $step);
    }

    #[Test]
    public function unverified_or_pending_kyc_never_resumes_at_liveness_even_with_stale_profile(): void
    {
        $batch = Batch::query()->create([
            'name' => 'Navigator KYC Gate Batch',
            'academic_year' => '2026-2027',
            'semester' => '1st Semester',
            'status' => 'active',
            'window_status' => 'active',
            'is_active' => true,
        ]);

        $user = User::factory()->create([
            'role' => 'student',
            'account_status' => 'unverified',
            'student_id' => '20231909',
            'name' => 'Rafael Balacuit',
        ]);

        $grantee = Grantee::query()->create([
            'user_id' => $user->id,
            'batch_id' => $batch->id,
            'student_id' => '20231909',
            'student_number' => '20231909',
            'full_name' => 'Rafael Balacuit',
            'email' => $user->email,
            'program' => 'BSIT',
            'year_level' => '1',
            'status' => 'unverified',
            'submission_status' => 'not_submitted',
        ]);

        GranteeIdentityProfile::query()->create([
            'grantee_id' => $grantee->id,
            'user_id' => $user->id,
            'status' => 'pending_liveness',
            'id_scan_completed_at' => now(),
            'onboarding_completed_at' => null,
        ]);

        $navigator = new StudentOnboardingNavigator;

        $this->assertSame('kyc', $navigator->nextStep($user->fresh()));

        $user->forceFill(['account_status' => 'pending_kyc'])->save();
        $this->assertSame('kyc', $navigator->nextStep($user->fresh()));
    }
}
