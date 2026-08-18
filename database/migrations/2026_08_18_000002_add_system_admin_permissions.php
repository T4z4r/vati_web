<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $permissions = [
            ['name' => 'manage-system-settings', 'guard_name' => 'web', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'purge-system-data', 'guard_name' => 'web', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'view-audit-trail', 'guard_name' => 'web', 'created_at' => $now, 'updated_at' => $now],
        ];

        DB::table('permissions')->insertOrIgnore($permissions);

        $superAdmin = DB::table('roles')->where('name', 'super_admin')->first();
        $headOffice = DB::table('roles')->where('name', 'head_office_admin')->first();
        $auditor = DB::table('roles')->where('name', 'auditor')->first();

        if ($superAdmin) {
            foreach (['manage-system-settings', 'purge-system-data', 'view-audit-trail'] as $perm) {
                DB::table('role_has_permissions')->insertOrIgnore([
                    'role_id' => $superAdmin->id,
                    'permission_id' => DB::table('permissions')->where('name', $perm)->value('id'),
                ]);
            }
        }

        if ($headOffice) {
            foreach (['manage-system-settings', 'view-audit-trail'] as $perm) {
                DB::table('role_has_permissions')->insertOrIgnore([
                    'role_id' => $headOffice->id,
                    'permission_id' => DB::table('permissions')->where('name', $perm)->value('id'),
                ]);
            }
        }

        if ($auditor) {
            DB::table('role_has_permissions')->insertOrIgnore([
                'role_id' => $auditor->id,
                'permission_id' => DB::table('permissions')->where('name', 'view-audit-trail')->value('id'),
            ]);
        }
    }

    public function down(): void
    {
        $permissionNames = ['manage-system-settings', 'purge-system-data', 'view-audit-trail'];

        DB::table('role_has_permissions')
            ->whereIn('permission_id', function ($query) use ($permissionNames) {
                $query->select('id')->from('permissions')->whereIn('name', $permissionNames);
            })
            ->delete();

        DB::table('permissions')->whereIn('name', $permissionNames)->delete();
    }
};
