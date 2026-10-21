<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\View;
use Modules\ProjectManagement\Models\Project;
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

    public function test_user_with_update_status_permission_can_update_task_status_without_edit_permission(): void
    {
        $assignee = User::factory()->create();
        $assignee->givePermissionTo('tasks.update-status');

        $project = Project::create([
            'name' => 'Status update project',
            'description' => 'Regression test for status update without edit permission',
            'start_date' => now()->subDay(),
            'end_date' => now()->addDays(5),
        ]);

        $task = $project->tasks()->create([
            'title' => 'Status only task',
            'description' => 'Allows status updates when update-status is granted',
            'due_date' => now()->addDays(2),
            'priority' => 'medium',
            'status' => 'pending',
            'assigned_to' => $assignee->id,
        ]);

        $this->actingAs($assignee)
            ->put(route('projects.tasks.update', [$project, $task]), [
                'status' => 'completed',
            ])
            ->assertRedirect(route('projects.tasks.show', [$project, $task]));

        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'status' => 'completed',
        ]);
    }

    public function test_project_and_task_detail_pages_show_assignee_names(): void
    {
        $viewer = User::factory()->create();
        $viewer->givePermissionTo(['projects.view', 'tasks.view']);

        $assignee = User::factory()->create(['name' => 'Jane Assignee']);
        $project = Project::create([
            'name' => 'Launch Project',
            'description' => 'Project detail visibility check',
            'start_date' => now()->subDays(3),
            'end_date' => now()->addDays(10),
        ]);

        $task = $project->tasks()->create([
            'title' => 'Prepare launch checklist',
            'description' => 'Make sure QA is ready.',
            'due_date' => now()->addDays(2),
            'priority' => 'high',
            'status' => 'in_progress',
            'assigned_to' => $assignee->id,
        ]);

        $this->actingAs($viewer)
            ->get(route('projects.show', $project))
            ->assertOk()
            ->assertSeeText('Jane Assignee');

        $this->actingAs($viewer)
            ->get(route('projects.tasks.show', [$project, $task]))
            ->assertOk()
            ->assertSeeText('Jane Assignee');
    }
}
