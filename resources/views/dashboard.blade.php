@php
    $now      = now();
    $today    = $now->copy()->startOfDay();
    $user     = auth()->user();
    $greeting = $now->hour < 12 ? 'Good morning' : ($now->hour < 17 ? 'Good afternoon' : 'Good evening');

    // ---- Status breakdown (mutually exclusive segments) ------------------
    // Palette: graphite base + semantic colours (emerald = done, amber = active, red = overdue).
    $total     = (int) $totalTasks;
    $completed = (int) $completedTasks;
    $overdue   = (int) $overdueTasks;
    $open      = max(0, $total - $completed - $overdue);
    $inProg    = isset($inProgressTasks) ? min((int) $inProgressTasks, $open) : null;

    if ($inProg !== null) {
        $segments = [
            ['Completed',                 $completed,      'stroke-emerald-500', 'bg-emerald-500'],
            ['In progress',               $inProg,         'stroke-amber-500',   'bg-amber-500'],
            ['Pending / backlog',         $open - $inProg, 'stroke-zinc-300', 'bg-zinc-300'],
            ['Overdue (action required)', $overdue,        'stroke-red-500',     'bg-red-500'],
        ];
    } else {
        $segments = [
            ['Completed',                 $completed, 'stroke-emerald-500', 'bg-emerald-500'],
            ['Pending / in progress',     $open,      'stroke-amber-500',   'bg-amber-500'],
            ['Overdue (action required)', $overdue,   'stroke-red-500',     'bg-red-500'],
        ];
    }
    $resolvedPct = $total > 0 ? (int) round(($completed / $total) * 100) : 0;
    $chartLabel  = collect($segments)->map(fn ($s) => $s[0] . ': ' . $s[1])->implode(', ');

    // ---- Helpers ---------------------------------------------------------
    $ticket = fn ($t) => 'TSK-' . $t->project_id . '-' . str_pad($t->id, 2, '0', STR_PAD_LEFT);

    $urgency = function ($t) {
        if (! $t->due_date) {
            return ['none', 'No due date', null];
        }
        $due  = \Illuminate\Support\Carbon::parse($t->due_date)->startOfDay();
        $days = (int) now()->startOfDay()->diffInDays($due, false);
        $date = $due->format('M d, Y');

        if ($t->status === 'completed') return ['done', 'Completed', $date];
        if ($days < 0)   return ['overdue', abs($days) . ' ' . Str::plural('day', abs($days)) . ' overdue', $date];
        if ($days === 0) return ['soon', 'Due today', $date];
        if ($days === 1) return ['soon', 'Due tomorrow', $date];
        if ($days <= 3)  return ['soon', 'Due in ' . $days . ' days', $date];
        return ['ok', 'Due in ' . $days . ' days', $date];
    };

    $priorityWeight = ['high' => 0, 'medium' => 1, 'low' => 2];

    // Sort: due date ascending (no date last), then priority (high first).
    $byUrgency = fn ($collection) => $collection->sortBy([
        fn ($a, $b) => ($a->due_date ? \Illuminate\Support\Carbon::parse($a->due_date)->timestamp : PHP_INT_MAX)
                   <=> ($b->due_date ? \Illuminate\Support\Carbon::parse($b->due_date)->timestamp : PHP_INT_MAX),
        fn ($a, $b) => ($priorityWeight[$a->priority] ?? 3) <=> ($priorityWeight[$b->priority] ?? 3),
    ])->values();

    // Only allow relative or http(s) links coming from notification data.
    $safeLink = fn ($l) => is_string($l) && ! Str::startsWith($l, '//') && Str::startsWith($l, ['/', 'http://', 'https://'])
        ? $l
        : route('dashboard');

    // Status: filled tints. Priority: outlined, so the two never read as the same thing.
    $statusChip = [
        'completed'   => ['bg-emerald-50 text-emerald-700', 'bg-emerald-500', 'Completed'],
        'in_progress' => ['bg-amber-50 text-amber-700',     'bg-amber-500',   'In progress'],
        'pending'     => ['bg-zinc-100 text-zinc-600',      'bg-zinc-400',    'Pending'],
    ];
    $priorityChip = [
        'high'   => ['bg-white text-red-700 ring-1 ring-inset ring-red-300',     'bg-red-500',   'High'],
        'medium' => ['bg-white text-amber-700 ring-1 ring-inset ring-amber-300', 'bg-amber-500', 'Medium'],
        'low'    => ['bg-white text-zinc-600 ring-1 ring-inset ring-zinc-300',   'bg-zinc-400',  'Low'],
    ];
    $chip  = 'inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-xs font-medium';
    $focus = 'focus:outline-none focus-visible:ring-2 focus-visible:ring-zinc-800 focus-visible:ring-offset-2';
    $card  = 'rounded-xl border border-zinc-200 bg-white shadow-[0_1px_2px_0_rgba(24,24,27,0.05)]';
    $btnSecondary = 'inline-flex h-10 items-center gap-2 rounded-lg border border-zinc-300 bg-white px-3.5 text-sm font-medium text-zinc-800 shadow-sm transition hover:border-zinc-400 hover:bg-zinc-100 hover:text-zinc-900 motion-reduce:transition-none ' . $focus;
    $btnPrimary   = 'inline-flex h-10 items-center gap-2 rounded-lg bg-zinc-800 px-4 text-sm font-semibold text-white shadow-[inset_0_1px_0_0_rgba(255,255,255,0.12)] transition hover:bg-zinc-700 motion-reduce:transition-none ' . $focus;
    $link         = 'inline-flex items-center gap-1 rounded text-sm font-medium text-zinc-700 underline-offset-4 hover:text-zinc-900 hover:underline ' . $focus;

    // ---- Notifications ---------------------------------------------------
    $notifications  = collect($notifications ?? []);
    $unreadCount    = (int) ($unreadNotificationCount ?? 0);
    $unreadBadge    = $unreadCount > 9 ? '9+' : (string) $unreadCount;
    $canReadAll     = \Illuminate\Support\Facades\Route::has('notifications.read-all');

    // ---- Lists -----------------------------------------------------------
    $upcoming  = collect($upcomingTasks);
    $openTasks = $upcoming->filter(fn ($t) => $t->status !== 'completed');

    $attention = isset($attentionTasks)
        ? collect($attentionTasks)
        : $openTasks->filter(fn ($t) => $t->due_date
            && \Illuminate\Support\Carbon::parse($t->due_date)->startOfDay()->lte(now()->addDays(3)->startOfDay()))->values();
    $attention            = $byUrgency($attention);
    $attentionOverdue     = $attention->filter(fn ($t) => $urgency($t)[0] === 'overdue')->count();
    $attentionApproaching = $attention->count() - $attentionOverdue;

    $next   = $byUrgency($openTasks)->take(3);
    $recent = isset($recentTasks) ? collect($recentTasks)->take(4) : $upcoming->sortByDesc('updated_at')->take(4);

    // ---- Focus task: most urgent thing to start with ---------------------
    $focusTask   = $attention->first() ?? $next->first();
    $focusState  = $focusTask ? $urgency($focusTask)[0] : 'clear';
    $focusAccent = match ($focusState) {
        'overdue' => 'border-l-red-500',
        'soon'    => 'border-l-amber-500',
        'clear'   => 'border-l-emerald-500',
        default   => 'border-l-zinc-700',
    };

    // ---- Summary sentence --------------------------------------------------
    if ($attentionOverdue > 0) {
        $summary = $attentionOverdue . ' ' . Str::plural('task', $attentionOverdue) . ' ' . ($attentionOverdue === 1 ? 'is' : 'are') . ' past due.';
        $summaryTone = 'text-red-700';
    } elseif ($attentionApproaching > 0) {
        $summary = $attentionApproaching . ' ' . Str::plural('task', $attentionApproaching) . ' ' . ($attentionApproaching === 1 ? 'is' : 'are') . ' due within 3 days.';
        $summaryTone = 'text-amber-700';
    } elseif ($total === 0) {
        $summary = 'No tasks yet. Create a project to get started.';
        $summaryTone = 'text-zinc-700';
    } else {
        $summary = 'You are on track. Nothing is overdue.';
        $summaryTone = 'text-emerald-700';
    }

    // ---- Next 7 days load --------------------------------------------------
    $week = collect(range(0, 6))->map(function ($i) use ($today, $openTasks) {
        $day = $today->copy()->addDays($i);
        return [
            'label'   => $i === 0 ? 'Today' : ($i === 1 ? 'Tomorrow' : $day->format('D, M d')),
            'weekend' => $day->isWeekend(),
            'count'   => $openTasks->filter(fn ($t) => $t->due_date
                && \Illuminate\Support\Carbon::parse($t->due_date)->isSameDay($day))->count(),
        ];
    });
    $weekMax   = max(1, $week->max('count'));
    $weekTotal = $week->sum('count');

    // ---- Priority balance (optional) ------------------------------------
    $priority = null;
    if (isset($priorityCounts)) {
        $pc     = collect($priorityCounts)->mapWithKeys(fn ($v, $k) => [Str::lower($k) => (int) $v]);
        $pTotal = max(1, $pc->sum());
        $priority = [
            ['High',   $pc->get('high', 0),   'bg-red-500', 'Needs immediate attention'],
            ['Medium', $pc->get('medium', 0), 'bg-amber-500', 'Standard planned work'],
            ['Low',    $pc->get('low', 0),    'bg-zinc-400', 'Lower urgency and maintenance'],
        ];
    }

    // ---- Project health (optional) --------------------------------------
    $health = null;
    if (isset($projectHealth)) {
        $health = collect($projectHealth)->map(function ($p) {
            $t = (int) data_get($p, 'total', 0);
            $c = (int) data_get($p, 'completed', 0);
            return [
                'id'      => data_get($p, 'id'),
                'name'    => data_get($p, 'name', 'Untitled project'),
                'total'   => $t,
                'done'    => $c,
                'overdue' => (int) data_get($p, 'overdue', 0),
                'pct'     => $t > 0 ? (int) round(($c / $t) * 100) : 0,
            ];
        })->sortBy([['overdue', 'desc'], ['pct', 'asc']])->take(6)->values();
    }
    $canOpenProject = \Illuminate\Support\Facades\Route::has('projects.show');

    // ---- KPI cards -------------------------------------------------------
    $kpiIcon = 'bg-zinc-100 text-zinc-700';
    $kpiDone = 'bg-emerald-50 text-emerald-600';
    $kpiWait = 'bg-amber-50 text-amber-600';
    $kpis = [
        ['Total projects',    $projectsCount,  'projects.view', 'projects.index',       'folder_open',  $kpiIcon,                'Active in workspace', null],
        ['Total tasks',       $totalTasks,     'tasks.view',    'projects.tasks.index', 'checklist',    $kpiIcon,                'Across all projects', null],
        ['Completed',         $completedTasks, 'tasks.view',    'projects.tasks.index', 'check_circle', $kpiDone,                $resolvedPct . '% resolved', 'completed'],
        ['Pending / in flow', $pendingTasks,   'tasks.view',    'projects.tasks.index', 'pending',      $kpiWait,                'Unresolved tasks', 'pending'],
        ['Overdue',           $overdueTasks,   'tasks.view',    'projects.tasks.index', 'warning',      'bg-red-50 text-red-600', $overdueTasks > 0 ? 'Past due date' : 'All clear', 'overdue'],
    ];
