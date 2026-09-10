<?php

namespace Tests\Feature;

use App\Models\OidcConnection;
use App\Models\RefreshToken;
use App\Models\User;
use App\Services\AuthTokenService;
use App\Services\Sso\SsoRolloutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Tests\TestCase;

class SsoSessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_remembered_rotation_preserves_absolute_thirty_day_limit(): void
    {
        $this->travelTo(now()->startOfSecond());
        $user = User::factory()->create(['role' => 'student', 'account_status' => 'active']);
        $tokens = app(AuthTokenService::class);
        $tokens->issuePair($user, Request::create('/'), rememberMe: true);
        $expiry = RefreshToken::firstOrFail()->absolute_expires_at;
        $this->assertSame(now()->addDays(30)->timestamp, $expiry->timestamp);
        $plain = Cookie::getQueuedCookies()[1]->getValue();
        $this->travel(20)->days();
        $request = Request::create('/api/auth/refresh', 'POST', [], [$tokens->refreshCookieName() => $plain]);
        $tokens->rotate($request);
        $replacement = RefreshToken::whereNull('revoked_at')->firstOrFail();
        $this->assertSame($expiry->timestamp, $replacement->expires_at->timestamp);
        $this->assertSame($expiry->timestamp, $replacement->absolute_expires_at->timestamp);
        $this->assertTrue($replacement->remembered);
    }

    public function test_nonremembered_sso_cookies_end_with_browser_session(): void
    {
        $user = User::factory()->create(['role' => 'student', 'account_status' => 'active']);
        app(AuthTokenService::class)->issuePair($user, Request::create('/'), rememberMe: false);
        foreach (Cookie::getQueuedCookies() as $cookie) {
            $this->assertSame(0, $cookie->getExpiresTime());
            $this->assertTrue($cookie->isHttpOnly());
        }
    }

    public function test_disabling_with_revoke_ends_restricted_sso_sessions_without_a_binding(): void
    {
        $user = User::factory()->create(['role' => 'student', 'account_status' => 'active']);
        $connection = OidcConnection::create(['display_name' => 'Test SIS', 'issuer' => 'https://sis.example.edu', 'client_id' => 'test', 'client_secret' => 'secret', 'validated_at' => now(), 'state' => 'all_students']);
        app(AuthTokenService::class)->issuePair($user, Request::create('/'), rememberMe: true, ssoConnectionId: $connection->id);
        app(SsoRolloutService::class)->rollout(['mode' => 'disabled', 'session_policy' => 'revoke']);
        $this->assertNotNull(RefreshToken::firstOrFail()->revoked_at);
    }
}
