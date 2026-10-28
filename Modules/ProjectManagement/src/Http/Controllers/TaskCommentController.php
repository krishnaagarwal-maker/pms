<?php

namespace Modules\ProjectManagement\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Modules\ProjectManagement\Models\Project;
use Modules\ProjectManagement\Models\Task;
use Modules\ProjectManagement\Models\TaskComment;

class TaskCommentController extends Controller
{
    /**
     * List comments for a task.
     */
    public function index(Project $project, Task $task)
    {
        $task = $project->tasks()
            ->visibleTo(request()->user())
            ->findOrFail($task->id);

        $comments = $task->comments()->with('user')->get();

        return response()->json(['comments' => $comments]);
    }

    /**
     * Store a new comment for a task.
     */
    public function store(Request $request, Project $project, Task $task)
    {
        $user = $request->user();
        $task = $project->tasks()
            ->visibleTo($user)
            ->findOrFail($task->id);

        $validated = $request->validate([
            'body' => ['required', 'string', 'min:3', 'max:2000'],
            'parent_id' => ['nullable', 'integer', 'exists:task_comments,id'],
        ]);

        $parent = null;
        $isManager = $user->can('tasks.edit');
        $isAssignee = (int) $task->assigned_to === (int) $user->id;

        if (! empty($validated['parent_id'])) {
            $parent = $task->comments()
                ->whereKey($validated['parent_id'])
                ->firstOrFail();

            abort_unless(
                $isManager || $user->can('tasks.assign') || $isAssignee,
                403,
                'Only a manager or the assigned user can reply to daily updates.'
            );
        } else {
            abort_unless(
                $isManager || $isAssignee,
                403,
                'Only a manager or the user assigned to this task can add a daily update.'
            );
        }

        $comment = TaskComment::create([
            'task_id' => $task->id,
            'user_id' => $user->id,
            'parent_id' => $parent?->id,
            'body' => $validated['body'],
        ]);

        activity()
            ->causedBy($user)
            ->performedOn($task)
            ->log($parent ? 'Replied to comment' : 'Added comment');

        return redirect()
            ->route('projects.tasks.show', [$project, $task])
            ->with('success', 'Daily work update added successfully.');
    }

    /**
     * Update a comment.
     */
    public function update(Request $request, Project $project, Task $task, TaskComment $comment)
    {
        $user = $request->user();
        [$task, $comment] = $this->resolveCommentForModification($user, $project, $task, $comment);

        $validated = $request->validate([
            'body' => ['required', 'string', 'min:3', 'max:2000'],
        ]);

        $comment->update(['body' => $validated['body']]);

        activity()
            ->causedBy($user)
            ->performedOn($task)
            ->log('Edited comment');

        return redirect()
            ->route('projects.tasks.show', [$project, $task])
            ->with('success', 'Daily work update edited successfully.');
    }

    /**
     * Delete a comment.
     */
    public function destroy(Request $request, Project $project, Task $task, TaskComment $comment)
    {
        $user = $request->user();
        [$task, $comment] = $this->resolveCommentForModification($user, $project, $task, $comment);

        $comment->delete();

        activity()
            ->causedBy($user)
            ->performedOn($task)
            ->log('Deleted comment');

        return redirect()
            ->route('projects.tasks.show', [$project, $task])
            ->with('success', 'Daily work update deleted successfully.');
    }

    /**
     * Manager can modify any comment. Regular user can modify their own latest update.
     *
     * @return array{0: Task, 1: TaskComment}
     */
    private function resolveCommentForModification(User $user, Project $project, Task $task, TaskComment $comment): array
    {
        $task = $project->tasks()
            ->visibleTo($user)
            ->findOrFail($task->id);

        $comment = $task->comments()->whereKey($comment->id)->firstOrFail();

        $isManager = $user->can('tasks.edit');
        if ($isManager) {
            return [$task, $comment];
        }

        $latestComment = $task->comments()->first();

        abort_unless(
            $latestComment
                && (int) $latestComment->id === (int) $comment->id
                && (int) $comment->user_id === (int) $user->id,
            403,
            'Only a manager or the author of the latest daily update can edit or delete it.'
        );

        return [$task, $comment];
    }
}
