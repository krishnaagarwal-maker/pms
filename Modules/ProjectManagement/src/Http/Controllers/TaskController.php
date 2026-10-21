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
    public function index()
    {
        $tasks = Task::with('project')
        ->latest()
        ->paginate(10);
        return view('ProjectManagement::tasks.index', compact('tasks'));
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
        ]);

        $task = $project->tasks()->create([
            ...$validated,
            'created_by' => $request->user()->id,
        ]);

        $this->notifyAllUsers(
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
        // $task->load('project');

        $task = $project->tasks()
        ->with('assignee')
        ->findOrFail($task->id);

        return view('ProjectManagement::tasks.show', compact('project', 'task'));
    }
  
    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Project $project, Task $task)
    {
        $task = $project->tasks()->findOrFail($task->id);
        $users = \App\Models\User::orderBy('name')->get();
    
        return view('ProjectManagement::tasks.edit', compact('project', 'task', 'users'));
    }

    /**
     * Update the specified resource in storage.
     */
  
public function update(Request $request, Project $project, Task $task)
{
    $task = $project->tasks()->findOrFail($task->id);

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

        $this->notifyAllUsers(
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
    ];

    $validated = $request->validate($rules);

    $previousAssigneeId = $task->assigned_to;
    $task->update($validated);

    $this->notifyAllUsers(
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

    private function notifyAllUsers(string $message, ?string $link = null, string $type = 'system'): void
    {
        User::query()->get()->each(function ($user) use ($message, $link, $type) {
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
        $projectId = $task->project_id;
        $taskTitle = $task->title;
        $task->delete();

        $this->notifyAllUsers(
            'Task deleted: ' . $taskTitle,
            route('projects.show', $projectId),
            'task_deleted'
        );

        return redirect()->route('projects.show', $projectId)->with('success', 'Task deleted successfully.');
    }
}
