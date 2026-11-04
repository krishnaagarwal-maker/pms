@php
    $columns = [
        'pending' => ['title' => 'To do', 'hint' => 'Ready when you are', 'dot' => 'bg-slate-400', 'accent' => 'border-t-slate-400', 'bar' => 'bg-slate-400'],
        'in_progress' => ['title' => 'In progress', 'hint' => 'Being worked on', 'dot' => 'bg-blue-600', 'accent' => 'border-t-blue-600', 'bar' => 'bg-blue-600'],
        'completed' => ['title' => 'Done', 'hint' => 'Wrapped up', 'dot' => 'bg-teal-600', 'accent' => 'border-t-teal-600', 'bar' => 'bg-teal-600'],
    ];
    $tasks = $tasks->filter(fn ($task) => !is_null($task->project));
    $grouped = $tasks->groupBy(fn ($task) => array_key_exists($task->status, $columns) ? $task->status : 'pending');
    $totals = collect($columns)->map(fn ($c, $s) => $grouped->get($s, collect())->count())->all();
    $canMove = auth()->user()->can('tasks.edit') || auth()->user()->can('tasks.update-status');
    $serverFiltered = request()->filled('q') || request()->filled('project');
    $assignees = $tasks->pluck('assignee')->filter()->unique('id')->values();

    $avatarTones = ['bg-sky-100 text-sky-800', 'bg-amber-100 text-amber-800', 'bg-teal-100 text-teal-800', 'bg-rose-100 text-rose-800', 'bg-violet-100 text-violet-800', 'bg-slate-200 text-slate-700'];
    $projectDots = ['bg-sky-500', 'bg-amber-500', 'bg-teal-500', 'bg-rose-500', 'bg-violet-500', 'bg-slate-500'];
    $priorities = [
        'high' => ['icon' => 'keyboard_double_arrow_up', 'tone' => 'text-rose-600'],
        'medium' => ['icon' => 'drag_handle', 'tone' => 'text-amber-500'],
        'low' => ['icon' => 'keyboard_arrow_down', 'tone' => 'text-sky-500'],
    ];
    $initials = fn ($name) => collect(preg_split('/\s+/', trim((string) $name)))->filter()->take(2)->map(fn ($p) => Str::upper(Str::substr($p, 0, 1)))->join('') ?: '?';
@endphp

