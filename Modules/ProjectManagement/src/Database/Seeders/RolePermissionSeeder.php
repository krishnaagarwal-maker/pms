<?php

namespace Modules\ProjectManagement\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\ProjectManagement\Support\ProjectManagementPermissionSync;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        ProjectManagementPermissionSync::ensure();
    }
}
