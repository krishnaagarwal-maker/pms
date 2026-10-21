{{-- View: projects.show --}}
@php
    $doneStatuses = ['completed', 'done'];

    // ---- Task counts ---------------------------------------------------
    $totalTasks     = method_exists($tasks, 'total') ? $tasks->total() : $tasks->count();
    $completedTasks = $project->tasks()->whereIn('status', $doneStatuses)->count();
    $progress       = $totalTasks > 0 ? (int) round(($completedTasks / $totalTasks) * 100) : 0;

    // Tasks visible on this page (used for the client-side filter tabs)
    $pageTasks       = method_exists($tasks, 'getCollection') ? $tasks->getCollection() : collect($tasks);
    $pageInProgress  = $pageTasks->where('status', 'in_progress')->count();
    $pageCompleted   = $pageTasks->filter(fn ($t) => in_array($t->status, $doneStatuses))->count();

    // ---- Project dates / status ---------------------------------------
    $today    = now()->startOfDay();
    $daysLeft = $project->end_date ? (int) $today->diffInDays($project->end_date, false) : null;
    $notStarted = $project->start_date && $project->start_date->gt($today);

    if ($notStarted) {
        $badge = ['text' => 'Upcoming', 'dot' => 'bg-secondary-container', 'pulse' => false];
    } elseif (! is_null($daysLeft) && $daysLeft < 0) {
        $badge = ['text' => 'Overdue by ' . abs($daysLeft) . ' ' . Str::plural('day', abs($daysLeft)), 'dot' => 'bg-error', 'pulse' => false];
    } elseif (! is_null($daysLeft) && $daysLeft === 0) {
        $badge = ['text' => 'Due today', 'dot' => 'bg-amber-500', 'pulse' => true];
    } elseif (! is_null($daysLeft) && $daysLeft <= 7) {
        $badge = ['text' => "Due soon ({$daysLeft} " . Str::plural('day', $daysLeft) . ' left)', 'dot' => 'bg-amber-500', 'pulse' => true];
    } elseif (! is_null($daysLeft)) {
        $badge = ['text' => "Active ({$daysLeft} days left)", 'dot' => 'bg-tertiary', 'pulse' => false];
    } else {
        $badge = ['text' => 'Active', 'dot' => 'bg-tertiary', 'pulse' => false];
    }

    // How far through its timeline the project is (0–100), by dates
    $timelinePct = null;
    if ($project->start_date && $project->end_date) {
        $span        = max(1, $project->start_date->diffInDays($project->end_date));
        $elapsed     = $project->start_date->diffInDays(now(), false);
        $timelinePct = (int) max(0, min(100, round(($elapsed / $span) * 100)));
    }

    // Pace health: compares task progress with elapsed time
    if ($totalTasks === 0) {
        $health = ['label' => 'No tasks yet', 'class' => 'text-on-surface-variant', 'icon' => 'remove'];
    } elseif ($progress >= 100) {
        $health = ['label' => 'Completed', 'class' => 'text-tertiary', 'icon' => 'task_alt'];
    } elseif (! is_null($timelinePct) && ($progress + 10) < $timelinePct) {
        $health = ['label' => 'Behind', 'class' => 'text-error', 'icon' => 'trending_down'];
    } else {
        $health = ['label' => 'On Track', 'class' => 'text-tertiary', 'icon' => 'trending_up'];
    }

    // End-date card sub-text
    if (is_null($daysLeft)) {
        $endNote = ['No end date set', 'text-on-surface-variant'];
    } elseif ($daysLeft < 0) {
        $endNote = ['Overdue by ' . abs($daysLeft) . ' ' . Str::plural('day', abs($daysLeft)), 'text-error font-medium'];
    } elseif ($daysLeft === 0) {
        $endNote = ['Due today', 'text-amber-700 font-medium'];
    } else {
        $endNote = [$daysLeft . ' ' . Str::plural('day', $daysLeft) . ' remaining', $daysLeft <= 7 ? 'text-amber-700 font-medium' : 'text-on-surface-variant'];
    }
    $endCardOverdue = ! is_null($daysLeft) && $daysLeft < 0;

    // ---- Task pill styles ----------------------------------------------
    $priorityStyles = [
        'high'   => ['bg-error-container text-on-error-container',      'bg-error'],
        'medium' => ['bg-secondary-fixed text-on-secondary-fixed',      'bg-secondary'],
        'low'    => ['bg-surface-container-high text-on-surface-variant', 'bg-outline'],
    ];
