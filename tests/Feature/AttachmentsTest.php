<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\ProjectManagement\Database\Seeders\RolePermissionSeeder;
use Modules\ProjectManagement\Models\Attachment;
use Modules\ProjectManagement\Models\Project;
use Modules\ProjectManagement\Models\Task;
use Tests\TestCase;

class AttachmentsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        Storage::fake('public');
    }

    public function test_manager_can_upload_attachments_to_project(): void
    {
        $manager = User::factory()->create();
        $manager->assignRole('manager');

        $project = Project::create([
            'name' => 'Attachment Project',
            'description' => 'Test project for attachments',
        ]);

        $file = UploadedFile::fake()->create('specification.pdf', 500, 'application/pdf');

        $response = $this->actingAs($manager)
            ->post(route('projects.attachments.store', $project), [
                'attachments' => [$file],
            ]);

        $response->assertSessionHas('success');
        $this->assertDatabaseHas('attachments', [
            'attachable_type' => Project::class,
            'attachable_id' => $project->id,
            'file_name' => 'specification.pdf',
            'uploaded_by' => $manager->id,
        ]);

        $attachment = Attachment::where('file_name', 'specification.pdf')->first();
        Storage::disk('public')->assertExists($attachment->file_path);
    }

    public function test_assignee_can_upload_attachments_to_task(): void
    {
        $assignee = User::factory()->create();
        $assignee->assignRole('team_member');
        $assignee->givePermissionTo('attachments.create');

        $project = Project::create([
            'name' => 'Task Attachments Project',
        ]);

        $task = $project->tasks()->create([
            'title' => 'Implement API Docs',
            'priority' => 'high',
            'status' => 'pending',
            'assigned_to' => $assignee->id,
        ]);

        $file = UploadedFile::fake()->image('screenshot.png');

        $response = $this->actingAs($assignee)
            ->post(route('projects.tasks.attachments.store', [$project, $task]), [
                'attachments' => [$file],
            ]);

        $response->assertSessionHas('success');
        $this->assertDatabaseHas('attachments', [
            'attachable_type' => Task::class,
            'attachable_id' => $task->id,
            'file_name' => 'screenshot.png',
            'uploaded_by' => $assignee->id,
        ]);
        $attachment = Attachment::where('file_name', 'screenshot.png')->first();
        $this->assertTrue($attachment->is_image);
        Storage::disk('public')->assertExists($attachment->file_path);
    }

    public function test_user_without_attachments_create_permission_cannot_upload_attachment(): void
    {
        $assignee = User::factory()->create();
        $assignee->assignRole('team_member');
        // lacks 'attachments.create'

        $project = Project::create([
            'name' => 'No Create Permission Project',
        ]);

        $task = $project->tasks()->create([
            'title' => 'Task without create permission',
            'priority' => 'high',
            'status' => 'pending',
            'assigned_to' => $assignee->id,
        ]);

        $file = UploadedFile::fake()->image('forbidden.png');

        $this->actingAs($assignee)
            ->post(route('projects.tasks.attachments.store', [$project, $task]), [
                'attachments' => [$file],
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('attachments', [
            'file_name' => 'forbidden.png',
        ]);
    }

    public function test_user_can_download_project_attachment(): void
    {
        $manager = User::factory()->create();
        $manager->assignRole('manager');

        $project = Project::create(['name' => 'Downloadable Project']);

        $file = UploadedFile::fake()->create('contract.docx', 200);
        $path = $file->store("attachments/projects/{$project->id}", 'public');

        $attachment = $project->attachments()->create([
            'uploaded_by' => $manager->id,
            'file_name' => 'contract.docx',
            'file_path' => $path,
            'file_size' => 200,
            'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'disk' => 'public',
        ]);

        $response = $this->actingAs($manager)
            ->get(route('projects.attachments.download', $attachment));

        $response->assertOk();
        $response->assertDownload('contract.docx');
    }

    public function test_user_can_preview_image_attachment(): void
    {
        $manager = User::factory()->create();
        $manager->assignRole('manager');

        $project = Project::create(['name' => 'Preview Project']);

        $file = UploadedFile::fake()->image('wireframe.png');
        $path = $file->store("attachments/projects/{$project->id}", 'public');

        $attachment = $project->attachments()->create([
            'uploaded_by' => $manager->id,
            'file_name' => 'wireframe.png',
            'file_path' => $path,
            'file_size' => 150,
            'mime_type' => 'image/png',
            'disk' => 'public',
        ]);

        $response = $this->actingAs($manager)
            ->get(route('projects.attachments.preview', $attachment));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'image/png');
    }

    public function test_unauthorized_user_cannot_access_project_attachment(): void
    {
        $unrelatedUser = User::factory()->create();
        $unrelatedUser->assignRole('team_member');

        $project = Project::create(['name' => 'Secret Project']);

        $attachment = $project->attachments()->create([
            'uploaded_by' => null,
            'file_name' => 'secret.pdf',
            'file_path' => 'attachments/projects/secret.pdf',
            'file_size' => 100,
            'disk' => 'public',
        ]);

        $response = $this->actingAs($unrelatedUser)
            ->get(route('projects.attachments.download', $attachment));

        $response->assertForbidden();
    }

    public function test_uploader_and_manager_can_delete_attachment(): void
    {
        $assignee = User::factory()->create();
        $assignee->assignRole('team_member');
        $assignee->givePermissionTo('attachments.delete');

        $project = Project::create(['name' => 'Delete Test Project']);
        $task = $project->tasks()->create([
            'title' => 'Delete Attachment Task',
            'priority' => 'low',
            'status' => 'pending',
            'assigned_to' => $assignee->id,
        ]);

        $file = UploadedFile::fake()->create('temp_log.txt', 50);
        $path = $file->store("attachments/tasks/{$task->id}", 'public');

        $attachment = $task->attachments()->create([
            'uploaded_by' => $assignee->id,
            'file_name' => 'temp_log.txt',
            'file_path' => $path,
            'file_size' => 50,
            'disk' => 'public',
        ]);

        Storage::disk('public')->assertExists($path);

        $response = $this->actingAs($assignee)
            ->delete(route('projects.attachments.destroy', $attachment));

        $response->assertSessionHas('success');
        $this->assertDatabaseMissing('attachments', ['id' => $attachment->id]);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_user_without_attachments_delete_permission_cannot_delete_attachment(): void
    {
        $uploader = User::factory()->create();
        $uploader->assignRole('team_member');

        $project = Project::create(['name' => 'No Delete Permission Project']);
        $task = $project->tasks()->create([
            'title' => 'No Delete Permission Task',
            'priority' => 'low',
            'status' => 'pending',
            'assigned_to' => $uploader->id,
        ]);

        $file = UploadedFile::fake()->create('sample.txt', 50);
        $path = $file->store("attachments/tasks/{$task->id}", 'public');

        $attachment = $task->attachments()->create([
            'uploaded_by' => $uploader->id,
            'file_name' => 'sample.txt',
            'file_path' => $path,
            'file_size' => 50,
            'disk' => 'public',
        ]);

        // User uploaded the file, but lacks 'attachments.delete' permission
        $this->actingAs($uploader)
            ->delete(route('projects.attachments.destroy', $attachment))
            ->assertForbidden();

        $this->assertDatabaseHas('attachments', ['id' => $attachment->id]);
    }

    public function test_user_with_permission_can_only_delete_their_own_attachment(): void
    {
        $uploader = User::factory()->create();
        $anotherUser = User::factory()->create();
        $anotherUser->assignRole('team_member');
        $anotherUser->givePermissionTo('attachments.delete');

        $project = Project::create(['name' => 'Shared Project']);
        $task = $project->tasks()->create([
            'title' => 'Shared Task',
            'priority' => 'low',
            'status' => 'pending',
            'assigned_to' => $anotherUser->id,
        ]);

        $file = UploadedFile::fake()->create('other_user.txt', 50);
        $path = $file->store("attachments/tasks/{$task->id}", 'public');

        $attachment = $task->attachments()->create([
            'uploaded_by' => $uploader->id,
            'file_name' => 'other_user.txt',
            'file_path' => $path,
            'file_size' => 50,
            'disk' => 'public',
        ]);

        // anotherUser has 'attachments.delete', but was not the uploader and is not a manager
        $this->actingAs($anotherUser)
            ->delete(route('projects.attachments.destroy', $attachment))
            ->assertForbidden();

        $this->assertDatabaseHas('attachments', ['id' => $attachment->id]);
    }

    public function test_project_show_page_renders_attachments(): void
    {
        $manager = User::factory()->create();
        $manager->assignRole('manager');

        $project = Project::create(['name' => 'Dashboard Project']);

        $file = UploadedFile::fake()->create('architecture.pdf', 300, 'application/pdf');
        $path = $file->store("attachments/projects/{$project->id}", 'public');

        $project->attachments()->create([
            'uploaded_by' => $manager->id,
            'file_name' => 'architecture.pdf',
            'file_path' => $path,
            'file_size' => 300,
            'mime_type' => 'application/pdf',
            'disk' => 'public',
        ]);

        $response = $this->actingAs($manager)
            ->get(route('projects.show', $project));

        $response->assertOk();
        $response->assertSeeText('Project Attachments');
        $response->assertSeeText('architecture.pdf');
    }

    public function test_task_show_page_renders_attachments(): void
    {
        $manager = User::factory()->create();
        $manager->assignRole('manager');

        $project = Project::create(['name' => 'Task Project']);
        $task = $project->tasks()->create([
            'title' => 'Design Review Task',
            'priority' => 'medium',
            'status' => 'pending',
            'assigned_to' => $manager->id,
        ]);

        $file = UploadedFile::fake()->image('mockup.png');
        $path = $file->store("attachments/tasks/{$task->id}", 'public');

        $task->attachments()->create([
            'uploaded_by' => $manager->id,
            'file_name' => 'mockup.png',
            'file_path' => $path,
            'file_size' => 120,
            'mime_type' => 'image/png',
            'disk' => 'public',
        ]);

        $response = $this->actingAs($manager)
            ->get(route('projects.tasks.show', [$project, $task]));

        $response->assertOk();
        $response->assertSeeText('Task Attachments');
        $response->assertSeeText('mockup.png');
    }

    public function test_project_creation_with_attachments(): void
    {
        $manager = User::factory()->create();
        $manager->assignRole('manager');

        $file = UploadedFile::fake()->create('roadmap.pdf', 100, 'application/pdf');

        $response = $this->actingAs($manager)
            ->post(route('projects.store'), [
                'name' => 'Brand New Project',
                'description' => 'Project with initial file',
                'attachments' => [$file],
            ]);

        $response->assertRedirect(route('projects.index'));

        $project = Project::where('name', 'Brand New Project')->firstOrFail();
        $this->assertCount(1, $project->attachments);
        $this->assertSame('roadmap.pdf', $project->attachments->first()->file_name);
    }

    public function test_task_creation_with_attachments(): void
    {
        $manager = User::factory()->create();
        $manager->assignRole('manager');

        $project = Project::create(['name' => 'Sprint 1']);
        $file = UploadedFile::fake()->create('checklist.xlsx', 80);

        $response = $this->actingAs($manager)
            ->post(route('projects.tasks.store', $project), [
                'title' => 'Verify Deployment Checklist',
                'priority' => 'high',
                'status' => 'pending',
                'attachments' => [$file],
            ]);

        $response->assertRedirect(route('projects.index'));

        $task = Task::where('title', 'Verify Deployment Checklist')->firstOrFail();
        $this->assertCount(1, $task->attachments);
        $this->assertSame('checklist.xlsx', $task->attachments->first()->file_name);
    }
}
