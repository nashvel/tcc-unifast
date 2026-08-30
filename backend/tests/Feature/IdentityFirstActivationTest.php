<?php

namespace Tests\Feature;

use App\Mail\GranteeActivationInviteMail;
use App\Models\ActivationToken;
use App\Models\Batch;
use App\Models\Grantee;
use App\Models\GranteeIdentityProfile;
use App\Models\User;
use App\Services\AuthTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Phases 3–5 — identity-first activation.
 *
 * Replaces OnboardingFlowTest::test_activation_with_token_only_moves_student_to_kyc,
 * which asserted the old password-at-link-click behaviour.
 */
class IdentityFirstActivationTest extends TestCase
{
    use RefreshDatabase;

    private const UNUSABLE = 'unusable-hash-sentinel';

    // ── I1: no credential before verification ─────────────────────────────────

    public function test_begin_issues_onboarding_session_without_creating_a_credential(): void
    {
        [$user, $token] = $this->invitedStudent();
        $before = $user->password;

        $this->postJson("/api/activation/{$token}/begin")
            ->assertOk()
            ->assertJsonPath('user.account_status', 'pending_kyc')
            ->assertJsonPath('user.onboarding_next_step', 'kyc')
            ->assertJsonPath('user.onboarding_path', '/student/kyc')
            ->assertPlainCookie(config('services.auth.access_cookie'));

        $user = $user->fresh();
        $this->assertSame($before, $user->password, 'Password must not change at link click.');
        $this->assertNull($user->email_verified_at);
        $this->assertNull($user->activated_at);
    }

    public function test_begin_does_not_accept_or_set_a_password(): void
    {
        [$user, $token] = $this->invitedStudent();

        $this->postJson("/api/activation/{$token}/begin", [
            'password' => 'attacker-chosen-password',
            'password_confirmation' => 'attacker-chosen-password',
        ])->assertOk();

        $this->assertFalse(
            Hash::check('attacker-chosen-password', $user->fresh()->password),
            'A password supplied at /begin must be ignored.',
        );
    }

    public function test_begin_issues_no_refresh_token(): void
    {
        [, $token] = $this->invitedStudent();

        $this->postJson("/api/activation/{$token}/begin")->assertOk();

        // Nothing to rotate ⇒ scope cannot be escalated (I3).
        $this->assertDatabaseCount('refresh_tokens', 0);
    }

    // ── Token lifecycle ───────────────────────────────────────────────────────

    public function test_begin_does_not_consume_the_token_so_the_funnel_can_resume(): void
    {
        [, $token] = $this->invitedStudent();

        $this->postJson("/api/activation/{$token}/begin")->assertOk();
        $row = ActivationToken::query()->firstOrFail();
        $this->assertNotNull($row->first_used_at);
        $this->assertNull($row->used_at, 'The link must stay usable for the whole funnel.');

        // Session expired / tab closed → same link works again.
        $this->postJson("/api/activation/{$token}/begin")->assertOk();
    }

    public function test_second_begin_revokes_the_previous_onboarding_session(): void
    {
        [$user, $token] = $this->invitedStudent();

        $this->postJson("/api/activation/{$token}/begin")->assertOk();
        $first = $user->fresh()->tokens()->pluck('id')->all();

        $this->postJson("/api/activation/{$token}/begin")->assertOk();
        $second = $user->fresh()->tokens()->pluck('id')->all();

        $this->assertCount(1, $second);
        $this->assertNotSame($first, $second);
    }

