<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $roles = DB::table('roles')->pluck('id', 'name');

        DB::table('users')
            ->select('id', 'role')
            ->orderBy('id')
            ->eachById(function (object $user) use ($roles): void {
                $roleName = $user->role === 'head' ? 'admin' : $user->role;
                $roleId = $roles->get($roleName);

                if ($roleId === null) {
                    return;
                }

                DB::table('role_user')->insertOrIgnore([
                    'user_id' => $user->id,
                    'role_id' => $roleId,
                ]);
            });
    }

    public function down(): void
    {
        // The backfill preserves existing assignments and must not remove roles on rollback.
    }
};
