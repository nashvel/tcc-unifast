<?php

namespace App\Http\Controllers;

use App\Mail\GranteeActivationInviteMail;
use App\Models\ActivationToken;
use App\Models\Batch;
use App\Models\Grantee;
use App\Models\User;
use App\Support\PaginatedJson;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Throwable;

class OnboardingCenterController extends Controller
{
    public function stats(Batch $batch): JsonResponse
    {
        $grantees = $batch->grantees()->with('user')->get();
        $total = $grantees->count();
        $invited = 0;
        $active = 0;
        $pendingFaceReview = 0;

        $userIds = $grantees->pluck('user_id')->filter()->toArray();
        $usersWithTokens = ActivationToken::query()->whereIn('user_id', $userIds)->pluck('user_id')->toArray();

        foreach ($grantees as $grantee) {
            $user = $grantee->user;
            if (! $user) {
                continue;
            }

            if ($user->account_status === 'active') {
                $active++;
                $invited++;
            } elseif ($user->account_status === 'pending_face_review') {
                $pendingFaceReview++;
                $invited++;
            } elseif (in_array($user->id, $usersWithTokens)) {
                $invited++;
            }
        }

        return response()->json([
            'data' => [
                'total' => $total,
                'invited' => $invited,
                'uninvited' => $total - $invited,
                'active' => $active,
                'pending_face_review' => $pendingFaceReview,
            ],
        ]);
    }

    public function grantees(Request $request, Batch $batch): JsonResponse
    {
        $search = $request->query('search');
        $status = $request->query('status');

        $query = $batch->grantees()->with('user');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                    ->orWhere('student_id', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($status) {
            // Because status is on the User model, we filter whereHas user
            // However, grantees might not have a user yet if they were just created, but in this new flow they always have a user immediately.
            // If status is 'unverified', it could mean user account_status is unverified.
            $query->whereHas('user', function ($q) use ($status) {
                $q->where('account_status', $status);
            });
        }

        $grantees = $query->paginate(20);

        // Find which users have an activation token
        $userIds = collect($grantees->items())->pluck('user_id')->filter()->toArray();
        $usersWithTokens = ActivationToken::query()->whereIn('user_id', $userIds)->pluck('user_id')->toArray();

        $rows = collect($grantees->items())->map(function (Grantee $grantee) use ($usersWithTokens) {
            $user = $grantee->user;
            $hasToken = $user && in_array($user->id, $usersWithTokens);
            $isInvited = $user && ($user->account_status !== 'unverified' || $hasToken);

            return [
                'id' => $grantee->id,
                'student_id' => $grantee->student_id,
                'full_name' => $grantee->full_name,
                'email' => $grantee->email,
                'program' => $grantee->program,
                'is_invited' => $isInvited,
                'account_status' => $user?->account_status ?? 'unverified',
            ];
        });

        return PaginatedJson::from($grantees, $rows->values());
    }

    public function blastInvites(Batch $batch): JsonResponse
    {
        $grantees = $batch->grantees()->with('user')->get();

        $userIds = $grantees->pluck('user_id')->filter()->toArray();
        $usersWithTokens = ActivationToken::query()->whereIn('user_id', $userIds)->pluck('user_id')->toArray();

        $sent = 0;
        $failed = [];

        foreach ($grantees as $grantee) {
            $user = $grantee->user;
            if (! $user) {
                continue;
            }

            // Skip if they are already active/pending, or if they already have a token
            if ($user->account_status !== 'unverified' || in_array($user->id, $usersWithTokens)) {
                continue;
            }

            try {
                $this->inviteUser($user);
                $sent++;
            } catch (Throwable $exception) {
                report($exception);
                $failed[] = ['email' => $user->email, 'message' => $exception->getMessage()];
            }
        }

        return response()->json([
            'message' => "Successfully blasted {$sent} invites.",
            'mail' => ['sent' => $sent, 'failed' => $failed],
        ], empty($failed) ? 200 : 207);
    }

    public function resendInvite(Grantee $grantee): JsonResponse
    {
        $user = $grantee->user;
        if (! $user) {
            return response()->json(['message' => 'No user account found for this grantee.'], 404);
        }

        if ($user->account_status === 'active') {
            return response()->json(['message' => 'User is already fully active.'], 422);
        }

        $key = 'resend-invite-'.$grantee->id;
        if (RateLimiter::tooManyAttempts($key, 1)) {
            $seconds = RateLimiter::availableIn($key);

            return response()->json(['message' => "Please wait {$seconds} seconds before resending this invite."], 429);
        }
        RateLimiter::hit($key, 60);

        // Delete old tokens
        ActivationToken::where('user_id', $user->id)->delete();

        try {
            $this->inviteUser($user);

            return response()->json(['message' => 'Invite resent successfully.']);
        } catch (Throwable $exception) {
            RateLimiter::clear($key); // allow retry if failed
            report($exception);

            return response()->json(['message' => 'Failed to send invite: '.$exception->getMessage()], 500);
        }
    }

    private function inviteUser(User $user): void
    {
        $temporaryPassword = $this->temporaryPassword();

        $user->update([
            'password' => Hash::make($temporaryPassword),
        ]);

        $plainToken = Str::random(48);
        ActivationToken::create([
            'user_id' => $user->id,
            'token_hash' => hash('sha256', $plainToken),
            'expires_at' => now()->addDays(7),
        ]);

        Mail::to($user->email, $user->name)->send(new GranteeActivationInviteMail(
            $user,
            $temporaryPassword,
            $this->activationUrl($plainToken),
        ));
    }

    private function temporaryPassword(): string
    {
        return 'TCC-'.Str::upper(Str::random(4)).'-'.Str::upper(Str::random(4));
    }

    private function activationUrl(string $plainToken): string
    {
        $frontend = rtrim((string) (env('ACTIVATION_FRONTEND_URL') ?: config('app.frontend_url') ?: env('FRONTEND_URL', 'http://localhost:5173')), '/');

        return $frontend.'/activate/'.$plainToken.'?lang=en';
    }
}
