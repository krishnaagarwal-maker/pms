<?php

namespace Modules\ProjectManagement\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\ProjectManagement\Models\Project;
use Modules\ProjectManagement\Models\Task;
use Modules\ProjectManagement\Models\TaskTimer;

class TaskTimerController extends Controller
{
    /**
     * Start a timer session for a task.
     */
    public function start(Request $request, Project $project, Task $task): JsonResponse
    {
        $this->authorizeTimer($request, $project, $task);

        if ($task->due_date && $task->due_date->startOfDay()->lt(today())) {
            return response()->json([
                'message' => 'The deadline for this task has passed. Timer cannot be started.',
                'state'   => $task->fresh()->timerState(),
            ], 403);
        }

        if (in_array($task->status, ['completed', 'done'], true)) {
            return response()->json([
                'message' => 'This task is completed. Timer cannot be started.',
                'state'   => $task->fresh()->timerState(),
            ], 403);
        }

        if ($project->end_date && $project->end_date->startOfDay()->lt(today())) {
            return response()->json([
                'message' => 'The project deadline has passed. Timer cannot be started.',
                'state'   => $task->fresh()->timerState(),
            ], 403);
        }

        $alreadyRunning = DB::transaction(function () use ($request, $task) {
            $lockedTask = Task::whereKey($task->id)->lockForUpdate()->firstOrFail();

            if ($lockedTask->timeEntries()->whereNull('stopped_at')->exists()) {
                return true;
            }

            $lockedTask->timeEntries()->create([
                'started_by' => $request->user()->id,
                'started_at' => now(),
            ]);

            return false;
        });

        if ($alreadyRunning) {
            return response()->json([
                'message' => 'A timer session is already active.',
                'state'   => $task->fresh()->timerState(),
            ], 409);
        }

        return response()->json([
            'message' => 'Timer started.',
            'state'   => $task->fresh()->timerState(),
        ]);
    }

    /**
     * Stop the active timer session.
     */
    public function stop(Request $request, Project $project, Task $task): JsonResponse
    {
        $this->authorizeTimer($request, $project, $task);

        $result = DB::transaction(function () use ($request, $task) {
            $lockedTask = Task::whereKey($task->id)->lockForUpdate()->firstOrFail();
            $query = $lockedTask->timeEntries()->whereNull('stopped_at');

            if (! $request->user()->can('tasks.edit')) {
                $query->where('started_by', $request->user()->id);
            }

            $entry = $query->first();

            if (! $entry) {
                return $lockedTask->timeEntries()->whereNull('stopped_at')->exists() ? 'not_owner' : 'not_running';
            }

            $stoppedAt = now();
            $entry->update([
                'stopped_at'       => $stoppedAt,
                'stopped_by'       => $request->user()->id,
                'duration_seconds' => max(0, $stoppedAt->getTimestamp() - $entry->started_at->getTimestamp()),
            ]);

            return 'stopped';
        });

        if ($result === 'not_owner') {
            return response()->json([
                'message' => 'You can only stop a timer session you started.',
                'state'   => $task->fresh()->timerState(),
            ], 403);
        }

        if ($result === 'not_running') {
            return response()->json([
                'message' => 'The timer is not running.',
                'state'   => $task->fresh()->timerState(),
            ], 409);
        }

        return response()->json([
            'message' => 'Session recorded in timesheet.',
            'state'   => $task->fresh()->timerState(),
        ]);
    }

    /**
     * Delete a timer session.
     */
    public function destroy(Request $request, Project $project, Task $task, TaskTimer $timer): JsonResponse
    {
        abort_unless((int) $task->project_id === (int) $project->id, 404);
        abort_unless((int) $timer->task_id === (int) $task->id, 404);

        $user = $request->user();
        $isAuthorized = $user->can('tasks.edit') || (int) $timer->started_by === (int) $user->id;
        abort_unless($isAuthorized, 403, 'You are not allowed to delete this timer session.');

        if ($timer->stopped_at === null) {
            return response()->json([
                'message' => 'Stop the timer before deleting its session.',
                'state'   => $task->fresh()->timerState(),
            ], 409);
        }

        $timer->delete();

        return response()->json([
            'message' => 'Session entry deleted.',
            'state'   => $task->fresh()->timerState(),
        ]);
    }

    /**
     * Verify that the task belongs to the project and user has permission.
     */
    private function authorizeTimer(Request $request, Project $project, Task $task): void
    {
        abort_unless((int) $task->project_id === (int) $project->id, 404);

        $user = $request->user();
        abort_unless(
            $user && ($user->can('tasks.edit') || ((int) $task->assigned_to === (int) $user->id && $user->can('tasks.update-status'))),
            403,
            'You are not authorized to track time on this task.'
        );
    }
}
