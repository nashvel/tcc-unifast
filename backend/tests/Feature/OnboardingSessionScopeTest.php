<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureFullSession;
use App\Models\RefreshToken;
use App\Models\User;
use App\Services\AuthTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * Phase 2 — scoped session plumbing.
 *
 * Locks the scope invariants before any route starts depending on them:
 *   I2 — onboarding sessions cannot reach non-funnel surfaces
 *   I3 — rotation cannot widen scope
 *   I6 — full sessions are unaffected
 */
class OnboardingSessionScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_full_scope_grants_wildcard_ability(): void
    {
        $this->assertSame(['*'], AuthTokenService::abilitiesFor(RefreshToken::SCOPE_FULL));
    }

    public function test_onboarding_scope_grants_only_the_identity_ability(): void
    {
        $this->assertSame(
            [AuthTokenService::ONBOARDING_ABILITY],
            AuthTokenService::abilitiesFor(RefreshToken::SCOPE_ONBOARDING),
        );
    }

    public function test_unknown_scope_never_silently_grants_onboarding_ability(): void
    {
        // Defaulting to ['*'] is safe here because scope is service-controlled, never
        // request-derived; the point is that a typo cannot mint a half-privileged token.
        $this->assertSame(['*'], AuthTokenService::abilitiesFor('typo'));
    }

    public function test_issue_pair_defaults_to_full_scope(): void
    {
        $user = User::factory()->create(['role' => 'student', 'account_status' => 'active']);

        app(AuthTokenService::class)->issuePair($user, Request::create('/', 'POST'));

        $this->assertDatabaseHas('refresh_tokens', [
            'user_id' => $user->id,
            'scope' => RefreshToken::SCOPE_FULL,
        ]);
        $this->assertTrue($user->fresh()->tokens()->first()->can('*'));
    }

    public function test_onboarding_session_issues_no_refresh_token(): void
    {
        $user = User::factory()->create(['role' => 'student', 'account_status' => 'pending_kyc']);

        $patId = app(AuthTokenService::class)->issueOnboardingSession($user, Request::create('/', 'POST'));

        // Nothing to rotate ⇒ scope cannot be widened by /auth/refresh (I3).
        $this->assertDatabaseCount('refresh_tokens', 0);
        $this->assertDatabaseHas('personal_access_tokens', [
            'id' => $patId,
            'tokenable_id' => $user->id,
        ]);

        $token = $user->fresh()->tokens()->firstOrFail();
        $this->assertTrue($token->can(AuthTokenService::ONBOARDING_ABILITY));
        $this->assertFalse($token->can('*'));
    }

    public function test_upgrade_to_full_session_replaces_onboarding_token(): void
    {
        $user = User::factory()->create(['role' => 'student', 'account_status' => 'pending_kyc']);
        $tokens = app(AuthTokenService::class);

        $tokens->issueOnboardingSession($user, Request::create('/', 'POST'));
        $tokens->upgradeToFullSession($user->fresh(), Request::create('/', 'POST'));

        $user = $user->fresh();
        $this->assertSame(1, $user->tokens()->count());
        $this->assertTrue($user->tokens()->first()->can('*'));
        $this->assertDatabaseHas('refresh_tokens', [
            'user_id' => $user->id,
            'scope' => RefreshToken::SCOPE_FULL,
        ]);
    }

    public function test_ensure_full_session_rejects_onboarding_token(): void
    {
        $user = User::factory()->create(['role' => 'student', 'account_status' => 'pending_kyc']);
        $plain = $user->createToken('onboarding', [AuthTokenService::ONBOARDING_ABILITY])->plainTextToken;

        $request = Request::create('/api/student/requirement-vault', 'GET');
        $request->headers->set('Authorization', 'Bearer '.$plain);
        $request->setUserResolver(fn () => $user->withAccessToken(
            \Laravel\Sanctum\PersonalAccessToken::findToken($plain)
        ));

        try {
            (new EnsureFullSession)->handle($request, fn ($r) => response('ok'));
            $this->fail('Onboarding-scoped token should not pass EnsureFullSession.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
    }

    public function test_ensure_full_session_allows_full_token(): void
    {
        $user = User::factory()->create(['role' => 'student', 'account_status' => 'active']);
        $plain = $user->createToken('access', ['*'])->plainTextToken;

        $request = Request::create('/api/student/requirement-vault', 'GET');
        $request->setUserResolver(fn () => $user->withAccessToken(
            \Laravel\Sanctum\PersonalAccessToken::findToken($plain)
        ));

        $response = (new EnsureFullSession)->handle($request, fn ($r) => response('ok'));

        $this->assertSame('ok', $response->getContent());
    }

    /**
     * Session-guard requests carry no PAT (or a TransientToken), so they cannot be
     * scope-limited and are passed through — `auth:sanctum` has already
     * authenticated them. Only a real PAT can be an onboarding session.
     */
    public function test_ensure_full_session_passes_through_session_guard_requests(): void
    {
        $request = Request::create('/api/student/requirement-vault', 'GET');
        $request->setUserResolver(fn () => null);

        $response = (new EnsureFullSession)->handle($request, fn ($r) => response('ok'));

        $this->assertSame('ok', $response->getContent());
    }

    public function test_refresh_token_scope_helper_treats_missing_scope_as_full(): void
    {
        $this->assertTrue((new RefreshToken)->isFullScope());
        $this->assertTrue((new RefreshToken(['scope' => RefreshToken::SCOPE_FULL]))->isFullScope());
        $this->assertFalse((new RefreshToken(['scope' => RefreshToken::SCOPE_ONBOARDING]))->isFullScope());
    }

    public function test_middleware_aliases_are_registered(): void
    {
        $router = app('router');
        $aliases = $router->getMiddleware();

        $this->assertArrayHasKey('full-session', $aliases);
        $this->assertArrayHasKey('ability', $aliases);
        $this->assertSame(EnsureFullSession::class, $aliases['full-session']);
    }
}
