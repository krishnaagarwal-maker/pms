<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\View;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProjectManagementViewsTest extends TestCase
{
    public function test_project_management_views_are_registered_with_the_correct_namespace(): void
    {
        $this->assertTrue(View::exists('ProjectManagement::projects.index'));
        $this->assertTrue(View::exists('ProjectManagement::projects.create'));
        $this->assertTrue(View::exists('ProjectManagement::tasks.index'));
        $this->assertTrue(View::exists('ProjectManagement::tasks.my-tasks'));
    }

    public function test_manager_can_delete_another_user_from_role_management_page(): void
    {
        Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'team_member', 'guard_name' => 'web']);

        Permission::firstOrCreate(['name' => 'roles.assign-permissions', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'roles.delete', 'guard_name' => 'web']);

        $manager = User::factory()->create();
        $manager->assignRole('manager');
        $manager->givePermissionTo(['roles.assign-permissions', 'roles.delete']);

        $targetUser = User::factory()->create();
        $targetUser->assignRole('team_member');

        $this->actingAs($manager)
            ->delete(route('projects.users.destroy', $targetUser))
            ->assertRedirect(route('projects.users.roles.index'));

        $this->assertDatabaseMissing('users', ['id' => $targetUser->id]);
    }

    public function test_team_member_cannot_delete_user_from_role_management_page(): void
    {
        Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'team_member', 'guard_name' => 'web']);

        $teamMember = User::factory()->create();
        $teamMember->assignRole('team_member');

        $targetUser = User::factory()->create();

        $this->actingAs($teamMember)
            ->delete(route('projects.users.destroy', $targetUser))
            ->assertForbidden();
    }
}
