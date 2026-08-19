<?php

namespace Database\Seeders;

use App\Models\ActivationToken;
use App\Models\Batch;
use App\Models\Grantee;
use App\Models\GranteeIdentityProfile;
use App\Models\KycProfile;
use App\Models\MasterlistImport;
use App\Models\MasterlistRow;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Seeds Jay Nashvel Quiblat with a fresh unused activation token.
 * Token-only activate (no temporary password) — open URL → set new password → KYC.
 *
 * Run:
 *   C:\php84\php.exe artisan db:seed --class=JayQuiblatActivationSeeder
 */
class JayQuiblatActivationSeeder extends MobileActivationSeeder
{
    private const STUDENT = [
        'student_id' => '20232131',
        'student_number' => '20232131',
        'full_name' => 'Jay Nashvel Quiblat',
        'email' => 'jay.nashvel.quiblat@tcc.edu.ph',
        'program' => 'BSIT',
    ];

    public function run(): void
    {
        $row = self::STUDENT;

        $batch = Batch::query()->updateOrCreate(
            ['name' => 'TES AY 2026-2027 1st (Activation E2E)'],
            [
                'academic_year' => '2026-2027',
                'semester' => '1st Semester',
                'status' => 'active',
                'window_status' => 'active',
                'is_active' => true,
                'submission_deadline' => now()->addDays(45),
            ],
        );

        $resolved = $this->resolveFrontendBases();
        $frontend = $resolved['mobile'];

        $adminId = User::query()->where('role', 'admin')->value('id')
            ?? User::query()->where('role', 'developer')->value('id');

        $import = MasterlistImport::query()->updateOrCreate(
            [
                'batch_id' => $batch->id,
                'original_name' => 'jay-quiblat-activation-seed.csv',
            ],
            [
                'uploaded_by' => $adminId,
                'stored_path' => 'masterlist-imports/jay-quiblat-activation-seed.csv',
                'status' => 'imported',
                'total_rows' => 1,
                'valid_rows' => 1,
                'imported_rows' => 1,
            ],
        );

        // Placeholder hash only — activation is token-only (no temp password to enter).
        $user = User::query()->updateOrCreate(
            ['email' => $row['email']],
            [
                'name' => $row['full_name'],
                'role' => 'student',
                'student_id' => $row['student_id'],
                'account_status' => 'unverified',
                'password' => Hash::make(Str::random(48)),
                'email_verified_at' => null,
                'activated_at' => null,
            ],
        );

        // Also match by student_id if email was previously different.
        User::query()
            ->where('student_id', $row['student_id'])
            ->where('id', '!=', $user->id)
            ->update(['student_id' => $row['student_id'].'-old-'.Str::random(4)]);

        ActivationToken::query()
            ->where('user_id', $user->id)
            ->whereNull('used_at')
            ->delete();

        $grantee = Grantee::query()->updateOrCreate(
            ['student_id' => $row['student_id'], 'batch_id' => $batch->id],
            [
                'user_id' => $user->id,
                'student_number' => $row['student_number'],
                'full_name' => $row['full_name'],
                'email' => $row['email'],
                'program' => $row['program'],
                'year_level' => '1',
                'status' => 'unverified',
                'submission_status' => 'not_submitted',
            ],
        );

        KycProfile::query()->where('user_id', $user->id)->delete();
        GranteeIdentityProfile::query()->where('grantee_id', $grantee->id)->delete();

        MasterlistRow::query()->updateOrCreate(
            [
                'masterlist_import_id' => $import->id,
                'student_id' => $row['student_id'],
            ],
            [
                'row_number' => 1,
                'student_number' => $row['student_number'],
                'full_name' => $row['full_name'],
                'email' => $row['email'],
                'program' => $row['program'],
                'year_level' => '1',
                'status' => 'valid',
            ],
        );

        $plainToken = Str::random(48);
        ActivationToken::create([
            'user_id' => $user->id,
            'token_hash' => hash('sha256', $plainToken),
            'expires_at' => now()->addDays(14),
        ]);

        $path = '/activate/'.$plainToken.'?lang=en';

        $lines = [
            '',
            '=== Jay Nashvel Quiblat activation ===',
            'Batch: '.$batch->name.' (id '.$batch->id.')',
            'Resolved via: '.$resolved['source'],
            'Name: '.$row['full_name'],
            'Student ID: '.$row['student_id'],
            'Email: '.$row['email'],
            'Temp password: NONE (token-only activate)',
            'TOKEN: '.$plainToken,
            'URL (primary): '.$frontend.$path,
        ];
        if ($resolved['localhost'] !== $frontend) {
            $lines[] = 'URL (desktop localhost): '.$resolved['localhost'].$path;
        }
        if ($resolved['lan'] !== null && $resolved['lan'] !== $frontend) {
            $lines[] = 'URL (LAN): '.$resolved['lan'].$path;
        }
        $lines[] = '';
        $lines[] = 'Flow: open URL → set new password (no temp password) → KYC → ID scan → liveness.';
        $lines[] = '';

        foreach ($lines as $line) {
            $this->command?->info($line);
        }
    }
}
