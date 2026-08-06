<?php

namespace App\Http\Controllers;

use App\Mail\GranteeActivationInviteMail;
use App\Models\ActivationToken;
use App\Models\Grantee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class BatchActivationNotificationController extends Controller
{
    public function __invoke(Request $request, string $batch): JsonResponse
    {
        $validated = $request->validate([
            'subject' => ['nullable', 'string', 'max:160'],
            'message' => ['nullable', 'string', 'max:3000'],
        ]);

        $students = Grantee::query()
            ->with('user')
            ->where('batch_id', $batch)
            ->whereHas('user', fn ($query) => $query->where('account_status', 'unverified'))
            ->get();

        $intro = $validated['message'] ?? 'Your student portal account has been created from the TES masterlist.';
        $sent = 0;
        $failed = [];

        foreach ($students as $student) {
            if (! $student->user) {
                continue;
            }
            $temporaryPassword = $this->temporaryPassword();
            $plainToken = Str::random(48);

            $student->user->forceFill([
                'password' => Hash::make($temporaryPassword),
                'email_verified_at' => null,
            ])->save();

            ActivationToken::create([
                'user_id' => $student->user->id,
                'token_hash' => hash('sha256', $plainToken),
                'expires_at' => now()->addDays(7),
            ]);

            try {
                Mail::to($student->email, $student->full_name)->send(new GranteeActivationInviteMail(
                    $student->user,
                    $temporaryPassword,
                    $this->activationUrl($plainToken),
                    $intro,
                    $validated['subject'] ?? null,
                ));
                $sent++;
            } catch (\Throwable $exception) {
                report($exception);
                $failed[] = ['email' => $student->email, 'message' => $exception->getMessage()];
            }
        }

        return response()->json([
            'batch' => $batch,
            'mailer' => config('mail.default'),
            'sent' => $sent,
            'failed' => $failed,
        ], $failed === [] ? 200 : 207);
    }

    private function temporaryPassword(): string
    {
        return 'TCC-'.Str::upper(Str::random(4)).'-'.Str::upper(Str::random(4));
    }

    private function activationUrl(string $plainToken): string
    {
        $frontend = rtrim((string) (config('app.frontend_url') ?: env('FRONTEND_URL', 'http://localhost:5173')), '/');

        return $frontend.'/activate/'.$plainToken.'?lang=en';
    }
}
