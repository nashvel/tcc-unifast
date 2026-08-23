<?php

namespace App\Http\Controllers;

use App\Models\ActivationToken;
use App\Models\Batch;
use App\Models\Grantee;
use App\Models\GranteeIdentityProfile;
use App\Models\KycProfile;
use App\Models\MasterlistImport;
use App\Models\MasterlistRow;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * ActivationSeederController
 *
 * Provides an API endpoint so admins can create activation-ready
 * grantees from the browser UI — equivalent to running an artisan
 * activation seeder, but without CLI access.
 *
 * POST /api/activation-seeder          → seed one grantee, return token URL
 * GET  /api/activation-seeder/batches  → list batches for the dropdown
 * GET  /api/activation-seeder/preview  → preview token for an existing grantee
 */
class ActivationSeederController extends Controller
{
    /**
     * GET /api/activation-seeder/batches
     * Return all batches for the batch picker dropdown.
     */
    public function batches(): JsonResponse
    {
        $batches = Batch::query()
            ->orderByDesc('id')
            ->get(['id', 'name', 'academic_year', 'semester', 'status', 'is_active'])
            ->map(fn ($b) => [
                'id' => $b->id,
                'name' => $b->name,
                'academic_year' => $b->academic_year,
                'semester' => $b->semester,
                'status' => $b->status,
                'is_active' => $b->is_active,
            ]);

        return response()->json(['data' => $batches]);
    }

    /**
     * GET /api/activation-seeder/history
     * Returns a list of recently seeded grantees and their token status.
     */
    public function history(): JsonResponse
    {
        // Query grantees with their latest activation token via a subquery join
        $grantees = Grantee::query()
            ->with('user')
            ->latest('id')
            ->limit(50)
            ->get()
            ->map(function ($grantee) {
                // Fetch the most recent token for this user directly
                $token = $grantee->user_id
                    ? ActivationToken::where('user_id', $grantee->user_id)
                        ->latest()
                        ->first()
                    : null;

                $tokenStatus = 'No Token';
                if ($token) {
                    if ($token->used_at) {
                        $tokenStatus = 'Used';
                    } elseif (now()->greaterThan($token->expires_at)) {
                        $tokenStatus = 'Expired';
                    } else {
                        $tokenStatus = 'Active';
                    }
                }

                return [
                    'id' => $grantee->id,
                    'student_id' => $grantee->student_id,
                    'full_name' => $grantee->full_name,
                    'email' => $grantee->email,
                    'program' => $grantee->program,
                    'year_level' => $grantee->year_level,
                    'created_at' => $grantee->created_at,
                    'token_status' => $tokenStatus,
                    'token_expires_at' => $token?->expires_at,
                ];
            });

        return response()->json(['data' => $grantees]);
    }

    /**
     * POST /api/activation-seeder/regenerate/{grantee}
     * Generate a new activation token for an existing grantee.
     */
    public function regenerate(Grantee $grantee): JsonResponse
    {
        $user = $grantee->user;
        if (! $user) {
            return response()->json(['message' => 'Grantee has no associated user account.'], 400);
        }

        // Invalidate past tokens
        ActivationToken::query()
            ->where('user_id', $user->id)
            ->whereNull('used_at')
            ->delete();

        $plainToken = Str::random(48);
        ActivationToken::create([
            'user_id' => $user->id,
            'token_hash' => hash('sha256', $plainToken),
            'expires_at' => now()->addDays(14),
        ]);

        preg_match('/^ACTIVATION_FRONTEND_URL=(.*)/m', file_get_contents(base_path('.env')), $matches);
        $frontendUrl = rtrim((string) ($matches[1] ?? 'http://localhost:5173'), '/');
        $activationUrl = $frontendUrl.'/activate/'.$plainToken.'?lang=en';

        return response()->json([
            'data' => [
                'grantee_id' => $grantee->id,
                'full_name' => $grantee->full_name,
                'plain_token' => $plainToken,
                'activation_url' => $activationUrl,
                'expires_at' => now()->addDays(14)->toIso8601String(),
            ],
        ]);
    }

