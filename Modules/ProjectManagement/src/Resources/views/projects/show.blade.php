{{-- View: projects.show --}}
@php
    $doneStatuses = ['completed', 'done'];
    $today        = now()->startOfDay();

    // ---- Theme classes (one place to tweak light / dark surfaces) -------
    // Light values are your existing tokens; dark values are neutral blacks.
    $cx = [
        'card'  => 'bg-surface-container-lowest shadow-sm ring-1 ring-black/5 dark:bg-neutral-950 dark:shadow-none dark:ring-white/10',
        'inset' => 'bg-surface-container-low dark:bg-neutral-900',
        'chip'  => 'bg-surface-container-high dark:bg-neutral-800',
        'text'  => 'text-on-surface dark:text-neutral-50',
        'muted' => 'text-on-surface-variant dark:text-neutral-400',
        'hover' => 'hover:bg-surface-container dark:hover:bg-neutral-800',
        'focus' => 'focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-container focus-visible:ring-offset-2 focus-visible:ring-offset-white dark:focus-visible:ring-primary-fixed dark:focus-visible:ring-offset-black',
    ];

    // ---- Task counts ---------------------------------------------------
    $totalTasks      = method_exists($tasks, 'total') ? $tasks->total() : $tasks->count();
    $visibleTaskScope = $project->tasks()->visibleTo(auth()->user());
    $completedTasks  = (clone $visibleTaskScope)->whereIn('status', $doneStatuses)->count();
    $inProgressTotal = (clone $visibleTaskScope)->where('status', 'in_progress')->count();
    $todoTotal       = max(0, $totalTasks - $completedTasks - $inProgressTotal);
    $overdueTotal    = (clone $visibleTaskScope)->whereNotIn('status', $doneStatuses)->whereDate('due_date', '<', $today)->count();

    $progress = $totalTasks > 0 ? (int) round(($completedTasks / $totalTasks) * 100) : 0;
    $pctProg  = $totalTasks > 0 ? min(100 - $progress, (int) round(($inProgressTotal / $totalTasks) * 100)) : 0;

    // Tasks visible on this page (used for the client-side filters)
    $pageTasks      = method_exists($tasks, 'getCollection') ? $tasks->getCollection() : collect($tasks);
    $pageInProgress = $pageTasks->where('status', 'in_progress')->count();
    $pageCompleted  = $pageTasks->filter(fn ($t) => in_array($t->status, $doneStatuses))->count();
    $pageTodo       = $pageTasks->count() - $pageInProgress - $pageCompleted;

    // ---- Project dates / status ---------------------------------------
    $daysLeft   = $project->end_date ? (int) $today->diffInDays($project->end_date, false) : null;
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
        $badge = ['text' => "Active ({$daysLeft} days left)", 'dot' => 'bg-tertiary dark:bg-tertiary-fixed', 'pulse' => false];
    } else {
        $badge = ['text' => 'Active', 'dot' => 'bg-tertiary dark:bg-tertiary-fixed', 'pulse' => false];
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
        $health = ['label' => 'No tasks yet', 'class' => 'text-on-surface-variant dark:text-neutral-400', 'icon' => 'remove'];
    } elseif ($progress >= 100) {
        $health = ['label' => 'Completed', 'class' => 'text-tertiary dark:text-tertiary-fixed', 'icon' => 'task_alt'];
    } elseif (! is_null($timelinePct) && ($progress + 10) < $timelinePct) {
        $health = ['label' => 'Behind', 'class' => 'text-error dark:text-error-container', 'icon' => 'trending_down'];
    } else {
        $health = ['label' => 'On Track', 'class' => 'text-tertiary dark:text-tertiary-fixed', 'icon' => 'trending_up'];
    }

    // End-date card sub-text
    if (is_null($daysLeft)) {
        $endNote = ['No end date set', $cx['muted']];
    } elseif ($daysLeft < 0) {
        $endNote = ['Overdue by ' . abs($daysLeft) . ' ' . Str::plural('day', abs($daysLeft)), 'text-error font-medium dark:text-error-container'];
    } elseif ($daysLeft === 0) {
        $endNote = ['Due today', 'text-amber-700 font-medium dark:text-amber-400'];
    } else {
        $endNote = [$daysLeft . ' ' . Str::plural('day', $daysLeft) . ' remaining', $daysLeft <= 7 ? 'text-amber-700 font-medium dark:text-amber-400' : $cx['muted']];
    }
    $endCardOverdue = ! is_null($daysLeft) && $daysLeft < 0;

    // ---- Task pill styles ----------------------------------------------
    $priorityStyles = [
        'high'   => ['bg-error-container text-on-error-container',                                   'bg-error'],
        'medium' => ['bg-secondary-fixed text-on-secondary-fixed',                                   'bg-secondary'],
        'low'    => ['bg-surface-container-high text-on-surface-variant dark:bg-neutral-800 dark:text-neutral-300', 'bg-outline'],
    ];

    // ---- Per-row filter metadata (drives the Alpine filters) -----------
    $rowMeta = [];
    foreach ($pageTasks as $t) {
        $d = in_array($t->status, $doneStatuses);
        $rowMeta['t' . $t->id] = [
            'g' => $d ? 'completed' : ($t->status === 'in_progress' ? 'in_progress' : 'other'),
            'p' => strtolower((string) $t->priority),
            'h' => Str::lower($t->title . ' TSK-' . $project->id . '-' . str_pad($t->id, 2, '0', STR_PAD_LEFT)),
        ];
    }

    $filterTabs = [
        ['all',         'All',         $pageTasks->count()],
        ['other',       'To do',       $pageTodo],
        ['in_progress', 'In progress', $pageInProgress],
        ['completed',   'Completed',   $pageCompleted],
    ];
