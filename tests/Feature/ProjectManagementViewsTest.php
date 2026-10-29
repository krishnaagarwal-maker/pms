<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\TaskAssignedNotification;
use App\Notifications\UserOnboardedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\View;
use Modules\ProjectManagement\Database\Seeders\RolePermissionSeeder;
use Modules\ProjectManagement\Models\Project;
use Modules\ProjectManagement\Models\TaskComment;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
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

        Notification::assertSentTo($createdUser, UserOnboardedNotification::class, function ($notification) use ($manager, $createdUser) {
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

    public function test_assigned_user_can_add_a_timestamped_daily_task_update(): void
    {
        $assignee = User::factory()->create(['name' => 'Daily Update Author']);
        $assignee->givePermissionTo(['tasks.view', 'tasks.update-status']);

        $project = Project::create([
            'name' => 'Daily updates project',
            'description' => 'Task update check',
            'start_date' => now()->subDay(),
            'end_date' => now()->addDays(5),
        ]);

        $task = $project->tasks()->create([
            'title' => 'Implement reporting endpoint',
            'description' => 'Build the endpoint and verify the response.',
            'due_date' => now()->addDays(2),
            'priority' => 'medium',
            'status' => 'in_progress',
            'assigned_to' => $assignee->id,
        ]);

        $this->actingAs($assignee)
            ->post(route('projects.tasks.comments.store', [$project, $task]), [
                'body' => 'Completed the response mapping and started endpoint tests.',
            ])
            ->assertRedirect(route('projects.tasks.show', [$project, $task]))
            ->assertSessionHas('success', 'Daily work update added successfully.');

        $this->assertDatabaseHas('task_comments', [
            'task_id' => $task->id,
            'user_id' => $assignee->id,
            'body' => 'Completed the response mapping and started endpoint tests.',
        ]);

        $this->actingAs($assignee)
            ->get(route('projects.tasks.show', [$project, $task]))
            ->assertOk()
            ->assertSeeText('Completed the response mapping and started endpoint tests.')
            ->assertSeeText('Daily Update Author');

        $comment = TaskComment::where('task_id', $task->id)->firstOrFail();

        $this->actingAs($assignee)
            ->post(route('projects.tasks.comments.store', [$project, $task]), [
                'body' => 'Started the integration test coverage.',
            ])
            ->assertRedirect(route('projects.tasks.show', [$project, $task]));

        $this->actingAs($assignee)
            ->put(route('projects.tasks.comments.update', [$project, $task, $comment]), [
                'body' => 'An older update must not be editable.',
            ])
            ->assertForbidden();

        $latestComment = TaskComment::where('task_id', $task->id)
            ->where('body', 'Started the integration test coverage.')
            ->firstOrFail();

        $this->actingAs($assignee)
            ->put(route('projects.tasks.comments.update', [$project, $task, $latestComment]), [
                'body' => 'Updated the response mapping and completed endpoint tests.',
            ])
            ->assertRedirect(route('projects.tasks.show', [$project, $task]))
            ->assertSessionHas('success', 'Daily work update edited successfully.');

        $this->assertDatabaseHas('task_comments', [
            'id' => $latestComment->id,
            'body' => 'Updated the response mapping and completed endpoint tests.',
        ]);

        $this->actingAs($assignee)
            ->delete(route('projects.tasks.comments.destroy', [$project, $task, $latestComment]))
            ->assertRedirect(route('projects.tasks.show', [$project, $task]))
            ->assertSessionHas('success', 'Daily work update deleted successfully.');

        $this->assertDatabaseMissing('task_comments', ['id' => $latestComment->id]);
        $this->assertDatabaseHas('task_comments', ['id' => $comment->id]);

        $this->actingAs($assignee)
            ->get(route('projects.tasks.show', [$project, $task]))
            ->assertOk()
            ->assertSeeText('Completed the response mapping and started endpoint tests.')
            ->assertDontSeeText('Updated the response mapping and completed endpoint tests.');
    }

    public function test_only_the_assigned_user_can_add_a_daily_task_update(): void
    {
        $assignedUser = User::factory()->create();
        $assignedUser->givePermissionTo('tasks.view');

        $otherUser = User::factory()->create();
        $otherUser->givePermissionTo(['tasks.view', 'tasks.assign']);

        $project = Project::create([
            'name' => 'Protected updates project',
            'description' => 'Task update authorization check',
            'start_date' => now()->subDay(),
            'end_date' => now()->addDays(5),
        ]);

        $task = $project->tasks()->create([
            'title' => 'Restricted task update',
            'description' => 'Only the assignee should post here.',
            'due_date' => now()->addDays(2),
            'priority' => 'low',
            'status' => 'pending',
            'assigned_to' => $assignedUser->id,
        ]);

        $this->actingAs($otherUser)
            ->post(route('projects.tasks.comments.store', [$project, $task]), [
                'body' => 'This update should not be accepted.',
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('task_comments', 0);

        $this->actingAs($assignedUser)
            ->post(route('projects.tasks.comments.store', [$project, $task]), [
                'body' => 'Completed the first implementation pass.',
            ])
            ->assertRedirect(route('projects.tasks.show', [$project, $task]));

        $comment = TaskComment::where('task_id', $task->id)->firstOrFail();

        $this->actingAs($otherUser)
            ->post(route('projects.tasks.comments.store', [$project, $task]), [
                'parent_id' => $comment->id,
                'body' => 'Thanks for the progress update. Please share the test results next.',
            ])
            ->assertRedirect(route('projects.tasks.show', [$project, $task]));

        $this->assertDatabaseHas('task_comments', [
            'task_id' => $task->id,
            'user_id' => $otherUser->id,
            'parent_id' => $comment->id,
            'body' => 'Thanks for the progress update. Please share the test results next.',
        ]);

        $this->actingAs($otherUser)
            ->get(route('projects.tasks.show', [$project, $task]))
            ->assertOk()
            ->assertSeeText('Reply')
            ->assertSeeText('Thanks for the progress update. Please share the test results next.');
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

    public function test_manager_can_comment_edit_and_delete_on_any_task(): void
    {
        $manager = User::factory()->create(['name' => 'Project Manager']);
        $manager->givePermissionTo(['projects.view', 'tasks.view', 'tasks.edit', 'tasks.assign']);

        $assignee = User::factory()->create(['name' => 'Worker User']);
        $assignee->givePermissionTo(['projects.view', 'tasks.view', 'tasks.update-status']);

        $project = Project::create([
            'name' => 'Manager Comment Project',
            'created_by' => $manager->id,
        ]);

        $task = $project->tasks()->create([
            'title' => 'Manager Supervised Task',
            'assigned_to' => $assignee->id,
            'status' => 'in_progress',
            'priority' => 'high',
        ]);

        // 1. Manager can add comment to a task not assigned to them
        $this->actingAs($manager)
            ->post(route('projects.tasks.comments.store', [$project, $task]), [
                'body' => 'Manager review note: please expedite testing.',
            ])
            ->assertRedirect(route('projects.tasks.show', [$project, $task]))
            ->assertSessionHas('success', 'Daily work update added successfully.');

        $comment = TaskComment::where('task_id', $task->id)->firstOrFail();
        $this->assertSame($manager->id, $comment->user_id);

        // 2. Manager can edit that comment
        $this->actingAs($manager)
            ->put(route('projects.tasks.comments.update', [$project, $task, $comment]), [
                'body' => 'Manager review note: testing expedited successfully.',
            ])
            ->assertRedirect(route('projects.tasks.show', [$project, $task]))
            ->assertSessionHas('success', 'Daily work update edited successfully.');

        $this->assertDatabaseHas('task_comments', [
            'id' => $comment->id,
            'body' => 'Manager review note: testing expedited successfully.',
        ]);

        // 3. Assignee adds their own comment
        $this->actingAs($assignee)
            ->post(route('projects.tasks.comments.store', [$project, $task]), [
                'body' => 'Assignee status report: all tests passed.',
            ])
            ->assertRedirect(route('projects.tasks.show', [$project, $task]));

        $assigneeComment = TaskComment::where('user_id', $assignee->id)->firstOrFail();

        // 4. Manager can delete assignee's comment
        $this->actingAs($manager)
            ->delete(route('projects.tasks.comments.destroy', [$project, $task, $assigneeComment]))
            ->assertRedirect(route('projects.tasks.show', [$project, $task]))
            ->assertSessionHas('success', 'Daily work update deleted successfully.');

        $this->assertDatabaseMissing('task_comments', ['id' => $assigneeComment->id]);
    }
}
