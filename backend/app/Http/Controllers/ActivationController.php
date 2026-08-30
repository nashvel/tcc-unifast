<?php

namespace App\Http\Controllers;

use App\Mail\GranteeActivationInviteMail;
use App\Models\ActivationToken;
use App\Models\User;
use App\Services\ActivationTokenIssuer;
use App\Services\AuthTokenService;
use App\Services\StudentOnboardingNavigator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Identity-first activation.
 *
 * The activation link no longer sets a password. It opens a short-lived,
 * ability-restricted onboarding session that can reach ONLY the identity funnel
 * (KYC → ID scan → liveness). The password is created afterwards, by
 * OnboardingCredentialController, once identity is actually verified.
 *
 * Consequence: the token is NOT consumed by the first click (first_used_at), only
 * by credential creation (used_at) — so the link stays usable for the whole funnel
 * and a student can resume after abandoning it.
 *
 * See docs/identity-first-activation-implementation-plan.md §2.
 */
class ActivationController extends Controller
{
    /**
     * Landing-page probe. Deliberately non-identifying: this endpoint is public,
     * so it must not confirm a student's name, student ID, or program to whoever
     * holds (or guesses) a link.
     */
    public function show(string $token): JsonResponse
    {
        $activation = $this->findUsableToken($token);
        $user = $activation->user()->firstOrFail();

        return response()->json([
            'data' => [
                'valid' => true,
                'masked_email' => $this->maskEmail((string) $user->email),
                'expires_at' => $activation->expires_at,
            ],
        ]);
    }

    /**
     * Open an onboarding session for the identity funnel. No password, no
     * credential, no full session.
     */
    public function begin(
        Request $request,
        string $token,
        StudentOnboardingNavigator $navigator,
        AuthTokenService $tokens,
    ): JsonResponse {
        $activation = $this->findUsableToken($token);
        $user = $activation->user()->firstOrFail();

        if ($user->account_status === 'blocked') {
            throw ValidationException::withMessages([
                'token' => 'This account is blocked. Contact the scholarship office.',
            ]);
        }

        // Revoke any prior onboarding session for this token so only one is live.
        $tokens->revokeAll($user);

        // Enter the funnel. Note: no password, no activated_at, no email_verified_at —
        // those are set by OnboardingCredentialController after verification.
        if ($user->account_status === 'unverified') {
            $user->forceFill(['account_status' => 'pending_kyc'])->save();
        }

        $user = $user->fresh();
        $sessionId = $tokens->issueOnboardingSession($user, $request);

        $activation->update([
            'first_used_at' => $activation->first_used_at ?? now(),
            'onboarding_session_id' => $sessionId,
        ]);

        $next = $navigator->nextStep($user);

        return response()->json([
            'user' => [
                ...$user->only('id', 'name', 'email', 'role', 'student_id', 'account_status'),
                'kyc_status' => $user->kycProfile?->status,
                'onboarding_next_step' => $next,
                'onboarding_path' => $navigator->frontendPath($next),
            ],
        ]);
    }

    /**
     * Self-service link recovery. Always responds identically whether or not the
     * address exists, so this cannot be used to enumerate grantees.
     */
    public function resend(Request $request, ActivationTokenIssuer $issuer): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:191'],
        ]);

        $email = Str::lower(trim($validated['email']));
        $generic = response()->json([
            'message' => 'If that email is on file, a new activation link is on its way.',
        ]);

        // Per-email limiter on top of the per-IP route throttle, so a known address
        // cannot be mail-bombed from rotating IPs.
        $perHour = max(1, (int) config('services.auth.activation_resend_throttle_per_hour', 3));
        $executed = RateLimiter::attempt(
            'activation-resend:'.sha1($email),
            $perHour,
            function () use ($email, $issuer): void {
                $user = User::query()
                    ->whereRaw('LOWER(email) = ?', [$email])
                    ->where('role', 'student')
                    ->whereNotIn('account_status', ['active', 'blocked'])
                    ->first();

                if (! $user) {
                    return;
                }

                $link = $issuer->issueLinkFor($user);

                try {
                    Mail::to($user->email, $user->name)->send(
                        new GranteeActivationInviteMail($user, $link['url']),
                    );
                } catch (\Throwable $exception) {
                    report($exception);
                }
            },
            3600,
        );

        // Same body either way — a 429 here would itself leak that the address exists.
        return $executed ? $generic : $generic;
    }

    /**
     * Usable = unspent (used_at) and unexpired. first_used_at deliberately does not
     * invalidate the link, so the funnel can be resumed.
     */
    private function findUsableToken(string $token): ActivationToken
    {
        $activation = ActivationToken::query()
            ->where('token_hash', hash('sha256', $token))
            ->first();

        if (! $activation || ! $activation->isUsable()) {
            throw ValidationException::withMessages([
                'token' => 'This activation link is invalid or expired. Request a new one.',
            ]);
        }

        return $activation;
    }

    private function maskEmail(string $email): string
    {
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');
        if ($domain === '') {
            return '•••';
        }

        $visible = mb_substr($local, 0, 1);

        return $visible.str_repeat('•', max(3, mb_strlen($local) - 1)).'@'.$domain;
    }
}
