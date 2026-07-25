<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $now = now();
        $programs = [
            ['BSIT', 'Bachelor of Science in Information Technology'],
            ['BSBA', 'Bachelor of Science in Business Administration'],
            ['BEED', 'Bachelor in Elementary Education'],
            ['BSED', 'Bachelor of Secondary Education'],
            ['BSPA', 'Bachelor of Science in Public Administration'],
            ['BSCRIM', 'Bachelor of Science in Criminology'],
            ['ABSocio', 'Bachelor of Arts in Sociology'],
            ['DEVCOM', 'Bachelor of Science in Development Communication'],
            ['BSFT', 'Bachelor of Science in Food Technology'],
            ['BSAT', 'Bachelor of Science in Automotive Technology'],
            ['BSET', 'Bachelor of Science in Electronics Technology'],
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
            'BSBA', 'BEED', 'BSED', 'BSPA', 'BSCRIM', 'ABSocio', 'DEVCOM', 'BSFT', 'BSAT', 'BSET',
        ])->delete();
    }
};
