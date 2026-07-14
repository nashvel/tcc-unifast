<?php

namespace App\Http\Controllers;

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

        // Demo batch members. Replace this with imported masterlist records once the real
        // masterlist table is finalized.
        $students = [
            ['name' => 'Maria Angela Santos', 'email' => 'student001@tcc.edu.ph'],
            ['name' => 'John Paul Ramirez', 'email' => 'student002@tcc.edu.ph'],
            ['name' => 'Nicole Anne Flores', 'email' => 'student003@tcc.edu.ph'],
            ['name' => 'Christian Dela Cruz', 'email' => 'student004@tcc.edu.ph'],
        ];

        $subject = $validated['subject'] ?? 'Activate your TCC UniFAST TES student portal account';
        $intro = $validated['message'] ?? 'Your student portal account has been created from the TES masterlist.';
        $sent = 0;
        $failed = [];

        foreach ($students as $student) {
            $temporaryPassword = $this->temporaryPassword();

            $user = \App\Models\User::query()->where('email', $student['email'])->first();
            if ($user) {
                $user->forceFill([
                    'password' => Hash::make($temporaryPassword),
                    'email_verified_at' => null,
                ])->save();
            }

            $body = implode("\n\n", [
                "Hello {$student['name']},",
                $intro,
                "Temporary password: {$temporaryPassword}",
                'Activation page: '.url('/activate'),
                'After activation, please change your password, upload your student ID, and complete live face verification.',
                'Do not share this temporary password with anyone.',
            ]);

            try {
                Mail::raw($body, fn ($message) => $message
                    ->to($student['email'], $student['name'])
                    ->subject($subject));
                $sent++;
            } catch (\Throwable $exception) {
                report($exception);
                $failed[] = ['email' => $student['email'], 'message' => $exception->getMessage()];
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
}
