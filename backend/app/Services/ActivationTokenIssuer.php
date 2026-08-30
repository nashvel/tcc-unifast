<?php

namespace App\Services;

use App\Models\ActivationToken;
use App\Models\User;
use App\Support\ActivationLink;
use Illuminate\Support\Str;

/**
 * Single source of truth for activation tokens.
 *
 * Previously the mint-and-hash block was copy-pasted across OnboardingCenterController,
 * BatchActivationNotificationController and ActivationSeederController, each with its
 * own TTL (7 or 14 days). Centralising it lets the shorter Option A window
 * (config services.auth.activation_token_ttl_hours, default 24h) be enforced in one
 * place — safe because expiry is now recoverable via /activation/resend.
 */
class ActivationTokenIssuer
{
    /**
     * Invalidate any unused tokens for the user and mint a fresh one.
     *
     * @return string the plain token (only ever available here; the row stores a hash)
     */
    public function issueFor(User $user, ?int $ttlHours = null): string
    {
        ActivationToken::query()
            ->where('user_id', $user->id)
            ->whereNull('used_at')
            ->delete();

        $plainToken = Str::random(48);

        ActivationToken::create([
            'user_id' => $user->id,
            'token_hash' => hash('sha256', $plainToken),
            'expires_at' => now()->addHours($ttlHours ?? $this->ttlHours()),
        ]);

        return $plainToken;
    }

    /**
     * Mint a token and return its ready-to-email URL.
     *
     * @return array{token: string, url: string}
     */
    public function issueLinkFor(User $user, ?int $ttlHours = null): array
    {
        $plainToken = $this->issueFor($user, $ttlHours);

        return [
            'token' => $plainToken,
            'url' => ActivationLink::for($plainToken),
        ];
    }

    public function ttlHours(): int
    {
        return max(1, (int) config('services.auth.activation_token_ttl_hours', 24));
    }
}
