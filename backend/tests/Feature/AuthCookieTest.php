<?php

namespace Tests\Feature;

use App\Models\RefreshToken;
use App\Models\User;
use App\Services\TotpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class AuthCookieTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.auth.dev_bypass_captcha' => true]);
        $this->disableCookieEncryption();
        $this->flushHeaders();
    }

    /**
     * Each HTTP call in the same test shares the AuthManager singleton; clear it
     * so a prior sanctum login cannot leak into the next request.
     */
    private function forgetAuthGuards(): void
    {
        Auth::forgetGuards();
    }

    private function cookieValue($response, string $name): string
    {
        $cookie = $response->getCookie($name, false);
        $this->assertNotNull($cookie, "Missing cookie {$name}");
        $value = (string) $cookie->getValue();
        $this->assertNotSame('', $value);

        return $value;
    }

    /** JSON request with plaintext auth cookies (excluded from EncryptCookies). */
    private function jsonWithAuthCookies(
        string $method,
        string $uri,
        array $cookies = [],
        array $data = [],
        array $headers = [],
    ) {
        $this->forgetAuthGuards();

        return $this->call(
            $method,
            $uri,
            [],
            $cookies,
            [],
            $this->transformHeadersToServerVars(array_merge([
                'Accept' => 'application/json',
                'CONTENT_TYPE' => 'application/json',
            ], $headers)),
            $data === [] ? null : json_encode($data),
        );
    }

    public function test_login_sets_http_only_cookies_without_json_token(): void
    {
        $user = User::factory()->create([
            'email' => 'staff@example.test',
            'password' => Hash::make('password'),
            'role' => 'staff',
            'account_status' => 'active',
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'staff@example.test',
            'password' => 'password',
            'captcha' => 'bypass',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['user' => ['id', 'email']])
            ->assertJsonMissingPath('token')
            ->assertPlainCookie(config('services.auth.access_cookie'))
            ->assertPlainCookie(config('services.auth.refresh_cookie'));

        $this->assertDatabaseCount('personal_access_tokens', 1);
        $this->assertDatabaseCount('refresh_tokens', 1);

        $access = $this->cookieValue($response, config('services.auth.access_cookie'));
        $this->assertTrue($response->getCookie(config('services.auth.access_cookie'), false)->isHttpOnly());

        $this->forgetAuthGuards();
        $this->getJson('/api/auth/me', [
            'Authorization' => 'Bearer '.$access,
        ])->assertOk()->assertJsonPath('user.id', $user->id);

        $this->jsonWithAuthCookies('GET', '/api/auth/me', [
            config('services.auth.access_cookie') => $access,
        ])->assertOk()->assertJsonPath('user.id', $user->id);
    }

    public function test_refresh_rotates_tokens_and_invalidates_old_refresh(): void
    {
        $login = $this->postJson('/api/auth/login', [
            'email' => User::factory()->create([
                'email' => 'rotate@example.test',
                'password' => Hash::make('password'),
                'role' => 'staff',
                'account_status' => 'active',
            ])->email,
            'password' => 'password',
            'captcha' => 'bypass',
        ])->assertOk();

        $oldRefresh = $this->cookieValue($login, config('services.auth.refresh_cookie'));
        $this->assertDatabaseHas('refresh_tokens', [
            'token_hash' => hash('sha256', $oldRefresh),
        ]);

        $refreshResponse = $this->jsonWithAuthCookies('POST', '/api/auth/refresh', [
            config('services.auth.refresh_cookie') => $oldRefresh,
        ])->assertOk()->assertJsonStructure(['user' => ['id']]);

        $newRefresh = $this->cookieValue($refreshResponse, config('services.auth.refresh_cookie'));
        $newAccess = $this->cookieValue($refreshResponse, config('services.auth.access_cookie'));
        $this->assertNotSame($oldRefresh, $newRefresh);
        $this->assertNotNull(PersonalAccessToken::findToken($newAccess));

        $this->assertNotNull(
            RefreshToken::query()->where('token_hash', hash('sha256', $oldRefresh))->value('revoked_at')
        );

        // Confirm new access works BEFORE reuse-detection (reuse nukes all PATs).
        $this->jsonWithAuthCookies('GET', '/api/auth/me', [
            config('services.auth.access_cookie') => $newAccess,
        ])->assertOk();

        $this->jsonWithAuthCookies('POST', '/api/auth/refresh', [
            config('services.auth.refresh_cookie') => $oldRefresh,
        ])->assertUnauthorized();

        // Reuse detection revokes the family and deletes access tokens.
        $this->jsonWithAuthCookies('GET', '/api/auth/me', [
            config('services.auth.access_cookie') => $newAccess,
        ])->assertUnauthorized();
    }

    public function test_refresh_reuse_revokes_family(): void
    {
        $login = $this->postJson('/api/auth/login', [
            'email' => User::factory()->create([
                'email' => 'reuse@example.test',
                'password' => Hash::make('password'),
                'role' => 'staff',
                'account_status' => 'active',
            ])->email,
            'password' => 'password',
            'captcha' => 'bypass',
        ])->assertOk();

        $firstRefresh = $this->cookieValue($login, config('services.auth.refresh_cookie'));

        $second = $this->jsonWithAuthCookies('POST', '/api/auth/refresh', [
            config('services.auth.refresh_cookie') => $firstRefresh,
        ])->assertOk();
        $secondRefresh = $this->cookieValue($second, config('services.auth.refresh_cookie'));

        $this->jsonWithAuthCookies('POST', '/api/auth/refresh', [
            config('services.auth.refresh_cookie') => $firstRefresh,
        ])->assertUnauthorized();

        $this->jsonWithAuthCookies('POST', '/api/auth/refresh', [
            config('services.auth.refresh_cookie') => $secondRefresh,
        ])->assertUnauthorized();

        $this->assertSame(
            0,
            RefreshToken::query()->whereNull('revoked_at')->count()
        );
    }

    public function test_logout_clears_cookies_and_blocks_refresh(): void
    {
        $login = $this->postJson('/api/auth/login', [
            'email' => User::factory()->create([
                'email' => 'logout@example.test',
                'password' => Hash::make('password'),
                'role' => 'staff',
                'account_status' => 'active',
            ])->email,
            'password' => 'password',
            'captcha' => 'bypass',
        ])->assertOk();

        $access = $this->cookieValue($login, config('services.auth.access_cookie'));
        $refresh = $this->cookieValue($login, config('services.auth.refresh_cookie'));

        $this->jsonWithAuthCookies(
            'POST',
            '/api/auth/logout',
            [
                config('services.auth.access_cookie') => $access,
                config('services.auth.refresh_cookie') => $refresh,
            ],
            [],
            ['Authorization' => 'Bearer '.$access],
        )->assertOk();

        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->assertGreaterThan(0, RefreshToken::query()->whereNotNull('revoked_at')->count());
        $this->assertSame(0, RefreshToken::query()->whereNull('revoked_at')->count());

        $this->forgetAuthGuards();
        $this->getJson('/api/auth/me', [
            'Authorization' => 'Bearer '.$access,
        ])->assertUnauthorized();

        $this->jsonWithAuthCookies('POST', '/api/auth/refresh', [
            config('services.auth.refresh_cookie') => $refresh,
        ])->assertUnauthorized();
    }

    public function test_expired_access_token_is_rejected_until_refresh(): void
    {
        $login = $this->postJson('/api/auth/login', [
            'email' => User::factory()->create([
                'email' => 'expire@example.test',
                'password' => Hash::make('password'),
                'role' => 'staff',
                'account_status' => 'active',
            ])->email,
            'password' => 'password',
            'captcha' => 'bypass',
        ])->assertOk();

        $access = $this->cookieValue($login, config('services.auth.access_cookie'));
        $refresh = $this->cookieValue($login, config('services.auth.refresh_cookie'));

        $pat = PersonalAccessToken::findToken($access);
        $this->assertNotNull($pat);
        $pat->forceFill(['expires_at' => now()->subMinute()])->save();

        $this->forgetAuthGuards();
        $this->getJson('/api/auth/me', [
            'Authorization' => 'Bearer '.$access,
        ])->assertUnauthorized();

        $renewed = $this->jsonWithAuthCookies('POST', '/api/auth/refresh', [
            config('services.auth.refresh_cookie') => $refresh,
        ])->assertOk();

        $newAccess = $this->cookieValue($renewed, config('services.auth.access_cookie'));
        $this->forgetAuthGuards();
        $this->getJson('/api/auth/me', [
            'Authorization' => 'Bearer '.$newAccess,
        ])->assertOk();
    }

    public function test_login_requires_two_factor_before_issuing_cookies(): void
    {
        $totp = app(TotpService::class);
        $secret = $totp->generateSecret();
        User::factory()->create([
            'email' => 'mfa@example.test',
            'password' => Hash::make('password'),
            'role' => 'staff',
            'account_status' => 'active',
            'two_factor_secret' => $secret,
            'two_factor_confirmed_at' => now(),
        ]);

        $challenge = $this->postJson('/api/auth/login', [
            'email' => 'mfa@example.test',
            'password' => 'password',
            'captcha' => 'bypass',
        ]);

        $challenge->assertOk()
            ->assertJsonPath('two_factor_required', true)
            ->assertJsonStructure(['challenge_id', 'expires_at'])
            ->assertCookieMissing(config('services.auth.access_cookie'))
            ->assertCookieMissing(config('services.auth.refresh_cookie'));

        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->assertDatabaseCount('refresh_tokens', 0);
    }

    public function test_two_factor_challenge_verification_issues_cookies(): void
    {
        $totp = app(TotpService::class);
        $secret = $totp->generateSecret();
        User::factory()->create([
            'email' => 'verify-mfa@example.test',
            'password' => Hash::make('password'),
            'role' => 'staff',
            'account_status' => 'active',
            'two_factor_secret' => $secret,
            'two_factor_confirmed_at' => now(),
        ]);

        $challenge = $this->postJson('/api/auth/login', [
            'email' => 'verify-mfa@example.test',
            'password' => 'password',
            'captcha' => 'bypass',
        ])->assertOk()->json('challenge_id');

        $response = $this->postJson('/api/auth/2fa/verify', [
            'challenge_id' => $challenge,
            'code' => $totp->currentCode($secret),
        ]);

        $response->assertOk()
            ->assertJsonStructure(['user' => ['id', 'email']])
            ->assertPlainCookie(config('services.auth.access_cookie'))
            ->assertPlainCookie(config('services.auth.refresh_cookie'));

        $this->assertDatabaseCount('personal_access_tokens', 1);
        $this->assertDatabaseCount('refresh_tokens', 1);

        $this->postJson('/api/auth/2fa/verify', [
            'challenge_id' => $challenge,
            'code' => $totp->currentCode($secret),
        ])->assertStatus(422);
    }
}
