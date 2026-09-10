<?php

namespace Tests\Feature;

use App\Models\ExternalIdentity;
use App\Models\OidcConnection;
use App\Models\User;
use App\Services\Sso\SsoIdentityMatcher;
use App\Services\Sso\SsoRolloutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SsoIdentityTest extends TestCase
{
    use RefreshDatabase;

    private OidcConnection $connection;

    private User $student;

    protected function setUp(): void
    {
        parent::setUp();
        $this->connection = OidcConnection::create(['display_name' => 'Fictional SIS', 'issuer' => 'https://sis.example.edu', 'client_id' => 'unifast', 'client_secret' => 'test-secret', 'student_id_claim' => 'student_id']);
        $this->student = User::factory()->create(['role' => 'student', 'account_status' => 'active', 'student_id' => 'STU-001', 'name' => 'Fictional Student', 'email' => 'fictional@example.edu']);
    }

    public function test_matching_existing_student_binds_once(): void
    {
        $this->assertSame($this->student->id, $this->match()['user']->id);
        $this->assertSame($this->student->id, $this->match()['user']->id);
        $this->assertDatabaseCount('external_identities', 1);
    }

    public function test_unknown_student_is_never_created(): void
    {
        $this->assertSame('sso_not_eligible', $this->match(['student_id' => 'UNKNOWN'])['code']);
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('external_identities', 0);
    }

    public function test_unverified_email_cannot_bind(): void
    {
        $this->assertSame('sso_not_eligible', $this->match(['email_verified' => false])['code']);
        $this->assertDatabaseCount('external_identities', 0);
    }

    public function test_staff_and_mixed_roles_are_rejected(): void
    {
        $this->student->update(['role' => 'admin']);
        $this->assertSame('sso_account_blocked', $this->match()['code']);
        $this->assertDatabaseCount('external_identities', 0);
        $this->assertDatabaseCount('sso_identity_reviews', 1);
    }

    public function test_email_collision_blocks_and_creates_review(): void
    {
        User::factory()->create(['email' => 'other@example.edu']);
        $result = $this->match(['email' => 'other@example.edu']);
        $this->assertNull($result['user']);
        $this->assertSame('sso_identity_review', $result['code']);
        $this->assertDatabaseHas('sso_identity_reviews', ['reason' => 'cross_account_collision']);
    }

    public function test_name_mismatch_restricts_existing_and_fallback_sessions(): void
    {
        $result = $this->match(['name' => 'Different Name']);
        $this->assertSame($this->student->id, $result['user']->id);
        $this->assertTrue($result['under_verification']);
        $this->actingAs($this->student)->getJson('/api/student/notifications')->assertForbidden();
        $this->getJson('/api/auth/me')->assertOk()->assertJsonPath('user.onboarding_next_step', 'sso_review');
        $this->getJson('/api/auth/sis/status')->assertOk()->assertJsonPath('under_verification', true);
        $this->assertDatabaseCount('external_identities', 0);
    }

    public function test_forwarded_pilot_cannot_bind_another_student(): void
    {
        $other = User::factory()->create(['role' => 'student']);
        $this->assertSame('sso_not_eligible', $this->match([], $other->id)['code']);
        $this->assertDatabaseCount('external_identities', 0);
    }

    public function test_bound_subject_cannot_change_student_id(): void
    {
        $this->match();
        $this->assertSame('sso_identity_review', $this->match(['student_id' => 'NEW-ID'])['code']);
        $this->assertDatabaseHas('sso_identity_reviews', ['reason' => 'student_id_changed']);
        $this->assertFalse(ExternalIdentity::firstOrFail()->enabled);
    }

    public function test_provider_replacement_unlinks_old_binding_for_a_fresh_pilot_match(): void
    {
        $this->match();
        app(SsoRolloutService::class)->save([
            'display_name' => 'Replacement SIS', 'issuer' => 'https://replacement.example.edu',
            'client_id' => 'replacement-client', 'client_secret' => 'replacement-secret',
            'student_id_claim' => 'student_id', 'confirm' => true,
        ]);
        $this->assertDatabaseCount('external_identities', 0);
        $connection = $this->connection->fresh();
        $this->assertSame('draft', $connection->state);
        $this->assertSame($this->student->id, app(SsoIdentityMatcher::class)->match($connection, [
            'sub' => 'replacement-subject', 'student_id' => 'STU-001', 'email' => 'fictional@example.edu',
            'email_verified' => true, 'name' => 'Fictional Student',
        ])['user']->id);
    }

    private function match(array $overrides = [], ?int $pilot = null): array
    {
        return app(SsoIdentityMatcher::class)->match($this->connection, array_replace(['sub' => 'subject-1', 'student_id' => 'stu001', 'email' => 'fictional@example.edu', 'email_verified' => true, 'name' => 'Fictional Student'], $overrides), $pilot);
    }
}
