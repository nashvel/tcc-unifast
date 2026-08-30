<?php

namespace Database\Seeders;

use App\Models\ActivationToken;
use App\Models\Batch;
use App\Models\Grantee;
use App\Models\MasterlistImport;
use App\Models\MasterlistRow;
use App\Models\User;
use Database\Seeders\Concerns\RestrictedToLocalEnvironment;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Seeds four fresh masterlist grantees with unused activation tokens for E2E testing
 * from activation → KYC → ID scan → liveness → vault.
 *
 * Run:
 *   C:\php84\php.exe artisan db:seed --class=ActivationTestGranteesSeeder
 */
class ActivationTestGranteesSeeder extends Seeder
{
    use RestrictedToLocalEnvironment;

    public const TEMP_PASSWORD = 'TCC-TEST-ACT1';

    /**
     * @var list<array{student_id: string, student_number: string, full_name: string, email: string, program: string}>
     */
    private const GRANTEES = [
        [
            'student_id' => '2026-ACT01',
            'student_number' => '2026-ACT01',
            'full_name' => 'Activation Tester One',
            'email' => 'activate1@tcc.edu.ph',
            'program' => 'BSIT',
        ],
        [
            'student_id' => '2026-ACT02',
            'student_number' => '2026-ACT02',
            'full_name' => 'Activation Tester Two',
            'email' => 'activate2@tcc.edu.ph',
            'program' => 'BSIT',
        ],
        [
            'student_id' => '2026-ACT03',
            'student_number' => '2026-ACT03',
            'full_name' => 'Activation Tester Three',
            'email' => 'activate3@tcc.edu.ph',
            'program' => 'BSIT',
        ],
        [
            'student_id' => '2026-ACT04',
            'student_number' => '2026-ACT04',
            'full_name' => 'Activation Tester Four',
            'email' => 'activate4@tcc.edu.ph',
            'program' => 'BSIT',
        ],
    ];

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
        $lines = [
            '',
            '=== Activation E2E test grantees ===',
            'Batch: '.$batch->name.' (id '.$batch->id.')',
            'Temporary password (all four): '.self::TEMP_PASSWORD,
            '',
        ];

        $adminId = User::query()->where('role', 'admin')->value('id')
            ?? User::query()->where('role', 'developer')->value('id');

        $import = MasterlistImport::query()->updateOrCreate(
            [
                'batch_id' => $batch->id,
                'original_name' => 'activation-e2e-seed.csv',
            ],
            [
                'uploaded_by' => $adminId,
                'stored_path' => 'masterlist-imports/activation-e2e-seed.csv',
                'status' => 'imported',
                'total_rows' => count(self::GRANTEES),
                'valid_rows' => count(self::GRANTEES),
                'imported_rows' => count(self::GRANTEES),
            ],
        );

        foreach (self::GRANTEES as $index => $row) {
            $user = User::query()->updateOrCreate(
                ['email' => $row['email']],
                [
                    'name' => $row['full_name'],
                    'role' => 'student',
                    'student_id' => $row['student_id'],
                    'account_status' => 'unverified',
                    'password' => Hash::make(self::TEMP_PASSWORD),
                    'email_verified_at' => null,
                    'activated_at' => null,
                ],
            );

            // Invalidate prior unused tokens so this seed always yields fresh links.
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

            MasterlistRow::query()->updateOrCreate(
                [
                    'masterlist_import_id' => $import->id,
                    'student_id' => $row['student_id'],
                ],
                [
                    'row_number' => $index + 1,
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

            $url = $frontend.'/activate/'.$plainToken.'?lang=en';
            $lines[] = sprintf(
                '%s | student_id=%s | email=%s | grantee_id=%d',
                $row['full_name'],
                $row['student_id'],
                $row['email'],
                $grantee->id,
            );
            $lines[] = '  TOKEN: '.$plainToken;
            $lines[] = '  URL: '.$url;
            $lines[] = '';
        }

        $lines[] = 'Flow: open URL → temp password → set new password → KYC → ID scan → liveness → vault.';
        $lines[] = '';

        foreach ($lines as $line) {
            $this->command?->info($line);
        }
    }
}
