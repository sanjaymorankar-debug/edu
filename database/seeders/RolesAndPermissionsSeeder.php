<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * The 10 roles from spec section J. Only parent/student/teacher/school_admin/
 * district_officer get real Phase 1 dashboards (see ROADMAP.md) — the rest
 * exist so RBAC, login, and routing are provably correct end to end even
 * where the UI for them is still a placeholder.
 */
class RolesAndPermissionsSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        Cache::forget('spatie.permission.cache');

        $permissions = [
            'submit-complaint',
            'submit-feedback',
            'view-own-complaints',
            'respond-to-complaint',
            'manage-school-profile',
            'review-district-complaints',
            'review-state-complaints',
            'access-protected-identity',
            'view-audit-logs',
            'manage-admin-settings',
            'view-national-analytics',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        $roleMap = [
            'public' => [],
            'parent' => ['submit-complaint', 'submit-feedback', 'view-own-complaints'],
            'student' => ['submit-complaint', 'submit-feedback', 'view-own-complaints'],
            'teacher' => ['view-own-complaints'],
            'school_admin' => ['respond-to-complaint', 'manage-school-profile'],
            'district_officer' => ['review-district-complaints', 'access-protected-identity'],
            'state_officer' => ['review-state-complaints', 'access-protected-identity', 'view-national-analytics'],
            'national_admin' => ['review-state-complaints', 'access-protected-identity', 'view-audit-logs', 'view-national-analytics'],
            'researcher' => ['view-national-analytics'],
            'system_admin' => $permissions,
        ];

        foreach ($roleMap as $role => $perms) {
            $roleModel = Role::firstOrCreate(['name' => $role]);
            $roleModel->syncPermissions($perms);
        }
    }
}
