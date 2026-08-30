<?php

namespace App\Http\Controllers;

use App\Mail\GranteeActivationInviteMail;
use App\Models\Grantee;
use App\Services\ActivationTokenIssuer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class BatchActivationNotificationController extends Controller
{
    public function __invoke(Request $request, string $batch, ActivationTokenIssuer $issuer): JsonResponse
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
            // No temporary password: an unusable hash keeps the column NOT NULL
            // without handing out a credential before identity verification.
            $student->user->forceFill([
                'password' => Hash::make(Str::random(64)),
                'email_verified_at' => null,
            ])->save();

            $link = $issuer->issueLinkFor($student->user);

            try {
                Mail::to($student->email, $student->full_name)->send(new GranteeActivationInviteMail(
                    $student->user,
                    $link['url'],
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

}
