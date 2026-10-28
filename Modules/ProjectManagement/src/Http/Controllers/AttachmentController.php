<?php

namespace Modules\ProjectManagement\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Modules\ProjectManagement\Models\Attachment;
use Modules\ProjectManagement\Models\Project;
use Modules\ProjectManagement\Models\Task;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttachmentController extends Controller
{
    /**
     * Store attachments for a Project.
     */
    public function storeProject(Request $request, Project $project): RedirectResponse
    {
        $user = $request->user();
        $this->ensureProjectVisible($user, $project);

        abort_unless(
            $user->can('attachments.create') && ($user->can('projects.edit') || $user->can('projects.create')),
            403,
            'You do not have permission to attach files to this project.'
        );

        $files = $this->extractFilesFromRequest($request);
        if (empty($files)) {
            return back()->withErrors(['attachments' => 'Please select at least one valid file to upload.']);
        }

        $count = $this->saveAttachments($project, $files, $user->id, "attachments/projects/{$project->id}");
        return back()->with('success', $count === 1 ? 'Attachment uploaded successfully.' : "{$count} attachments uploaded successfully.");
    }

    /**
     * Store attachments for a Task.
     */
    public function storeTask(Request $request, Project $project, Task $task): RedirectResponse
    {
        $user = $request->user();
        abort_unless((int) $task->project_id === (int) $project->id, 404);
        $this->ensureTaskVisible($user, $task);

        $isAuthorized = $user->can('tasks.edit') || ((int) $task->assigned_to === (int) $user->id && $user->can('tasks.update-status'));
        abort_unless($user->can('attachments.create') && $isAuthorized, 403, 'You do not have permission to attach files to this task.');

        $files = $this->extractFilesFromRequest($request);
        if (empty($files)) {
            return back()->withErrors(['attachments' => 'Please select at least one valid file to upload.']);
        }

        $count = $this->saveAttachments($task, $files, $user->id, "attachments/tasks/{$task->id}");
        return back()->with('success', $count === 1 ? 'Attachment uploaded successfully.' : "{$count} attachments uploaded successfully.");
    }

    /**
     * Download the attachment file.
     */
    public function download(Request $request, Attachment $attachment): StreamedResponse
    {
        $this->ensureAttachableVisible($request->user(), $attachment);
        $disk = Storage::disk($attachment->disk ?: 'public');
        abort_unless($disk->exists($attachment->file_path), 404, 'Attachment file not found on disk.');

        return $disk->download($attachment->file_path, $attachment->file_name);
    }

    /**
     * Preview the attachment inline.
     */
    public function preview(Request $request, Attachment $attachment): BinaryFileResponse
    {
        $this->ensureAttachableVisible($request->user(), $attachment);
        $disk = Storage::disk($attachment->disk ?: 'public');
        abort_unless($disk->exists($attachment->file_path), 404, 'Attachment file not found on disk.');

        return response()->file($disk->path($attachment->file_path), [
            'Content-Type'        => $attachment->mime_type ?: 'application/octet-stream',
            'Content-Disposition' => 'inline; filename="' . addslashes($attachment->file_name) . '"',
        ]);
    }

    /**
     * Delete an attachment.
     */
    public function destroy(Request $request, Attachment $attachment): RedirectResponse
    {
        $user = $request->user();
        $this->ensureAttachableVisible($user, $attachment);

        abort_unless($user->can('attachments.delete'), 403, 'You do not have permission to delete attachments.');

        $isUploader = (int) $attachment->uploaded_by === (int) $user->id;
        $attachable = $attachment->attachable;

        $hasManagerPermission = match (true) {
            $attachable instanceof Project => $user->can('projects.delete') || $user->can('projects.edit'),
            $attachable instanceof Task    => $user->can('tasks.delete') || $user->can('tasks.edit'),
            default                        => false,
        };

        abort_unless($isUploader || $hasManagerPermission, 403, 'You can only delete attachments that you uploaded.');

        $fileName = $attachment->file_name;
        $attachment->delete();

        return back()->with('success', 'Attachment "' . $fileName . '" deleted successfully.');
    }

    private function ensureAttachableVisible(User $user, Attachment $attachment): void
    {
        match (true) {
            $attachment->attachable instanceof Project => $this->ensureProjectVisible($user, $attachment->attachable),
            $attachment->attachable instanceof Task    => $this->ensureTaskVisible($user, $attachment->attachable),
            default                                    => abort(404),
        };
    }

    private function ensureProjectVisible(User $user, Project $project): void
    {
        abort_unless(
            Project::visibleTo($user)->whereKey($project->getKey())->exists(),
            403,
            'You can only access projects with tasks assigned to you.'
        );
    }

    private function ensureTaskVisible(User $user, Task $task): void
    {
        abort_unless(
            Task::visibleTo($user)->whereKey($task->getKey())->exists(),
            403,
            'You do not have access to this task.'
        );
    }

    private function extractFilesFromRequest(Request $request): array
    {
        $request->validate([
            'attachments'   => ['nullable'],
            'attachments.*' => ['file', 'max:25600'],
            'file'          => ['nullable', 'file', 'max:25600'],
        ]);

        $files = $request->hasFile('attachments')
            ? (is_array($request->file('attachments')) ? $request->file('attachments') : [$request->file('attachments')])
            : ($request->hasFile('file') ? [$request->file('file')] : []);

        return array_filter($files);
    }

    private function saveAttachments(Project|Task $attachable, array $files, int $userId, string $directory): int
    {
        $count = 0;
        foreach ($files as $file) {
            if (! $file || ! $file->isValid()) {
                continue;
            }

            $path = $file->store($directory, 'public');
            $attachable->attachments()->create([
                'uploaded_by' => $userId,
                'file_name'   => $file->getClientOriginalName(),
                'file_path'   => $path,
                'file_size'   => $file->getSize() ?: 0,
                'mime_type'   => $file->getClientMimeType() ?: $file->getMimeType(),
                'disk'        => 'public',
            ]);
            $count++;
        }
        return $count;
    }
}
