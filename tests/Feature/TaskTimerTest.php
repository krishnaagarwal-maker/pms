<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Modules\ProjectManagement\Database\Seeders\RolePermissionSeeder;
use Modules\ProjectManagement\Models\Project;
use Modules\ProjectManagement\Models\Task;
use Modules\ProjectManagement\Models\TaskTimer;
use Tests\TestCase;

class TaskTimerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_assignee_can_start_timer_on_task(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(['tasks.view', 'tasks.update-status']);

        $project = Project::create([
            'name' => 'Alpha Project',
            'status' => 'in_progress',
            'created_by' => $user->id,
        ]);

        $task = Task::create([
            'project_id' => $project->id,
            'title' => 'Timer Task',
            'assigned_to' => $user->id,
            'status' => 'in_progress',
            'priority' => 'medium',
        ]);

        $response = $this->actingAs($user)
            ->postJson(route('projects.tasks.timer.start', [$project, $task]));

        $response->assertOk()
            ->assertJsonPath('state.running', true)
            ->assertJsonPath('state.started_by', $user->name);

        $this->assertDatabaseHas('task_timers', [
            'task_id'    => $task->id,
            'started_by' => $user->id,
            'stopped_at' => null,
        ]);
    }

    public function test_cannot_start_timer_if_already_running(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(['tasks.view', 'tasks.update-status']);

        $project = Project::create([
            'name' => 'Beta Project',
            'status' => 'in_progress',
            'created_by' => $user->id,
        ]);

        $task = Task::create([
            'project_id' => $project->id,
            'title' => 'Running Task',
            'assigned_to' => $user->id,
            'status' => 'in_progress',
            'priority' => 'high',
        ]);

        // Start timer first time
        $this->actingAs($user)->postJson(route('projects.tasks.timer.start', [$project, $task]))->assertOk();

        // Start again while running
        $response = $this->actingAs($user)
            ->postJson(route('projects.tasks.timer.start', [$project, $task]));

        $response->assertStatus(409)
            ->assertJsonPath('message', 'A timer session is already active.');
    }

    public function test_assignee_can_stop_timer_and_duration_is_calculated(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-27 10:00:00'));

        $user = User::factory()->create();
        $user->givePermissionTo(['tasks.view', 'tasks.update-status']);

        $project = Project::create([
            'name' => 'Gamma Project',
            'status' => 'in_progress',
            'created_by' => $user->id,
        ]);

        $task = Task::create([
            'project_id' => $project->id,
            'title' => 'Tracking Task',
            'assigned_to' => $user->id,
            'status' => 'in_progress',
            'priority' => 'low',
        ]);

        $this->actingAs($user)->postJson(route('projects.tasks.timer.start', [$project, $task]))->assertOk();

        // Travel 45 minutes (2700 seconds)
        Carbon::setTestNow(Carbon::parse('2026-10-27 10:45:00'));

        $response = $this->actingAs($user)
            ->postJson(route('projects.tasks.timer.stop', [$project, $task]));

        $response->assertOk()
            ->assertJsonPath('state.running', false)
            ->assertJsonPath('state.total_seconds', 2700);

        $this->assertDatabaseHas('task_timers', [
            'task_id'          => $task->id,
            'started_by'       => $user->id,
            'stopped_by'       => $user->id,
            'duration_seconds' => 2700,
        ]);
    }

    public function test_cannot_stop_timer_if_not_running(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(['tasks.view', 'tasks.update-status']);

        $project = Project::create([
            'name' => 'Delta Project',
            'status' => 'in_progress',
            'created_by' => $user->id,
        ]);

        $task = Task::create([
            'project_id' => $project->id,
            'title' => 'Stopped Task',
            'assigned_to' => $user->id,
            'status' => 'in_progress',
            'priority' => 'medium',
        ]);

        $response = $this->actingAs($user)
            ->postJson(route('projects.tasks.timer.stop', [$project, $task]));

        $response->assertStatus(409)
            ->assertJsonPath('message', 'The timer is not running.');
    }

    public function test_user_can_delete_their_timesheet_entry(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(['tasks.view', 'tasks.update-status']);

        $project = Project::create([
            'name' => 'Delete Test Project',
            'status' => 'in_progress',
            'created_by' => $user->id,
        ]);

        $task = Task::create([
            'project_id' => $project->id,
            'title' => 'Delete Task',
            'assigned_to' => $user->id,
            'status' => 'in_progress',
            'priority' => 'medium',
        ]);

        $timer = TaskTimer::create([
            'task_id'          => $task->id,
            'started_by'       => $user->id,
            'stopped_by'       => $user->id,
            'started_at'       => now()->subHour(),
            'stopped_at'       => now(),
            'duration_seconds' => 3600,
        ]);

        $response = $this->actingAs($user)
            ->deleteJson(route('projects.tasks.timer.destroy', [$project, $task, $timer]));

        $response->assertOk()
            ->assertJsonPath('state.total_seconds', 0);

        $this->assertDatabaseMissing('task_timers', [
            'id' => $timer->id,
        ]);
    }

    public function test_accumulated_total_time_and_work_distribution_across_multiple_users(): void
    {
        $user1 = User::factory()->create(['name' => 'Alice']);
        $user2 = User::factory()->create(['name' => 'Bob']);
        $user1->givePermissionTo(['tasks.view', 'tasks.edit']);

        $project = Project::create([
            'name' => 'Epsilon Project',
            'status' => 'in_progress',
            'created_by' => $user1->id,
        ]);

        $task = Task::create([
            'project_id' => $project->id,
            'title' => 'Multi Session Task',
            'assigned_to' => $user1->id,
            'status' => 'in_progress',
            'priority' => 'medium',
        ]);

        // Alice: 2 hours (7200s)
        TaskTimer::create([
            'task_id'          => $task->id,
            'started_by'       => $user1->id,
            'stopped_by'       => $user1->id,
            'started_at'       => now()->subHours(4),
            'stopped_at'       => now()->subHours(2),
            'duration_seconds' => 7200,
        ]);

        // Bob: 1 hour (3600s)
        TaskTimer::create([
            'task_id'          => $task->id,
            'started_by'       => $user2->id,
            'stopped_by'       => $user2->id,
            'started_at'       => now()->subHour(),
            'stopped_at'       => now(),
            'duration_seconds' => 3600,
        ]);

        $state = $task->fresh()->timerState();

        $this->assertSame(10800, $state['total_seconds']);
        $this->assertSame('3h', $state['total_formatted']);
        $this->assertCount(2, $state['entries']);
        $this->assertCount(2, $state['by_user']);

        // First user in distribution should be Alice with 67%
        $this->assertSame('Alice', $state['by_user'][0]['name']);
        $this->assertSame('2h', $state['by_user'][0]['formatted']);
        $this->assertSame(67, $state['by_user'][0]['percentage']);
    }

    public function test_unauthorized_user_cannot_control_timer(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $stranger->givePermissionTo(['tasks.view']); // no edit, no update-status

        $project = Project::create([
            'name' => 'Private Project',
            'status' => 'in_progress',
            'created_by' => $owner->id,
        ]);

        $task = Task::create([
            'project_id' => $project->id,
            'title' => 'Secret Task',
            'assigned_to' => $owner->id,
            'status' => 'in_progress',
            'priority' => 'high',
        ]);

        $this->actingAs($stranger)
            ->postJson(route('projects.tasks.timer.start', [$project, $task]))
            ->assertForbidden();

        $this->actingAs($stranger)
            ->postJson(route('projects.tasks.timer.stop', [$project, $task]))
            ->assertForbidden();
    }

    public function test_task_show_view_renders_timesheet_card(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(['projects.view', 'tasks.view', 'tasks.assign', 'tasks.update-status']);

        $project = Project::create([
            'name' => 'UI Project',
            'status' => 'in_progress',
            'created_by' => $user->id,
        ]);

        $task = Task::create([
            'project_id' => $project->id,
            'title' => 'UI Task',
            'assigned_to' => $user->id,
            'status' => 'in_progress',
            'priority' => 'medium',
        ]);

        $response = $this->actingAs($user)
            ->get(route('projects.tasks.show', [$project, $task]));

        $response->assertOk()
            ->assertSeeText('Time Tracker & Timesheet')
            ->assertSeeText('Total Time Spent')
            ->assertSeeText('Timesheet Sessions')
            ->assertSeeText('Start Timer')
            ->assertSeeText('History')
            ->assertSeeText('Filter By Date');
    }

    public function test_timer_state_includes_date_keys_for_timesheet_history(): void
    {
        $user = User::factory()->create(['name' => 'Timesheet User']);
        $user->givePermissionTo(['tasks.view', 'tasks.update-status']);

        $project = Project::create([
            'name' => 'History Project',
            'status' => 'in_progress',
            'created_by' => $user->id,
        ]);

        $task = Task::create([
            'project_id' => $project->id,
            'title' => 'History Task',
            'assigned_to' => $user->id,
            'status' => 'in_progress',
            'priority' => 'high',
        ]);

        TaskTimer::create([
            'task_id'          => $task->id,
            'started_by'       => $user->id,
            'stopped_by'       => $user->id,
            'started_at'       => now()->subHours(2),
            'stopped_at'       => now()->subHour(),
            'duration_seconds' => 3600,
        ]);

        $state = $task->timerState();
        $this->assertNotEmpty($state['entries']);
        $entry = $state['entries'][0];

        $this->assertArrayHasKey('date_key', $entry);
        $this->assertArrayHasKey('date_label', $entry);
        $this->assertArrayHasKey('day_number', $entry);
        $this->assertArrayHasKey('day_name', $entry);
        $this->assertArrayHasKey('month_name', $entry);
        $this->assertEquals(3600, $entry['duration_seconds']);
        $this->assertEquals('1h', $entry['duration_formatted']);
    }

    public function test_task_show_view_renders_calendar_button_and_removes_obsolete_sections(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(['projects.view', 'tasks.view', 'tasks.assign', 'tasks.update-status']);

        $project = Project::create([
            'name' => 'Calendar UI Project',
            'status' => 'in_progress',
            'created_by' => $user->id,
        ]);

        $task = Task::create([
            'project_id' => $project->id,
            'title' => 'Calendar Task',
            'assigned_to' => $user->id,
            'status' => 'in_progress',
            'priority' => 'medium',
        ]);

        $response = $this->actingAs($user)
            ->get(route('projects.tasks.show', [$project, $task]));

        $response->assertOk()
            ->assertSeeText('Time tracking')
            ->assertSeeText('Start when you begin work, stop when you pause.')
            ->assertSeeText('Calendar')
            ->assertSeeText('Today')
            ->assertSeeText('Productive')
            ->assertSeeText('Idle')
            ->assertDontSeeText('Time by Team Member')
            ->assertDontSeeText('Open Date-wise History');
    }
}

