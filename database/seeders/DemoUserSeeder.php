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
            ['System Administrator', 'admin@unifast.gov.ph', 'admin', null],
            ['Office Head', 'head@unifast.gov.ph', 'head', null],
            ['UniFAST Staff', 'staff@unifast.gov.ph', 'staff', null],
            ['Maria Angela Santos', 'student@tcc.edu.ph', 'student', '2024-00182'],
        ] as [$name, $email, $role, $studentId]) {
            User::updateOrCreate(['email' => $email], [
                'name' => $name, 'role' => $role, 'student_id' => $studentId, 'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]);
        }
    }
}
