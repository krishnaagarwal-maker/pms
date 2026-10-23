<?php

namespace Modules\ProjectManagement\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\SystemNotification;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\ProjectManagement\Models\Project;

class ProjectController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $projects = Project::visibleTo($user)
            ->with(['tasks' => function ($query) use ($user) {
                $query->visibleTo($user)->with('assignee');
            }])
            ->latest()
            ->paginate(12);

        $allProjects = Project::visibleTo($user)
            ->with(['tasks' => function ($query) use ($user) {
                $query->visibleTo($user);
            }])
            ->get();

        return view('ProjectManagement::projects.index', compact('projects', 'allProjects'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    public function create()
    {
        return view('ProjectManagement::projects.create');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return Response
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'attachments' => ['nullable'],
            'attachments.*' => ['file', 'max:25600'],
        ]);

        $project = Project::create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'start_date' => $validated['start_date'] ?? null,
            'end_date' => $validated['end_date'] ?? null,
        ]);

        $this->storeAttachments($request, $project);

        $this->notifyProjectManagers(
            'Project created: '.$project->name,
            route('projects.show', $project),
            'project_created'
        );

        return redirect()
            ->route('projects.index')
            ->with('success', 'Project created successfully.');
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Project  $project
     * @return Response
     */
    public function show(Request $request, Project $project)
    {
        $this->ensureVisibleTo($request->user(), $project);

        $project->load(['attachments.uploader']);

        $tasks = $project->tasks()
            ->visibleTo($request->user())
            ->with('assignee')
            ->latest()
            ->paginate(10);

        return view('ProjectManagement::projects.show', compact('project', 'tasks'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\Project  $project
     * @return Response
     */
    public function edit(Request $request, Project $project)
    {
        $this->ensureVisibleTo($request->user(), $project);

        $project->load(['attachments.uploader']);

        return view('ProjectManagement::projects.edit', compact('project'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \App\Models\Project  $project
     * @return Response
     */
    public function update(Request $request, Project $project)
    {
        $this->ensureVisibleTo($request->user(), $project);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'attachments' => ['nullable'],
            'attachments.*' => ['file', 'max:25600'],
        ]);

        $project->update([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'start_date' => $validated['start_date'] ?? null,
            'end_date' => $validated['end_date'] ?? null,
        ]);

        $this->storeAttachments($request, $project);

        $this->notifyProjectManagers(
            'Project updated: '.$project->name,
            route('projects.show', $project),
            'project_updated'
        );

        return redirect()
            ->route('projects.index')
            ->with('success', 'Project updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Project  $project
     * @return Response
     */
    public function destroy(Request $request, Project $project)
    {
        $this->ensureVisibleTo($request->user(), $project);

        $projectName = $project->name;
        $project->delete();

        $this->notifyProjectManagers(
            'Project deleted: '.$projectName,
            route('projects.index'),
            'project_deleted'
        );

        return redirect()
            ->route('projects.index')
            ->with('success', 'Project "'.$projectName.'" deleted successfully.');
    }

    /**
     * Notify users who have permission to assign tasks.
     */
    private function notifyProjectManagers(
        string $message,
        ?string $link = null,
        string $type = 'system'
    ): void {
        User::query()
            ->get()
            ->filter(fn (User $user) => $user->can('tasks.assign'))
            ->each(function (User $user) use ($message, $link, $type) {
                $user->notify(
                    new SystemNotification('System update', $message, $link, $type)
                );
            });
    }

    /**
     * Ensure the current user can access the project.
     */
    private function ensureVisibleTo(User $user, Project $project): void
    {
        abort_unless(
            Project::visibleTo($user)->whereKey($project->getKey())->exists(),
            403,
            'You can only access projects with tasks assigned to you.'
        );
    }

    /**
     * Store attachments uploaded with a project.
     */
    private function storeAttachments(Request $request, Project $project): void
    {
        if (! $request->hasFile('attachments')) {
            return;
        }

        $uploadedFiles = $request->file('attachments');

        $files = is_array($uploadedFiles)
            ? $uploadedFiles
            : [$uploadedFiles];

        foreach ($files as $file) {
            if (! $file || ! $file->isValid()) {
                continue;
            }

            $path = $file->store(
                "attachments/projects/{$project->id}",
                'public'
            );

            $project->attachments()->create([
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