    public function test_expired_token_is_rejected(): void
    {
        [, $token] = $this->invitedStudent(expiresInHours: -1);

        $this->postJson("/api/activation/{$token}/begin")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['token']);
    }

    public function test_spent_token_is_rejected(): void
    {
        [, $token] = $this->invitedStudent();
        ActivationToken::query()->update(['used_at' => now()]);

        $this->postJson("/api/activation/{$token}/begin")->assertUnprocessable();
    }

    // ── Public probe must not leak PII ────────────────────────────────────────

    public function test_show_does_not_leak_identifying_details(): void
    {
        [$user, $token] = $this->invitedStudent();

        $response = $this->getJson("/api/activation/{$token}")
            ->assertOk()
            ->assertJsonPath('data.valid', true);

        $body = $response->getContent();
        $this->assertStringNotContainsString((string) $user->student_id, $body);
        $this->assertStringNotContainsString((string) $user->name, $body);
        $this->assertStringNotContainsString((string) $user->email, $body);
        $response->assertJsonMissingPath('data.student_id')
            ->assertJsonMissingPath('data.name')
            ->assertJsonMissingPath('data.program');
    }

    // ── I2: scope confinement ─────────────────────────────────────────────────

    public function test_onboarding_session_can_reach_the_identity_funnel(): void
    {
        [$user, $token] = $this->invitedStudent();
        $this->postJson("/api/activation/{$token}/begin")->assertOk();
        $this->actingAsOnboarding($user);

        $this->getJson('/api/student/kyc')
            ->assertOk();
    }

    public function test_onboarding_session_cannot_reach_the_vault(): void
    {
        [$user, $token] = $this->invitedStudent();
        $this->postJson("/api/activation/{$token}/begin")->assertOk();
        $this->actingAsOnboarding($user);

        $this->getJson('/api/student/requirement-vault')
            ->assertForbidden();
    }

    public function test_onboarding_session_cannot_reach_notifications_or_settings(): void
    {
        [$user, $token] = $this->invitedStudent();
        $this->postJson("/api/activation/{$token}/begin")->assertOk();
        $this->actingAsOnboarding($user);

        $this->getJson('/api/student/notifications')
            ->assertForbidden();

        $this->postJson('/api/student/settings/pin', ['pin' => '123456'])
            ->assertForbidden();
    }

    // ── I1/I4: credential creation gate ───────────────────────────────────────

    public function test_credentials_rejected_before_identity_is_verified(): void
    {
        [$user, $token] = $this->invitedStudent();
        $this->postJson("/api/activation/{$token}/begin")->assertOk();
        $this->actingAsOnboarding($user);

        $this->postJson('/api/onboarding/credentials', [
                'password' => 'Str0ng-Passw0rd!',
                'password_confirmation' => 'Str0ng-Passw0rd!',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['account_status']);
    }

    public function test_credentials_rejected_while_under_face_review(): void
    {
        [$user, $token] = $this->invitedStudent();
        $this->postJson("/api/activation/{$token}/begin")->assertOk();
        $user->forceFill(['account_status' => 'pending_face_review'])->save();
        $this->actingAsOnboarding($user);

        $this->postJson('/api/onboarding/credentials', [
                'password' => 'Str0ng-Passw0rd!',
                'password_confirmation' => 'Str0ng-Passw0rd!',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['account_status']);
    }

    public function test_credentials_created_after_verification_activate_the_account(): void
    {
        [$user, $token] = $this->invitedStudent();
        $this->postJson("/api/activation/{$token}/begin")->assertOk();

        // Simulate liveness auto-pass / staff approval, then bind the session to
        // the updated user (actingAs captures the instance it is given).
        $user->forceFill(['account_status' => 'identity_verified'])->save();
        $this->actingAsOnboarding($user);

        $this->postJson('/api/onboarding/credentials', [
                'password' => 'Str0ng-Passw0rd!',
                'password_confirmation' => 'Str0ng-Passw0rd!',
            ])
            ->assertOk()
            ->assertJsonPath('user.account_status', 'active')
            ->assertPlainCookie(config('services.auth.access_cookie'))
            ->assertPlainCookie(config('services.auth.refresh_cookie'));

        $user = $user->fresh();
        $this->assertTrue(Hash::check('Str0ng-Passw0rd!', $user->password));
        $this->assertNotNull($user->email_verified_at, 'email_verified_at is set here, not at link click (I4).');
        $this->assertNotNull($user->activated_at);

        // Token is spent only now.
        $this->assertNotNull(ActivationToken::query()->firstOrFail()->used_at);

        // Promoted to a full session.
        $this->assertTrue($user->tokens()->firstOrFail()->can('*'));
        $this->assertDatabaseHas('refresh_tokens', ['user_id' => $user->id, 'scope' => 'full']);
    }

    public function test_credentials_enforce_password_confirmation(): void
    {
        [$user, $token] = $this->invitedStudent();
        $this->postJson("/api/activation/{$token}/begin")->assertOk();
        $user->forceFill(['account_status' => 'identity_verified'])->save();
        $this->actingAsOnboarding($user);

        $this->postJson('/api/onboarding/credentials', [
                'password' => 'Str0ng-Passw0rd!',
                'password_confirmation' => 'mismatch',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);
    }

    // ── I7: no dead ends ──────────────────────────────────────────────────────

    public function test_resend_returns_the_same_response_for_known_and_unknown_emails(): void
    {
        Mail::fake();
        [$user] = $this->invitedStudent();

        $known = $this->postJson('/api/activation/resend', ['email' => $user->email])->assertOk();
        $unknown = $this->postJson('/api/activation/resend', ['email' => 'nobody@example.test'])->assertOk();

        $this->assertSame($known->json('message'), $unknown->json('message'));
        Mail::assertSent(GranteeActivationInviteMail::class, 1);
    }

    public function test_resend_issues_a_fresh_token_and_invalidates_the_old_one(): void
    {
        Mail::fake();
        [$user, $oldToken] = $this->invitedStudent();

        $this->postJson('/api/activation/resend', ['email' => $user->email])->assertOk();

        $this->assertDatabaseCount('activation_tokens', 1);
        $this->postJson("/api/activation/{$oldToken}/begin")->assertUnprocessable();
    }

    public function test_resend_sends_nothing_for_an_already_active_account(): void
    {
        Mail::fake();
        [$user] = $this->invitedStudent();
        $user->forceFill(['account_status' => 'active'])->save();

        $this->postJson('/api/activation/resend', ['email' => $user->email])->assertOk();

        Mail::assertNothingSent();
    }

    public function test_login_is_refused_while_mid_funnel(): void
    {
        [$user, $token] = $this->invitedStudent();
        $user->forceFill([
            'password' => Hash::make('known-password'),
            'account_status' => 'identity_verified',
        ])->save();

        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'known-password',
            'captcha' => 'x',
        ])->assertForbidden();
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * @return array{0: User, 1: string}
     */
    private function invitedStudent(int $expiresInHours = 24): array
    {
        $user = User::factory()->create([
            'role' => 'student',
            'student_id' => 'STU-IFA-1',
            'name' => 'Maria Angela Santos',
            'account_status' => 'unverified',
            // Unusable hash: invited accounts hold no credential.
            'password' => Hash::make(self::UNUSABLE.Str::random(16)),
            'email_verified_at' => null,
            'activated_at' => null,
        ]);

        $batch = Batch::create([
            'name' => 'TES Batch 1',
            'academic_year' => '2026-2027',
            'semester' => '1st Semester',
            'submission_deadline' => now()->addDays(14),
            'status' => 'draft',
            'window_status' => 'draft',
            'is_active' => false,
        ]);

        $grantee = Grantee::create([
            'user_id' => $user->id,
            'batch_id' => $batch->id,
            'student_id' => $user->student_id,
            'student_number' => '2026-0001',
            'full_name' => $user->name,
            'email' => $user->email,
            'program' => 'BSIT',
            'year_level' => '1',
            'status' => 'unverified',
        ]);

        GranteeIdentityProfile::query()->firstOrCreate(
            ['grantee_id' => $grantee->id],
            ['user_id' => $user->id, 'status' => 'pending_id_scan'],
        );

        $plainToken = Str::random(48);
        ActivationToken::create([
            'user_id' => $user->id,
            'token_hash' => hash('sha256', $plainToken),
            'expires_at' => now()->addHours($expiresInHours),
        ]);

        return [$user, $plainToken];
    }

    /**
     * Act as the user holding a pre-credential onboarding session.
     *
     * Uses Sanctum::actingAs because the plaintext half of a PAT only exists at
     * creation time and so cannot be replayed from the database. The abilities are
     * what the route guards actually inspect.
     */
    private function actingAsOnboarding(User $user): void
    {
        Sanctum::actingAs($user->fresh(), [AuthTokenService::ONBOARDING_ABILITY]);
    }
}