@endphp

<x-app-layout>

    <style>
        [x-cloak]{display:none !important}

        @keyframes project-view-enter {
            from { opacity: 0; transform: translateY(14px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @keyframes project-view-fill {
            from { transform: scaleX(0); }
            to { transform: scaleX(1); }
        }

        @keyframes project-view-donut {
            from { stroke-dasharray: 0 100; }
            to { stroke-dasharray: var(--project-progress) 100; }
        }

        .project-view-enter {
            animation: project-view-enter .55s cubic-bezier(.22,.8,.25,1) both;
        }

        .project-view-fill {
            transform: scaleX(0);
            transform-origin: left center;
            animation: project-view-fill .7s cubic-bezier(.22,.8,.25,1) both;
        }

        .project-view-donut {
            animation: project-view-donut .9s cubic-bezier(.22,.8,.25,1) both;
        }

        .project-view-row {
            animation: project-view-enter .42s cubic-bezier(.22,.8,.25,1) both;
        }

        @keyframes project-view-sweep {
            0% { background-position: 200% -200%; }
            100% { background-position: -200% 200%; }
        }

        .dark .project-view-sweep {
            background-image: linear-gradient(225deg, transparent 40%, rgba(255,255,255,.10) 50%, transparent 60%);
            background-size: 200% 200%;
            animation: project-view-sweep 6s linear infinite;
        }

        @media (prefers-reduced-motion: reduce) {
            .project-view-enter,
            .project-view-fill,
            .project-view-donut,
            .project-view-row,
            .project-view-sweep { animation: none !important; transform: none !important; }
        }
    </style>

    {{-- Theme wrapper: toggles the `dark` class on <html> and remembers the choice --}}
    <div class="min-h-[calc(100vh-4rem)] bg-transparent dark:bg-black"
         x-data="{
             dark: document.documentElement.classList.contains('dark'),
             toggle() {
                 this.dark = ! this.dark;
                 document.documentElement.classList.toggle('dark', this.dark);
                 try { localStorage.setItem('theme', this.dark ? 'dark' : 'light'); } catch (e) {}
             }
         }">
    <div class="mx-auto flex w-full max-w-7xl flex-col gap-space-lg px-6 py-space-xl font-inter lg:px-12">

        {{-- Flash message --}}
        @if (session('success'))
            <div x-data="{ show: true }" x-show="show" x-transition.opacity role="status"
                 class="flex w-full items-center justify-between rounded-xl p-space-md {{ $cx['card'] }}">
                <div class="flex min-w-0 items-center gap-space-sm">
                    <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-tertiary-fixed text-on-tertiary-fixed">
                        <span class="material-symbols-outlined text-[18px]" aria-hidden="true">check_circle</span>
                    </div>
                    <span class="truncate text-title-sm {{ $cx['text'] }}">{{ session('success') }}</span>
                </div>
                <button type="button" @click="show = false" aria-label="Dismiss"
                        class="rounded-lg p-1 {{ $cx['muted'] }} {{ $cx['hover'] }} {{ $cx['focus'] }} transition-colors">
                    <span class="material-symbols-outlined text-[18px]" aria-hidden="true">close</span>
                </button>
            </div>
        @endif

        {{-- Breadcrumb & top actions --}}
        <div class="project-view-enter flex flex-col justify-between gap-space-md md:flex-row md:items-end">
            <div class="flex min-w-0 flex-col gap-space-xs">
                <nav class="flex items-center gap-2 text-label-md {{ $cx['muted'] }}" aria-label="Breadcrumb">
                    <a href="{{ route('projects.index') }}" class="flex items-center gap-1 rounded transition-colors hover:text-primary dark:hover:text-primary-fixed {{ $cx['focus'] }}">
                        <span class="material-symbols-outlined text-[16px]" aria-hidden="true">folder</span>
                        Projects
                    </a>
                    <span class="material-symbols-outlined text-[14px]" aria-hidden="true">chevron_right</span>
                    <span class="max-w-xs truncate text-title-sm md:max-w-md {{ $cx['text'] }}">{{ $project->name }}</span>
                </nav>
                <h1 class="mt-1 text-headline-lg tracking-tight {{ $cx['text'] }}">Project Details</h1>
            </div>

            <div class="flex items-center gap-space-sm">
                {{-- Theme toggle --}}
                <button type="button" @click="toggle()" :aria-pressed="dark.toString()" aria-label="Toggle dark mode"
                        :title="dark ? 'Switch to light theme' : 'Switch to dark theme'"
                        class="grid h-9 w-9 place-items-center rounded-xl transition-colors {{ $cx['card'] }} {{ $cx['muted'] }} {{ $cx['hover'] }} {{ $cx['focus'] }}">
                    <span x-show="!dark" class="material-symbols-outlined text-[20px]" aria-hidden="true">dark_mode</span>
                    <span x-show="dark" x-cloak class="material-symbols-outlined text-[20px]" aria-hidden="true">light_mode</span>
                </button>

                @can('tasks.create')
                    <a href="{{ route('projects.tasks.create', $project) }}"
                       class="flex h-9 items-center gap-1.5 rounded-xl px-space-md text-label-md transition-colors {{ $cx['card'] }} {{ $cx['text'] }} {{ $cx['hover'] }} {{ $cx['focus'] }}">
                        <span class="material-symbols-outlined text-[18px]" aria-hidden="true">add</span>
                        New Task
                    </a>
                @endcan
                @can('projects.edit')
                    <a href="{{ route('projects.edit', $project) }}"
                       class="flex h-9 items-center gap-1.5 rounded-xl bg-primary-container px-space-md text-label-md text-on-primary shadow-sm transition-colors hover:bg-primary {{ $cx['focus'] }}">
                        <span class="material-symbols-outlined text-[18px]" aria-hidden="true">edit</span>
                        Edit Project
                    </a>
                @endcan
            </div>
        </div>

        {{-- ───────── Project overview ───────── --}}
        <div class="project-view-enter flex w-full flex-col overflow-hidden rounded-xl {{ $cx['card'] }} project-view-sweep" style="animation-delay: 80ms">
            <div class="h-2 w-full bg-gradient-to-r from-primary-container via-secondary-container to-tertiary-fixed"></div>

            <div class="flex flex-col gap-space-lg p-space-lg">

                {{-- Title, badges & pace health --}}
                <div class="flex flex-col justify-between gap-space-md lg:flex-row lg:items-start">
                    <div class="flex max-w-3xl flex-col gap-space-xs">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="rounded-full bg-primary-fixed px-2.5 py-0.5 text-label-sm uppercase tracking-wider text-on-primary-fixed">
                                Project #{{ $project->id }}
                            </span>
                            <span class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-label-sm {{ $cx['chip'] }} {{ $cx['text'] }}">
                                <span class="h-2 w-2 rounded-full {{ $badge['dot'] }} {{ $badge['pulse'] ? 'motion-safe:animate-pulse' : '' }}" aria-hidden="true"></span>
                                {{ $badge['text'] }}
                            </span>
                        </div>
                        <h2 class="mt-1 text-headline-md {{ $cx['text'] }}">{{ $project->name }}</h2>
                        <p class="whitespace-pre-line text-body-md leading-relaxed {{ $cx['muted'] }}">
                            {{ $project->description ?: 'No description provided.' }}
                        </p>
                    </div>

                    <div class="flex shrink-0 items-center gap-space-md rounded-xl p-space-md {{ $cx['inset'] }}">
                        <div class="relative flex h-14 w-14 items-center justify-center">
                            <svg class="h-14 w-14 -rotate-90 transform" viewBox="0 0 36 36" aria-hidden="true">
                                <path class="text-surface-container-high dark:text-neutral-800" fill="none" stroke="currentColor" stroke-width="3.5"
                                      d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
                                <path class="project-view-donut text-tertiary dark:text-tertiary-fixed" fill="none" stroke="currentColor" stroke-width="3.5" stroke-linecap="round"
                                      stroke-dasharray="{{ $progress }}, 100"
                                      style="--project-progress: {{ $progress }}; animation-delay: 250ms"
                                      d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
                            </svg>
                            <span class="absolute text-title-sm font-bold tabular-nums {{ $cx['text'] }}">{{ $progress }}%</span>
                        </div>
                        <div class="flex flex-col">
                            <span class="text-label-sm uppercase tracking-wider {{ $cx['muted'] }}">Pace</span>
                            <span class="flex items-center gap-0.5 text-title-sm {{ $health['class'] }}">
                                <span class="material-symbols-outlined text-[16px]" aria-hidden="true">{{ $health['icon'] }}</span>
                                {{ $health['label'] }}
                            </span>
                            <span class="font-code-sm text-code-sm {{ $cx['muted'] }}">{{ $completedTasks }} / {{ $totalTasks }} delivered</span>
                        </div>
                    </div>
                </div>

                {{-- Overall completion --}}
                <div class="flex flex-col gap-space-sm rounded-xl p-space-md {{ $cx['inset'] }}">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <span class="text-title-sm {{ $cx['text'] }}">Overall Completion</span>
                        <span class="text-title-sm font-semibold tabular-nums {{ $cx['text'] }}">
                            {{ $completedTasks }} / {{ $totalTasks }} {{ Str::plural('task', $totalTasks) }} · {{ $progress }}%
                        </span>
                    </div>

                    {{-- Segmented bar: done + in progress --}}
                    <div class="flex h-2.5 w-full overflow-hidden rounded-full {{ $cx['chip'] }}"
                         role="progressbar" aria-label="Project completion" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $progress }}">
                        <div class="project-view-fill h-full bg-tertiary dark:bg-tertiary-fixed" style="width: {{ $progress }}%; animation-delay: 240ms"></div>
                        <div class="project-view-fill h-full bg-secondary dark:bg-secondary-fixed" style="width: {{ $pctProg }}%; animation-delay: 360ms"></div>
                    </div>

                    {{-- Legend --}}
                    <div class="flex flex-wrap items-center gap-x-space-md gap-y-1 text-label-sm {{ $cx['muted'] }}">
                        <span class="inline-flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-tertiary dark:bg-tertiary-fixed" aria-hidden="true"></span>{{ $completedTasks }} completed</span>
                        <span class="inline-flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-secondary dark:bg-secondary-fixed" aria-hidden="true"></span>{{ $inProgressTotal }} in progress</span>
                        <span class="inline-flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-outline" aria-hidden="true"></span>{{ $todoTotal }} to do</span>
                        @if ($overdueTotal > 0)
                            <span class="inline-flex items-center gap-1 rounded-full bg-error-container px-2 py-0.5 font-medium text-on-error-container">
                                <span class="material-symbols-outlined text-[14px]" aria-hidden="true">warning</span>
                                {{ $overdueTotal }} overdue
                            </span>
                        @endif
                    </div>

                    {{-- Timeline elapsed vs work done --}}
                    @if (! is_null($timelinePct))
                        <div class="flex flex-col gap-1 pt-1">
                            <div class="flex items-center justify-between text-label-sm {{ $cx['muted'] }}">
                                <span>Timeline elapsed</span>
                                <span class="tabular-nums">{{ $timelinePct }}%</span>
                            </div>
                            <div class="h-1.5 w-full overflow-hidden rounded-full {{ $cx['chip'] }}" aria-hidden="true">
                                <div class="project-view-fill h-full rounded-full bg-amber-500" style="width: {{ $timelinePct }}%; animation-delay: 460ms"></div>
                            </div>
                        </div>
                    @endif

                    <div class="flex items-center justify-between pt-1 text-label-sm {{ $cx['muted'] }}">
                        <span>{{ $project->start_date ? ($notStarted ? 'Starts: ' : 'Started: ') . $project->start_date->format('d M Y') : 'No start date' }}</span>
                        @if ($project->end_date)
                            <span class="font-medium text-secondary dark:text-secondary-fixed">Target end: {{ $project->end_date->format('d M Y') }}</span>
                        @endif
                    </div>
                </div>

                {{-- Meta grid --}}
                <div class="grid grid-cols-2 gap-space-md md:grid-cols-4">
                    <div class="project-view-enter flex flex-col gap-1 rounded-xl p-space-md {{ $cx['inset'] }}" style="animation-delay: 180ms">
                        <span class="flex items-center gap-1 text-label-sm uppercase tracking-wider {{ $cx['muted'] }}">
                            <span class="material-symbols-outlined text-[14px]" aria-hidden="true">calendar_today</span>
                            Start Date
                        </span>
                        <span class="text-title-md {{ $cx['text'] }}">{{ $project->start_date?->format('d M Y') ?? 'Not set' }}</span>
                        <span class="text-body-sm {{ $cx['muted'] }}">{{ $project->start_date?->diffForHumans() ?? '—' }}</span>
                    </div>

                    <div class="project-view-enter relative flex flex-col gap-1 overflow-hidden rounded-xl p-space-md {{ $endCardOverdue ? 'bg-error-container/30 dark:bg-error/10' : 'bg-secondary-fixed/20 dark:bg-secondary-fixed/10' }}" style="animation-delay: 240ms">
                        <div class="pointer-events-none absolute right-0 top-0 h-12 w-12 rounded-bl-full {{ $endCardOverdue ? 'bg-error-container/50 dark:bg-error/20' : 'bg-secondary-fixed/30 dark:bg-secondary-fixed/10' }}"></div>
                        <span class="flex items-center gap-1 text-label-sm uppercase tracking-wider text-on-secondary-fixed dark:text-secondary-fixed">
                            <span class="material-symbols-outlined text-[14px]" aria-hidden="true">event_busy</span>
                            End Date
                        </span>
                        <span class="text-title-md font-semibold {{ $cx['text'] }}">{{ $project->end_date?->format('d M Y') ?? 'Not set' }}</span>
                        <span class="text-body-sm {{ $endNote[1] }}">{{ $endNote[0] }}</span>
                    </div>

                    <div class="project-view-enter flex flex-col gap-1 rounded-xl p-space-md {{ $cx['inset'] }}" style="animation-delay: 300ms">
                        <span class="flex items-center gap-1 text-label-sm uppercase tracking-wider {{ $cx['muted'] }}">
                            <span class="material-symbols-outlined text-[14px]" aria-hidden="true">add_circle</span>
                            Created
                        </span>
                        <span class="text-title-md {{ $cx['text'] }}">{{ $project->created_at->format('d M Y') }}</span>
                        <span class="text-body-sm {{ $cx['muted'] }}">{{ $project->created_at->diffForHumans() }}</span>
                    </div>

                    <div class="project-view-enter flex flex-col gap-1 rounded-xl p-space-md {{ $cx['inset'] }}" style="animation-delay: 360ms">
                        <span class="flex items-center gap-1 text-label-sm uppercase tracking-wider {{ $cx['muted'] }}">
                            <span class="material-symbols-outlined text-[14px]" aria-hidden="true">update</span>
                            Last Updated
                        </span>
                        <span class="text-title-md {{ $cx['text'] }}">{{ $project->updated_at->diffForHumans() }}</span>
                        <span class="text-body-sm {{ $cx['muted'] }}">{{ $project->updated_at->format('d M Y, H:i') }}</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- ───────── Project Attachments ───────── --}}
        @include('ProjectManagement::attachments._section', [
            'attachable' => $project,
            'title' => 'Project Attachments',
            'subtitle' => 'Deliverables, specifications, design assets, and project files',
            'uploadRoute' => route('projects.attachments.store', $project),
            'canUpload' => auth()->user()->can('projects.edit') || auth()->user()->can('projects.create'),
        ])

        {{-- ───────── Project tasks ───────── --}}
        <div class="project-view-enter flex w-full flex-col overflow-hidden rounded-xl {{ $cx['card'] }}" style="animation-delay: 240ms"
             x-data="{
                 status: 'all',
                 priority: 'any',
                 q: '',
                 rows: @js($rowMeta),
                 ok(id) {
                     const r = this.rows[id];
                     return (this.status === 'all' || this.status === r.g)
                         && (this.priority === 'any' || this.priority === r.p)
                         && (this.q === '' || r.h.includes(this.q.toLowerCase()));
                 },
                 get visible() { return Object.keys(this.rows).filter(id => this.ok(id)).length; },
                 get filtered() { return this.status !== 'all' || this.priority !== 'any' || this.q !== ''; },
                 reset() { this.status = 'all'; this.priority = 'any'; this.q = ''; }
             }">

            {{-- Section header --}}
            <div class="flex flex-col justify-between gap-space-md p-space-lg md:flex-row md:items-center">
                <div>
                    <div class="flex items-center gap-2">
                        <h3 class="text-title-md font-bold {{ $cx['text'] }}">Project Tasks</h3>
                        <span class="rounded-full px-2 py-0.5 text-label-sm {{ $cx['chip'] }} {{ $cx['muted'] }}">
                            {{ $totalTasks }} {{ Str::plural('task', $totalTasks) }} total
                        </span>
                    </div>
                    <p class="mt-0.5 text-body-sm {{ $cx['muted'] }}">Track deliverables, sprint milestones, and continuous QA validations</p>
                </div>

                <div class="flex flex-wrap items-center gap-space-sm">
                    @if ($tasks->isNotEmpty())
                        <div class="inline-flex flex-wrap rounded-xl p-1 {{ $cx['inset'] }}" role="group" aria-label="Filter by status">
                            @foreach ($filterTabs as [$key, $label, $n])
                                <button type="button" @click="status = @js($key)"
                                        :aria-pressed="(status === @js($key)).toString()"
                                        :class="status === @js($key)
                                            ? 'bg-surface-container-lowest text-on-surface shadow-sm dark:bg-neutral-700 dark:text-white'
                                            : 'text-on-surface-variant hover:text-on-surface dark:text-neutral-400 dark:hover:text-white'"
                                        class="rounded-lg px-space-sm py-1 text-label-sm transition-colors {{ $cx['focus'] }}">
                                    {{ $label }} ({{ $n }})
                                </button>
                            @endforeach
                        </div>
                    @endif
                    @can('tasks.create')
                        <a href="{{ route('projects.tasks.create', $project) }}"
                           class="flex h-9 items-center gap-1.5 rounded-xl bg-primary-container px-space-md text-label-md text-on-primary shadow-sm transition-colors hover:bg-primary {{ $cx['focus'] }}">

                            + Create Task
                        </a>
                    @endcan
                </div>
            </div>

            @if ($tasks->isEmpty())
                <div class="px-6 py-16 text-center">
                    <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-br from-primary-container to-secondary-container text-on-primary shadow-lg shadow-primary-container/30 dark:shadow-none">
                        <span class="material-symbols-outlined text-[28px]" aria-hidden="true">checklist</span>
                    </div>
                    <h4 class="mt-5 text-title-md {{ $cx['text'] }}">No tasks yet</h4>
                    <p class="mt-1 text-body-md {{ $cx['muted'] }}">Break this project into tasks to start tracking progress.</p>
                    @can('tasks.create')
                        <a href="{{ route('projects.tasks.create', $project) }}"
                           class="mt-6 inline-flex h-9 items-center rounded-xl bg-primary-container px-space-md text-label-md text-on-primary shadow-sm transition-colors hover:bg-primary {{ $cx['focus'] }}">
                            Create your first task
                        </a>
                    @endcan
                </div>
            @else
                {{-- Toolbar --}}
                <div class="flex flex-col items-center justify-between gap-space-sm px-space-lg py-space-sm sm:flex-row {{ $cx['inset'] }}">
                    <div class="flex w-full items-center gap-space-xs rounded-xl px-space-md py-1.5 shadow-sm ring-1 ring-black/5 focus-within:ring-2 focus-within:ring-primary-container dark:bg-neutral-950 dark:ring-white/10 dark:focus-within:ring-primary-fixed sm:w-72 bg-surface-container-lowest {{ $cx['muted'] }}">
                        <span class="material-symbols-outlined text-[18px]" aria-hidden="true">search</span>
                        <input type="text" x-model="q" placeholder="Filter tasks by name, id..." aria-label="Filter tasks"
                               class="w-full border-none bg-transparent p-0 text-body-sm outline-none ring-0 placeholder:text-on-surface-variant/70 focus:border-none focus:outline-none focus:ring-0 {{ $cx['text'] }} dark:placeholder:text-neutral-500" />
                        <button type="button" x-show="q !== ''" x-cloak @click="q = ''" aria-label="Clear search"
                                class="rounded p-0.5 {{ $cx['hover'] }}">
                            <span class="material-symbols-outlined text-[16px]" aria-hidden="true">close</span>
                        </button>
                    </div>

                    <div class="flex w-full flex-wrap items-center justify-end gap-space-sm sm:w-auto">
                        <span class="text-label-sm {{ $cx['muted'] }}">
                            <span x-text="visible"></span> of {{ $pageTasks->count() }} shown
                            @if (method_exists($tasks, 'hasPages') && $tasks->hasPages())
                                on this page
                            @endif
                        </span>
                        <label class="flex items-center gap-1.5 rounded-xl bg-surface-container-lowest px-3 py-1.5 text-label-sm shadow-sm ring-1 ring-black/5 dark:bg-neutral-950 dark:ring-white/10 {{ $cx['muted'] }}">
                            <span class="material-symbols-outlined text-[16px]" aria-hidden="true">filter_list</span>
                            <span>Priority:</span>
                            <select x-model="priority"
                                    class="cursor-pointer border-none bg-transparent p-0 pr-6 text-label-sm outline-none ring-0 focus:border-none focus:outline-none focus:ring-0 dark:[color-scheme:dark] {{ $cx['text'] }}">
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
                            <tr class="h-10 text-label-sm uppercase tracking-wider {{ $cx['inset'] }} {{ $cx['muted'] }}">
                                <th scope="col" class="px-space-lg py-2.5">Task Title</th>
                                <th scope="col" class="px-space-md py-2.5">Assignee</th>
                                <th scope="col" class="px-space-md py-2.5">Due Date</th>
                                <th scope="col" class="px-space-md py-2.5">Priority</th>
                                <th scope="col" class="px-space-md py-2.5">Status</th>
                                <th scope="col" class="px-space-lg py-2.5 text-right">Actions</th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-black/5 dark:divide-white/10">
                            @foreach ($tasks as $task)
                                @php
                                    $isDone     = in_array($task->status, $doneStatuses);
                                    $isProgress = $task->status === 'in_progress';
                                    $isOverdue  = $task->due_date && $task->due_date->lt($today) && ! $isDone;
                                    $prioKey    = strtolower((string) $task->priority);
                                    [$prioPill, $prioDot] = $priorityStyles[$prioKey] ?? $priorityStyles['low'];
                                    $ticket     = 'TSK-' . $project->id . '-' . str_pad($task->id, 2, '0', STR_PAD_LEFT);
                                @endphp

                                <tr x-show="ok('t{{ $task->id }}')" style="animation-delay: {{ 260 + ($loop->index * 55) }}ms"
                                    class="project-view-row h-14 transition-colors hover:bg-surface-container-low/70 dark:hover:bg-neutral-900 {{ $isOverdue ? 'bg-error-container/20 dark:bg-error/10' : 'bg-surface-container-lowest dark:bg-neutral-950' }}">

                                    {{-- Title --}}
                                    <td class="min-w-[280px] px-space-lg py-3">
                                        <div class="flex items-center gap-space-sm">
                                            @if ($isDone)
                                                <span class="material-symbols-outlined shrink-0 text-[20px] text-tertiary dark:text-tertiary-fixed" style="font-variation-settings: 'FILL' 1;" aria-hidden="true">check_box</span>
                                            @elseif ($isProgress)
                                                <span class="material-symbols-outlined shrink-0 text-[20px] text-secondary dark:text-secondary-fixed" aria-hidden="true">check_box_outline_blank</span>
                                            @else
                                                <span class="material-symbols-outlined shrink-0 text-[20px] text-outline" aria-hidden="true">radio_button_unchecked</span>
                                            @endif

                                            <div class="flex min-w-0 flex-col">
                                                <a href="{{ route('projects.tasks.show', [$project, $task]) }}"
                                                   class="max-w-xs truncate rounded md:max-w-md {{ $cx['focus'] }} {{ $isDone ? 'text-body-md text-on-surface-variant/60 line-through dark:text-neutral-500' : 'text-title-sm text-on-surface hover:text-primary dark:text-neutral-50 dark:hover:text-primary-fixed' }}">
                                                    {{ $task->title }}
                                                </a>
                                                <span class="font-code-sm text-code-sm {{ $isDone ? 'text-on-surface-variant/50 dark:text-neutral-600' : ($isProgress ? 'text-primary dark:text-primary-fixed' : 'text-on-surface-variant/60 dark:text-neutral-500') }}">
                                                    {{ $ticket }}
                                                </span>
                                            </div>
                                        </div>
                                    </td>

                                    {{-- Assignee --}}
                                    <td class="whitespace-nowrap px-space-md py-3">
                                        @if ($task->assignee)
                                            <div class="flex items-center gap-2">
                                                <span class="flex h-7 w-7 items-center justify-center rounded-full bg-primary-container text-[10px] font-semibold text-on-primary" aria-hidden="true">
                                                    {{ strtoupper(mb_substr($task->assignee->name, 0, 2)) }}
                                                </span>
                                                <span class="text-body-sm font-medium {{ $cx['text'] }}">{{ $task->assignee->name }}</span>
                                            </div>
                                        @else
                                            <span class="text-body-sm {{ $cx['muted'] }}">Unassigned</span>
                                        @endif
                                    </td>

                                    {{-- Due date --}}
                                    <td class="whitespace-nowrap px-space-md py-3">
                                        @if ($isOverdue)
                                            <span class="flex items-center gap-1 text-body-sm font-semibold text-error dark:text-error-container">
                                                <span class="material-symbols-outlined text-[14px]" aria-hidden="true">warning</span>
                                                {{ $task->due_date->format('d M Y') }}
                                            </span>
                                            <span class="block text-label-sm text-error/80 dark:text-error-container/70">{{ $task->due_date->diffForHumans() }}</span>
                                        @else
                                            <span class="block text-body-sm {{ $isDone ? $cx['muted'] : 'font-medium ' . $cx['text'] }}">
                                                {{ $task->due_date?->format('d M Y') ?? '—' }}
                                            </span>
                                            @if ($task->due_date && ! $isDone)
                                                <span class="block text-label-sm {{ $cx['muted'] }}">{{ $task->due_date->diffForHumans() }}</span>
                                            @endif
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
                                                <span class="h-2 w-2 rounded-full bg-secondary motion-safe:animate-pulse" aria-hidden="true"></span>
                                                In Progress
                                            </span>
                                        @else
                                            <span class="inline-flex items-center gap-1.5 rounded-full bg-surface-container px-3 py-1 text-label-sm text-on-surface-variant dark:bg-neutral-800 dark:text-neutral-300">
                                                <span class="h-1.5 w-1.5 rounded-full bg-outline" aria-hidden="true"></span>
                                                {{ ucfirst(str_replace('_', ' ', $task->status)) }}
                                            </span>
                                        @endif
                                    </td>

                                    {{-- Actions --}}
                                    <td class="whitespace-nowrap px-space-lg py-3 text-right">
                                        <div class="flex items-center justify-end gap-1">
                                            <a href="{{ route('projects.tasks.show', [$project, $task]) }}" title="View Details"
                                               class="rounded-lg p-1.5 transition-colors {{ $cx['muted'] }} {{ $cx['hover'] }} hover:text-on-surface dark:hover:text-white {{ $cx['focus'] }}">
                                                <span class="sr-only">View</span>
                                                <span class="material-symbols-outlined text-[18px]" aria-hidden="true">visibility</span>
                                            </a>
                                            @can('tasks.edit')
                                                <a href="{{ route('projects.tasks.edit', [$project, $task]) }}" title="Edit Task"
                                                   class="rounded-lg p-1.5 transition-colors {{ $cx['muted'] }} {{ $cx['hover'] }} hover:text-on-surface dark:hover:text-white {{ $cx['focus'] }}">
                                                    <span class="sr-only">Edit</span>
                                                    <span class="material-symbols-outlined text-[18px]" aria-hidden="true">edit</span>
                                                </a>
                                            @endcan
                                            @can('tasks.delete')
                                                <form action="{{ route('projects.tasks.destroy', [$project, $task]) }}" method="POST" class="inline"
                                                      onsubmit="return confirm('Delete this task?')">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" title="Delete Task"
                                                            class="rounded-lg p-1.5 transition-colors {{ $cx['muted'] }} hover:bg-error-container hover:text-error dark:hover:bg-error/20 dark:hover:text-error-container {{ $cx['focus'] }}">
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

                    {{-- No rows match the current filters --}}
                    <div x-show="visible === 0" x-cloak class="px-6 py-12 text-center">
                        <span class="material-symbols-outlined text-[32px] {{ $cx['muted'] }}" aria-hidden="true">filter_alt_off</span>
                        <p class="mt-2 text-title-sm {{ $cx['text'] }}">No tasks match your filters</p>
                        <button type="button" @click="reset()"
                                class="mt-3 rounded-lg px-3 py-1.5 text-label-md text-primary transition-colors dark:text-primary-fixed {{ $cx['hover'] }} {{ $cx['focus'] }}">
                            Clear filters
                        </button>
                    </div>
                </div>

                {{-- Pagination --}}
                @if (method_exists($tasks, 'hasPages') && $tasks->hasPages())
                    <div class="flex flex-col items-center justify-between gap-space-sm p-space-md sm:flex-row">
                        <span class="text-body-sm {{ $cx['muted'] }}">
                            Showing
                            <span class="font-semibold {{ $cx['text'] }}">{{ $tasks->firstItem() }}</span>
                            to
                            <span class="font-semibold {{ $cx['text'] }}">{{ $tasks->lastItem() }}</span>
                            of
                            <span class="font-semibold {{ $cx['text'] }}">{{ $tasks->total() }}</span>
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
               class="inline-flex items-center gap-1.5 rounded text-label-md transition-colors hover:text-on-surface dark:hover:text-white {{ $cx['muted'] }} {{ $cx['focus'] }}">
                <span class="material-symbols-outlined text-[18px]" aria-hidden="true">arrow_back</span>
                Back to Projects
            </a>

            @can('projects.delete')
                <form action="{{ route('projects.destroy', $project) }}" method="POST"
                      onsubmit="return confirm('Are you sure you want to delete this project? All its tasks will be removed.')">
                    @csrf
                    @method('DELETE')
                    <button type="submit"
                            class="flex h-9 items-center gap-1.5 rounded-xl bg-error-container px-space-md text-label-md text-on-error-container shadow-sm transition-colors hover:bg-error hover:text-on-error dark:bg-error/15 dark:text-error-container dark:shadow-none dark:hover:bg-error dark:hover:text-on-error focus:outline-none focus-visible:ring-2 focus-visible:ring-error focus-visible:ring-offset-2 dark:focus-visible:ring-offset-black">
                        <span class="material-symbols-outlined text-[18px]" aria-hidden="true">delete</span>
                        Delete Project
                    </button>
                </form>
            @endcan
        </div>
    </div>
    </div>
</x-app-layout>