<x-app-layout>
    <style>
        [x-cloak] { display: none !important; }

        @keyframes kanban-enter {
            from { opacity: 0; transform: translateY(14px) scale(.985); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }
        @keyframes kanban-landed {
            0% { box-shadow: 0 0 0 0 rgba(59, 130, 246, .55); }
            45% { box-shadow: 0 0 0 10px rgba(59, 130, 246, 0); }
            100% { box-shadow: 0 0 0 0 rgba(59, 130, 246, 0); }
        }
        @keyframes kanban-drop-pulse {
            0% { box-shadow: 0 0 0 0 rgba(59,130,246,.28), inset 0 0 0 1px rgba(59,130,246,.38); }
            60% { box-shadow: 0 0 0 12px rgba(59,130,246,0), inset 0 0 0 1px rgba(59,130,246,.52); }
            100% { box-shadow: 0 0 0 0 rgba(59,130,246,0), inset 0 0 0 1px rgba(59,130,246,.48); }
        }
        @keyframes kanban-drop-glow {
            0% { transform: scale(.98); opacity: .65; }
            50% { transform: scale(1.01); opacity: 1; }
            100% { transform: scale(1); opacity: 1; }
        }
        @keyframes kanban-card-pop {
            0% { transform: scale(.96); box-shadow: 0 0 0 0 rgba(16,185,129,.3); }
            35% { transform: scale(1.02); box-shadow: 0 0 0 8px rgba(16,185,129,0); }
            100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(16,185,129,0); }
        }
        @keyframes kanban-float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-2px); }
        }

        .kanban-enter { animation: kanban-enter .5s cubic-bezier(.22,1,.36,1) both; }
        .kanban-scroll { scrollbar-width: thin; scrollbar-color: rgb(203 213 225) transparent; }
        .kanban-scroll::-webkit-scrollbar { height: 10px; width: 10px; }
        .kanban-scroll::-webkit-scrollbar-thumb { background: rgba(148, 163, 184, .6); border-radius: 9999px; }

        .kanban-card {
            position: relative;
            will-change: transform, box-shadow;
            transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease, opacity .18s ease, background-color .18s ease;
            backface-visibility: hidden;
        }
        .kanban-card:hover {
            border-color: rgb(148 163 184);
            box-shadow: 0 12px 24px rgba(15, 23, 42, .08), 0 3px 10px rgba(15, 23, 42, .04);
            transform: translateY(-2px);
        }
        .kanban-card:focus-within {
            border-color: rgb(96, 165, 250);
            box-shadow: 0 0 0 4px rgba(191, 219, 254, .4);
        }
        .kanban-card.is-dragging {
            opacity: .32;
            transform: rotate(1.5deg) scale(.98);
            box-shadow: 0 20px 34px rgba(15, 23, 42, .2);
            filter: saturate(.7);
        }
        .kanban-card.is-landed { animation: kanban-landed .9s ease-out; }
        .kanban-card.is-dropped {
            animation: kanban-card-pop .7s cubic-bezier(.2,.9,.2,1);
            border-color: rgb(110 231 183);
            background: linear-gradient(180deg, rgba(240,253,244,.96), rgba(255,255,255,1));
        }
        .kanban-card[data-busy] { opacity: .78; cursor: progress; }
        .kanban-card.is-saving::after {
            content: ""; position: absolute; inset: 0; border-radius: inherit;
            background: linear-gradient(110deg, transparent 25%, rgba(255,255,255,.62) 48%, transparent 72%);
            background-size: 220% 100%; animation: kanban-saving 1s linear infinite;
            pointer-events: none;
        }
        @keyframes kanban-saving { to { background-position: -220% 0; } }
        .kanban-column.is-receiving { background-color: rgba(219,234,254,.55); }
        .kanban-column [data-empty] { transition: opacity .18s ease, transform .18s ease; }
        .kanban-card.is-optimistic { outline: 2px solid rgba(59,130,246,.22); }
        .kanban-card[data-status="completed"] .card-title {
            color: rgb(100 116 139);
            text-decoration: line-through;
            text-decoration-color: rgb(203 213 225);
        }
        .kanban-card[data-status="completed"] .due-chip { background: transparent !important; color: rgb(100 116 139) !important; }

        .card-menu-btn { opacity: 0; transition: opacity .15s ease; }
        .kanban-card:hover .card-menu-btn, .card-menu-btn:focus-visible, .card-menu-btn[aria-expanded="true"] { opacity: 1; }

        .kanban-drop-target {
            position: relative;
            background: linear-gradient(180deg, rgba(239,246,255,.98) 0%, rgba(248,250,252,.96) 100%);
            border-color: rgba(59,130,246,.6) !important;
            box-shadow: inset 0 0 0 1px rgba(59,130,246,.45), 0 0 0 1px rgba(59,130,246,.2), 0 18px 26px rgba(59,130,246,.12);
            animation: kanban-drop-pulse .9s ease-in-out infinite alternate;
        }
        .kanban-drop-target::before {
            content: "";
            position: absolute;
            inset: 10px 12px;
            border: 2px dashed rgba(59,130,246,.72);
            border-radius: .9rem;
            pointer-events: none;
            background: rgba(191,219,254,.13);
            animation: kanban-drop-glow .7s ease-in-out infinite alternate;
        }
        .kanban-drop-target.is-dropped {
            animation: kanban-drop-pulse .45s ease-in-out 2 alternate, kanban-landed .7s ease-out;
            border-color: rgba(16,185,129,.8) !important;
            box-shadow: inset 0 0 0 1px rgba(16,185,129,.45), 0 0 0 1px rgba(16,185,129,.2), 0 16px 20px rgba(16,185,129,.18);
            background: linear-gradient(180deg, rgba(240,253,244,.97) 0%, rgba(248,250,252,.97) 100%);
        }

        .drop-hint {
            opacity: 0;
            transform: translateY(-4px);
            transition: opacity .18s ease, transform .18s ease;
        }
        .kanban-drop-target .drop-hint { opacity: 1; transform: translateY(0); }

        .board-filter-btn { transition: all .18s ease; }
        .board-filter-btn[data-active="true"] {
            background: linear-gradient(135deg, rgba(59,130,246,.12), rgba(99,102,241,.1));
            border-color: rgba(59,130,246,.4);
            color: rgb(30,64,175);
            box-shadow: 0 8px 14px rgba(59,130,246,.08);
        }

        .kanban-column {
            transition: width .28s cubic-bezier(.22,1,.36,1), min-width .28s cubic-bezier(.22,1,.36,1), opacity .18s ease, transform .18s ease;
            transform-origin: top left;
        }
        .kanban-column.collapsed {
            width: 4rem !important;
            min-width: 4rem !important;
            opacity: .98;
        }
        .kanban-column.collapsed .board-column-main {
            display: none;
        }
        .kanban-column.collapsed .board-column-collapsed {
            display: flex !important;
        }

        .drag-ghost {
            position: fixed;
            left: 0;
            top: 0;
            z-index: 9999;
            width: 320px;
            max-width: calc(100vw - 2rem);
            pointer-events: none;
            opacity: .98;
            transform: rotate(2deg) scale(1.02);
            box-shadow: 0 24px 42px rgba(15, 23, 42, .22);
            border-radius: 0.9rem;
        }

        @media (hover: none) { .card-menu-btn { opacity: 1; } }
        @media (prefers-reduced-motion: reduce) {
            .kanban-enter, .kanban-card.is-landed, .kanban-drop-target, .kanban-drop-target::before, .board-filter-btn { animation: none !important; }
            .kanban-card, .board-filter-btn, .kanban-column { transition: none !important; }
        }
    </style>

    <div class="board-shell min-h-[calc(100vh-4rem)] bg-slate-50 text-slate-900 transition-colors duration-200" x-data="kanbanBoard()" @keydown.window="if ($event.key === '/' && !['INPUT','TEXTAREA','SELECT'].includes($event.target.tagName)) { $event.preventDefault(); $refs.search.focus(); }">
        <div class="board-panel mx-auto max-w-[1680px] px-4 pb-10 pt-6 sm:px-6 lg:px-8">

            {{-- Header --}}
            <div class="flex flex-col justify-between gap-5 lg:flex-row lg:items-end">
                <div class="min-w-0">
                    <nav class="flex items-center gap-1 text-sm text-slate-500" aria-label="Breadcrumb">
                        <a href="{{ route('projects.index') }}" class="rounded hover:text-blue-700 hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-600">Projects</a>
                        <span class="material-symbols-outlined text-[16px]" aria-hidden="true">chevron_right</span>
                        <span class="text-slate-700">Board</span>
                    </nav>
                    <h1 class="mt-2 text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">Task board</h1>
                    <p class="mt-1 text-sm text-slate-500">{{ $tasks->count() }} {{ Str::plural('task', $tasks->count()) }} in view.<span x-show="canMove" class="hidden sm:inline"> Drag cards between columns, or use the <span class="material-symbols-outlined align-[-4px] text-[17px]" aria-hidden="true">more_horiz</span> menu on a card.</span></p>
                </div>

                <div class="flex flex-col gap-4 sm:flex-row sm:items-end lg:gap-6">
                    {{-- Progress --}}
                    <div class="w-full sm:w-72" aria-live="polite">
                        <div class="flex items-baseline justify-between text-sm">
                            <span class="font-semibold text-slate-900"><span x-text="donePct()">0</span>% complete</span>
                            <span class="text-xs font-medium" :class="overdue > 0 ? 'text-rose-600' : 'text-slate-500'" x-text="overdue > 0 ? overdue + ' overdue' : 'Nothing overdue'"></span>
                        </div>
                        <div class="mt-1.5 flex h-2 overflow-hidden rounded-full bg-slate-200" role="img" aria-label="Task distribution across To do, In progress and Done">
                            @foreach ($columns as $status => $column)
                                <span class="{{ $column['bar'] }} h-full transition-[width] duration-500" :style="{ width: pct('{{ $status }}') + '%' }"></span>
                            @endforeach
                        </div>
                        <div class="mt-1.5 flex flex-wrap gap-x-3 text-xs text-slate-500">
                            @foreach ($columns as $status => $column)
                                <span class="inline-flex items-center gap-1"><span class="h-2 w-2 rounded-full {{ $column['dot'] }}"></span>{{ $column['title'] }} <span class="font-semibold tabular-nums text-slate-700" x-text="totals.{{ $status }}">{{ $totals[$status] }}</span></span>
                            @endforeach
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        <a href="{{ route('projects.tasks.index') }}" class="inline-flex h-10 items-center gap-2 rounded-lg border border-slate-300 bg-white px-3.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-600">
                            <span class="material-symbols-outlined text-[18px]" aria-hidden="true">format_list_bulleted</span> List view
                        </a>
                        @can('projects.view')
                            <a href="{{ route('projects.index') }}" class="inline-flex h-10 items-center gap-2 rounded-lg bg-slate-900 px-4 text-sm font-semibold text-white transition hover:bg-slate-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-600 focus-visible:ring-offset-2">
                                <span class="material-symbols-outlined text-[18px]" aria-hidden="true">folder_open</span> Projects
                            </a>
                        @endcan
                    </div>
                </div>
            </div>

            @if (session('success'))
                <div x-data="{ show: true }" x-show="show" x-transition class="mt-5 flex items-center justify-between rounded-lg border border-teal-200 bg-teal-50 px-4 py-3 text-sm text-teal-900" role="status">
                    <span class="flex items-center gap-2"><span class="material-symbols-outlined text-[19px]" aria-hidden="true">check_circle</span>{{ session('success') }}</span>
                    <button type="button" @click="show = false" class="rounded p-1 hover:bg-teal-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-teal-600"><span class="sr-only">Dismiss</span><span class="material-symbols-outlined text-[18px]" aria-hidden="true">close</span></button>
                </div>
            @endif

            <div class="mt-5 rounded-2xl border border-slate-200 bg-white/90 p-3 shadow-sm shadow-slate-200/60 backdrop-blur-sm">
                <div class="flex flex-col gap-3 xl:flex-row xl:items-center xl:justify-between">
                    <div class="flex flex-1 items-center gap-3">
                        <label for="board-search" class="relative block w-full max-w-xl flex-1">
                            <span class="material-symbols-outlined pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-base text-slate-400" aria-hidden="true">search</span>
                            <input id="board-search" x-ref="search" x-model="filters.q" @input="applyFilters()" type="search" placeholder="Search tasks, projects, keys..." class="board-search h-11 w-full rounded-xl border border-slate-200 bg-slate-50 pl-10 pr-3 text-sm text-slate-700 shadow-inner shadow-slate-200/60 outline-none transition focus:border-blue-300 focus:bg-white focus:ring-4 focus:ring-blue-100" />
                        </label>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <button type="button" @click="filters.overdue = !filters.overdue; applyFilters();" :data-active="filters.overdue" class="board-filter-btn inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-600 shadow-sm" aria-pressed="false">
                            <span class="material-symbols-outlined text-[14px]" aria-hidden="true">warning</span>
                            Overdue
                        </button>
                        <button type="button" @click="filters.high = !filters.high; applyFilters();" :data-active="filters.high" class="board-filter-btn inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-600 shadow-sm" aria-pressed="false">
                            <span class="material-symbols-outlined text-[14px]" aria-hidden="true">priority_high</span>
                            High priority
                        </button>
                        @if ($assignees->isNotEmpty())
                            <div class="relative">
                                <select x-model="filters.assignee" @change="applyFilters()" class="h-10 rounded-full border border-slate-200 bg-white px-3 pr-8 text-xs font-semibold text-slate-700 shadow-sm outline-none transition focus:border-blue-300 focus:ring-4 focus:ring-blue-100">
                                    <option :value="null">All assignees</option>
                                    @foreach ($assignees as $assignee)
                                        <option value="{{ $assignee->id }}">{{ $assignee->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @endif
                        <button type="button" @click="clearAll()" class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-slate-100 px-3 py-1.5 text-xs font-semibold text-slate-600 transition hover:bg-slate-200 focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-600">
                            <span class="material-symbols-outlined text-[14px]" aria-hidden="true">restart_alt</span>
                            Reset
                        </button>
                    </div>
                </div>
            </div>

            {{-- Board --}}
            <div class="kanban-scroll -mx-4 mt-5 flex snap-x snap-mandatory items-start gap-4 overflow-x-auto px-4 pb-4 sm:-mx-6 sm:px-6 lg:mx-0 lg:snap-none lg:overflow-visible lg:px-0">
                @foreach ($columns as $status => $column)
                    @php $columnTasks = $grouped->get($status, collect()); @endphp
                    <section class="kanban-enter kanban-column w-[86vw] shrink-0 snap-start rounded-xl border border-t-[3px] border-slate-200 {{ $column['accent'] }} bg-slate-100/80 transition-colors sm:w-[22rem] lg:w-auto lg:min-w-[19rem]"
                             style="animation-delay:{{ 60 + $loop->index * 70 }}ms"
                             :class="[collapsed.{{ $status }} ? 'kanban-column collapsed lg:!w-16 lg:!min-w-0 lg:!flex-none' : 'lg:flex-1', over === '{{ $status }}' ? 'kanban-drop-target !bg-blue-50/80 ring-2 ring-blue-500/40' : '']"
                             aria-labelledby="column-{{ $status }}"
                             @dragover.prevent="dragOver($event, '{{ $status }}')" @dragleave="dragLeave($event, '{{ $status }}')" @drop.prevent="drop($event, '{{ $status }}')">

                        {{-- Rail (collapsed, desktop) --}}
                        <div x-cloak x-show="collapsed.{{ $status }}" class="board-column-collapsed hidden flex-col items-center gap-3 py-3 lg:flex">
                            <button type="button" @click="toggleColumn('{{ $status }}')" class="grid h-8 w-8 place-items-center rounded text-slate-500 hover:bg-slate-200 focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-600" aria-label="Expand {{ $column['title'] }} column">
                                <span class="material-symbols-outlined text-[19px]" aria-hidden="true">keyboard_double_arrow_right</span>
                            </button>
                            <span class="rounded-full bg-white px-2 py-0.5 text-xs font-bold tabular-nums text-slate-600" x-text="counts.{{ $status }}">{{ $columnTasks->count() }}</span>
                            <span class="mt-1 text-sm font-semibold text-slate-700 [writing-mode:vertical-rl]">{{ $column['title'] }}</span>
                        </div>

                        <div class="board-column-main" :class="collapsed.{{ $status }} ? 'lg:hidden' : ''">
                            <header class="relative flex items-center gap-2 px-3 pb-2 pt-3">
                                <span class="h-2.5 w-2.5 shrink-0 rounded-full {{ $column['dot'] }}"></span>
                                <h2 id="column-{{ $status }}" class="font-semibold text-slate-800">{{ $column['title'] }}</h2>
                                <span class="rounded-full bg-white px-2 py-0.5 text-xs font-bold tabular-nums text-slate-600 shadow-sm" x-text="counts.{{ $status }}" aria-label="tasks">{{ $columnTasks->count() }}</span>
                                <span class="hidden truncate text-xs text-slate-500 xl:inline">{{ $column['hint'] }}</span>

                                <button type="button" @click="toggleColumn('{{ $status }}')" class="ml-auto hidden h-7 w-7 place-items-center rounded text-slate-400 hover:bg-slate-200 hover:text-slate-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-600 lg:grid" aria-label="Collapse {{ $column['title'] }} column">
                                    <span class="material-symbols-outlined text-[19px]" aria-hidden="true">keyboard_double_arrow_left</span>
                                </button>
                            </header>

                            <div class="kanban-scroll flex min-h-[220px] flex-col gap-2 overflow-y-auto px-2 pb-2 lg:max-h-[calc(100vh-22rem)]" data-column="{{ $status }}">
                                @foreach ($columnTasks as $task)
                                    @php
                                        $daysLeft = $task->due_date ? (int) now()->startOfDay()->diffInDays($task->due_date->copy()->startOfDay(), false) : null;
                                        $isOverdue = $daysLeft !== null && $daysLeft < 0 && $task->status !== 'completed';
                                        $dueSoon = $daysLeft !== null && $daysLeft >= 0 && $daysLeft <= 2 && $task->status !== 'completed';
                                        $dueLabel = $daysLeft === null ? null : ($daysLeft === 0 ? 'Today' : ($daysLeft === 1 ? 'Tomorrow' : $task->due_date->format('M j')));
                                        $dueTone = $isOverdue ? 'bg-rose-50 text-rose-700' : ($dueSoon ? 'bg-amber-50 text-amber-800' : 'bg-slate-100 text-slate-600');
                                        $priority = $priorities[$task->priority] ?? null;
                                        $projectName = $task->project?->name ?? 'No Project';
                                        $key = (Str::upper(Str::substr(preg_replace('/[^A-Za-z]/', '', $projectName), 0, 3)) ?: 'TSK') . '-' . $task->id;
                                        $searchText = Str::lower($task->title . ' ' . $projectName . ' ' . $task->description . ' ' . $key);
                                    @endphp
                                    <article id="task-{{ $task->id }}"
                                             x-data="{ open: false }" :class="open ? 'z-20' : ''"
                                             tabindex="{{ $canMove ? '0' : '-1' }}"
                                             data-task-id="{{ $task->id }}" data-status="{{ $status }}" data-priority="{{ $task->priority }}"
                                             data-overdue="{{ $isOverdue ? 1 : 0 }}" data-assignee="{{ $task->assignee?->id }}" data-search="{{ $searchText }}"
                                             draggable="{{ $canMove ? 'true' : 'false' }}" @dragstart="dragStart($event, {{ $task->id }})" @dragend="dragEnd()"
                                             @keydown.enter.prevent="open = true; setTimeout(() => $el.querySelector('.card-menu-btn')?.focus(), 20)"
                                             @keydown.space.prevent="open = true; setTimeout(() => $el.querySelector('.card-menu-btn')?.focus(), 20)"
                                             @keydown.arrowdown.prevent="moveFocus($event.currentTarget, 1)"
                                             @keydown.arrowup.prevent="moveFocus($event.currentTarget, -1)"
                                             @keydown.escape.prevent="open = false"
                                             class="kanban-card relative rounded-lg border border-slate-200 bg-white p-3 shadow-[0_1px_1px_rgba(15,23,42,.12)] {{ $canMove ? 'cursor-grab active:cursor-grabbing' : 'cursor-default' }}">
                                        <div class="flex items-start gap-2">
                                            @if ($task->project)
                                                <a href="{{ route('projects.tasks.show', [$task->project, $task]) }}" draggable="false" class="card-title min-w-0 flex-1 rounded text-sm font-medium leading-5 text-slate-800 hover:text-blue-700 hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-600">{{ $task->title }}</a>
                                            @else
                                                <span class="card-title min-w-0 flex-1 rounded text-sm font-medium leading-5 text-slate-800">{{ $task->title }}</span>
                                            @endif
                                            @if ($canMove)
                                                <div class="-mr-1 -mt-1 shrink-0" @keydown.escape.stop="open = false" @click.outside="open = false">
                                                    <button type="button" class="card-menu-btn grid h-7 w-7 place-items-center rounded text-slate-500 hover:bg-slate-100 hover:text-slate-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-600"
                                                            @click="open = !open" @keydown.enter.prevent="open = !open" @keydown.space.prevent="open = !open" :aria-expanded="open" aria-haspopup="menu" aria-label="Actions for {{ $task->title }}">
                                                        <span class="material-symbols-outlined text-[18px]" aria-hidden="true">more_horiz</span>
                                                    </button>
                                                    <div x-cloak x-show="open" x-transition.origin.top.right.duration.120ms role="menu" class="absolute right-2 top-9 z-30 w-48 rounded-lg border border-slate-200 bg-white py-1 text-sm shadow-lg">
                                                        <p class="px-3 pb-1 pt-1.5 text-xs font-medium text-slate-500">Move to</p>
                                                        @foreach ($columns as $key2 => $col2)
                                                            <button type="button" role="menuitem" @click="open = false; move({{ $task->id }}, '{{ $key2 }}')" :disabled="open && isIn($el, '{{ $key2 }}')"
                                                                    class="flex w-full items-center gap-2 px-3 py-1.5 text-left text-slate-700 hover:bg-slate-50 focus:bg-slate-50 focus:outline-none disabled:cursor-default disabled:text-slate-400 disabled:hover:bg-transparent">
                                                                <span class="h-2 w-2 rounded-full {{ $col2['dot'] }}"></span>{{ $col2['title'] }}
                                                                <span x-show="open && isIn($el, '{{ $key2 }}')" class="material-symbols-outlined ml-auto text-[17px]" aria-hidden="true">check</span>
                                                            </button>
                                                        @endforeach
                                                        @if ($task->project)
                                                            <div class="my-1 border-t border-slate-100"></div>
                                                            <a href="{{ route('projects.tasks.show', [$task->project, $task]) }}" role="menuitem" draggable="false" class="flex items-center gap-2 px-3 py-1.5 text-slate-700 hover:bg-slate-50 focus:bg-slate-50 focus:outline-none">
                                                                <span class="material-symbols-outlined text-[17px]" aria-hidden="true">open_in_new</span>Open task
                                                            </a>
                                                        @endif
                                                    </div>
                                                </div>
                                            @endif
                                        </div>

                                        @if ($task->description)
                                            <p class="mt-1 line-clamp-2 text-[13px] leading-5 text-slate-500">{{ $task->description }}</p>
                                        @endif

                                        <p class="mt-2 flex items-center gap-1.5 text-xs text-slate-500">
                                            <span class="h-2 w-2 shrink-0 rounded-full {{ $task->project ? $projectDots[$task->project->id % count($projectDots)] : 'bg-slate-400' }}"></span>
                                            <span class="truncate">{{ $projectName }}</span>
                                        </p>

                                        <div class="mt-3 flex items-center gap-2 text-xs">
                                            @if ($priority)
                                                <span class="material-symbols-outlined text-[19px] {{ $priority['tone'] }}" role="img" title="{{ ucfirst($task->priority) }} priority" aria-label="{{ ucfirst($task->priority) }} priority">{{ $priority['icon'] }}</span>
                                            @endif
                                            <span class="font-medium tabular-nums text-slate-500">{{ $key }}</span>
                                            @if ($dueLabel)
                                                <span class="due-chip inline-flex items-center gap-1 rounded px-1.5 py-0.5 font-medium {{ $dueTone }}">
                                                    <span class="material-symbols-outlined text-[14px]" aria-hidden="true">{{ $isOverdue ? 'event_busy' : 'calendar_today' }}</span>
                                                    @if ($isOverdue)<span class="sr-only">Overdue</span>@else<span class="sr-only">Due</span>@endif{{ $dueLabel }}
                                                </span>
                                            @endif
                                            @if ($task->assignee)
                                                <span title="{{ $task->assignee->name }}" class="ml-auto grid h-6 w-6 shrink-0 place-items-center rounded-full text-[10px] font-bold {{ $avatarTones[$task->assignee->id % count($avatarTones)] }}"><span aria-hidden="true">{{ $initials($task->assignee->name) }}</span><span class="sr-only">Assigned to {{ $task->assignee->name }}</span></span>
                                            @else
                                                <span class="ml-auto grid h-6 w-6 shrink-0 place-items-center rounded-full border border-dashed border-slate-300 text-slate-300" title="Unassigned"><span class="material-symbols-outlined text-[14px]" aria-hidden="true">person</span><span class="sr-only">Unassigned</span></span>
                                            @endif
                                        </div>

                                        @if ($task->project)
                                            <form data-status-form method="POST" action="{{ route('projects.tasks.status.update', [$task->project, $task]) }}" class="hidden">@csrf<input type="hidden" name="status" value="{{ $status }}"></form>
                                        @endif
                                    </article>
                                @endforeach

                                <div data-empty @class(['pointer-events-none grid min-h-[96px] place-items-center rounded-lg border border-dashed border-slate-300 px-3 text-center text-xs text-slate-500', 'hidden' => $columnTasks->isNotEmpty()])>
                                    <span data-empty-text>{{ $canMove ? 'Drop a task here' : 'No tasks yet' }}</span>
                                </div>
                            </div>
                        </div>
                    </section>
                @endforeach
            </div>
        </div>

        {{-- Toast --}}
        <div x-cloak x-show="toast.show" x-transition.opacity.duration.200ms class="fixed inset-x-0 bottom-6 z-50 flex justify-center px-4" role="status" aria-live="polite">
            <div class="flex max-w-md items-center gap-3 rounded-lg px-4 py-2.5 text-sm text-white shadow-xl" :class="toast.tone === 'error' ? 'bg-rose-700' : 'bg-slate-900'">
                <span class="material-symbols-outlined text-[19px]" aria-hidden="true" x-text="toast.tone === 'error' ? 'error' : 'check_circle'"></span>
                <span x-text="toast.message"></span>
                <button type="button" x-show="toast.undo" @click="const u = toast.undo; toast.show = false; u && u()" class="rounded px-1.5 font-semibold underline underline-offset-2 hover:bg-white/10 focus:outline-none focus-visible:ring-2 focus-visible:ring-white">Undo</button>
                <button type="button" @click="toast.show = false" class="-mr-1 rounded p-0.5 hover:bg-white/10 focus:outline-none focus-visible:ring-2 focus-visible:ring-white"><span class="sr-only">Dismiss</span><span class="material-symbols-outlined text-[17px]" aria-hidden="true">close</span></button>
            </div>
        </div>
    </div>

    <script>
        function kanbanBoard() {
            const STORE = 'projectms.board.collapsed';
            return {
                canMove: @js($canMove),
                statuses: @js(array_keys($columns)),
                counts: @js($totals),
                totals: @js($totals),
                overdue: 0,
                filters: { q: @js((string) request('q', '')), overdue: false, high: false, assignee: null },
                collapsed: { pending: false, in_progress: false, completed: false },
                dragging: null,
                dragGhost: null,
                over: null,
                toast: { show: false, message: '', tone: 'ok', undo: null },
                toastTimer: null,

                init() {
                    try { Object.assign(this.collapsed, JSON.parse(localStorage.getItem(STORE) || '{}')); } catch (e) {}
                    document.addEventListener('dragover', (event) => {
                        if (this.dragGhost) {
                            this.updateDragGhost(event);
                        }
                    });
                    this.applyFilters();
                    this.$nextTick(() => {
                        const searchInput = this.$refs.search;
                        if (searchInput && this.filters.q) {
                            searchInput.value = this.filters.q;
                        }
                    });
                },

                body(status) { return document.querySelector('[data-column="' + status + '"]'); },
                isIn(el, status) { const card = el.closest('[data-task-id]'); return !!card && card.dataset.status === status; },
                pct(status) { const total = Object.values(this.totals).reduce((a, b) => a + b, 0); return total ? (this.totals[status] / total) * 100 : 0; },
                donePct() { return Math.round(this.pct('completed')); },
                hasFilters() { return this.filters.q.trim() !== '' || this.filters.overdue || this.filters.high || this.filters.assignee !== null; },

                toggleAssignee(id) { this.filters.assignee = this.filters.assignee === id ? null : id; this.applyFilters(); },
                toggleColumn(status) {
                    this.collapsed[status] = !this.collapsed[status];
                    try { localStorage.setItem(STORE, JSON.stringify(this.collapsed)); } catch (e) {}
                },
                clearAll() {
                    if (@js($serverFiltered)) { window.location.href = @js(route('projects.tasks.board')); return; }
                    this.filters = { q: '', overdue: false, high: false, assignee: null };
                    this.$refs.search.value = '';
                    this.applyFilters();
                },

                applyFilters() {
                    const q = this.filters.q.trim().toLowerCase();
                    document.querySelectorAll('[data-task-id]').forEach(card => {
                        const d = card.dataset;
                        const show = (!q || d.search.includes(q))
                            && (!this.filters.overdue || (d.status !== 'completed' && d.overdue === '1'))
                            && (!this.filters.high || d.priority === 'high')
                            && (this.filters.assignee === null || d.assignee === String(this.filters.assignee));
                        card.classList.toggle('hidden', !show);
                    });
                    this.refresh();
                    document.querySelectorAll('.board-filter-btn').forEach(btn => {
                        const active = btn.getAttribute('data-active') === 'true';
                        btn.setAttribute('aria-pressed', active ? 'true' : 'false');
                    });
                },

                refresh() {
                    let overdue = 0;
                    this.statuses.forEach(status => {
                        const body = this.body(status);
                        if (!body) return;
                        const cards = Array.from(body.querySelectorAll('[data-task-id]'));
                        const visible = cards.filter(c => !c.classList.contains('hidden')).length;
                        this.counts[status] = visible;
                        this.totals[status] = cards.length;
                        const empty = body.querySelector('[data-empty]');
                        if (empty) {
                            empty.classList.toggle('hidden', visible > 0);
                            const emptyText = empty.querySelector('[data-empty-text]');
                            if (emptyText) {
                                emptyText.textContent = cards.length > 0
                                    ? 'No tasks match these filters'
                                    : (this.canMove ? 'Drop a task here' : 'No tasks yet');
                            }
                        }
                    });
                    document.querySelectorAll('[data-task-id]').forEach(c => { if (c.dataset.status !== 'completed' && c.dataset.overdue === '1') overdue++; });
                    this.overdue = overdue;
                },

                notify(message, tone = 'ok', undo = null) {
                    clearTimeout(this.toastTimer);
                    this.toast = { show: true, message, tone, undo };
                    this.toastTimer = setTimeout(() => { this.toast.show = false; }, undo ? 6000 : 4500);
                },

                flash(card) {
                    card.classList.add('is-landed');
                    setTimeout(() => card.classList.remove('is-landed'), 1000);
                },
                flashDrop(status, card = null) {
                    const column = this.body(status);
                    if (column) {
                        column.classList.remove('is-dropped');
                        void column.offsetWidth;
                        column.classList.add('is-dropped');
                        setTimeout(() => column.classList.remove('is-dropped'), 700);
                    }
                    if (card) {
                        card.classList.remove('is-dropped');
                        void card.offsetWidth;
                        card.classList.add('is-dropped');
                        setTimeout(() => card.classList.remove('is-dropped'), 750);
                    }
                },

                dragStart(event, id) {
                    if (!this.canMove) return;
                    this.dragging = id;
                    event.dataTransfer.effectAllowed = 'move';
                    event.dataTransfer.setData('text/plain', String(id));
                    this.createDragGhost(event.currentTarget, event);
                    event.currentTarget.classList.add('is-dragging');
                    document.body.classList.add('cursor-grabbing');
                },
                createDragGhost(card, event) {
                    if (this.dragGhost) {
                        this.dragGhost.remove();
                    }
                    const ghost = card.cloneNode(true);
                    ghost.classList.add('drag-ghost');
                    ghost.classList.remove('is-dragging');
                    ghost.querySelectorAll('button, a, form, input, select, textarea').forEach(el => el.setAttribute('tabindex', '-1'));
                    document.body.appendChild(ghost);
                    this.dragGhost = ghost;
                    this.updateDragGhost(event);
                },
                updateDragGhost(event) {
                    if (!this.dragGhost || !event) return;
                    this.dragGhost.style.left = (event.clientX + 18) + 'px';
                    this.dragGhost.style.top = (event.clientY + 18) + 'px';
                },
                dragEnd() {
                    if (this.dragGhost) {
                        this.dragGhost.remove();
                        this.dragGhost = null;
                    }
                    document.querySelectorAll('.is-dragging').forEach(el => el.classList.remove('is-dragging'));
                    this.dragging = null;
                    this.over = null;
                    document.body.classList.remove('cursor-grabbing');
                },
                dragOver(event, status) {
                    if (!this.dragging) return;
                    this.updateDragGhost(event);
                    event.dataTransfer.dropEffect = 'move';
                    this.over = status;
                },
                dragLeave(event, status) {
                    if (!event.currentTarget.contains(event.relatedTarget) && this.over === status) this.over = null;
                },
                drop(event, status) {
                    const id = event.dataTransfer?.getData('text/plain') || this.dragging;
                    this.over = null;

                    if (!id || !this.statuses.includes(status)) return;

                    const card = document.getElementById('task-' + id);
                    if (!card) return;

                    this.move(id, status);
                },
                moveFocus(current, delta) {
                    const cards = Array.from(document.querySelectorAll('[data-task-id]')).filter(card => !card.classList.contains('hidden'));
                    const currentIndex = cards.indexOf(current);
                    const nextIndex = currentIndex + delta;
                    if (nextIndex >= 0 && nextIndex < cards.length) {
                        cards[nextIndex].focus();
                    }
                },

                async move(id, status) {
                    const card = document.getElementById('task-' + id);
                    if (!this.canMove || !card || !this.statuses.includes(status) ||
                        card.dataset.status === status || card.dataset.busy === '1') return;

                    const form = card.querySelector('form[data-status-form]');
                    const input = form?.querySelector('input[name="status"]');
                    const sourceStatus = card.dataset.status;
                    const sourceBody = this.body(sourceStatus);
                    const targetBody = this.body(status);
                    if (!form || !input || !sourceBody || !targetBody) {
                        this.notify('Unable to move this task. Please refresh and try again.', 'error');
                        return;
                    }

                    const sourceNext = card.nextElementSibling;
                    const previousInput = input.value;
                    const before = card.getBoundingClientRect();
                    const targetColumn = targetBody.closest('.kanban-column');

                    // Optimistic UI: move immediately; persist in the background.
                    card.dataset.busy = '1';
                    card.classList.add('is-saving', 'is-optimistic');
                    input.value = status;
                    targetBody.insertBefore(card, targetBody.querySelector('[data-empty]'));
                    card.dataset.status = status;
                    this.applyFilters();

                    const after = card.getBoundingClientRect();
                    const dx = before.left - after.left;
                    const dy = before.top - after.top;
                    if (typeof card.animate === 'function' && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
                        card.animate([
                            { transform: `translate(${dx}px, ${dy}px) scale(.985)`, opacity: .82 },
                            { transform: 'translate(0, 0) scale(1)', opacity: 1 }
                        ], { duration: 300, easing: 'cubic-bezier(.2,.8,.2,1)' });
                    }
                    targetColumn?.classList.add('is-receiving');
                    window.setTimeout(() => targetColumn?.classList.remove('is-receiving'), 420);

                    try {
                        const response = await fetch(form.action, {
                            method: (form.method || 'POST').toUpperCase(),
                            body: new FormData(form),
                            credentials: 'same-origin',
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest',
                                'Accept': 'application/json'
                            }
                        });

                        // Laravel may redirect after a successful web request. Fetch follows
                        // that redirect; explicitly reject login redirects and HTTP failures.
                        const redirectedToLogin = response.redirected && /\/login(?:[/?#]|$)/i.test(response.url);
                        if (!response.ok || redirectedToLogin) {
                            let message = 'The server could not update this task. Your board has been restored.';
                            if (response.status === 403) message = 'You are not allowed to move this task.';
                            else if (response.status === 419) message = 'Your session expired. Refresh the page and try again.';
                            else if (response.status === 422) message = 'The selected status is invalid.';
                            else if (redirectedToLogin) message = 'Your session expired. Please sign in again.';
                            throw new Error(message);
                        }

                        card.classList.remove('is-saving', 'is-optimistic');
                        delete card.dataset.busy;
                        this.refresh();
                        this.flashDrop(status, card);
                        this.notify('Task moved to ' + (status === 'pending' ? 'To do' : status === 'in_progress' ? 'In progress' : 'Done') + '.');
                    } catch (error) {
                        // Roll back both the DOM position and the status value on failure.
                        const rollbackTarget = sourceNext && sourceNext.parentElement === sourceBody
                            ? sourceNext : sourceBody.querySelector('[data-empty]');
                        sourceBody.insertBefore(card, rollbackTarget || null);
                        card.dataset.status = sourceStatus;
                        input.value = previousInput;
                        card.classList.remove('is-saving', 'is-optimistic');
                        delete card.dataset.busy;
                        this.applyFilters();
                        this.notify(error.message || 'Network error. The task was not moved.', 'error');
                    }
                },
            };
        }
    </script>
</x-app-layout>