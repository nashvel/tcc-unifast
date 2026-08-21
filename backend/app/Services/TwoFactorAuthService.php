<?php

namespace App\Services;

use App\Models\AuthChallenge;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

class TwoFactorAuthService
{
    public function __construct(private readonly TotpService $totp)
    {
    }

    public function enabled(User $user): bool
    {
        return $user->two_factor_secret !== null && $user->two_factor_confirmed_at !== null;
    }

    public function createChallenge(User $user, Request $request, string $purpose = 'login'): array
    {
        AuthChallenge::query()
            ->where('user_id', $user->id)
            ->where('purpose', $purpose)
            ->whereNull('consumed_at')
            ->update(['consumed_at' => now()]);

        $plain = Str::random(48);
        $challenge = AuthChallenge::query()->create([
            'user_id' => $user->id,
            'token_hash' => hash('sha256', $plain),
            'purpose' => $purpose,
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 512, ''),
            'expires_at' => now()->addMinutes($this->challengeTtlMinutes()),
        ]);

        return [
            'challenge_id' => $challenge->id.'|'.$plain,
            'expires_at' => $challenge->expires_at->toIso8601String(),
        ];
    }

    public function verifyChallenge(string $challengeId, string $code, Request $request): User
    {
        [$id, $plain] = array_pad(explode('|', $challengeId, 2), 2, '');

        $challenge = AuthChallenge::query()
            ->whereKey((int) $id)
            ->where('token_hash', hash('sha256', $plain))
            ->where('purpose', 'login')
            ->whereNull('consumed_at')
            ->first();

        if (! $challenge || $challenge->expires_at->isPast()) {
            throw new UnauthorizedHttpException('Bearer', 'The two-factor challenge has expired.');
        }

        /** @var User $user */
        $user = $challenge->user()->firstOrFail();
        if (! $this->verifyUserCode($user, $code)) {
            throw new UnauthorizedHttpException('Bearer', 'The two-factor code is invalid.');
        }

        $challenge->update([
            'consumed_at' => now(),
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 512, ''),
        ]);

        return $user;
    }

    public function verifyUserCode(User $user, string $code): bool
    {
        if (! $this->enabled($user)) {
            return false;
        }

        if ($this->totp->verify((string) $user->two_factor_secret, $code)) {
            return true;
        }

        $codes = $user->two_factor_recovery_codes ?? [];
        foreach ($codes as $index => $hash) {
            if (Hash::check(strtoupper(trim($code)), $hash)) {
                unset($codes[$index]);
                $user->forceFill([
                    'two_factor_recovery_codes' => array_values($codes),
                ])->save();

                return true;
            }
        }

        return false;
    }

    public function challengeTtlMinutes(): int
    {
        return max(1, (int) config('services.auth.two_factor_challenge_ttl_minutes', 10));
    }

    public function hashRecoveryCodes(array $codes): array
    {
        return array_map(fn (string $code): string => Hash::make(strtoupper(trim($code))), $codes);
    }
}
