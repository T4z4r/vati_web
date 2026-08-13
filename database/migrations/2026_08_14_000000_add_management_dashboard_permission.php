<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permission = Permission::firstOrCreate([
            'name' => 'view-management-dashboard',
            'guard_name' => 'web',
        ]);

        Role::query()
            ->where('guard_name', 'web')
            ->whereIn('name', [
                'super_admin',
                'head_office_admin',
                'regional_manager',
                'area_manager',
                'branch_manager',
                'assistant_branch_manager',
            ])
            ->get()
            ->each(fn (Role $role) => $role->givePermissionTo($permission));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::query()
            ->where('name', 'view-management-dashboard')
            ->where('guard_name', 'web')
            ->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
