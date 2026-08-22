<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $developerRoleId = DB::table('roles')->where('name', 'developer')->value('id');

        if (! $developerRoleId) {
            return;
        }

        $permissionId = DB::table('permissions')->where('name', 'view_database')->value('id');

        if (! $permissionId) {
            $permissionId = DB::table('permissions')->insertGetId([
                'name' => 'view_database',
                'description' => 'View allowlisted database tables',
                'category' => 'Developer Tools',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $exists = DB::table('permission_role')
            ->where('role_id', $developerRoleId)
            ->where('permission_id', $permissionId)
            ->exists();

        if (! $exists) {
            DB::table('permission_role')->insert([
                'role_id' => $developerRoleId,
                'permission_id' => $permissionId,
            ]);
        }
    }

    public function down(): void
    {
        $permissionId = DB::table('permissions')->where('name', 'view_database')->value('id');

        if (! $permissionId) {
            return;
        }

        DB::table('permission_role')->where('permission_id', $permissionId)->delete();
        DB::table('permissions')->where('id', $permissionId)->delete();
    }
};
