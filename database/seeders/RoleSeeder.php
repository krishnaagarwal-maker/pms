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

        Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'team_member', 'guard_name' => 'web']);

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
}
