<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * The platform's roles. Only parent/student/teacher/school_admin/
 * district_officer get real Phase 1 dashboards (see ROADMAP.md) — the rest
 * exist so RBAC, login, and routing are provably correct end to end even
 * where the UI for them is still a placeholder.
 *
 * career_mentor and data_protection_officer were added with the student
 * growth/career modules (spec section 6, roles 7 and 17).
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
            // Spec sections 15-17. Note there is deliberately no
            // "view-any-student-growth" permission: access to an individual
            // child's development record is decided per child by
            // DevelopmentAccessService (guardian, own school, or the child
            // themself), never granted wholesale by holding a role.
            'record-capability-observation',
            'manage-growth-plan',
            'record-life-skills',
            'manage-consent',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        $roleMap = [
            'public' => [],
            'parent' => ['submit-complaint', 'submit-feedback', 'view-own-complaints', 'record-capability-observation', 'manage-consent'],
            'student' => ['submit-complaint', 'submit-feedback', 'view-own-complaints', 'record-capability-observation'],
            'teacher' => ['view-own-complaints', 'record-capability-observation', 'manage-growth-plan', 'record-life-skills'],
            'career_mentor' => ['record-life-skills', 'record-capability-observation'],
            'school_admin' => ['respond-to-complaint', 'manage-school-profile'],
            'district_officer' => ['review-district-complaints', 'access-protected-identity'],
            'state_officer' => ['review-state-complaints', 'access-protected-identity', 'view-national-analytics'],
            'national_admin' => ['review-state-complaints', 'access-protected-identity', 'view-audit-logs', 'view-national-analytics'],
            'researcher' => ['view-national-analytics'],
            // Spec section 6 role 17 — owns consent records and data-subject
            // requests under DPDP. Auditing consent is not the same as reading
            // what the consent covers, so this role gets no development-data
            // access of its own.
            'data_protection_officer' => ['manage-consent', 'view-audit-logs'],
            'system_admin' => $permissions,
        ];

        foreach ($roleMap as $role => $perms) {
            $roleModel = Role::firstOrCreate(['name' => $role]);
            $roleModel->syncPermissions($perms);
        }
    }
}
