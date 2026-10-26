<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\TaskAssignedNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\View;
use Modules\ProjectManagement\Models\Project;
use Modules\ProjectManagement\Database\Seeders\RolePermissionSeeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectManagementViewsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

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

    public function test_manager_can_onboard_new_user(): void
    {
        Notification::fake();

        Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'team_member', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'users.create', 'guard_name' => 'web']);

        $manager = User::factory()->create();
        $manager->assignRole('manager');
        $manager->givePermissionTo('users.create');

        $payload = [
            'name' => 'Onboarded Developer',
            'email' => 'developer@example.com',
            'password' => 'secret12345',
            'password_confirmation' => 'secret12345',
            'role' => 'team_member',
        ];

        $response = $this->actingAs($manager)
            ->post(route('projects.users.onboard'), $payload);

        $response->assertRedirect(route('projects.users.roles.index'));
        $this->assertDatabaseHas('users', [
            'name' => 'Onboarded Developer',
            'email' => 'developer@example.com',
        ]);

        $createdUser = User::where('email', 'developer@example.com')->first();
        $this->assertNotNull($createdUser);
        $this->assertTrue($createdUser->hasRole('team_member'));

        Notification::assertSentTo($createdUser, \App\Notifications\UserOnboardedNotification::class, function ($notification) use ($manager, $createdUser) {
            $mail = $notification->toMail($createdUser);
            $this->assertStringContainsString('Welcome to', $mail->subject);
            return $notification->onboardedBy->id === $manager->id;
        });
    }

    public function test_manager_onboards_user_defaults_to_team_member_when_role_omitted(): void
    {
        Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'team_member', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'users.create', 'guard_name' => 'web']);

        $manager = User::factory()->create();
        $manager->assignRole('manager');
        $manager->givePermissionTo('users.create');

        $payload = [
            'name' => 'Default Member',
            'email' => 'defaultmember@example.com',
            'password' => 'secret12345',
            'password_confirmation' => 'secret12345',
            // 'role' omitted
        ];

        $response = $this->actingAs($manager)
            ->post(route('projects.users.onboard'), $payload);

        $response->assertRedirect(route('projects.users.roles.index'));
        $createdUser = User::where('email', 'defaultmember@example.com')->first();
        $this->assertNotNull($createdUser);
        $this->assertTrue($createdUser->hasRole('team_member'));
    }

    public function test_unauthorized_user_cannot_onboard_new_user(): void
    {
        Role::firstOrCreate(['name' => 'team_member', 'guard_name' => 'web']);

        $teamMember = User::factory()->create();
        $teamMember->assignRole('team_member');

        $payload = [
            'name' => 'Unauthorized Creation',
            'email' => 'unauthorized@example.com',
            'password' => 'secret12345',
            'password_confirmation' => 'secret12345',
            'role' => 'team_member',
        ];

        $this->actingAs($teamMember)
            ->post(route('projects.users.onboard'), $payload)
            ->assertForbidden();

        $this->assertDatabaseMissing('users', [
            'email' => 'unauthorized@example.com',
        ]);
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
        $viewer->givePermissionTo(['projects.view', 'tasks.view', 'tasks.assign']);

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

    public function test_manager_assignment_sends_email_notification_to_assignee(): void
    {
        Notification::fake();

        $manager = User::factory()->create();
        $manager->givePermissionTo(['tasks.edit', 'tasks.assign']);

        $assignee = User::factory()->create(['name' => 'Assigned User']);

        $project = Project::create([
            'name' => 'Assignment notification project',
            'description' => 'Task assignment email check',
            'start_date' => now()->subDay(),
            'end_date' => now()->addDays(10),
        ]);

        $task = $project->tasks()->create([
            'title' => 'Send onboarding email',
            'description' => 'New hired employee needs setup tasks.',
            'due_date' => now()->addDays(3),
            'priority' => 'medium',
            'status' => 'pending',
        ]);

        $this->actingAs($manager)
            ->put(route('projects.tasks.update', [$project, $task]), [
                'title' => 'Send onboarding email',
                'description' => 'New hired employee needs setup tasks.',
                'due_date' => now()->addDays(3)->format('Y-m-d'),
                'priority' => 'medium',
                'status' => 'pending',
                'assigned_to' => $assignee->id,
            ])
            ->assertRedirect(route('projects.tasks.show', [$project, $task]));

        Notification::assertSentTo($assignee, TaskAssignedNotification::class);
    }

    public function test_task_assignment_notification_uses_in_app_database_channel(): void
    {
        $manager = User::factory()->create();
        $assignee = User::factory()->create(['name' => 'Assigned User']);

        $project = Project::create([
            'name' => 'In-app notification project',
            'description' => 'Dashboard notification check',
            'start_date' => now()->subDay(),
            'end_date' => now()->addDays(10),
        ]);

        $task = $project->tasks()->create([
            'title' => 'Review handoff checklist',
            'description' => 'Need to check onboarding tasks.',
            'due_date' => now()->addDays(2),
            'priority' => 'high',
            'status' => 'pending',
            'assigned_to' => $assignee->id,
        ]);

        $notification = new TaskAssignedNotification($task, $manager);

        $this->assertContains('database', $notification->via($assignee));
        $this->assertSame('Task assigned: Review handoff checklist', $notification->toArray($assignee)['message']);
    }

    public function test_projects_index_page_renders_four_status_stat_cards(): void
    {
        $viewer = User::factory()->create();
        $viewer->givePermissionTo(['projects.view', 'tasks.view', 'tasks.assign']);

        $response = $this->actingAs($viewer)
            ->get(route('projects.index'));

        $response->assertOk()
            ->assertSeeText('Active')
            ->assertSeeText('Due Soon')
            ->assertSeeText('Upcoming')
            ->assertSeeText('Ended');
    }
}