@endphp

<x-app-layout>
    <style>
        [x-cloak] { display: none !important; }

        @keyframes dash-enter {
            0% {
                opacity: 0;
                transform: translateY(12px) scale(0.985);
            }
            60% {
                opacity: 1;
                transform: translateY(-2px) scale(1.004);
            }
            100% {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        @keyframes dash-refresh-sheen {
            0% {
                box-shadow: inset 0 0 0 rgba(255,255,255,0), 0 0 0 rgba(255,255,255,0);
            }
            50% {
                box-shadow: inset 0 1px 0 rgba(255,255,255,0.72), 0 0 0 3px rgba(253, 186, 116, 0.10);
            }
            100% {
                box-shadow: inset 0 1px 0 rgba(255,255,255,0.72), 0 0 0 rgba(255,255,255,0);
            }
        }

        @keyframes dash-progress-fill {
            from { transform: scaleX(0); }
            to { transform: scaleX(1); }
        }

        @keyframes dash-donut-draw {
            from { stroke-dasharray: 0 100; }
            to { stroke-dasharray: var(--dash-segment) var(--dash-gap); }
        }

        @keyframes dash-status-reveal {
            from { opacity: 0; transform: translateX(-8px); }
            to { opacity: 1; transform: translateX(0); }
        }

        .task-attention-card {
            background: linear-gradient(180deg, rgba(254, 242, 242, 0.92), rgba(255, 255, 255, 0.96));
            border-color: rgba(254, 202, 202, 0.95);
            box-shadow: inset 0 1px 0 rgba(255,255,255,0.8), 0 1px 2px rgba(15, 23, 42, 0.04);
            animation: dash-refresh-sheen 2.4s ease-in-out 1, dash-enter .65s ease-out both;
        }

        .dash-animate-card {
            animation: dash-enter .7s cubic-bezier(.2,.8,.2,1) both;
        }

        .dash-animate-card.delay-1 { animation-delay: .06s; }
        .dash-animate-card.delay-2 { animation-delay: .12s; }
        .dash-animate-card.delay-3 { animation-delay: .18s; }
        .dash-animate-card.delay-4 { animation-delay: .24s; }
        .dash-animate-card.delay-5 { animation-delay: .30s; }

        .dash-progress-segment,
        .dash-week-fill {
            transform: scaleX(0);
            transform-origin: left center;
            animation: dash-progress-fill .7s cubic-bezier(.22,.8,.25,1) both;
        }

        .dash-donut-segment {
            animation: dash-donut-draw .85s cubic-bezier(.22,.8,.25,1) both;
        }

        .dash-status-center,
        .dash-status-row {
            animation: dash-status-reveal .45s cubic-bezier(.22,.8,.25,1) both;
        }

        .dash-motion a,
        .dash-motion button {
            text-decoration: none !important;
        }

        .dash-motion a:hover,
        .dash-motion a:focus-visible,
        .dash-motion button:hover,
        .dash-motion button:focus-visible {
            text-decoration: none !important;
        }

        @media (prefers-reduced-motion: reduce) {
            .dash-motion * { transition: none !important; animation: none !important; }
        }
    </style>

    <div class="dash-motion min-h-screen bg-white py-space-xl font-inter text-zinc-900">
        <div class="mx-auto max-w-[1600px] space-y-6 px-4 sm:px-6 lg:px-8">

            {{-- Greeting --}}
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <div class="flex flex-wrap items-center gap-3">
                        <h1 class="text-[32px] font-bold leading-10 tracking-[-0.025em] text-zinc-900">{{ $greeting }}, {{ $user->name }}</h1>
                        <span class="inline-flex items-center gap-1.5 rounded-full border border-zinc-200 bg-zinc-100 px-2.5 py-1 font-code-sm text-[11px] font-semibold text-zinc-700">
                            <span class="material-symbols-outlined text-[14px]" aria-hidden="true">calendar_today</span>
                            {{ $now->format('l, M d, Y') }}
                        </span>
                    </div>
                    <p class="mt-1 text-sm text-zinc-500">
                        <span class="font-medium {{ $summaryTone }}">{{ $summary }}</span>
                        Overview of {{ number_format($projectsCount) }} {{ Str::plural('project', $projectsCount) }} and {{ number_format($total) }} {{ Str::plural('task', $total) }}.
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-3">

                    @can('tasks.view')
                        <a href="{{ route('projects.my-tasks') }}" class="{{ $btnSecondary }}">
                            <span class="material-symbols-outlined text-[18px]" aria-hidden="true">assignment_ind</span>
                            View my tasks
                        </a>
                    @endcan
                    @can('projects.create')
                        <a href="{{ route('projects.create') }}" class="{{ $btnPrimary }}">
                            <span class="material-symbols-outlined text-[18px]" aria-hidden="true">add</span>
                            New project
                        </a>
                    @endcan
                </div>
            </div>

            {{-- Focus + week outlook --}}
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">

                <section class="{{ $card }} dash-animate-card delay-1 overflow-hidden border-l-4 {{ $focusAccent }} lg:col-span-2" aria-labelledby="focus-heading">
                    <div class="p-6">
                        <h2 id="focus-heading" class="text-sm font-semibold text-zinc-500">Start here</h2>

                        @if ($focusTask)
                            @php
                                [$fState, $fLabel, $fDate] = $urgency($focusTask);
                                [$fpPill, $fpDot, $fpText] = $priorityChip[$focusTask->priority] ?? $priorityChip['low'];
                                [$fsPill, $fsDot, $fsText] = $statusChip[$focusTask->status] ?? $statusChip['pending'];
                            @endphp
                            <div class="mt-3 flex flex-wrap items-start justify-between gap-4">
                                <div class="min-w-0 flex-1">
                                    <a href="{{ route('projects.tasks.show', [$focusTask->project_id, $focusTask]) }}"
                                       class="block rounded text-2xl font-bold leading-8 tracking-tight text-zinc-900 hover:underline hover:underline-offset-4 {{ $focus }}">{{ $focusTask->title }}</a>
                                    <p class="mt-1 text-sm text-zinc-500">
                                        <span class="font-code-sm text-xs">{{ $ticket($focusTask) }}</span>
                                        in {{ $focusTask->project?->name ?? 'No project' }}
                                        @if ($fDate) · {{ $fDate }} @endif
                                    </p>
                                    <div class="mt-3 flex flex-wrap items-center gap-2">
                                        @if ($fState === 'overdue')
                                            <span class="{{ $chip }} bg-red-50 text-red-700"><span class="material-symbols-outlined text-[14px]" aria-hidden="true">error</span>{{ $fLabel }}</span>
                                        @elseif ($fState === 'soon')
                                            <span class="{{ $chip }} bg-amber-100 text-amber-800"><span class="material-symbols-outlined text-[14px]" aria-hidden="true">schedule</span>{{ $fLabel }}</span>
                                        @else
                                            <span class="{{ $chip }} bg-zinc-100 text-zinc-600">{{ $fLabel }}</span>
                                        @endif
                                        <span class="{{ $chip }} {{ $fpPill }}"><span class="h-1.5 w-1.5 rounded-full {{ $fpDot }}"></span>{{ $fpText }}</span>
                                        <span class="{{ $chip }} {{ $fsPill }}"><span class="h-1.5 w-1.5 rounded-full {{ $fsDot }}"></span>{{ $fsText }}</span>
                                    </div>
                                </div>
                                <a href="{{ route('projects.tasks.show', [$focusTask->project_id, $focusTask]) }}" class="{{ $btnPrimary }} h-11">
                                    {{ $focusTask->status === 'in_progress' ? 'Resume task' : 'Open task' }}
                                    <span class="material-symbols-outlined text-[18px]" aria-hidden="true">arrow_forward</span>
                                </a>
                            </div>
                        @else
                            <div class="mt-3 flex items-center gap-4">
                                <span class="flex h-12 w-12 items-center justify-center rounded-full bg-emerald-50 text-emerald-600">
                                    <span class="material-symbols-outlined text-[24px]" aria-hidden="true">task_alt</span>
                                </span>
                                <div>
                                    <p class="text-lg font-semibold text-zinc-900">Nothing is waiting on you</p>
                                    <p class="text-sm text-zinc-500">Open tasks with due dates will show up here.</p>
                                </div>
                            </div>
                        @endif
                    </div>

                    {{-- Segmented progress across all tasks --}}
                    <div class="border-t border-zinc-100 bg-zinc-50 px-6 py-4">
                        <div class="mb-2 flex items-center justify-between text-xs text-zinc-500">
                            <span class="font-medium text-zinc-700">Overall progress</span>
                            <span class="tabular-nums">{{ $completed }} of {{ $total }} {{ Str::plural('task', $total) }} done ({{ $resolvedPct }}%)</span>
                        </div>
                        <div class="flex h-2 w-full overflow-hidden rounded-full bg-zinc-200" role="img" aria-label="{{ $chartLabel }}">
                            @foreach ($segments as [$sLabel, $sCount, $sStroke, $sBg])
                                @if ($total > 0 && $sCount > 0)
                                    <div class="dash-progress-segment h-full {{ $sBg }}"
                                         style="width: {{ round(($sCount / $total) * 100, 2) }}%; animation-delay: {{ 180 + ($loop->index * 110) }}ms"
                                         title="{{ $sLabel }}: {{ $sCount }}"></div>
                                @endif
                            @endforeach
                        </div>
                    </div>
                </section>

                {{-- Next 7 days --}}
                <section class="{{ $card }} dash-animate-card delay-2 p-6" aria-labelledby="week-heading">
                    <div class="mb-4 flex items-center justify-between">
                        <h2 id="week-heading" class="text-base font-semibold tracking-tight text-zinc-900">Next 7 days</h2>
                        <span class="text-xs text-zinc-500">{{ $weekTotal }} due</span>
                    </div>

                    @if ($overdue > 0)
                        <div class="mb-3 flex items-center justify-between rounded-md bg-red-50 px-3 py-2 text-sm">
                            <span class="font-medium text-red-700">Already overdue</span>
                            <span class="font-semibold tabular-nums text-red-700">{{ $overdue }}</span>
                        </div>
                    @endif

                    <ul class="space-y-2.5">
                        @foreach ($week as $d)
                            <li class="flex items-center gap-3 text-sm">
                                <span class="w-24 shrink-0 {{ $loop->first ? 'font-semibold text-zinc-900' : ($d['weekend'] ? 'text-zinc-400' : 'text-zinc-600') }}">{{ $d['label'] }}</span>
                                <span class="h-2 flex-1 overflow-hidden rounded-full bg-zinc-100">
                                    <span class="dash-week-fill block h-full rounded-full {{ $loop->first ? 'bg-zinc-900' : 'bg-zinc-400' }}"
                                          style="width: {{ $d['count'] > 0 ? max(8, round(($d['count'] / $weekMax) * 100)) : 0 }}%; animation-delay: {{ 250 + ($loop->index * 70) }}ms"></span>
                                </span>
                                <span class="w-5 text-right text-xs font-semibold tabular-nums {{ $d['count'] ? 'text-zinc-900' : 'text-zinc-300' }}">{{ $d['count'] }}<span class="sr-only"> {{ Str::plural('task', $d['count']) }} due</span></span>
                            </li>
                        @endforeach
                    </ul>
                    @if ($weekTotal === 0)
                        <p class="mt-4 text-xs text-zinc-500">Nothing is scheduled this week.</p>
                    @endif
                </section>
            </div>

            {{-- KPI cards --}}
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 xl:grid-cols-5">
                @foreach ($kpis as [$label, $count, $permission, $routeName, $icon, $iconBox, $hint, $statusFilter])
                    @can($permission)
                        @php $alert = $label === 'Overdue' && $count > 0; @endphp
                        <a href="{{ route($routeName, $statusFilter ? ['status' => $statusFilter] : []) }}"
                           class="dash-animate-card delay-{{ min($loop->iteration, 5) }} group rounded-lg border bg-white p-4 shadow-[0_1px_2px_0_rgba(24,24,27,0.05)] transition hover:shadow-md {{ $focus }} {{ $alert ? 'border-red-200 hover:border-red-300' : 'border-zinc-200 hover:border-zinc-400' }}">
                            <div class="flex items-start justify-between">
                                <p class="text-[13px] font-medium text-zinc-600">{{ $label }}</p>
                                <span class="flex h-8 w-8 items-center justify-center rounded-md {{ $iconBox }}">
                                    <span class="material-symbols-outlined text-[18px]" aria-hidden="true">{{ $icon }}</span>
                                </span>
                            </div>
                            <p class="mt-2 text-3xl font-bold leading-none tabular-nums {{ $alert ? 'text-red-600' : 'text-zinc-900' }}">{{ number_format($count) }}</p>
                            <p class="mt-2 truncate text-xs text-zinc-500">{{ $hint }}</p>
                        </a>
                    @endcan
                @endforeach
            </div>

            {{-- Distribution row --}}
            <div class="grid grid-cols-1 gap-6 {{ $priority ? 'lg:grid-cols-2' : '' }}">

                {{-- Task status distribution --}}
                <section class="{{ $card }} dash-animate-card delay-4 p-6" aria-labelledby="dist-heading">
                    <div class="mb-5 flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="material-symbols-outlined text-[20px] text-zinc-700" aria-hidden="true">donut_large</span>
                            <h2 id="dist-heading" class="text-base font-semibold tracking-tight text-zinc-900">Task status</h2>
                        </div>
                        <span class="font-code-sm text-[11px] text-zinc-500">{{ $total }} total {{ Str::plural('task', $total) }}</span>
                    </div>

                    <div class="flex flex-col items-center gap-8 sm:flex-row">
                        <div class="relative h-40 w-40 shrink-0">
                            <svg viewBox="0 0 36 36" class="h-40 w-40 -rotate-90" role="img" aria-label="{{ $chartLabel }}">
                                <circle cx="18" cy="18" r="15.9155" fill="none" class="stroke-zinc-100" stroke-width="4"></circle>
                                @php $acc = 0; @endphp
                                @foreach ($segments as [$sLabel, $sCount, $stroke])
                                    @php $pct = $total > 0 ? ($sCount / $total) * 100 : 0; @endphp
                                    @if ($pct > 0)
                                        <circle cx="18" cy="18" r="15.9155" fill="none" class="dash-donut-segment {{ $stroke }} transition-opacity hover:opacity-75" stroke-width="4"
                                                stroke-dasharray="{{ round($pct, 2) }} {{ round(100 - $pct, 2) }}"
                                                stroke-dashoffset="{{ round(-$acc, 2) }}"
                                                style="--dash-segment: {{ round($pct, 2) }}; --dash-gap: {{ round(100 - $pct, 2) }}; animation-delay: {{ 180 + ($loop->index * 130) }}ms">
                                            <title>{{ $sLabel }}: {{ $sCount }} ({{ number_format($pct, 1) }}%)</title>
                                        </circle>
                                        @php $acc += $pct; @endphp
                                    @endif
                                @endforeach
                            </svg>
                            <div class="dash-status-center pointer-events-none absolute inset-0 flex flex-col items-center justify-center" style="animation-delay: 650ms">
                                <span class="text-3xl font-bold tabular-nums text-zinc-900">{{ $resolvedPct }}%</span>
                                <span class="text-xs font-medium text-zinc-500">Resolved</span>
                            </div>
                        </div>

                        <ul class="w-full flex-1 space-y-2.5">
                            @foreach ($segments as [$sLabel, $sCount, $stroke, $dot])
                                @php $isOverdueRow = str_starts_with($sLabel, 'Overdue') && $sCount > 0; @endphp
                                <li class="dash-status-row flex items-center justify-between gap-3 rounded-md px-2 py-1.5 {{ $isOverdueRow ? 'bg-red-50' : '' }}" style="animation-delay: {{ 260 + ($loop->index * 90) }}ms">
                                    <span class="flex min-w-0 items-center gap-2 text-sm {{ $isOverdueRow ? 'font-medium text-red-700' : 'text-zinc-700' }}">
                                        <span class="h-2.5 w-2.5 shrink-0 rounded-full {{ $dot }}"></span>
                                        <span class="truncate">{{ $sLabel }}</span>
                                    </span>
                                    <span class="flex shrink-0 items-center gap-4 font-code-sm text-xs">
                                        <span class="font-semibold text-zinc-900">{{ $sCount }}</span>
                                        <span class="w-12 text-right text-zinc-500">{{ $total > 0 ? number_format(($sCount / $total) * 100, 1) : '0.0' }}%</span>
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    </div>

                    <div class="mt-5 flex items-center justify-between border-t border-zinc-100 pt-4 text-xs text-zinc-500">
                        <span class="flex items-center gap-1.5">
                            <span class="material-symbols-outlined text-[14px]" aria-hidden="true">info</span>
                            Calculated across all tasks
                        </span>
                        @can('tasks.view')
                            <a href="{{ route('projects.tasks.index') }}" class="{{ $link }}">
                                View all tasks
                                <span class="material-symbols-outlined text-[16px]" aria-hidden="true">arrow_forward</span>
                            </a>
                        @endcan
                    </div>
                </section>

                @if ($priority)
                    <section class="{{ $card }} p-6" aria-labelledby="prio-heading">
                        <div class="mb-5 flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <span class="material-symbols-outlined text-[20px] text-zinc-700" aria-hidden="true">bar_chart</span>
                                <h2 id="prio-heading" class="text-base font-semibold tracking-tight text-zinc-900">Task priority balance</h2>
                            </div>
                            <span class="rounded-md bg-zinc-100 px-2 py-0.5 text-xs font-medium text-zinc-500">Workload density</span>
                        </div>

                        <div class="space-y-5">
                            @foreach ($priority as [$pLabel, $pCount, $pBar, $pNote])
                                @php $pPct = (int) round(($pCount / $pTotal) * 100); @endphp
                                <div>
                                    <div class="mb-1.5 flex items-center justify-between text-sm">
                                        <span class="font-medium text-zinc-800">{{ $pLabel }}</span>
                                        <span class="font-code-sm text-xs">
                                            <span class="font-semibold text-zinc-900">{{ $pCount }}</span>
                                            <span class="ml-1 text-zinc-500">({{ $pPct }}%)</span>
                                        </span>
                                    </div>
                                    <div class="h-1.5 w-full overflow-hidden rounded-full bg-zinc-100">
                                        <div class="h-full rounded-full {{ $pBar }}" style="width: {{ $pPct }}%"></div>
                                    </div>
                                    <p class="mt-1 text-xs text-zinc-500">{{ $pNote }}</p>
                                </div>
                            @endforeach
                        </div>
                    </section>
                @endif
            </div>

            {{-- Tasks needing attention --}}
            <section class="{{ $card }} task-attention-card overflow-hidden" x-data="{ filter: 'all' }" aria-labelledby="attn-heading">
                <div class="flex flex-wrap items-center justify-between gap-3 px-6 py-4">
                    <div class="flex flex-wrap items-center gap-3">
                        <span class="material-symbols-outlined text-[22px] {{ $attentionOverdue > 0 ? 'text-red-600' : 'text-zinc-700' }}" aria-hidden="true">notifications_active</span>
                        <h2 id="attn-heading" class="text-base font-semibold tracking-tight text-zinc-900">Tasks needing attention</h2>
                    </div>

                    <div class="flex flex-wrap items-center gap-3">
                        <div class="inline-flex rounded-md border border-zinc-200 bg-zinc-100 p-0.5 text-sm" role="group" aria-label="Filter tasks">
                            @foreach ([['all', 'All', $attention->count()], ['overdue', 'Overdue', $attentionOverdue], ['approaching', 'Approaching', $attentionApproaching]] as [$fKey, $fName, $fCount])
                                <button type="button"
                                        @if ($fCount === 0 && $fKey !== 'all') disabled @endif
                                        x-on:click="filter = '{{ $fKey }}'"
                                        x-bind:aria-pressed="(filter === '{{ $fKey }}').toString()"
                                        x-bind:class="filter === '{{ $fKey }}' ? 'bg-white text-zinc-900 shadow-sm' : 'text-zinc-500 hover:text-zinc-800'"
                                        class="inline-flex items-center gap-1.5 rounded px-3 py-1 font-medium transition disabled:cursor-not-allowed disabled:opacity-40 {{ $focus }}">
                                    {{ $fName }}
                                    <span class="rounded-full px-1.5 text-xs tabular-nums {{ $fKey === 'overdue' && $fCount > 0 ? 'bg-red-100 text-red-700' : 'bg-zinc-200 text-zinc-700' }}">{{ $fCount }}</span>
                                </button>
                            @endforeach
                        </div>
                        @can('tasks.view')
                            <a href="{{ route('projects.tasks.index') }}" class="{{ $link }}">
                                View all tasks
                                <span class="material-symbols-outlined text-[16px]" aria-hidden="true">arrow_forward</span>
                            </a>
                        @endcan
                    </div>
                </div>

                @if ($attention->isEmpty())
                    <div class="border-t border-zinc-100 px-6 py-12 text-center">
                        <span class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-emerald-50 text-emerald-600">
                            <span class="material-symbols-outlined text-[24px]" aria-hidden="true">task_alt</span>
                        </span>
                        <p class="mt-3 font-medium text-zinc-800">Nothing needs attention</p>
                        <p class="mt-1 text-sm text-zinc-500">No overdue or approaching tasks right now.</p>
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[820px] text-left">
                            <thead>
                                <tr class="h-9 border-y border-zinc-200 bg-zinc-50 text-xs font-semibold text-zinc-500">
                                    <th scope="col" class="px-6">Task</th>
                                    <th scope="col" class="px-4">Project</th>
                                    <th scope="col" class="px-4">Due date</th>
                                    <th scope="col" class="px-4">Priority</th>
                                    <th scope="col" class="px-4">Status</th>
                                    <th scope="col" class="px-6 text-right"><span class="sr-only">Action</span></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-100">
                                @foreach ($attention as $task)
                                    @php
                                        [$uState, $uLabel, $uDate] = $urgency($task);
                                        [$sPill, $sDot, $sText]    = $statusChip[$task->status] ?? $statusChip['pending'];
                                        [$pPill, $pDot, $pText]    = $priorityChip[$task->priority] ?? $priorityChip['low'];
                                        $rowKey = $uState === 'overdue' ? 'overdue' : 'approaching';
                                    @endphp
                                    <tr x-show="filter === 'all' || filter === '{{ $rowKey }}'"
                                        class="transition-colors hover:bg-zinc-50 {{ $uState === 'overdue' ? 'bg-red-50/40' : '' }}">
                                        <td class="px-6 py-3">
                                            <div class="flex items-start gap-3">
                                                <span class="material-symbols-outlined mt-0.5 text-[18px] {{ $uState === 'overdue' ? 'text-red-600' : 'text-zinc-400' }}" aria-hidden="true">
                                                    {{ $uState === 'overdue' ? 'error' : 'event' }}
                                                </span>
                                                <div class="min-w-0">
                                                    <a href="{{ route('projects.tasks.show', [$task->project_id, $task]) }}"
                                                       class="block max-w-xs truncate rounded text-sm font-medium text-zinc-900 hover:underline hover:underline-offset-4 {{ $focus }}">{{ $task->title }}</a>
                                                    <span class="font-code-sm text-[11px] {{ $uState === 'overdue' ? 'text-red-600' : 'text-zinc-500' }}">{{ $ticket($task) }}</span>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-4 py-3">
                                            <span class="flex items-center gap-2 text-sm text-zinc-700">
                                                <span class="h-2 w-2 shrink-0 rounded-full bg-zinc-500"></span>
                                                <span class="max-w-[10rem] truncate">{{ $task->project?->name ?? 'No project' }}</span>
                                            </span>
                                        </td>
                                        <td class="px-4 py-3">
                                            @if ($uDate)
                                                <div class="text-sm font-medium text-zinc-900">{{ $uDate }}</div>
                                            @endif
                                            <div class="text-xs {{ $uState === 'overdue' ? 'font-medium text-red-600' : ($uState === 'soon' ? 'font-medium text-amber-600' : 'text-zinc-500') }}">{{ $uLabel }}</div>
                                        </td>
                                        <td class="px-4 py-3">
                                            <span class="{{ $chip }} {{ $pPill }}"><span class="h-1.5 w-1.5 rounded-full {{ $pDot }}"></span>{{ $pText }}</span>
                                        </td>
                                        <td class="px-4 py-3">
                                            <span class="{{ $chip }} {{ $sPill }}"><span class="h-1.5 w-1.5 rounded-full {{ $sDot }}"></span>{{ $sText }}</span>
                                        </td>
                                        <td class="px-6 py-3 text-right">
                                            <a href="{{ route('projects.tasks.show', [$task->project_id, $task]) }}" title="Open task"
                                               class="inline-flex rounded-md p-1.5 text-zinc-400 transition hover:bg-zinc-100 hover:text-zinc-900 {{ $focus }}">
                                                <span class="sr-only">Open {{ $task->title }}</span>
                                                <span class="material-symbols-outlined text-[18px]" aria-hidden="true">open_in_new</span>
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>

            {{-- Next actions + recent activity --}}
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">

                {{-- Your next actions --}}
                <section class="{{ $card }} p-6" aria-labelledby="next-heading">
                    <div class="mb-1 flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="material-symbols-outlined text-[22px] text-zinc-700" aria-hidden="true">play_circle</span>
                            <h2 id="next-heading" class="text-base font-semibold tracking-tight text-zinc-900">Your next actions</h2>
                        </div>
                        @can('tasks.view')
                            <a href="{{ route('projects.my-tasks') }}" class="{{ $link }}">
                                My tasks
                                <span class="material-symbols-outlined text-[16px]" aria-hidden="true">chevron_right</span>
                            </a>
                        @endcan
                    </div>
                    <p class="mb-4 text-sm text-zinc-500">Open tasks, earliest due date first. High priority wins ties.</p>

                    <div class="space-y-3">
                        @forelse ($next as $task)
                            @php [$uState, $uLabel] = $urgency($task); @endphp
                            <div class="flex items-center gap-4 rounded-lg px-4 py-3 {{ $uState === 'overdue' ? 'bg-red-50' : 'bg-zinc-50' }}">
                                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-md text-sm font-bold {{ $loop->first ? 'bg-zinc-900 text-white' : 'bg-white text-zinc-600 ring-1 ring-zinc-200' }}">{{ $loop->iteration }}</span>

                                <div class="min-w-0 flex-1">
                                    <div class="mb-0.5 flex flex-wrap items-center gap-2">
                                        <span class="font-code-sm text-[11px] text-zinc-500">{{ $ticket($task) }}</span>
                                        @if ($uState === 'overdue')
                                            <span class="{{ $chip }} bg-red-100 text-red-700">{{ $uLabel }}</span>
                                        @elseif ($uState === 'soon')
                                            <span class="{{ $chip }} bg-amber-100 text-amber-800">{{ $uLabel }}</span>
                                        @endif
                                    </div>
                                    <a href="{{ route('projects.tasks.show', [$task->project_id, $task]) }}"
                                       class="block truncate rounded text-sm font-medium text-zinc-900 hover:underline hover:underline-offset-4 {{ $focus }}">{{ $task->title }}</a>
                                    <p class="truncate text-xs text-zinc-500">
                                        {{ $task->project?->name ?? 'No project' }}
                                        @if ($task->due_date) · Due {{ \Illuminate\Support\Carbon::parse($task->due_date)->format('M d') }} @endif
                                    </p>
                                </div>

                                <a href="{{ route('projects.tasks.show', [$task->project_id, $task]) }}"
                                   class="inline-flex h-8 shrink-0 items-center rounded-md px-3 text-sm font-medium {{ $focus }} {{ $task->status === 'in_progress' ? 'bg-zinc-900 text-white hover:bg-zinc-700' : 'border border-zinc-300 bg-white text-zinc-700 hover:border-zinc-400 hover:bg-zinc-100' }}">
                                    {{ $task->status === 'in_progress' ? 'Resume' : 'Open' }}
                                </a>
                            </div>
                        @empty
                            <div class="rounded-lg bg-zinc-50 px-4 py-10 text-center">
                                <p class="font-medium text-zinc-700">No upcoming tasks</p>
                                <p class="mt-1 text-sm text-zinc-500">Tasks with due dates will appear here.</p>
                                @can('projects.view')
                                    <a href="{{ route('projects.index') }}" class="mt-3 inline-block rounded text-sm font-medium text-zinc-900 underline underline-offset-4 {{ $focus }}">Browse projects</a>
                                @endcan
                            </div>
                        @endforelse
                    </div>
                </section>

                {{-- Recently updated --}}
                <section class="{{ $card }} p-6" aria-labelledby="recent-heading">
                    <div class="mb-4 flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="material-symbols-outlined text-[22px] text-zinc-700" aria-hidden="true">history</span>
                            <h2 id="recent-heading" class="text-base font-semibold tracking-tight text-zinc-900">Recently updated</h2>
                        </div>
                        <span class="text-xs font-medium text-zinc-400">Activity feed</span>
                    </div>

                    <ul class="divide-y divide-zinc-100">
                        @forelse ($recent as $task)
                            @php
                                [$sPill, $sDot, $sText] = $statusChip[$task->status] ?? $statusChip['pending'];
                                $icon = match ($task->status) {
                                    'completed'   => ['check', 'bg-emerald-50 text-emerald-600'],
                                    'in_progress' => ['sync',  'bg-amber-50 text-amber-600'],
                                    default       => ['edit_note', 'bg-zinc-100 text-zinc-500'],
                                };
                                $verb = match ($task->status) {
                                    'completed'   => 'Marked done',
                                    'in_progress' => 'Moved to in progress',
                                    default       => 'Updated',
                                };
                            @endphp
                            <li class="flex items-start justify-between gap-3 py-3">
                                <div class="flex min-w-0 items-start gap-3">
                                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full {{ $icon[1] }}">
                                        <span class="material-symbols-outlined text-[18px]" aria-hidden="true">{{ $icon[0] }}</span>
                                    </span>
                                    <div class="min-w-0">
                                        <a href="{{ route('projects.tasks.show', [$task->project_id, $task]) }}"
                                           class="block truncate rounded text-sm font-medium text-zinc-900 hover:underline hover:underline-offset-4 {{ $focus }}">
                                            <span class="font-code-sm text-[11px] text-zinc-500">{{ $ticket($task) }}</span> {{ $task->title }}
                                        </a>
                                        <p class="text-xs text-zinc-500">
                                            {{ $verb }} {{ $task->updated_at->diffForHumans() }}
                                            @if ($task->project?->name) in {{ $task->project->name }} @endif
                                        </p>
                                    </div>
                                </div>
                                <span class="{{ $chip }} shrink-0 {{ $sPill }}">{{ $sText }}</span>
                            </li>
                        @empty
                            <li class="py-10 text-center text-sm text-zinc-500">No recent activity yet.</li>
                        @endforelse
                    </ul>
                </section>
            </div>

            {{-- Project health (needs $projectHealth) --}}
            @if ($health && $health->isNotEmpty())
                <section class="{{ $card }} p-6" aria-labelledby="health-heading">
                    <div class="mb-5 flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="material-symbols-outlined text-[22px] text-zinc-700" aria-hidden="true">monitoring</span>
                            <h2 id="health-heading" class="text-base font-semibold tracking-tight text-zinc-900">Project health</h2>
                        </div>
                        <span class="text-xs text-zinc-500">Projects with the most overdue work first</span>
                    </div>

                    <ul class="grid grid-cols-1 gap-x-8 gap-y-5 md:grid-cols-2 xl:grid-cols-3">
                        @foreach ($health as $p)
                            <li>
                                <div class="mb-1.5 flex items-center justify-between gap-3 text-sm">
                                    @if ($canOpenProject && $p['id'])
                                        <a href="{{ route('projects.show', $p['id']) }}" class="min-w-0 truncate rounded font-medium text-zinc-900 hover:underline hover:underline-offset-4 {{ $focus }}">{{ $p['name'] }}</a>
                                    @else
                                        <span class="min-w-0 truncate font-medium text-zinc-900">{{ $p['name'] }}</span>
                                    @endif
                                    <span class="shrink-0 text-xs tabular-nums text-zinc-500">{{ $p['done'] }}/{{ $p['total'] }} done</span>
                                </div>
                                <div class="h-1.5 w-full overflow-hidden rounded-full bg-zinc-100" role="img" aria-label="{{ $p['name'] }}: {{ $p['pct'] }} percent complete">
                                    <div class="h-full rounded-full {{ $p['overdue'] > 0 ? 'bg-red-500' : 'bg-emerald-500' }}" style="width: {{ $p['pct'] }}%"></div>
                                </div>
                                <p class="mt-1 text-xs {{ $p['overdue'] > 0 ? 'font-medium text-red-600' : 'text-zinc-500' }}">
                                    {{ $p['overdue'] > 0 ? $p['overdue'] . ' overdue' : 'No overdue tasks' }}
                                </p>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif

            {{-- Admin quick links (only for users with those permissions) --}}
            @canany(['roles.view', 'roles.create'])
                <div class="{{ $card }} flex flex-wrap items-center gap-3 px-6 py-4">
                    <span class="text-xs font-semibold text-zinc-500">Administration</span>
                    @can('roles.view')
                        <a href="{{ route('projects.roles.index') }}"
                           class="inline-flex h-8 items-center gap-1.5 rounded-md border border-zinc-200 bg-white px-3 text-sm font-medium text-zinc-700 hover:border-zinc-300 hover:bg-zinc-100 {{ $focus }}">
                            <span class="material-symbols-outlined text-[16px]" aria-hidden="true">admin_panel_settings</span>
                            Roles &amp; permissions
                        </a>
                    @endcan
                 
                    @can('roles.assign-permissions')
                        <a href="{{ route('projects.users.roles.index') }}"
                           class="inline-flex h-8 items-center gap-1.5 rounded-md border border-zinc-200 bg-white px-3 text-sm font-medium text-zinc-700 hover:border-zinc-300 hover:bg-zinc-100 {{ $focus }}">
                            <span class="material-symbols-outlined text-[16px]" aria-hidden="true">person_add</span>
                            Assign user roles
                        </a>
                    @endcan
                </div>
            @endcanany
        </div>
    </div>
</x-app-layout>