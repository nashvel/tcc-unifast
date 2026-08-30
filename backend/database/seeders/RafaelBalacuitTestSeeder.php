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
use Database\Seeders\Concerns\RestrictedToLocalEnvironment;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Seeds Rafael Balacuit for real School ID OCR / KYC matching tests.
 *
 * Masterlist / KYC truth:
 *   student_id 20231909, full_name "Rafael Balacuit", program BSIT
 *   (first: Rafael, middle: empty, last: Balacuit)
 *
 * OCR front of School ID must read: "Rafael Balacuit" + "20231909".
 *
 * Run:
 *   C:\php84\php.exe artisan db:seed --class=RafaelBalacuitTestSeeder
 */
class RafaelBalacuitTestSeeder extends Seeder
{
    use RestrictedToLocalEnvironment;

    public const TEMP_PASSWORD = 'TCC-TEST-ACT1';

    public const EMAIL = 'rafael.balacuit@tcc.edu.ph';

    public const STUDENT_ID = '20231909';

    public const FULL_NAME = 'Rafael Balacuit';

    public const PROGRAM = 'BSIT';

    public function run(): void
    {
        $this->assertLocalEnvironment();

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

        $frontend = rtrim((string) (env('FRONTEND_URL') ?: 'http://localhost:5173'), '/');

        $adminId = User::query()->where('role', 'admin')->value('id')
            ?? User::query()->where('role', 'developer')->value('id');

        $import = MasterlistImport::query()->updateOrCreate(
            [
                'batch_id' => $batch->id,
                'original_name' => 'rafael-balacuit-school-id-seed.csv',
            ],
            [
                'uploaded_by' => $adminId,
                'stored_path' => 'masterlist-imports/rafael-balacuit-school-id-seed.csv',
                'status' => 'imported',
                'total_rows' => 1,
                'valid_rows' => 1,
                'imported_rows' => 1,
            ],
        );

        $user = User::query()->updateOrCreate(
            ['email' => self::EMAIL],
            [
                'name' => self::FULL_NAME,
                'role' => 'student',
                'student_id' => self::STUDENT_ID,
                'account_status' => 'unverified',
                'password' => Hash::make(self::TEMP_PASSWORD),
                'email_verified_at' => null,
                'activated_at' => null,
            ],
        );

        ActivationToken::query()
            ->where('user_id', $user->id)
            ->whereNull('used_at')
            ->delete();

        $grantee = Grantee::query()->updateOrCreate(
            ['student_id' => self::STUDENT_ID, 'batch_id' => $batch->id],
            [
                'user_id' => $user->id,
                'student_number' => self::STUDENT_ID,
                'full_name' => self::FULL_NAME,
                'email' => self::EMAIL,
                'program' => self::PROGRAM,
                'year_level' => '1',
                'status' => 'unverified',
                'submission_status' => 'not_submitted',
            ],
        );

        // Fresh seed: clear prior KYC / ID / liveness so OCR re-scan is required.
        KycProfile::query()->where('user_id', $user->id)->delete();
        GranteeIdentityProfile::query()->where('grantee_id', $grantee->id)->delete();

        MasterlistRow::query()->updateOrCreate(
            [
                'masterlist_import_id' => $import->id,
                'student_id' => self::STUDENT_ID,
            ],
            [
                'row_number' => 1,
                'student_number' => self::STUDENT_ID,
                'full_name' => self::FULL_NAME,
                'email' => self::EMAIL,
                'program' => self::PROGRAM,
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

        $url = $frontend.'/activate/'.$plainToken.'?lang=en';

        $lines = [
            '',
            '=== Rafael Balacuit School ID test account ===',
            'Batch: '.$batch->name.' (id '.$batch->id.')',
            'Email: '.self::EMAIL,
            'Student ID: '.self::STUDENT_ID,
            'Full name: '.self::FULL_NAME.' (first: Rafael, middle: empty, last: Balacuit)',
            'Program: '.self::PROGRAM,
            'Temp password: '.self::TEMP_PASSWORD,
            'account_status: unverified (after activate → pending_kyc)',
            'grantee_id: '.$grantee->id,
            '',
            'TOKEN: '.$plainToken,
            'URL: '.$url,
            '',
            'Steps: open URL → temp password → set new password → KYC',
            '  (student_id 20231909, full_name Rafael Balacuit, program BSIT)',
            '  → ID scan → liveness → vault.',
            'OCR front of School ID MUST read: "Rafael Balacuit" + "20231909".',
            '',
        ];

        foreach ($lines as $line) {
            $this->command?->info($line);
        }
    }
}