@endphp

<x-app-layout>
    <div class="mx-auto flex w-full max-w-7xl flex-col gap-space-lg px-6 py-space-xl font-inter lg:px-12">

        {{-- Flash message --}}
        @if (session('success'))
            <div x-data="{ show: true }" x-show="show" x-transition.opacity role="status"
                 class="flex w-full items-center justify-between rounded-xl bg-surface-container-lowest p-space-md shadow-sm">
                <div class="flex min-w-0 items-center gap-space-sm">
                    <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-tertiary-fixed text-on-tertiary-fixed">
                        <span class="material-symbols-outlined text-[18px]">check_circle</span>
                    </div>
                    <span class="truncate text-title-sm text-on-surface">{{ session('success') }}</span>
                </div>
                <button type="button" @click="show = false" aria-label="Dismiss"
                        class="rounded-lg p-1 text-on-surface-variant transition-colors hover:bg-surface-container hover:text-on-surface">
                    <span class="material-symbols-outlined text-[18px]">close</span>
                </button>
            </div>
        @endif

        {{-- Breadcrumb & top actions --}}
        <div class="flex flex-col justify-between gap-space-md md:flex-row md:items-end">
            <div class="flex min-w-0 flex-col gap-space-xs">
                <nav class="flex items-center gap-2 text-label-md text-on-surface-variant" aria-label="Breadcrumb">
                    <a href="{{ route('projects.index') }}" class="flex items-center gap-1 transition-colors hover:text-primary">
                        <span class="material-symbols-outlined text-[16px]" aria-hidden="true">folder</span>
                        Projects
                    </a>
                    <span class="material-symbols-outlined text-[14px]" aria-hidden="true">chevron_right</span>
                    <span class="max-w-xs truncate text-title-sm text-on-surface md:max-w-md">{{ $project->name }}</span>
                </nav>
                <div class="mt-1 flex items-center gap-space-sm">
                    <h1 class="text-headline-lg tracking-tight text-on-surface">Project Details</h1>
                    <span class="rounded-full bg-primary-fixed px-2.5 py-0.5 text-label-sm uppercase tracking-wider text-on-primary-fixed">
                        Project #{{ $project->id }}
                    </span>
                </div>
            </div>

            <div class="flex items-center gap-space-sm">
                @can('tasks.create')
                    <a href="{{ route('projects.tasks.create', $project) }}"
                       class="flex h-9 items-center gap-1.5 rounded-xl bg-surface-container-lowest px-space-md text-label-md text-on-surface shadow-sm transition-colors hover:bg-surface-container">
                        <span class="material-symbols-outlined text-[18px]" aria-hidden="true">add</span>
                        New Task
                    </a>
                @endcan
                @can('projects.edit')
                    <a href="{{ route('projects.edit', $project) }}"
                       class="flex h-9 items-center gap-1.5 rounded-xl bg-primary-container px-space-md text-label-md text-on-primary shadow-sm transition-colors hover:bg-primary focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-container focus-visible:ring-offset-2">
                        <span class="material-symbols-outlined text-[18px]" aria-hidden="true">edit</span>
                        Edit Project
                    </a>
                @endcan
            </div>
        </div>

        {{-- Project overview --}}
        <div class="flex w-full flex-col overflow-hidden rounded-xl bg-surface-container-lowest shadow-sm">
            <div class="h-2 w-full bg-gradient-to-r from-primary-container via-secondary-container to-tertiary-fixed"></div>

            <div class="flex flex-col gap-space-lg p-space-lg">

                {{-- Title, badges & pace health --}}
                <div class="flex flex-col justify-between gap-space-md lg:flex-row lg:items-start">
                    <div class="flex max-w-3xl flex-col gap-space-xs">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="rounded-full bg-primary-fixed px-2.5 py-0.5 text-label-sm uppercase tracking-wider text-on-primary-fixed">
                                Project #{{ $project->id }}
                            </span>
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-surface-container-high px-3 py-1 text-label-sm text-on-surface">
                                <span class="h-2 w-2 rounded-full {{ $badge['dot'] }} {{ $badge['pulse'] ? 'animate-pulse' : '' }}" aria-hidden="true"></span>
                                {{ $badge['text'] }}
                            </span>
                        </div>
                        <h2 class="mt-1 text-headline-md text-on-surface">{{ $project->name }}</h2>
                        <p class="whitespace-pre-line text-body-md leading-relaxed text-on-surface-variant">
                            {{ $project->description ?: 'No description provided.' }}
                        </p>
                    </div>

                    <div class="flex shrink-0 items-center gap-space-md rounded-xl bg-surface-container-low p-space-md">
                        <div class="relative flex h-14 w-14 items-center justify-center">
                            <svg class="h-14 w-14 -rotate-90 transform" viewBox="0 0 36 36" aria-hidden="true">
                                <path class="text-surface-container-high" fill="none" stroke="currentColor" stroke-width="3.5"
                                      d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
                                <path class="text-tertiary" fill="none" stroke="currentColor" stroke-width="3.5" stroke-linecap="round"
                                      stroke-dasharray="{{ $progress }}, 100"
                                      d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
                            </svg>
                            <span class="absolute text-title-sm font-bold tabular-nums text-on-surface">{{ $progress }}%</span>
                        </div>
                        <div class="flex flex-col">
                            <span class="text-label-sm uppercase tracking-wider text-on-surface-variant"></span>
                            <span class="flex items-center gap-0.5 text-title-sm {{ $health['class'] }}">
                                <span class="material-symbols-outlined text-[16px]" aria-hidden="true">{{ $health['icon'] }}</span>
                                {{ $health['label'] }}
                            </span>
                            <span class="font-code-sm text-code-sm text-on-surface-variant">{{ $completedTasks }} / {{ $totalTasks }} delivered</span>
                        </div>
                    </div>
                </div>

                {{-- Overall completion --}}
                <div class="flex flex-col gap-space-xs rounded-xl bg-surface-container-low p-space-md">
                    <div class="flex items-center justify-between">
                        <span class="text-title-sm text-on-surface">Overall Completion</span>
                        <span class="text-title-sm font-semibold tabular-nums text-on-surface">
                            {{ $completedTasks }} / {{ $totalTasks }} {{ Str::plural('task', $totalTasks) }} · {{ $progress }}%
                        </span>
                    </div>
                    <div class="h-2.5 w-full overflow-hidden rounded-full bg-surface-container-high p-0.5">
                        <div class="h-full rounded-full bg-gradient-to-r from-primary-container via-secondary to-tertiary transition-all duration-500 ease-out"
                             style="width: {{ $progress }}%"></div>
                    </div>
                    <div class="flex items-center justify-between pt-1 text-label-sm text-on-surface-variant">
                        <span>{{ $project->start_date ? ($notStarted ? 'Starts: ' : 'Started: ') . $project->start_date->format('d M Y') : 'No start date' }}</span>
                        @if ($project->end_date)
                            <span class="font-medium text-secondary">Target end: {{ $project->end_date->format('d M Y') }}</span>
                        @endif
                    </div>
                </div>

                {{-- Meta grid --}}
                <div class="grid grid-cols-2 gap-space-md md:grid-cols-4">
                    <div class="flex flex-col gap-1 rounded-xl bg-surface-container-low p-space-md">
                        <span class="flex items-center gap-1 text-label-sm uppercase tracking-wider text-on-surface-variant">
                            <span class="material-symbols-outlined text-[14px]" aria-hidden="true">calendar_today</span>
                            Start Date
                        </span>
                        <span class="text-title-md text-on-surface">{{ $project->start_date?->format('d M Y') ?? 'Not set' }}</span>
                        <span class="text-body-sm text-on-surface-variant">{{ $project->start_date?->diffForHumans() ?? '—' }}</span>
                    </div>

                    <div class="relative flex flex-col gap-1 overflow-hidden rounded-xl p-space-md {{ $endCardOverdue ? 'bg-error-container/30' : 'bg-secondary-fixed/20' }}">
                        <div class="pointer-events-none absolute right-0 top-0 h-12 w-12 rounded-bl-full {{ $endCardOverdue ? 'bg-error-container/50' : 'bg-secondary-fixed/30' }}"></div>
                        <span class="flex items-center gap-1 text-label-sm uppercase tracking-wider text-on-secondary-fixed">
                            <span class="material-symbols-outlined text-[14px]" aria-hidden="true">event_busy</span>
                            End Date
                        </span>
                        <span class="text-title-md font-semibold text-on-surface">{{ $project->end_date?->format('d M Y') ?? 'Not set' }}</span>
                        <span class="text-body-sm {{ $endNote[1] }}">{{ $endNote[0] }}</span>
                    </div>

                    <div class="flex flex-col gap-1 rounded-xl bg-surface-container-low p-space-md">
                        <span class="flex items-center gap-1 text-label-sm uppercase tracking-wider text-on-surface-variant">
                            <span class="material-symbols-outlined text-[14px]" aria-hidden="true">add_circle</span>
                            Created
                        </span>
                        <span class="text-title-md text-on-surface">{{ $project->created_at->format('d M Y') }}</span>
                        <span class="text-body-sm text-on-surface-variant">{{ $project->created_at->diffForHumans() }}</span>
                    </div>

                    <div class="flex flex-col gap-1 rounded-xl bg-surface-container-low p-space-md">
                        <span class="flex items-center gap-1 text-label-sm uppercase tracking-wider text-on-surface-variant">
                            <span class="material-symbols-outlined text-[14px]" aria-hidden="true">update</span>
                            Last Updated
                        </span>
                        <span class="text-title-md text-on-surface">{{ $project->updated_at->diffForHumans() }}</span>
                        <span class="text-body-sm text-on-surface-variant">{{ $project->updated_at->format('d M Y, H:i') }}</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Project tasks --}}
        <div class="flex w-full flex-col overflow-hidden rounded-xl bg-surface-container-lowest shadow-sm"
             x-data="{
                 status: 'all',
                 priority: 'any',
                 q: '',
                 matches(group, priority, haystack) {
                     return (this.status === 'all' || this.status === group)
                         && (this.priority === 'any' || this.priority === priority)
                         && (this.q === '' || haystack.includes(this.q.toLowerCase()));
                 }
             }">

            {{-- Section header --}}
            <div class="flex flex-col justify-between gap-space-md bg-surface-container-lowest p-space-lg md:flex-row md:items-center">
                <div>
                    <div class="flex items-center gap-2">
                        <h3 class="text-title-md font-bold text-on-surface">Project Tasks</h3>
                        <span class="rounded-full bg-surface-container-high px-2 py-0.5 text-label-sm text-on-surface-variant">
                            {{ $totalTasks }} {{ Str::plural('task', $totalTasks) }} total
                        </span>
                    </div>
                    <p class="mt-0.5 text-body-sm text-on-surface-variant">Track deliverables, sprint milestones, and continuous QA validations</p>
                </div>

                <div class="flex flex-wrap items-center gap-space-sm">
                    @if ($tasks->isNotEmpty())
                        <div class="inline-flex rounded-xl bg-surface-container-low p-1">
                            @foreach ([
                                ['all',         'All',         $pageTasks->count()],
                                ['in_progress', 'In Progress', $pageInProgress],
                                ['completed',   'Completed',   $pageCompleted],
                            ] as [$key, $label, $n])
                                <button type="button" @click="status = @js($key)"
                                        :class="status === @js($key)
                                            ? 'bg-surface-container-lowest text-on-surface shadow-sm'
                                            : 'text-on-surface-variant hover:text-on-surface'"
                                        class="rounded-lg px-space-sm py-1 text-label-sm transition-colors">
                                    {{ $label }} ({{ $n }})
                                </button>
                            @endforeach
                        </div>
                    @endif
                    @can('tasks.create')
                    <a href="{{ route('projects.tasks.create', $project) }}"
                       class="flex h-9 items-center gap-1.5 rounded-xl bg-primary-container px-space-md text-label-md text-on-primary shadow-sm transition-colors hover:bg-primary">
                        <span class="material-symbols-outlined text-[18px]" aria-hidden="true">add_task</span>
                        Create Task
                    </a>
                    @endcan
                </div>
            </div>

            @if ($tasks->isEmpty())
                <div class="px-6 py-16 text-center">
                    <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-br from-primary-container to-secondary-container text-on-primary shadow-lg shadow-primary-container/30">
                        <span class="material-symbols-outlined text-[28px]">checklist</span>
                    </div>
                    <h4 class="mt-5 text-title-md text-on-surface">No tasks yet</h4>
                    <p class="mt-1 text-body-md text-on-surface-variant">Break this project into tasks to start tracking progress.</p>
                    <a href="{{ route('projects.tasks.create', $project) }}"
                       class="mt-6 inline-flex h-9 items-center rounded-xl bg-primary-container px-space-md text-label-md text-on-primary shadow-sm transition-colors hover:bg-primary">
                        Create your first task
                    </a>
                </div>
            @else
                {{-- Toolbar --}}
                <div class="flex flex-col items-center justify-between gap-space-sm bg-surface-container-low px-space-lg py-space-sm sm:flex-row">
                    <div class="flex w-full items-center gap-space-xs rounded-xl bg-surface-container-lowest px-space-md py-1.5 text-on-surface-variant shadow-sm sm:w-72">
                        <span class="material-symbols-outlined text-[18px]" aria-hidden="true">search</span>
                        <input type="text" x-model="q" placeholder="Filter tasks by name, id..."
                               class="w-full border-none bg-transparent p-0 text-body-sm text-on-surface outline-none ring-0 placeholder:text-on-surface-variant/70 focus:border-none focus:outline-none focus:ring-0" />
                    </div>

                    <div class="flex w-full items-center justify-end gap-space-sm sm:w-auto">
                        <label class="flex items-center gap-1.5 rounded-xl bg-surface-container-lowest px-3 py-1.5 text-label-sm text-on-surface-variant shadow-sm">
                            <span class="material-symbols-outlined text-[16px]" aria-hidden="true">filter_list</span>
                            <span>Priority:</span>
                            <select x-model="priority"
                                    class="cursor-pointer border-none bg-transparent p-0 pr-6 text-label-sm text-on-surface outline-none ring-0 focus:border-none focus:outline-none focus:ring-0">
                                <option value="any">Any</option>
                                <option value="high">High</option>
                                <option value="medium">Medium</option>
                                <option value="low">Low</option>
                            </select>
                        </label>
                    </div>
                </div>

                {{-- Table --}}
                <div class="w-full overflow-x-auto">
                    <table class="w-full border-collapse text-left">
                        <thead>
                            <tr class="h-10 bg-surface-container-low text-label-sm uppercase tracking-wider text-on-surface-variant">
                                <th scope="col" class="px-space-lg py-2.5">Task Title</th>
                                <th scope="col" class="px-space-md py-2.5">Assignee</th>
                                <th scope="col" class="px-space-md py-2.5">Due Date</th>
                                <th scope="col" class="px-space-md py-2.5">Priority</th>
                                <th scope="col" class="px-space-md py-2.5">Status</th>
                                <th scope="col" class="px-space-lg py-2.5 text-right">Actions</th>
                            </tr>
                        </thead>

                        <tbody>
                            @foreach ($tasks as $task)
                                @php
                                    $isDone     = in_array($task->status, $doneStatuses);
                                    $isProgress = $task->status === 'in_progress';
                                    $isOverdue  = $task->due_date && $task->due_date->isPast() && ! $isDone;
                                    $group      = $isDone ? 'completed' : ($isProgress ? 'in_progress' : 'other');
                                    $prioKey    = strtolower((string) $task->priority);
                                    [$prioPill, $prioDot] = $priorityStyles[$prioKey] ?? $priorityStyles['low'];
                                    $ticket     = 'TSK-' . $project->id . '-' . str_pad($task->id, 2, '0', STR_PAD_LEFT);
                                    $haystack   = Str::lower($task->title . ' ' . $ticket);
                                @endphp

                                <tr x-show="matches(@js($group), @js($prioKey), @js($haystack))"
                                    class="h-14 transition-colors hover:bg-surface-container-low/70 {{ $isOverdue ? 'bg-secondary-fixed/5' : 'bg-surface-container-lowest' }}">

                                    {{-- Title --}}
                                    <td class="min-w-[280px] px-space-lg py-3">
                                        <div class="flex items-center gap-space-sm">
                                            @if ($isDone)
                                                <span class="material-symbols-outlined shrink-0 text-[20px] text-tertiary" style="font-variation-settings: 'FILL' 1;" aria-hidden="true">check_box</span>
                                            @elseif ($isProgress)
                                                <span class="material-symbols-outlined shrink-0 text-[20px] text-secondary" aria-hidden="true">check_box_outline_blank</span>
                                            @else
                                                <span class="material-symbols-outlined shrink-0 text-[20px] text-outline" aria-hidden="true">radio_button_unchecked</span>
                                            @endif

                                            <div class="flex min-w-0 flex-col">
                                                <a href="{{ route('projects.tasks.show', [$project, $task]) }}"
                                                   class="max-w-xs truncate md:max-w-md {{ $isDone ? 'text-body-md text-on-surface-variant/60 line-through' : 'text-title-sm text-on-surface hover:text-primary' }}">
                                                    {{ $task->title }}
                                                </a>
                                                <span class="font-code-sm text-code-sm {{ $isDone ? 'text-on-surface-variant/50' : ($isProgress ? 'text-primary' : 'text-on-surface-variant/60') }}">
                                                    {{ $ticket }}
                                                </span>
                                            </div>
                                        </div>
                                    </td>

                                    {{-- Assignee --}}
                                    <td class="whitespace-nowrap px-space-md py-3">
                                        @if ($task->assignee)
                                            <div class="flex items-center gap-2">
                                                <span class="flex h-7 w-7 items-center justify-center rounded-full bg-primary-container text-[10px] font-semibold text-primary">
                                                    {{ strtoupper(substr($task->assignee->name, 0, 2)) }}
                                                </span>
                                                <span class="text-body-sm font-medium text-on-surface">{{ $task->assignee->name }}</span>
                                            </div>
                                        @else
                                            <span class="text-body-sm text-on-surface-variant">Unassigned</span>
                                        @endif
                                    </td>

                                    {{-- Due date --}}
                                    <td class="whitespace-nowrap px-space-md py-3">
                                        @if ($isOverdue)
                                            <span class="flex items-center gap-1 text-body-sm font-semibold text-on-error-container">
                                                <span class="material-symbols-outlined text-[14px]" aria-hidden="true">warning</span>
                                                {{ $task->due_date->format('d M Y') }}
                                            </span>
                                        @else
                                            <span class="text-body-sm {{ $isDone ? 'text-on-surface-variant' : 'font-medium text-on-surface' }}">
                                                {{ $task->due_date?->format('d M Y') ?? '—' }}
                                            </span>
                                        @endif
                                    </td>

                                    {{-- Priority --}}
                                    <td class="whitespace-nowrap px-space-md py-3">
                                        <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-label-sm {{ $prioPill }}">
                                            <span class="h-1.5 w-1.5 rounded-full {{ $prioDot }}" aria-hidden="true"></span>
                                            {{ ucfirst($task->priority) }}
                                        </span>
                                    </td>

                                    {{-- Status --}}
                                    <td class="whitespace-nowrap px-space-md py-3">
                                        @if ($isDone)
                                            <span class="inline-flex items-center gap-1.5 rounded-full bg-tertiary-fixed px-3 py-1 text-label-sm text-on-tertiary-fixed">
                                                <span class="material-symbols-outlined text-[14px]" aria-hidden="true">done</span>
                                                {{ ucfirst(str_replace('_', ' ', $task->status)) }}
                                            </span>
                                        @elseif ($isProgress)
                                            <span class="inline-flex items-center gap-1.5 rounded-full bg-secondary-fixed px-3 py-1 text-label-sm text-on-secondary-fixed">
                                                <span class="h-2 w-2 animate-pulse rounded-full bg-secondary" aria-hidden="true"></span>
                                                In Progress
                                            </span>
                                        @else
                                            <span class="inline-flex items-center gap-1.5 rounded-full bg-surface-container px-3 py-1 text-label-sm text-on-surface-variant">
                                                <span class="h-1.5 w-1.5 rounded-full bg-outline" aria-hidden="true"></span>
                                                {{ ucfirst(str_replace('_', ' ', $task->status)) }}
                                            </span>
                                        @endif
                                    </td>

                                    {{-- Actions --}}
                                    <td class="whitespace-nowrap px-space-lg py-3 text-right">
                                        <div class="flex items-center justify-end gap-1">
                                            <a href="{{ route('projects.tasks.show', [$project, $task]) }}" title="View Details"
                                               class="rounded-lg p-1.5 text-on-surface-variant transition-colors hover:bg-surface-container hover:text-on-surface">
                                                <span class="sr-only">View</span>
                                                <span class="material-symbols-outlined text-[18px]" aria-hidden="true">visibility</span>
                                            </a>
                                            @can('tasks.edit')
                                                <a href="{{ route('projects.tasks.edit', [$project, $task]) }}" title="Edit Task"
                                                   class="rounded-lg p-1.5 text-on-surface-variant transition-colors hover:bg-surface-container hover:text-on-surface">
                                                    <span class="sr-only">Edit</span>
                                                    <span class="material-symbols-outlined text-[18px]" aria-hidden="true">edit</span>
                                                </a>
                                            @endcan
                                            @can('tasks.update-status')
                                                <form action="{{ route('projects.tasks.update', [$project, $task]) }}" method="POST" class="inline">
                                                    @csrf
                                                    @method('PUT')
                                                    <input type="hidden" name="status" value="{{ $task->status === 'pending' ? 'in_progress' : ($task->status === 'in_progress' ? 'completed' : 'pending') }}">
                                                    <button type="submit" title="Update Status"
                                                            class="rounded-lg p-1.5 text-on-surface-variant transition-colors hover:bg-secondary-container hover:text-on-secondary-container">
                                                        <span class="sr-only">Update Status</span>
                                                        <span class="material-symbols-outlined text-[18px]" aria-hidden="true">sync</span>
                                                    </button>
                                                </form>
                                            @endcan
                                            @can('tasks.delete')
                                                <form action="{{ route('projects.tasks.destroy', [$project, $task]) }}" method="POST" class="inline"
                                                      onsubmit="return confirm('Delete this task?')">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" title="Delete Task"
                                                            class="rounded-lg p-1.5 text-on-surface-variant transition-colors hover:bg-error-container hover:text-error">
                                                        <span class="sr-only">Delete</span>
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

                {{-- Pagination --}}
                @if (method_exists($tasks, 'hasPages') && $tasks->hasPages())
                    <div class="flex flex-col items-center justify-between gap-space-sm bg-surface-container-lowest p-space-md sm:flex-row">
                        <span class="text-body-sm text-on-surface-variant">
                            Showing
                            <span class="font-semibold text-on-surface">{{ $tasks->firstItem() }}</span>
                            to
                            <span class="font-semibold text-on-surface">{{ $tasks->lastItem() }}</span>
                            of
                            <span class="font-semibold text-on-surface">{{ $tasks->total() }}</span>
                            tasks
                        </span>
                        <div>{{ $tasks->links() }}</div>
                    </div>
                @endif
            @endif
        </div>

        {{-- Footer actions --}}
        <div class="flex items-center justify-between gap-space-md pb-space-lg pt-space-md">
            <a href="{{ route('projects.index') }}"
               class="inline-flex items-center gap-1.5 text-label-md text-on-surface-variant transition-colors hover:text-on-surface">
                <span class="material-symbols-outlined text-[18px]" aria-hidden="true">arrow_back</span>
                Back to Projects
            </a>

            @can('projects.delete')
                <form action="{{ route('projects.destroy', $project) }}" method="POST"
                      onsubmit="return confirm('Are you sure you want to delete this project? All its tasks will be removed.')">
                    @csrf
                    @method('DELETE')
                    <button type="submit"
                            class="flex h-9 items-center gap-1.5 rounded-xl bg-error-container px-space-md text-label-md text-on-error-container shadow-sm transition-colors hover:bg-error hover:text-on-error focus:outline-none focus-visible:ring-2 focus-visible:ring-error focus-visible:ring-offset-2">
                        <span class="material-symbols-outlined text-[18px]" aria-hidden="true">delete</span>
                        Delete Project
                    </button>
                </form>
            @endcan
        </div>
    </div>
</x-app-layout>