    /**
     * POST /api/activation-seeder
     *
     * Body:
     *   batch_id         int|null  — use existing batch
     *   batch_name       string    — or create a new batch (required if batch_id null)
     *   academic_year    string    — required if creating batch
     *   semester         string    — required if creating batch
     *   student_id       string    — unique student ID
     *   first_name       string
     *   last_name        string
     *   middle_name      string|null
     *   email            string
     *   program          string
     *   year_level       string
     *   reset_kyc        bool      — wipe KYC/identity data to start fresh
     */
    public function seed(Request $request): JsonResponse
    {
        $data = $request->validate([
            'batch_id' => ['nullable', 'integer', 'exists:batches,id'],
            'batch_name' => ['required_without:batch_id', 'nullable', 'string', 'max:191'],
            'academic_year' => ['required_without:batch_id', 'nullable', 'string', 'max:20'],
            'semester' => ['required_without:batch_id', 'nullable', 'string', 'max:50'],
            'student_id' => ['required', 'string', 'max:50'],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:191'],
            'program' => ['required', 'string', 'max:100'],
            'year_level' => ['nullable', 'string', 'max:10'],
            'reset_kyc' => ['boolean'],
        ]);

        $yearLevel = $data['year_level'] ?? '1';
        $resetKyc = (bool) ($data['reset_kyc'] ?? false);
        preg_match('/^ACTIVATION_FRONTEND_URL=(.*)/m', file_get_contents(base_path('.env')), $matches);
        $frontendUrl = rtrim((string) ($matches[1] ?? 'http://localhost:5173'), '/');

        $fullName = trim(
            $data['first_name'].' '.
            (! empty($data['middle_name']) ? $data['middle_name'].' ' : '').
            $data['last_name']
        );

        return DB::transaction(function () use ($data, $yearLevel, $resetKyc, $frontendUrl, $fullName): JsonResponse {

            // ── 1. Resolve or create batch ─────────────────────────────────────
            if (! empty($data['batch_id'])) {
                $batch = Batch::findOrFail($data['batch_id']);
            } else {
                $batch = Batch::query()->updateOrCreate(
                    ['name' => $data['batch_name']],
                    [
                        'academic_year' => $data['academic_year'],
                        'semester' => $data['semester'],
                        'status' => 'active',
                        'window_status' => 'active',
                        'is_active' => true,
                        'submission_deadline' => now()->addDays(45),
                    ]
                );
            }

            // ── 2. Resolve admin for import attribution ────────────────────────
            $adminId = User::query()->where('role', 'admin')->value('id')
                ?? User::query()->where('role', 'developer')->value('id');

            // ── 3. Masterlist import record ────────────────────────────────────
            $importSlug = 'activation-seeder-ui-'.$batch->id.'.csv';
            $import = MasterlistImport::query()->firstOrCreate(
                [
                    'batch_id' => $batch->id,
                    'original_name' => $importSlug,
                ],
                [
                    'uploaded_by' => $adminId,
                    'stored_path' => 'masterlist-imports/'.$importSlug,
                    'status' => 'imported',
                    'total_rows' => 0,
                    'valid_rows' => 0,
                    'imported_rows' => 0,
                ]
            );

            // ── 4. User ────────────────────────────────────────────────────────
            // Deconflict any OTHER user that already holds this student_id.
            User::query()
                ->where('student_id', $data['student_id'])
                ->where('email', '!=', $data['email'])
                ->update(['student_id' => $data['student_id'].'-old-'.Str::random(4)]);

            $user = User::query()->updateOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $fullName,
                    'role' => 'student',
                    'student_id' => $data['student_id'],
                    'account_status' => 'unverified',
                    'password' => Hash::make(Str::random(48)),
                    'email_verified_at' => null,
                    'activated_at' => null,
                ]
            );

            // ── 5. Optional KYC reset ──────────────────────────────────────────
            if ($resetKyc) {
                KycProfile::query()->where('user_id', $user->id)->delete();
                // Identity profile is linked via grantee — handled after grantee upsert.
            }

            // ── 6. Grantee ────────────────────────────────────────────────────
            $grantee = Grantee::query()->updateOrCreate(
                ['student_id' => $data['student_id'], 'batch_id' => $batch->id],
                [
                    'user_id' => $user->id,
                    'student_id' => $data['student_id'],
                    'student_number' => null,
                    'full_name' => $fullName,
                    'email' => $data['email'],
                    'program' => $data['program'],
                    'year_level' => $yearLevel,
                    'status' => 'unverified',
                    'submission_status' => 'not_submitted',
                ]
            );

            if ($resetKyc) {
                GranteeIdentityProfile::query()->where('grantee_id', $grantee->id)->delete();
            }

            // ── 7. Masterlist row ──────────────────────────────────────────────
            $rowNumber = MasterlistRow::query()
                ->where('masterlist_import_id', $import->id)
                ->count() + 1;

            MasterlistRow::query()->updateOrCreate(
                [
                    'masterlist_import_id' => $import->id,
                    'student_id' => $data['student_id'],
                ],
                [
                    'row_number' => $rowNumber,
                    'student_number' => null,
                    'full_name' => $fullName,
                    'email' => $data['email'],
                    'program' => $data['program'],
                    'year_level' => $yearLevel,
                    'status' => 'valid',
                ]
            );

            // Update import counters
            $import->increment('total_rows');
            $import->increment('valid_rows');
            $import->increment('imported_rows');

            // ── 8. Activation token ────────────────────────────────────────────
            // Invalidate all prior unused tokens for this user.
            ActivationToken::query()
                ->where('user_id', $user->id)
                ->whereNull('used_at')
                ->delete();

            $plainToken = Str::random(48);
            ActivationToken::create([
                'user_id' => $user->id,
                'token_hash' => hash('sha256', $plainToken),
                'expires_at' => now()->addDays(14),
            ]);

            $activationUrl = $frontendUrl.'/activate/'.$plainToken.'?lang=en';

            return response()->json([
                'data' => [
                    'user_id' => $user->id,
                    'grantee_id' => $grantee->id,
                    'batch_id' => $batch->id,
                    'batch_name' => $batch->name,
                    'student_id' => $data['student_id'],
                    'full_name' => $fullName,
                    'email' => $data['email'],
                    'program' => $data['program'],
                    'plain_token' => $plainToken,
                    'activation_url' => $activationUrl,
                    'expires_at' => now()->addDays(14)->toIso8601String(),
                    'reset_kyc' => $resetKyc,
                ],
            ], 201);
        });
    }
}
