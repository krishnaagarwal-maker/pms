<?php

namespace Modules\ProjectManagement\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\SystemNotification;
use App\Notifications\TaskAssignedNotification;
use Illuminate\Http\Request;
use Modules\ProjectManagement\Models\Project;
use Modules\ProjectManagement\Models\Task;

class TaskController extends Controller
{
    /**
     * Create a new controller instance.
     *
     *
     */
    // public function __construct()
    // {
    //     parent::__construct();
    // }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    
public function index(Request $request)
{
    $allowedStatuses = ['pending', 'in_progress', 'completed'];
    $allowedPriorities = ['low', 'medium', 'high'];
    $allowedSorts = ['due_asc', 'due_desc', 'priority', 'updated'];

    $filters = $request->validate([
        'q' => ['nullable', 'string', 'max:255'],
        'status' => ['nullable', 'in:pending,in_progress,completed,overdue'],
        'priority' => ['nullable', 'in:low,medium,high'],
        'sort' => ['nullable', 'in:due_asc,due_desc,priority,updated'],
        'page' => ['nullable', 'integer', 'min:1'],
    ]);

    $query = Task::visibleTo($request->user())
        ->with('project');

    if (! empty($filters['q'])) {
        $term = $filters['q'];

        $query->where('title', 'like', '%' . $term . '%');
    }

    if (! empty($filters['priority'])) {
        $query->where('priority', $filters['priority']);
    }

    if (($filters['status'] ?? null) === 'overdue') {
        $query->whereNot('status', 'completed')
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', today());
    // } elseif (($filters['status'] ?? null) === 'pending') {
    //     $query->whereIn('status', ['pending', 'in_progress']);
    } elseif (in_array($filters['status'] ?? null, $allowedStatuses, true)) {
        $query->where('status', $filters['status']);
    }

    $sort = $filters['sort'] ?? 'due_asc';

    switch ($sort) {
        case 'due_desc':
            $query->orderByRaw('due_date IS NULL')
                ->orderByDesc('due_date');
            break;
            
        case 'priority':
            $query->orderByRaw(
                "CASE priority WHEN 'high' THEN 1 WHEN 'medium' THEN 2 WHEN 'low' THEN 3 ELSE 4 END"
            );
            break;

        case 'updated':
            $query->latest('updated_at');
            break;

        default:
            $query->orderByRaw('due_date IS NULL')
                ->orderBy('due_date');
            break;
    }

    $tasks = $query->paginate(15)->withQueryString();

    // Counts use the same visibility rules, but are independent of active filters.
    $countQuery = Task::visibleTo($request->user());

    $statusCounts = [
        'all' => (clone $countQuery)->count(),
        'pending' => (clone $countQuery)->where('status', ['pending'])->count(),
        'in_progress' => (clone $countQuery)->where('status', 'in_progress')->count(),
        'completed' => (clone $countQuery)->where('status', 'completed')->count(),
        'overdue' => (clone $countQuery)
            ->whereNot('status', 'completed')
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', today())
            ->count(),
    ];

    return view('ProjectManagement::tasks.index', compact(
        'tasks',
        'statusCounts'
    ));
}

    /**
     * Show the form for creating a new resource.
     */
    public function create(Project $project)
    {
        // $projectId = $request->query('project_id')?? key($request->query());
        // $project = Project::find($projectId);

        // if (!$project) {
        //     $project = new Project();
        //     $project->id = $projectId ?: 1; // Gives it an ID of 2 (or 1 as fallback)
        //     $project->exists = true;        // Tricked so route() knows it has an identity
        // }
        $users = User::orderBy('name')->get();
        return view('ProjectManagement::tasks.create',compact('project', 'users'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request, Project $project)
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'due_date' => ['nullable', 'date'],
            'priority' => ['required', 'in:low,medium,high'],
            'status' => ['required', 'in:pending,in_progress,completed'],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
            'attachments' => ['nullable'],
            'attachments.*' => ['file', 'max:25600'],
        ]);

        $task = $project->tasks()->create([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'due_date' => $validated['due_date'] ?? null,
            'priority' => $validated['priority'],
            'status' => $validated['status'],
            'assigned_to' => $validated['assigned_to'] ?? null,
            'created_by' => $request->user()->id,
        ]);

        if ($request->hasFile('attachments')) {
            $files = is_array($request->file('attachments')) ? $request->file('attachments') : [$request->file('attachments')];
            foreach ($files as $file) {
                if ($file && $file->isValid()) {
                    $path = $file->store("attachments/tasks/{$task->id}", 'public');
                    $task->attachments()->create([
                        'uploaded_by' => $request->user()->id,
                        'file_name' => $file->getClientOriginalName(),
                        'file_path' => $path,
                        'file_size' => $file->getSize() ?: 0,
                        'mime_type' => $file->getClientMimeType() ?: $file->getMimeType(),
                        'disk' => 'public',
                    ]);
                }
            }
        }

        $this->notifyTaskRecipients(
            $task,
            'Task created: ' . $task->title,
            route('projects.tasks.show', [$project, $task]),
            'task_created'
        );

        $this->sendAssignmentNotification($task, $request->user());

