{{-- View: All tasks --}}

{{--
|--------------------------------------------------------------------------
| DATA NOTES
|--------------------------------------------------------------------------
| Required:  $tasks   paginator, each task with ->project loaded
| Optional:  $statusCounts  array ['all' => n, 'pending' => n, 'in_progress' => n,
|                                  'completed' => n, 'overdue' => n]   (shows counts on the tabs)
|
| Filters are plain GET params: q, status, priority, sort.
| The page works without any controller change, but the filters only do something
| once the controller applies them. A starting point for TaskController@index:
|
|   $tasks = Task::with('project')
|       ->when($request->q, fn ($q, $term) => $q->where('title', 'like', "%{$term}%"))
|       ->when($request->priority, fn ($q, $p) => $q->where('priority', $p))
|       ->when(in_array($request->status, ['pending', 'in_progress', 'completed']),
|           fn ($q) => $q->where('status', $request->status))
|       ->when($request->status === 'overdue',
|           fn ($q) => $q->where('status', '!=', 'completed')->whereDate('due_date', '<', today()))
|       ->when(true, fn ($q) => match ($request->sort) {
|           'due_desc' => $q->orderByDesc('due_date'),
|           'priority' => $q->orderByRaw("FIELD(priority, 'high', 'medium', 'low')"), // MySQL
|           'updated'  => $q->latest('updated_at'),
|           default    => $q->orderByRaw('due_date IS NULL, due_date ASC'),
|       })
|       ->paginate(15)
|       ->withQueryString();
--}}

@php
    $filters    = [
        'q'        => trim((string) request('q')),
        'status'   => request('status'),
        'priority' => request('priority'),
        'sort'     => request('sort', 'due_asc'),
    ];
    $hasFilters = $filters['q'] !== '' || $filters['status'] || $filters['priority'];
    $totalCount = method_exists($tasks, 'total') ? $tasks->total() : $tasks->count();
    $counts     = isset($statusCounts) ? collect($statusCounts) : null;

    $ticket = fn ($t) => 'TSK-' . $t->project_id . '-' . str_pad($t->id, 2, '0', STR_PAD_LEFT);

    $urgency = function ($t) {
        if (! $t->due_date) {
            return ['none', 'No due date', null];
        }
        $due  = \Illuminate\Support\Carbon::parse($t->due_date)->startOfDay();
        $days = (int) now()->startOfDay()->diffInDays($due, false);
        $date = $due->format('d M Y');

        if ($t->status === 'completed') return ['done', 'Completed', $date];
        if ($days < 0)   return ['overdue', abs($days) . ' ' . Str::plural('day', abs($days)) . ' overdue', $date];
        if ($days === 0) return ['soon', 'Due today', $date];
        if ($days === 1) return ['soon', 'Due tomorrow', $date];
        if ($days <= 3)  return ['soon', 'Due in ' . $days . ' days', $date];
        return ['ok', 'Due in ' . $days . ' days', $date];
    };

    // ---- Colours: white page, indigo accent, semantic status colours ----
    $statusChip = [
        'completed'   => ['bg-emerald-50 text-emerald-700', 'bg-emerald-500', 'Completed'],
        'in_progress' => ['bg-[#c9e6ff] text-[#001e2f]',    'bg-[#64a5c8]',   'In progress'],
        'pending'     => ['bg-slate-100 text-slate-600',    'bg-slate-400',   'Pending'],
    ];
    $priorityChip = [
        'high'   => ['bg-red-50 text-red-700',      'bg-red-500',   'High'],
        'medium' => ['bg-amber-50 text-amber-700',  'bg-amber-500', 'Medium'],
        'low'    => ['bg-slate-100 text-slate-600', 'bg-slate-400', 'Low'],
    ];

    $flashTones = [
        'success' => ['border-emerald-200 bg-emerald-50 text-emerald-800', 'hover:bg-emerald-100', 'check_circle'],
        'error'   => ['border-red-200 bg-red-50 text-red-800',             'hover:bg-red-100',     'error'],
    ];

    $chip     = 'inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-xs font-medium';
    $focus    = 'focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-600 focus-visible:ring-offset-2';
    $card     = 'rounded-xl border border-slate-200 bg-white shadow-[0_1px_3px_0_rgba(15,23,42,0.06)]';
    $field    = 'h-10 rounded-lg border border-slate-200 bg-white text-sm text-slate-700 shadow-sm focus:border-indigo-500 focus:ring-indigo-500';
    $iconBtn  = 'inline-flex h-8 w-8 items-center justify-center rounded-lg text-slate-400 transition hover:bg-slate-100 hover:text-slate-900 motion-reduce:transition-none ' . $focus;
    $btnGhost = 'inline-flex h-10 items-center gap-2 rounded-lg border border-slate-200 bg-white px-3.5 text-sm font-medium text-slate-700 shadow-sm transition hover:border-slate-300 hover:bg-slate-50 hover:text-slate-900 motion-reduce:transition-none ' . $focus;
    $btnSolid = 'inline-flex h-10 items-center gap-2 rounded-lg bg-indigo-600 px-4 text-sm font-semibold text-white shadow-[inset_0_1px_0_0_rgba(255,255,255,0.15)] transition hover:bg-indigo-700 motion-reduce:transition-none ' . $focus;
    $tabs = [
        ['',            'All'],
        ['pending',     'Pending'],
        ['in_progress', 'In progress'],
        ['completed',   'Completed'],
        ['overdue',     'Overdue'],
    ];
    $tabUrl = fn ($status) => request()->fullUrlWithQuery(['status' => $status ?: null, 'page' => null]);

    $sorts = [
        'due_asc'  => 'Due soonest',
        'due_desc' => 'Due latest',
        'priority' => 'Highest priority',
        'updated'  => 'Recently updated',
    ];

    // Everything the row and the card need, computed once per task.
    $meta = function ($task) use ($urgency, $statusChip, $priorityChip, $ticket) {
        [$uState, $uLabel, $uDate] = $urgency($task);
        return [
            'overdue' => $uState === 'overdue',
            'uState'  => $uState,
            'uLabel'  => $uLabel,
            'uDate'   => $uDate,
            'status'  => $statusChip[$task->status] ?? $statusChip['pending'],
            'prio'    => $priorityChip[$task->priority] ?? $priorityChip['low'],
            'ticket'  => $ticket($task),
            'confirm' => 'Delete “' . $task->title . '”? This cannot be undone.',
        ];
    };
