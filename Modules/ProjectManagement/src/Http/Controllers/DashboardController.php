<?php

namespace Modules\ProjectManagement\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\ProjectManagement\Models\Project;
use Modules\ProjectManagement\Models\Task;

class DashboardController extends Controller
{
    public function index(Request $request)
{
    $user = $request->user();

    $projectsCount = $user->can('projects.view')
        ? Project::count()
        : 0;

    $tasksQuery = Task::query();

    // Users without task-assignment authority see their own tasks.
    if (! $user->can('tasks.assign')) {
        $tasksQuery->where('assigned_to', $user->id);
    }

    $totalTasks = (clone $tasksQuery)->count();

    $completedTasks = (clone $tasksQuery)
        ->where('status', 'completed')
        ->count();

    $pendingTasks = (clone $tasksQuery)
        ->whereIn('status', ['pending', 'in_progress'])
        ->count();

    $overdueTasks = (clone $tasksQuery)
        ->whereDate('due_date', '<', today())
        ->where('status', '!=', 'completed')
        ->count();

    $upcomingTasks = (clone $tasksQuery)
        ->whereNotNull('due_date')
        ->where('status', '!=', 'completed')
        ->orderBy('due_date')
        ->limit(5)
        ->with('project')
        ->get();

    $notifications = $user
        ? $user->notifications()->latest()->limit(6)->get()
        : collect();

    $unreadNotificationCount = $user
        ? $user->unreadNotifications()->count()
        : 0;

    return view('dashboard', compact(
        'projectsCount',
        'totalTasks',
        'completedTasks',
        'pendingTasks',
        'overdueTasks',
        'upcomingTasks',
        'notifications',
        'unreadNotificationCount'
    ));
}

    public function markAllAsRead(Request $request)
    {
        $request->user()?->unreadNotifications->markAsRead();

        return redirect()->back()->with('success', 'All notifications marked as read.');
    }
}
