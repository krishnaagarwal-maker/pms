<?php

namespace Modules\ProjectManagement\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\SystemNotification;
use App\Notifications\TaskAssignedNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\ProjectManagement\Models\Project;
use Modules\ProjectManagement\Models\Task;

class TaskController extends Controller
{
    /**
     * Display a listing of tasks with filters and status counts.
     */
    public function index(Request $request): View
    {
        $allowedStatuses = ['pending', 'in_progress', 'completed'];

        $filters = $request->validate([
            'q'        => ['nullable', 'string', 'max:255'],
            'status'   => ['nullable', 'in:pending,in_progress,completed,overdue'],
            'priority' => ['nullable', 'in:low,medium,high'],
            'sort'     => ['nullable', 'in:due_asc,due_desc,priority,updated'],
            'page'     => ['nullable', 'integer', 'min:1'],
        ]);

        $query = Task::visibleTo($request->user())->with('project');

        if (! empty($filters['q'])) {
            $query->where('title', 'like', '%' . $filters['q'] . '%');
        }

        if (! empty($filters['priority'])) {
            $query->where('priority', $filters['priority']);
        }

        if (($filters['status'] ?? null) === 'overdue') {
            $query->whereNot('status', 'completed')->whereNotNull('due_date')->whereDate('due_date', '<', today());
        } elseif (in_array($filters['status'] ?? null, $allowedStatuses, true)) {
            $query->where('status', $filters['status']);
        }

        match ($filters['sort'] ?? 'due_asc') {
            'due_desc' => $query->orderByRaw('due_date IS NULL')->orderByDesc('due_date'),
            'priority' => $query->orderByRaw("CASE priority WHEN 'high' THEN 1 WHEN 'medium' THEN 2 WHEN 'low' THEN 3 ELSE 4 END"),
            'updated'  => $query->latest('updated_at'),
            default    => $query->orderByRaw('due_date IS NULL')->orderBy('due_date'),
        };

        $tasks = $query->paginate(15)->withQueryString();
        $countQuery = Task::visibleTo($request->user());

        $statusCounts = [
            'all'         => (clone $countQuery)->count(),
            'pending'     => (clone $countQuery)->where('status', 'pending')->count(),
            'in_progress' => (clone $countQuery)->where('status', 'in_progress')->count(),
            'completed'   => (clone $countQuery)->where('status', 'completed')->count(),
            'overdue'     => (clone $countQuery)->whereNot('status', 'completed')->whereNotNull('due_date')->whereDate('due_date', '<', today())->count(),
        ];

        return view('ProjectManagement::tasks.index', compact('tasks', 'statusCounts'));
    }

    /**
     * Show task creation form.
     */
    public function create(Project $project): View
    {
        $users = User::orderBy('name')->get();
        return view('ProjectManagement::tasks.create', compact('project', 'users'));
    }

    /**
     * Store a newly created task.
     */
    public function store(Request $request, Project $project): RedirectResponse
    {
        $validated = $request->validate([
            'title'         => ['required', 'string', 'max:255'],
            'description'   => ['nullable', 'string'],
            'due_date'      => ['nullable', 'date'],
            'priority'      => ['required', 'in:low,medium,high'],
            'status'        => ['required', 'in:pending,in_progress,completed'],
            'assigned_to'   => ['nullable', 'integer', 'exists:users,id'],
            'attachments'   => ['nullable'],
            'attachments.*' => ['file', 'max:25600'],
        ]);

        $task = $project->tasks()->create([
            'title'       => $validated['title'],
            'description' => $validated['description'] ?? null,
            'due_date'    => $validated['due_date'] ?? null,
            'priority'    => $validated['priority'],
            'status'      => $validated['status'],
            'assigned_to' => $validated['assigned_to'] ?? null,
            'created_by'  => $request->user()->id,
        ]);

        $this->storeTaskAttachments($request, $task);
        $this->notifyTaskRecipients($task, 'Task created: ' . $task->title, route('projects.tasks.show', [$project, $task]), 'task_created');
        $this->sendAssignmentNotification($task, $request->user());

        return redirect()->route('projects.index')->with('success', 'Task created successfully.');
    }

    /**
     * Display current user's assigned tasks.
     */
    public function myTasks(): View
    {
        $tasks = Task::query()->where('assigned_to', auth()->id())->with('project')->latest()->paginate(10);
        return view('ProjectManagement::tasks.my-tasks', compact('tasks'));
    }

    /**
     * Display a specific task.
     */
    public function show(Project $project, Task $task): View
    {
        $task = $project->tasks()->visibleTo(request()->user())
            ->with(['assignee', 'attachments.uploader', 'comments.user', 'comments.parent.user'])
            ->findOrFail($task->id);

        return view('ProjectManagement::tasks.show', compact('project', 'task'));
    }

