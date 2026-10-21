<?php

namespace Modules\ProjectManagement\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\SystemNotification;
use Illuminate\Http\Request;
use Modules\ProjectManagement\Models\Project; 


class ProjectController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $projects = Project::latest()->paginate(10);

        return view('ProjectManagement::projects.index', compact('projects'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        return view('ProjectManagement::projects.create');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ]);

        $project = Project::create($validated);

        $this->notifyAllUsers(
            'Project created: ' . $project->name,
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
     * @return \Illuminate\Http\Response
     */
    public function show(Project $project)
    {
        $tasks = $project->tasks()->with('assignee')->latest()->paginate(10);

        return view('ProjectManagement::projects.show', compact('project', 'tasks'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\Project  $project
     * @return \Illuminate\Http\Response
     */
    public function edit(Project $project)
    {
        return view('ProjectManagement::projects.edit', compact('project'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Project  $project
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Project $project)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ]);

        $project->update($validated);

        $this->notifyAllUsers(
            'Project updated: ' . $project->name,
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
     * @return \Illuminate\Http\Response
     */
    public function destroy(Project $project)
    {
        $projectName = $project->name;
        $project->delete();

        $this->notifyAllUsers(
            'Project deleted: ' . $projectName,
            route('projects.index'),
            'project_deleted'
        );
    }

    private function notifyAllUsers(string $message, ?string $link = null, string $type = 'system'): void
    {
        User::query()->get()->each(function ($user) use ($message, $link, $type) {
            $user->notify(new SystemNotification('System update', $message, $link, $type));
        });
    }
}
