<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserRoleSeeder extends Seeder
{
    public function run(): void
    {
        $assignments = [
            'admin@unifast.gov.ph' => 'developer',
            'head@unifast.gov.ph' => 'admin',
            'staff@unifast.gov.ph' => 'staff',
            'student@tcc.edu.ph' => 'student',
        ];

        foreach ($assignments as $email => $roleName) {
            $user = User::where('email', $email)->first();
            $role = Role::where('name', $roleName)->first();

            if ($user && $role) {
                $user->roles()->syncWithoutDetaching([$role->id]);
            }
        }
    }
}