@endphp

<x-app-layout>
    <style>
        @keyframes pmFadeIn { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: none; } }
        .pm-fade-in { animation: pmFadeIn .4s ease-out both; }
    </style>
    <div class="min-h-screen bg-white py-space-xl font-inter text-slate-900 pm-fade-in">
        <div class="mx-auto max-w-[1600px] space-y-6 px-4 sm:px-6 lg:px-8">

            {{-- Page header --}}
            <div class="flex flex-wrap items-end justify-between gap-4 border-b border-slate-200 pb-6">
                <div>
                    <h1 class="text-[32px] font-bold leading-10 tracking-[-0.025em] text-slate-900">All tasks</h1>
                    <p class="mt-1 text-sm text-slate-500">
                        {{ number_format($totalCount) }} {{ Str::plural('task', $totalCount) }}
                        {{ $hasFilters ? 'match your filters' : 'across all projects' }}.
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    @can('tasks.view')
                        <a href="{{ route('projects.my-tasks') }}" class="{{ $btnGhost }}">
                            <span class="material-symbols-outlined text-[18px]" aria-hidden="true">assignment_ind</span>
                            My tasks
                        </a>
                    @endcan
                    @can('projects.view')
                        <a href="{{ route('projects.index') }}" class="{{ $btnSolid }}">
                            <span class="material-symbols-outlined text-[18px]" aria-hidden="true">folder_open</span>
                            Open a project to add a task
                        </a>
                    @endcan
                </div>
            </div>

            {{-- Flash messages --}}
            @foreach ($flashTones as $flashKey => [$toneClass, $toneHover, $flashIcon])
                @if (session($flashKey))
                    <div x-data="{ show: true }"
                         x-show="show"
                         x-init="setTimeout(() => show = false, 6000)"
                         x-transition.opacity
                         role="{{ $flashKey === 'error' ? 'alert' : 'status' }}"
                         class="flex items-start gap-3 rounded-lg border px-4 py-3 text-sm {{ $toneClass }}">
                        <span class="material-symbols-outlined text-[20px]" aria-hidden="true">{{ $flashIcon }}</span>
                        <p class="flex-1">{{ session($flashKey) }}</p>
                        <button type="button" x-on:click="show = false" class="rounded p-0.5 {{ $toneHover }} {{ $focus }}">
                            <span class="sr-only">Dismiss</span>
                            <span class="material-symbols-outlined text-[18px]" aria-hidden="true">close</span>
                        </button>
                    </div>
                @endif
            @endforeach

            <section class="{{ $card }} overflow-hidden" aria-label="Tasks">

                {{-- Status tabs --}}
                <div class="overflow-x-auto border-b border-slate-200 px-2">
                    <nav class="flex min-w-max gap-1" aria-label="Filter by status">
                        @foreach ($tabs as [$tabKey, $tabLabel])
                            @php
                                $isActive = ($filters['status'] ?: '') === $tabKey;
                                $tabCount = $counts ? $counts->get($tabKey === '' ? 'all' : $tabKey) : null;
                            @endphp
                            <a href="{{ $tabUrl($tabKey) }}"
                               @if ($isActive) aria-current="page" @endif
                               class="-mb-px inline-flex items-center gap-2 border-b-2 px-3 py-3.5 text-sm font-medium transition motion-reduce:transition-none {{ $focus }}
                                      {{ $isActive ? 'border-indigo-600 text-indigo-700' : 'border-transparent text-slate-500 hover:border-slate-300 hover:text-slate-800' }}">
                                {{ $tabLabel }}
                                @if (! is_null($tabCount))
                                    <span class="rounded-full px-1.5 text-xs tabular-nums {{ $tabKey === 'overdue' && $tabCount > 0 ? 'bg-red-100 text-red-700' : ($isActive ? 'bg-indigo-50 text-indigo-700' : 'bg-slate-100 text-slate-600') }}">{{ $tabCount }}</span>
                                @endif
                            </a>
                        @endforeach
                    </nav>
                </div>

                {{-- Search + filters --}}
                <form method="GET" action="{{ url()->current() }}" class="flex flex-wrap items-center gap-3 border-b border-slate-200 bg-white px-4 py-3">
                    @if ($filters['status'])
                        <input type="hidden" name="status" value="{{ $filters['status'] }}">
                    @endif

                    <div class="relative min-w-[14rem] flex-1 sm:max-w-sm">
                        <span class="material-symbols-outlined pointer-events-none absolute left-2.5 top-1/2 -translate-y-1/2 text-[18px] text-slate-400" aria-hidden="true">search</span>
                        <label for="task-search" class="sr-only">Search tasks</label>
                        <input id="task-search" type="search" name="q" value="{{ $filters['q'] }}" placeholder="Search by task title"
                               class="{{ $field }} w-full pl-9 placeholder:text-slate-400">
                    </div>

                    <div>
                        <label for="task-priority" class="sr-only">Priority</label>
                        <select id="task-priority" name="priority" onchange="this.form.submit()" class="{{ $field }} pr-8">
                            <option value="">All priorities</option>
                            @foreach (['high' => 'High', 'medium' => 'Medium', 'low' => 'Low'] as $pKey => $pLabel)
                                <option value="{{ $pKey }}" @selected($filters['priority'] === $pKey)>{{ $pLabel }} priority</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="task-sort" class="sr-only">Sort by</label>
                        <select id="task-sort" name="sort" onchange="this.form.submit()" class="{{ $field }} pr-8">
                            @foreach ($sorts as $sKey => $sLabel)
                                <option value="{{ $sKey }}" @selected($filters['sort'] === $sKey)>{{ $sLabel }}</option>
                            @endforeach
                        </select>
                    </div>

                    <button type="submit" class="{{ $btnGhost }} h-10">Apply</button>

                    @if ($hasFilters)
                        <a href="{{ url()->current() }}" class="inline-flex items-center gap-1 rounded text-sm font-medium text-indigo-600 hover:text-indigo-700 {{ $focus }}">
                            <span class="material-symbols-outlined text-[16px]" aria-hidden="true">filter_alt_off</span>
                            Clear filters
                        </a>
                    @endif
                </form>

                @if ($tasks->isEmpty())
                    {{-- Empty state --}}
                    <div class="px-6 py-16 text-center">
                        <span class="mx-auto flex h-12 w-12 items-center justify-center rounded-full {{ $hasFilters ? 'bg-slate-100 text-slate-500' : 'bg-indigo-50 text-indigo-600' }}">
                            <span class="material-symbols-outlined text-[24px]" aria-hidden="true">{{ $hasFilters ? 'search_off' : 'checklist' }}</span>
                        </span>
                        @if ($hasFilters)
                            <h2 class="mt-3 text-base font-semibold text-slate-900">No tasks match your filters</h2>
                            <p class="mt-1 text-sm text-slate-500">Try a different search or clear the filters to see everything.</p>
                            <a href="{{ url()->current() }}" class="{{ $btnGhost }} mt-4 h-9">Clear filters</a>
                        @else
                            <h2 class="mt-3 text-base font-semibold text-slate-900">No tasks yet</h2>
                            <p class="mt-1 text-sm text-slate-500">Open a project to create its first task.</p>
                            @can('projects.view')
                                <a href="{{ route('projects.index') }}" class="{{ $btnSolid }} mt-4 h-9">View projects</a>
                            @endcan
                        @endif
                    </div>
                @else
                    {{-- Desktop table --}}
                    <div class="hidden overflow-x-auto md:block">
                        <table class="w-full min-w-[860px] text-left">
                            <thead>
                                <tr class="h-10 border-b border-slate-200 bg-slate-50 text-xs font-semibold text-slate-500">
                                    <th scope="col" class="px-6">Task</th>
                                    <th scope="col" class="px-4">Project</th>
                                    <th scope="col" class="px-4">Due date</th>
                                    <th scope="col" class="px-4">Priority</th>
                                    <th scope="col" class="px-4">Status</th>
                                    <th scope="col" class="px-6 text-right"><span class="sr-only">Actions</span></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach ($tasks as $task)
                                    @php $m = $meta($task); @endphp
                                    <tr class="transition-colors hover:bg-slate-50 motion-reduce:transition-none {{ $m['overdue'] ? 'bg-red-50/40' : '' }}">
                                        <td class="border-l-[3px] px-6 py-4 {{ $m['overdue'] ? 'border-l-red-600' : 'border-l-transparent' }}">
                                            <a href="{{ route('projects.tasks.show', [$task->project, $task]) }}"
                                               class="block max-w-sm truncate rounded text-sm font-semibold text-slate-900 hover:text-indigo-600 {{ $focus }}">{{ $task->title }}</a>
                                            <span class="font-code-sm text-[11px] {{ $m['overdue'] ? 'text-red-600' : 'text-slate-500' }}">{{ $m['ticket'] }}</span>
                                        </td>

                                        <td class="px-4 py-4">
                                            <a href="{{ route('projects.show', $task->project) }}"
                                               class="flex items-center gap-2 rounded text-sm text-slate-700 hover:text-indigo-600 {{ $focus }}">
                                                <span class="h-2 w-2 shrink-0 rounded-full bg-indigo-500" aria-hidden="true"></span>
                                                <span class="max-w-[12rem] truncate">{{ $task->project->name }}</span>
                                            </a>
                                        </td>

                                        <td class="px-4 py-4">
                                            @if ($m['uDate'])
                                                <div class="text-sm font-medium text-slate-900">{{ $m['uDate'] }}</div>
                                            @endif
                                            <div class="text-xs {{ $m['overdue'] ? 'font-medium text-red-600' : ($m['uState'] === 'soon' ? 'font-medium text-amber-600' : 'text-slate-500') }}">{{ $m['uLabel'] }}</div>
                                        </td>

                                        <td class="px-4 py-4">
                                            <span class="{{ $chip }} {{ $m['prio'][0] }}"><span class="h-1.5 w-1.5 rounded-full {{ $m['prio'][1] }}" aria-hidden="true"></span>{{ $m['prio'][2] }}</span>
                                        </td>

                                        <td class="px-4 py-4">
                                            <span class="{{ $chip }} {{ $m['status'][0] }}"><span class="h-1.5 w-1.5 rounded-full {{ $m['status'][1] }}" aria-hidden="true"></span>{{ $m['status'][2] }}</span>
                                        </td>

                                        <td class="px-6 py-4">
                                            <div class="flex items-center justify-end gap-0.5">
                                                <a href="{{ route('projects.tasks.show', [$task->project, $task]) }}" title="View task" class="{{ $iconBtn }}">
                                                    <span class="sr-only">View {{ $task->title }}</span>
                                                    <span class="material-symbols-outlined text-[18px]" aria-hidden="true">visibility</span>
                                                </a>

                                                @can('tasks.edit')
                                                    <a href="{{ route('projects.tasks.edit', [$task->project, $task]) }}" title="Edit task" class="{{ $iconBtn }}">
                                                        <span class="sr-only">Edit {{ $task->title }}</span>
                                                        <span class="material-symbols-outlined text-[18px]" aria-hidden="true">edit</span>
                                                    </a>
                                                @endcan

                                                @can('tasks.delete')
                                                    <form action="{{ route('projects.tasks.destroy', [$task->project, $task]) }}" method="POST" class="inline"
                                                          data-confirm="{{ $m['confirm'] }}"
                                                          onsubmit="return confirm(this.dataset.confirm)">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" title="Delete task"
                                                                class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-slate-400 transition hover:bg-red-50 hover:text-red-600 motion-reduce:transition-none {{ $focus }}">
                                                            <span class="sr-only">Delete {{ $task->title }}</span>
                                                            <span class="material-symbols-outlined text-[18px]" aria-hidden="true">delete</span>
                                                        </button>
                                                    </form>
                                                @endcan
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    {{-- Mobile cards --}}
                    <ul class="divide-y divide-slate-100 md:hidden">
                        @foreach ($tasks as $task)
                            @php $m = $meta($task); @endphp
                            <li class="border-l-[3px] px-4 py-4 {{ $m['overdue'] ? 'border-l-red-600 bg-red-50/40' : 'border-l-transparent' }}">
                                <a href="{{ route('projects.tasks.show', [$task->project, $task]) }}"
                                   class="block rounded text-sm font-semibold text-slate-900 hover:text-indigo-600 {{ $focus }}">{{ $task->title }}</a>
                                <p class="mt-0.5 text-xs text-slate-500">
                                    <span class="font-code-sm">{{ $m['ticket'] }}</span> in
                                    <a href="{{ route('projects.show', $task->project) }}" class="font-medium text-slate-700 hover:text-indigo-600">{{ $task->project->name }}</a>
                                </p>

                                <div class="mt-2.5 flex flex-wrap items-center gap-2">
                                    <span class="{{ $chip }} {{ $m['status'][0] }}"><span class="h-1.5 w-1.5 rounded-full {{ $m['status'][1] }}" aria-hidden="true"></span>{{ $m['status'][2] }}</span>
                                    <span class="{{ $chip }} {{ $m['prio'][0] }}"><span class="h-1.5 w-1.5 rounded-full {{ $m['prio'][1] }}" aria-hidden="true"></span>{{ $m['prio'][2] }}</span>
                                    <span class="text-xs {{ $m['overdue'] ? 'font-medium text-red-600' : ($m['uState'] === 'soon' ? 'font-medium text-amber-600' : 'text-slate-500') }}">
                                        {{ $m['uLabel'] }}
                                        @if ($m['uDate'])
                                            · {{ $m['uDate'] }}
                                        @endif
                                    </span>
                                </div>

                                <div class="mt-3 flex items-center gap-4 text-sm font-medium">
                                    <a href="{{ route('projects.tasks.show', [$task->project, $task]) }}" class="rounded text-indigo-600 hover:text-indigo-700 {{ $focus }}">View</a>
                                    @can('tasks.edit')
                                        <a href="{{ route('projects.tasks.edit', [$task->project, $task]) }}" class="rounded text-indigo-600 hover:text-indigo-700 {{ $focus }}">Edit</a>
                                    @endcan
                                    @can('tasks.delete')
                                        <form action="{{ route('projects.tasks.destroy', [$task->project, $task]) }}" method="POST" class="inline"
                                              data-confirm="{{ $m['confirm'] }}"
                                              onsubmit="return confirm(this.dataset.confirm)">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="rounded text-red-600 hover:text-red-700 {{ $focus }}">Delete</button>
                                        </form>
                                    @endcan
                                </div>
                            </li>
                        @endforeach
                    </ul>

                    {{-- Footer / pagination --}}
                    <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 px-6 py-4 text-sm text-slate-500">
                        <p>
                            Showing <span class="font-medium text-slate-900">{{ $tasks->firstItem() }}</span>
                            to <span class="font-medium text-slate-900">{{ $tasks->lastItem() }}</span>
                            @if (method_exists($tasks, 'total'))
                                of <span class="font-medium text-slate-900">{{ number_format($tasks->total()) }}</span>
                            @endif
                            {{ Str::plural('task', $totalCount) }}
                        </p>
                        @if (method_exists($tasks, 'hasPages') && $tasks->hasPages())
                            <div>{{ $tasks->withQueryString()->links() }}</div>
                        @endif
                    </div>
                @endif
            </section>
        </div>
    </div>
</x-app-layout>