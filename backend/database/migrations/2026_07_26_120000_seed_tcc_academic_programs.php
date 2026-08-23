<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $programs = [
            ['ABSocio', 'Bachelor of Arts in Sociology'],
            ['BEED', 'Bachelor in Elementary Education'],
            ['BSBA', 'Bachelor of Science in Business Administration'],
            ['BSCRIM', 'Bachelor of Science in Criminology'],
            ['BSED', 'Bachelor of Secondary Education'],
            ['BSIT', 'Bachelor of Science in Information Technology'],
        ];

        foreach ($programs as [$code, $name]) {
            $exists = DB::table('academic_programs')->where('code', $code)->exists();
            if ($exists) {
                DB::table('academic_programs')->where('code', $code)->update([
                    'name' => $name,
                    'pass_grade' => 3.0,
                    'is_active' => true,
                    'updated_at' => $now,
                ]);

                continue;
            }

            DB::table('academic_programs')->insert([
                'code' => $code,
                'name' => $name,
                'pass_grade' => 3.0,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('academic_programs')->whereIn('code', [
            'ABSocio', 'BEED', 'BSBA', 'BSCRIM', 'BSED', 'BSIT',
        ])->delete();
    }
};
