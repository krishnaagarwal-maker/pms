{{-- View: my tasks (route: projects.my-tasks) --}}

{{--
| Data: $tasks (paginator of the user's tasks, each with ->project loaded).
| Tip: in the controller use ->with('project') to avoid extra queries, e.g.
|   Task::with('project')->where('assigned_to', auth()->id())
|       ->orderByRaw('due_date is null, due_date asc')->paginate(15);
| Colors use standard Tailwind classes, so no tailwind.config.js changes are needed.
--}}

@php
    $pageTasks = $tasks->getCollection();

    $isOverdue = fn ($t) => $t->due_date
        && $t->status !== 'completed'
        && $t->due_date->copy()->startOfDay()->lt(now()->startOfDay());

    $counts = [
        'all'         => $pageTasks->count(),
        'pending'     => $pageTasks->where('status', 'pending')->count(),
        'in_progress' => $pageTasks->where('status', 'in_progress')->count(),
        'completed'   => $pageTasks->where('status', 'completed')->count(),
        'overdue'     => $pageTasks->filter($isOverdue)->count(),
    ];

    $tabs = [
        'all'         => 'All',
        'pending'     => 'Pending',
        'in_progress' => 'In Progress',
        'completed'   => 'Completed',
        'overdue'     => 'Overdue',
    ];

    $ticket = fn ($t) => 'TSK-' . $t->project_id . '-' . str_pad($t->id, 2, '0', STR_PAD_LEFT);

    $urgency = function ($t) {
        if (! $t->due_date) return ['none', 'No due date'];
        if ($t->status === 'completed') return ['done', 'Completed'];
        $days = (int) now()->startOfDay()->diffInDays($t->due_date->copy()->startOfDay(), false);
        if ($days < 0)   return ['overdue', abs($days) . ' ' . Str::plural('day', abs($days)) . ' overdue'];
        if ($days === 0) return ['soon', 'Due today'];
        if ($days === 1) return ['soon', 'Tomorrow'];
        if ($days <= 3)  return ['soon', 'Due in ' . $days . ' days'];
        return ['ok', 'Due in ' . $days . ' days'];
    };

    $statusChip = [
        'completed'   => ['bg-emerald-50 text-emerald-700', 'bg-emerald-500', 'Completed'],
        'in_progress' => ['bg-indigo-50 text-indigo-700',   'bg-indigo-500',  'In Progress'],
        'pending'     => ['bg-slate-100 text-slate-600',    'bg-slate-400',   'Pending'],
    ];
    $priorityChip = [
        'high'   => ['bg-red-50 text-red-700',      'bg-red-500',   'High'],
        'medium' => ['bg-amber-50 text-amber-700',  'bg-amber-500', 'Medium'],
        'low'    => ['bg-slate-100 text-slate-600', 'bg-slate-400', 'Low'],
    ];
    $chip = 'inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wide';

    $rows = $pageTasks->map(fn ($t) => [
        'status'  => $t->status,
        'overdue' => (bool) $isOverdue($t),
        'hay'     => Str::lower($t->title . ' ' . ($t->project->name ?? '') . ' ' . $ticket($t)),
    ])->values();
@endphp

<x-app-layout>
    <style>
        @keyframes pmFadeIn { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: none; } }
        .pm-fade-in { animation: pmFadeIn .4s ease-out both; }
    </style>
    <div class="min-h-screen bg-slate-50 py-space-xl font-inter pm-fade-in"
         x-data="{
             filter: 'all',
             q: '',
             rows: @js($rows),
             match(r) {
                 return (this.filter === 'all'
                         || (this.filter === 'overdue' ? r.overdue : r.status === this.filter))
                     && (this.q === '' || r.hay.includes(this.q.toLowerCase()));
             },
             get shown() { return this.rows.filter(r => this.match(r)).length; }
         }">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">

            {{-- Header --}}
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <h1 class="text-[28px] font-bold leading-9 tracking-[-0.025em] text-slate-900">My Tasks</h1>
                    <p class="mt-1 text-sm text-slate-500">
                        @if ($tasks->total() > 0)
                            {{ number_format($tasks->total()) }} {{ Str::plural('task', $tasks->total()) }} assigned to you
                            @if ($counts['overdue'] > 0)
                                · <span class="font-medium text-red-600">{{ $counts['overdue'] }} overdue</span>
                            @endif
                        @else
                            Tasks assigned to you will appear here.
                        @endif
                    </p>
                </div>

                @can('projects.view')
                    <a href="{{ route('projects.index') }}"
                       class="inline-flex h-9 items-center gap-2 rounded-md border border-slate-200 bg-white px-3.5 text-sm font-medium text-slate-700 transition hover:border-slate-300 hover:bg-slate-50 hover:text-slate-900">
                        <span class="material-symbols-outlined text-[18px]" aria-hidden="true">folder_open</span>
                        Browse Projects
                    </a>
                @endcan
            </div>

            @if ($tasks->isEmpty())
                {{-- Empty state --}}
                <div class="rounded-lg border-2 border-dashed border-slate-200 bg-white px-6 py-16 text-center">
                    <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-indigo-50 text-indigo-600">
                        <span class="material-symbols-outlined text-[28px]" aria-hidden="true">assignment_turned_in</span>
                    </span>
                    <h2 class="mt-5 text-base font-semibold text-slate-900">No tasks assigned to you yet</h2>
                    <p class="mx-auto mt-1 max-w-sm text-sm text-slate-500">
                        When a teammate assigns you a task, it will show up here with its due date and priority.
                    </p>
                    @can('projects.view')
                        <a href="{{ route('projects.index') }}"
                           class="mt-6 inline-flex h-9 items-center rounded-md bg-indigo-600 px-4 text-sm font-medium text-white shadow-[inset_0_1px_0_0_rgba(255,255,255,0.15)] hover:bg-indigo-700">
                            Browse projects
                        </a>
                    @endcan
                </div>
            @else
                {{-- Toolbar --}}
                <div class="flex flex-col justify-between gap-3 rounded-lg border border-slate-200 bg-white p-3 shadow-[0_1px_3px_0_rgba(15,23,42,0.06)] md:flex-row md:items-center">
                    <div class="relative w-full md:max-w-sm">
                        <span class="material-symbols-outlined pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-[18px] text-slate-400" aria-hidden="true">search</span>
                        <input type="text" x-model="q" placeholder="Search by title, project or ID..."
                               class="h-9 w-full rounded-md border border-slate-300 bg-white pl-9 pr-3 text-sm text-slate-900 placeholder:text-slate-400 focus:border-indigo-600 focus:outline-none focus:ring-[3px] focus:ring-indigo-600/15">
                    </div>

                    <div class="flex flex-wrap gap-10 rounded-md bg-slate-100 p-1">
                        @foreach ($tabs as $key => $label)
                            <button type="button" @click="filter = @js($key)"
                                    :class="filter === @js($key)
                                        ? 'bg-white text-slate-900 shadow-sm ring-1 ring-slate-200'
                                        : 'text-slate-600 hover:text-slate-900'"
                                    class="rounded px-3 py-1 text-[13px] font-medium transition-colors">
                                {{ $label }}
                                <span class="ml-0.5 tabular-nums {{ $key === 'overdue' && $counts[$key] > 0 ? 'text-red-600' : 'text-slate-400' }}">({{ $counts[$key] }})</span>
                            </button>
                        @endforeach
                    </div>
                </div>

                {{-- List --}}
                <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-[0_1px_3px_0_rgba(15,23,42,0.06)]">

                    {{-- Column headers (desktop) --}}
                    <div class="hidden h-9 grid-cols-12 items-center gap-4 border-b border-slate-200 bg-slate-50 px-6 text-[11px] font-semibold uppercase tracking-wider text-slate-500 lg:grid">
                        <div class="col-span-4">Task title &amp; ID</div>
                        <div class="col-span-2">Project</div>
                        <div class="col-span-2">Due date</div>
                        <div class="col-span-1">Priority</div>
                        <div class="col-span-2">Status</div>
                        <div class="col-span-1 text-right">Action</div>
                    </div>

                    <ul class="divide-y divide-slate-100">
                        @foreach ($tasks as $task)
                            @php
                                $overdue = $isOverdue($task);
                                $done    = $task->status === 'completed';
                                [$uState, $uLabel]      = $urgency($task);
                                [$sPill, $sDot, $sText] = $statusChip[$task->status] ?? $statusChip['pending'];
                                [$pPill, $pDot, $pText] = $priorityChip[$task->priority] ?? $priorityChip['low'];
                                $url = route('projects.tasks.show', [$task->project_id, $task]);
                            @endphp

                            <li x-show="match(rows[{{ $loop->index }}])"
                                class="grid grid-cols-1 items-center gap-x-4 gap-y-2 px-6 py-3.5 transition-colors hover:bg-slate-50 lg:grid-cols-12 {{ $overdue ? 'bg-red-50/40' : '' }}">

                                {{-- Title & ID --}}
                                <div class="min-w-0 lg:col-span-4">
                                    <div class="flex items-start gap-3">
                                        <span class="material-symbols-outlined mt-0.5 text-[18px] {{ $overdue ? 'text-red-500' : ($done ? 'text-emerald-500' : 'text-slate-400') }}" aria-hidden="true">
                                            {{ $overdue ? 'error' : ($done ? 'check_circle' : 'radio_button_unchecked') }}
                                        </span>
                                        <div class="min-w-0">
                                            <a href="{{ $url }}"
                                               class="block truncate text-sm font-medium hover:text-indigo-600 {{ $done ? 'text-slate-400 line-through' : 'text-slate-900' }}">{{ $task->title }}</a>
                                            <span class="font-code-sm text-[11px] {{ $overdue ? 'text-red-600' : 'text-slate-500' }}">{{ $ticket($task) }}</span>
                                        </div>
                                    </div>
                                </div>

                                {{-- Project --}}
                                <div class="min-w-0 lg:col-span-2">
                                    <span class="flex items-center gap-2 text-sm text-slate-700">
                                        <span class="h-2 w-2 shrink-0 rounded-full bg-indigo-500"></span>
                                        <span class="truncate">{{ $task->project->name ?? 'Unknown project' }}</span>
                                    </span>
                                </div>

                                {{-- Due date --}}
                                <div class="lg:col-span-2">
                                    <div class="text-sm font-medium text-slate-900">{{ $task->due_date?->format('d M Y') ?? '—' }}</div>
                                    <div class="text-xs {{ $uState === 'overdue' ? 'font-medium text-red-600' : ($uState === 'soon' ? 'font-medium text-amber-600' : 'text-slate-500') }}">{{ $uLabel }}</div>
                                </div>

                                {{-- Priority --}}
                                <div class="lg:col-span-1">
                                    <span class="{{ $chip }} {{ $pPill }}"><span class="h-1.5 w-1.5 rounded-full {{ $pDot }}"></span>{{ $pText }}</span>
                                </div>

                                {{-- Status --}}
                                <div class="lg:col-span-2">
                                    <span class="{{ $chip }} {{ $sPill }}"><span class="h-1.5 w-1.5 rounded-full {{ $sDot }}"></span>{{ $sText }}</span>
                                </div>

                                {{-- Action --}}
                                <div class="lg:col-span-1 lg:text-right">
                                    <a href="{{ $url }}"
                                       class="inline-flex h-8 items-center gap-1 rounded-md border border-slate-200 bg-white px-3 text-[13px] font-medium text-slate-700 transition hover:border-slate-300 hover:bg-slate-50 hover:text-slate-900">
                                        View
                                        <span class="material-symbols-outlined text-[16px]" aria-hidden="true">arrow_forward</span>
                                    </a>
                                </div>
                            </li>
                        @endforeach
                    </ul>

                    {{-- No matches for the current filter/search --}}
                    <div x-show="shown === 0" style="display: none;" class="px-6 py-12 text-center">
                        <span class="material-symbols-outlined text-[28px] text-slate-300" aria-hidden="true">search_off</span>
                        <p class="mt-2 text-sm font-medium text-slate-700">No tasks match your filter</p>
                        <button type="button" @click="filter = 'all'; q = ''" class="mt-2 text-sm font-medium text-indigo-600 hover:underline">Clear filters</button>
                    </div>

                    {{-- Pagination --}}
                    @if ($tasks->hasPages())
                        <div class="flex flex-col items-center justify-between gap-3 border-t border-slate-200 bg-slate-50 px-6 py-4 sm:flex-row">
                            <p class="text-xs text-slate-500">
                                Showing
                                <span class="font-semibold text-slate-900">{{ $tasks->firstItem() }}</span>
                                to  
                                <span class="font-semibold text-slate-900">{{ $tasks->lastItem() }}</span>
                                of
                                <span class="font-semibold text-slate-900">{{ $tasks->total() }}</span>
                                tasks
                            </p>
                            <div>{{ $tasks->links() }}</div>
                        </div>
                    @endif
                </div>

                <p class="px-1 text-xs text-slate-500">Filters and search apply to the tasks on this page.</p>
            @endif
        </div>
    </div>
</x-app-layout>