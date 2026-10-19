<?php

namespace Modules\ProjectManagement\Database\Seeders;

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
            'projects.view',
            'projects.create',
            'projects.edit',
            'projects.delete',
            'tasks.view',
            'tasks.create',
            'tasks.edit',
            'tasks.delete',
            'tasks.assign',
            'tasks.update-status',
            'roles.view',
            'roles.create',
            'roles.edit',
            'roles.delete',
            'roles.assign-permissions',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web',
            ]);
        }

        Role::whereIn('name', [
            'manager',
            'team_member',
            'Manager',
            'Team Member',
            'team-member',
            'Team member',
            'project_manager',
            'team_lead',
            'Project Manager',
            'Team Lead',
        ])->delete();

        $manager = Role::firstOrCreate([
            'name' => 'manager',
            'guard_name' => 'web',
        ]);

        $teamMember = Role::firstOrCreate([
            'name' => 'team_member',
            'guard_name' => 'web',
        ]);

        $manager->syncPermissions($permissions);

        $teamMember->syncPermissions([
            'projects.view',
            'tasks.view',
            'tasks.update-status',
        ]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