    /**
     * Show task edit form.
     */
    public function edit(Project $project, Task $task): View
    {
        $task = $project->tasks()->visibleTo(request()->user())->with(['attachments.uploader'])->findOrFail($task->id);
        $users = User::orderBy('name')->get();

        return view('ProjectManagement::tasks.edit', compact('project', 'task', 'users'));
    }

    /**
     * Update a task.
     */
    public function update(Request $request, Project $project, Task $task): RedirectResponse
    {
        $task = $project->tasks()->visibleTo($request->user())->findOrFail($task->id);
        $user = $request->user();

        if (! $user->can('tasks.edit')) {
            abort_unless($user->can('tasks.update-status'), 403, 'You do not have permission to update this task.');
            abort_unless((int) $task->assigned_to === (int) $user->id, 403, 'You can only update tasks assigned to you.');

            $validated = $request->validate(['status' => ['required', 'in:pending,in_progress,completed']]);
            $task->update(['status' => $validated['status']]);

            $this->notifyTaskRecipients($task, 'Task status updated: ' . $task->title, route('projects.tasks.show', [$project, $task]), 'task_status_updated');

            return redirect()->route('projects.tasks.show', [$project, $task])->with('success', 'Task status updated successfully.');
        }

        $validated = $request->validate([
            'title'         => ['required', 'string', 'max:255'],
            'description'   => ['nullable', 'string'],
            'due_date'      => ['nullable', 'date'],
            'priority'      => ['required', 'in:low,medium,high'],
            'status'        => ['required', 'in:pending,in_progress,completed'],
            'assigned_to'   => ['nullable', 'integer', 'exists:users,id'],
            'attachments'   => ['nullable'],
            'attachments.*' => ['file', 'max:25600'],
        ]);

        $previousAssigneeId = $task->assigned_to;
        $task->update([
            'title'       => $validated['title'],
            'description' => $validated['description'] ?? null,
            'due_date'    => $validated['due_date'] ?? null,
            'priority'    => $validated['priority'],
            'status'      => $validated['status'],
            'assigned_to' => $validated['assigned_to'] ?? null,
        ]);

        $this->storeTaskAttachments($request, $task);
        $this->notifyTaskRecipients($task, 'Task updated: ' . $task->title, route('projects.tasks.show', [$project, $task]), 'task_updated');

        if ((int) ($task->assigned_to ?? 0) > 0 && (int) $previousAssigneeId !== (int) $task->assigned_to) {
            $this->sendAssignmentNotification($task, $user);
        }

        return redirect()->route('projects.tasks.show', [$project, $task])->with('success', 'Task updated successfully.');
    }

    /**
     * Delete a task.
     */
    public function destroy(Project $project, Task $task): RedirectResponse
    {
        $task = $project->tasks()->visibleTo(request()->user())->findOrFail($task->id);
        $projectId = $task->project_id;
        $taskTitle = $task->title;
        $task->delete();

        $this->notifyTaskRecipients($task, 'Task deleted: ' . $taskTitle, route('projects.show', $projectId), 'task_deleted');

        return redirect()->route('projects.show', $projectId)->with('success', 'Task deleted successfully.');
    }

    /**
     * Notify the task assignee and assignment-capable users.
     */
    private function notifyTaskRecipients(Task $task, string $message, ?string $link = null, string $type = 'system'): void
    {
        User::query()->get()
            ->filter(fn (User $user) => (int) $user->id === (int) $task->assigned_to || $user->can('tasks.assign'))
            ->unique('id')
            ->each(fn (User $user) => $user->notify(new SystemNotification('System update', $message, $link, $type)));
    }

    /**
     * Send email notification on task assignment.
     */
    private function sendAssignmentNotification(Task $task, User $assignedBy): void
    {
        if (! $task->assigned_to) {
            return;
        }

        $assignee = User::find($task->assigned_to);
        if ($assignee?->email) {
            $assignee->notify(new TaskAssignedNotification($task, $assignedBy));
        }
    }

    /**
     * Store uploaded attachments for a task.
     */
    private function storeTaskAttachments(Request $request, Task $task): void
    {
        if (! $request->hasFile('attachments')) {
            return;
        }

        $files = is_array($request->file('attachments')) ? $request->file('attachments') : [$request->file('attachments')];

        foreach ($files as $file) {
            if (! $file || ! $file->isValid()) {
                continue;
            }

            $path = $file->store("attachments/tasks/{$task->id}", 'public');

            $task->attachments()->create([
                'uploaded_by' => $request->user()->id,
                'file_name'   => $file->getClientOriginalName(),
                'file_path'   => $path,
                'file_size'   => $file->getSize() ?: 0,
                'mime_type'   => $file->getMimeType(),
                'disk'        => 'public',
            ]);
        }
    }
}
