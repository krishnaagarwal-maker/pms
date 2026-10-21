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

        $canonicalRoles = [
            'manager' => ['manager', 'Manager'],
            'team_member' => ['team_member', 'team-member', 'Team Member', 'Team member', 'Team Lead', 'team_lead'],
        ];

        foreach ($canonicalRoles as $canonicalName => $aliases) {
            $canonicalRole = Role::firstOrCreate([
                'name' => $canonicalName,
                'guard_name' => 'web',
            ]);

            foreach ($aliases as $alias) {
                if ($alias === $canonicalName) {
                    continue;
                }

                $duplicate = Role::where('guard_name', 'web')->where('name', $alias)->first();
                if (! $duplicate || $duplicate->id === $canonicalRole->id) {
                    continue;
                }

                foreach ($duplicate->users as $user) {
                    $user->syncRoles([$canonicalName]);
                }

                $duplicate->delete();
            }
        }

        $manager = Role::where('guard_name', 'web')->where('name', 'manager')->firstOrFail();
        $teamMember = Role::where('guard_name', 'web')->where('name', 'team_member')->firstOrFail();

        $manager->syncPermissions($permissions);

        $teamMember->syncPermissions([
            'projects.view',
            'tasks.view',
            'tasks.update-status',
        ]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
