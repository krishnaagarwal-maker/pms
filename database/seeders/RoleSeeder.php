<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

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

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
}
