<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $permissions = [
            'view-dashboard', 'view-management-dashboard', 'manage-organization', 'view-users', 'manage-users',
            'view-members', 'create-members', 'edit-members', 'delete-members',
            'view-groups', 'create-groups', 'edit-groups', 'view-group-portfolio',
            'view-group-witnesses', 'manage-group-witnesses', 'view-group-visits', 'view-loan-products', 'manage-loan-products',
            'view-loan-applications', 'create-loan-applications', 'review-loan-applications',
            'approve-loan-applications', 'reject-loan-applications', 'view-loans', 'disburse-loans',
            'view-payments', 'collect-payments', 'reverse-payments', 'view-security', 'manage-security',
            'settle-loans', 'view-reports', 'export-reports', 'view-audit-logs',
            'manage-loan-compliance', 'verify-loan-documents', 'replace-passbooks',
            'issue-default-notices', 'authorize-loan-clearances',
            'view-portfolio',
        ];
        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        $roles = ['super_admin', 'head_office_admin', 'regional_manager', 'area_manager', 'branch_manager', 'assistant_branch_manager', 'credit_officer', 'loan_officer', 'cashier', 'finance_officer', 'auditor', 'member'];
        foreach ($roles as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
        Role::findByName('super_admin')->syncPermissions(Permission::all());
        Role::findByName('head_office_admin')->syncPermissions(Permission::all());
        Role::findByName('branch_manager')->syncPermissions(Permission::whereNotIn('name', ['manage-organization', 'manage-users', 'view-audit-logs', 'reverse-payments'])->get());
        Role::findByName('regional_manager')->syncPermissions(Role::findByName('branch_manager')->permissions);
        Role::findByName('area_manager')->syncPermissions(Role::findByName('branch_manager')->permissions);
        Role::findByName('assistant_branch_manager')->syncPermissions(Role::findByName('branch_manager')->permissions->whereNotIn('name', ['approve-loan-applications', 'disburse-loans', 'settle-loans', 'authorize-loan-clearances']));
        Role::findByName('loan_officer')->syncPermissions(['view-dashboard', 'view-members', 'create-members', 'edit-members', 'view-groups', 'view-group-portfolio', 'view-group-witnesses', 'manage-group-witnesses', 'view-group-visits', 'view-loan-products', 'view-loan-applications', 'create-loan-applications', 'manage-loan-compliance', 'replace-passbooks', 'issue-default-notices', 'view-loans', 'view-payments', 'collect-payments', 'view-security']);
        Role::findByName('cashier')->syncPermissions(['view-dashboard', 'view-members', 'view-loans', 'view-payments', 'collect-payments', 'view-security', 'manage-security']);
        Role::findByName('finance_officer')->syncPermissions(['view-dashboard', 'view-members', 'view-loans', 'view-payments', 'collect-payments', 'reverse-payments', 'view-security', 'manage-security', 'settle-loans', 'view-reports', 'export-reports']);
        Role::findByName('credit_officer')->syncPermissions(['view-dashboard', 'view-members', 'view-groups', 'view-group-portfolio', 'view-group-witnesses', 'view-loan-products', 'view-loan-applications', 'review-loan-applications', 'verify-loan-documents', 'view-loans', 'view-portfolio']);
        Role::findByName('auditor')->syncPermissions(['view-dashboard', 'view-members', 'view-groups', 'view-loan-products', 'view-loan-applications', 'view-loans', 'view-payments', 'view-security', 'view-reports', 'export-reports', 'view-audit-logs']);
    }
}