        return redirect()
            ->route('projects.index')
            ->with('success', 'Task created successfully.');
    }

    /**
     * Display the specified resource.
     */

    public function myTasks()
    {
        $tasks = Task::query()
            ->where('assigned_to', auth()->id())
            ->with('project')
            ->latest()
            ->paginate(10);

        return view('ProjectManagement::tasks.my-tasks', compact('tasks'));
    }


    public function show(Project $project, Task $task)
    {
        $task = $project->tasks()
            ->visibleTo(request()->user())
            ->with(['assignee', 'attachments.uploader'])
            ->findOrFail($task->id);

        return view('ProjectManagement::tasks.show', compact('project', 'task'));
    }
  
    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Project $project, Task $task)
    {
        $task = $project->tasks()
            ->visibleTo(request()->user())
            ->with(['attachments.uploader'])
            ->findOrFail($task->id);
        $users = \App\Models\User::orderBy('name')->get();
    
        return view('ProjectManagement::tasks.edit', compact('project', 'task', 'users'));
    }

    /**
     * Update the specified resource in storage.
     */
  
public function update(Request $request, Project $project, Task $task)
{
    $task = $project->tasks()->visibleTo($request->user())->findOrFail($task->id);

    $user = $request->user();

    if (! $user->can('tasks.edit')) {
        abort_unless($user->can('tasks.update-status'), 403, 'You do not have permission to update this task.');

        abort_unless(
            (int) $task->assigned_to === (int) $user->id,
            403,
            'You can only update tasks assigned to you.'
        );

        $validated = $request->validate([
            'status' => ['required', 'in:pending,in_progress,completed'],
        ]);

        $task->update([
            'status' => $validated['status'],
        ]);

        $this->notifyTaskRecipients(
            $task,
            'Task status updated: ' . $task->title,
            route('projects.tasks.show', [$project, $task]),
            'task_status_updated'
        );

        return redirect()
            ->route('projects.tasks.show', [$project, $task])
            ->with('success', 'Task status updated successfully.');
    }

    $rules = [
        'title' => ['required', 'string', 'max:255'],
        'description' => ['nullable', 'string'],
        'due_date' => ['nullable', 'date'],
        'priority' => ['required', 'in:low,medium,high'],
        'status' => ['required', 'in:pending,in_progress,completed'],
        'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
        'attachments' => ['nullable'],
        'attachments.*' => ['file', 'max:25600'],
    ];

    $validated = $request->validate($rules);

    $previousAssigneeId = $task->assigned_to;
    $task->update([
        'title' => $validated['title'],
        'description' => $validated['description'] ?? null,
        'due_date' => $validated['due_date'] ?? null,
        'priority' => $validated['priority'],
        'status' => $validated['status'],
        'assigned_to' => $validated['assigned_to'] ?? null,
    ]);

    if ($request->hasFile('attachments')) {
        $files = is_array($request->file('attachments')) ? $request->file('attachments') : [$request->file('attachments')];
        foreach ($files as $file) {
            if ($file && $file->isValid()) {
                $path = $file->store("attachments/tasks/{$task->id}", 'public');
                $task->attachments()->create([
                    'uploaded_by' => $user->id,
                    'file_name' => $file->getClientOriginalName(),
                    'file_path' => $path,
                    'file_size' => $file->getSize() ?: 0,
                    'mime_type' => $file->getClientMimeType() ?: $file->getMimeType(),
                    'disk' => 'public',
                ]);
            }
        }
    }

    $this->notifyTaskRecipients(
        $task,
        'Task updated: ' . $task->title,
        route('projects.tasks.show', [$project, $task]),
        'task_updated'
    );

    if ((int) ($task->assigned_to ?? 0) > 0 && (int) $previousAssigneeId !== (int) $task->assigned_to) {
        $this->sendAssignmentNotification($task, $user);
    }

    return redirect()
        ->route('projects.tasks.show', [$project, $task])
        ->with('success', 'Task updated successfully.');
}

    /** Notify the task assignee and assignment-capable users, never unrelated members. */
    private function notifyTaskRecipients(Task $task, string $message, ?string $link = null, string $type = 'system'): void
    {
        User::query()->get()
            ->filter(fn (User $user) => (int) $user->id === (int) $task->assigned_to || $user->can('tasks.assign'))
            ->unique('id')
            ->each(function (User $user) use ($message, $link, $type) {
            $user->notify(new SystemNotification('System update', $message, $link, $type));
        });
    }

    private function sendAssignmentNotification(Task $task, User $assignedBy): void
    {
        $assigneeId = $task->assigned_to;

        if (! $assigneeId) {
            return;
        }

        $assignee = User::find($assigneeId);

        if (! $assignee || ! $assignee->email) {
            return;
        }

        $assignee->notify(new TaskAssignedNotification($task, $assignedBy));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Project $project, Task $task)
    {
        $task = $project->tasks()->visibleTo(request()->user())->findOrFail($task->id);

        $projectId = $task->project_id;
        $taskTitle = $task->title;
        $task->delete();

        $this->notifyTaskRecipients(
            $task,
            'Task deleted: ' . $taskTitle,
            route('projects.show', $projectId),
            'task_deleted'
        );

        return redirect()->route('projects.show', $projectId)->with('success', 'Task deleted successfully.');
    }
}
