<?php

namespace Database\Seeders;

use App\Models\User;
use Database\Seeders\Concerns\RestrictedToLocalEnvironment;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Local demo accounts, all sharing the password 'password'.
 *
 * Includes a `developer` account (admin@unifast.gov.ph), so running this outside
 * local/testing would plant a fully privileged, publicly guessable credential.
 */
class DemoUserSeeder extends Seeder
{
    use RestrictedToLocalEnvironment;

    public function run(): void
    {
        $this->assertLocalEnvironment();

        foreach ([
            ['System Developer', 'admin@unifast.gov.ph', 'developer', null],
            ['Office Administrator', 'head@unifast.gov.ph', 'admin', null],
            ['UniFAST Staff', 'staff@unifast.gov.ph', 'staff', null],
            ['Maria Angela Santos', 'student@tcc.edu.ph', 'student', '2024-00182'],
        ] as [$name, $email, $role, $studentId]) {
            User::updateOrCreate(['email' => $email], [
                'name' => $name,
                'role' => $role,
                'student_id' => $studentId,
                'password' => Hash::make('password'),
                'account_status' => 'active',
                'email_verified_at' => now(),
            ]);
        }
    }
}
