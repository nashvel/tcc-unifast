<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoUserSeeder extends Seeder
{
    public function run(): void
    {
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
