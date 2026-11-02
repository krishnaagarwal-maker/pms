@php
    $columns = [
        'pending' => ['title' => 'To do', 'hint' => 'Ready when you are', 'dot' => 'bg-slate-400', 'accent' => 'border-t-slate-400', 'bar' => 'bg-slate-400'],
        'in_progress' => ['title' => 'In progress', 'hint' => 'Being worked on', 'dot' => 'bg-blue-600', 'accent' => 'border-t-blue-600', 'bar' => 'bg-blue-600'],
        'completed' => ['title' => 'Done', 'hint' => 'Wrapped up', 'dot' => 'bg-teal-600', 'accent' => 'border-t-teal-600', 'bar' => 'bg-teal-600'],
    ];
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
        @keyframes kanban-enter { from { opacity: 0; transform: translateY(12px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes kanban-landed { 0% { box-shadow: 0 0 0 0 rgba(37, 99, 235, .55); } 100% { box-shadow: 0 0 0 11px rgba(37, 99, 235, 0); } }
        @keyframes kanban-drop-pulse { 0% { box-shadow: 0 0 0 0 rgba(59, 130, 246, .38), inset 0 0 0 1px rgba(59,130,246,.35); } 60% { box-shadow: 0 0 0 12px rgba(59, 130, 246, 0), inset 0 0 0 1px rgba(59,130,246,.45); } 100% { box-shadow: 0 0 0 0 rgba(59, 130, 246, 0), inset 0 0 0 1px rgba(59,130,246,.45); } }
        @keyframes kanban-drop-glow { 0% { transform: scale(.98); opacity: .6; } 50% { transform: scale(1.02); opacity: 1; } 100% { transform: scale(1); opacity: 1; } }
        @keyframes kanban-card-pop { 0% { transform: scale(.96); box-shadow: 0 0 0 0 rgba(34,197,94,.35); } 35% { transform: scale(1.03); box-shadow: 0 0 0 8px rgba(34,197,94,0); } 100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(34,197,94,0); } }
        .kanban-enter { animation: kanban-enter .45s cubic-bezier(.22,.8,.25,1) both; }
        .kanban-scroll { scrollbar-width: thin; scrollbar-color: rgb(203 213 225) transparent; }
        .kanban-card { position: relative; transition: border-color .15s ease, box-shadow .15s ease, background-color .15s ease, transform .12s ease, opacity .12s ease; }
        .kanban-card:hover { border-color: rgb(148 163 184); box-shadow: 0 3px 10px rgba(15, 23, 42, .09); }
        .kanban-card.is-dragging {
            opacity: .36;
            transform: rotate(1.5deg) scale(.98);
            box-shadow: 0 18px 32px rgba(15, 23, 42, .18);
            filter: saturate(.7);
        }
        .kanban-card.is-landed { animation: kanban-landed .9s ease-out; }
        .kanban-card.is-dropped {
            animation: kanban-card-pop .68s cubic-bezier(.2, .9, .2, 1);
            border-color: rgb(134 239 172);
            background: linear-gradient(180deg, rgba(240,253,244,.96), rgba(255,255,255,1));
        }
        .kanban-card[data-busy] { opacity: .7; }
        .kanban-card[data-status="completed"] .card-title { color: rgb(100 116 139); text-decoration: line-through; text-decoration-color: rgb(203 213 225); }
        .kanban-card[data-status="completed"] .due-chip { background: transparent !important; color: rgb(100 116 139) !important; }
        .card-menu-btn { opacity: 0; transition: opacity .15s ease; }
        .kanban-card:hover .card-menu-btn, .card-menu-btn:focus-visible, .card-menu-btn[aria-expanded="true"] { opacity: 1; }
        .kanban-drop-target {
            position: relative;
            background: linear-gradient(180deg, rgba(239,246,255,.95) 0%, rgba(248,250,252,.95) 100%);
            border-color: rgba(59,130,246,.6) !important;
            box-shadow: inset 0 0 0 1px rgba(59,130,246,.45), 0 0 0 1px rgba(59,130,246,.2), 0 18px 26px rgba(59,130,246,.12);
            animation: kanban-drop-pulse .9s ease-in-out infinite alternate;
        }
        .kanban-drop-target::before {
            content: "";
            position: absolute;
            inset: 10px 12px;
            border: 2px dashed rgba(59,130,246,.7);
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
        .kanban-drop-target .drop-hint {
            opacity: 1;
            transform: translateY(0);
        }
        .drop-hint {
            opacity: 0;
            transform: translateY(-4px);
            transition: opacity .18s ease, transform .18s ease;
        }
        @media (hover: none) { .card-menu-btn { opacity: 1; } }
        @media (prefers-reduced-motion: reduce) { .kanban-enter, .kanban-card.is-landed, .kanban-drop-target, .kanban-drop-target::before { animation: none !important; } .kanban-card { transition: none !important; } }
    </style>

    <div class="min-h-[calc(100vh-4rem)] bg-slate-50" x-data="kanbanBoard()" @keydown.window="if ($event.key === '/' && !['INPUT','TEXTAREA','SELECT'].includes($event.target.tagName)) { $event.preventDefault(); $refs.search.focus(); }">
        <div class="mx-auto max-w-[1680px] px-4 pb-10 pt-6 sm:px-6 lg:px-8">

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
                                <span class="material-symbols-outlined text-[18px]" aria-hidden="true">add</span> New task
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

            {{-- Toolbar --}}
           
            {{-- Board --}}
            <div class="kanban-scroll -mx-4 mt-5 flex snap-x snap-mandatory items-start gap-4 overflow-x-auto px-4 pb-4 sm:-mx-6 sm:px-6 lg:mx-0 lg:snap-none lg:overflow-visible lg:px-0">
                @foreach ($columns as $status => $column)
                    @php $columnTasks = $grouped->get($status, collect()); @endphp
                    <section class="kanban-enter w-[86vw] shrink-0 snap-start rounded-xl border border-t-[3px] border-slate-200 {{ $column['accent'] }} bg-slate-100/80 transition-colors sm:w-[22rem] lg:w-auto lg:min-w-[19rem]"
                             style="animation-delay:{{ 60 + $loop->index * 70 }}ms"
                             :class="[collapsed.{{ $status }} ? 'lg:!w-16 lg:!min-w-0 lg:!flex-none' : 'lg:flex-1', over === '{{ $status }}' ? 'kanban-drop-target !bg-blue-50/80 ring-2 ring-blue-500/40' : '']"
                             aria-labelledby="column-{{ $status }}"
                             @dragover.prevent="dragOver($event, '{{ $status }}')" @dragleave="dragLeave($event, '{{ $status }}')" @drop.prevent="drop($event, '{{ $status }}')">

                        {{-- Rail (collapsed, desktop) --}}
                        <div x-cloak x-show="collapsed.{{ $status }}" class="hidden flex-col items-center gap-3 py-3 lg:flex">
                            <button type="button" @click="toggleColumn('{{ $status }}')" class="grid h-8 w-8 place-items-center rounded text-slate-500 hover:bg-slate-200 focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-600" aria-label="Expand {{ $column['title'] }} column">
                                <span class="material-symbols-outlined text-[19px]" aria-hidden="true">keyboard_double_arrow_right</span>
                            </button>
                            <span class="rounded-full bg-white px-2 py-0.5 text-xs font-bold tabular-nums text-slate-600" x-text="counts.{{ $status }}">{{ $columnTasks->count() }}</span>
                            <span class="mt-1 text-sm font-semibold text-slate-700 [writing-mode:vertical-rl]">{{ $column['title'] }}</span>
                        </div>

                        <div :class="collapsed.{{ $status }} ? 'lg:hidden' : ''">
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
                                        $projectName = $task->project->name;
                                        $key = (Str::upper(Str::substr(preg_replace('/[^A-Za-z]/', '', $projectName), 0, 3)) ?: 'TSK') . '-' . $task->id;
                                        $searchText = Str::lower($task->title . ' ' . $projectName . ' ' . $task->description . ' ' . $key);
                                    @endphp
                                    <article id="task-{{ $task->id }}"
                                             x-data="{ open: false }" :class="open ? 'z-20' : ''"
                                             data-task-id="{{ $task->id }}" data-status="{{ $status }}" data-priority="{{ $task->priority }}"
                                             data-overdue="{{ $isOverdue ? 1 : 0 }}" data-assignee="{{ $task->assignee?->id }}" data-search="{{ $searchText }}"
                                             draggable="{{ $canMove ? 'true' : 'false' }}" @dragstart="dragStart($event, {{ $task->id }})" @dragend="dragEnd()"
                                             class="kanban-card relative rounded-lg border border-slate-200 bg-white p-3 shadow-[0_1px_1px_rgba(15,23,42,.12)] {{ $canMove ? 'cursor-grab active:cursor-grabbing' : 'cursor-default' }}">
                                        <div class="flex items-start gap-2">
                                            <a href="{{ route('projects.tasks.show', [$task->project, $task]) }}" draggable="false" class="card-title min-w-0 flex-1 rounded text-sm font-medium leading-5 text-slate-800 hover:text-blue-700 hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-600">{{ $task->title }}</a>
                                            @if ($canMove)
                                                <div class="-mr-1 -mt-1 shrink-0" @keydown.escape.stop="open = false" @click.outside="open = false">
                                                    <button type="button" class="card-menu-btn grid h-7 w-7 place-items-center rounded text-slate-500 hover:bg-slate-100 hover:text-slate-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-600"
                                                            @click="open = !open" :aria-expanded="open" aria-haspopup="menu" aria-label="Actions for {{ $task->title }}">
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
                                                        <div class="my-1 border-t border-slate-100"></div>
                                                        <a href="{{ route('projects.tasks.show', [$task->project, $task]) }}" role="menuitem" draggable="false" class="flex items-center gap-2 px-3 py-1.5 text-slate-700 hover:bg-slate-50 focus:bg-slate-50 focus:outline-none">
                                                            <span class="material-symbols-outlined text-[17px]" aria-hidden="true">open_in_new</span>Open task
                                                        </a>
                                                    </div>
                                                </div>
                                            @endif
                                        </div>

                                        @if ($task->description)
                                            <p class="mt-1 line-clamp-2 text-[13px] leading-5 text-slate-500">{{ $task->description }}</p>
                                        @endif

                                        <p class="mt-2 flex items-center gap-1.5 text-xs text-slate-500">
                                            <span class="h-2 w-2 shrink-0 rounded-full {{ $projectDots[$task->project->id % count($projectDots)] }}"></span>
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

                                        <form data-status-form method="POST" action="{{ route('projects.tasks.status.update', [$task->project, $task]) }}" class="hidden">@csrf<input type="hidden" name="status" value="{{ $status }}"></form>
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
                labels: @js(collect($columns)->map(fn ($c) => $c['title'])),
                counts: @js($totals),
                totals: @js($totals),
                overdue: 0,
                filters: { q: '', overdue: false, high: false, assignee: null },
                collapsed: { pending: false, in_progress: false, completed: false },
                dragging: null,
                over: null,
                toast: { show: false, message: '', tone: 'ok', undo: null },
                toastTimer: null,

                init() {
                    try { Object.assign(this.collapsed, JSON.parse(localStorage.getItem(STORE) || '{}')); } catch (e) {}
                    this.applyFilters();
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
                },

                refresh() {
                    let overdue = 0;
                    Object.keys(this.labels).forEach(status => {
                        const body = this.body(status);
                        if (!body) return;
                        const cards = Array.from(body.querySelectorAll('[data-task-id]'));
                        const visible = cards.filter(c => !c.classList.contains('hidden')).length;
                        this.counts[status] = visible;
                        this.totals[status] = cards.length;
                        const empty = body.querySelector('[data-empty]');
                        empty.classList.toggle('hidden', visible > 0);
                        empty.querySelector('[data-empty-text]').textContent = cards.length > 0 ? 'No tasks match these filters' : (this.canMove ? 'Drop a task here' : 'No tasks yet');
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
                    event.dataTransfer.setDragImage(event.currentTarget, 18, 18);
                    event.currentTarget.classList.add('is-dragging');
                    document.body.classList.add('cursor-grabbing');
                },
                dragEnd() {
                    document.querySelectorAll('.is-dragging').forEach(el => el.classList.remove('is-dragging'));
                    this.dragging = null;
                    this.over = null;
                    document.body.classList.remove('cursor-grabbing');
                },
                dragOver(event, status) {
                    if (!this.dragging) return;
                    event.dataTransfer.dropEffect = 'move';
                    this.over = status;
                },
                dragLeave(event, status) {
                    if (!event.currentTarget.contains(event.relatedTarget) && this.over === status) this.over = null;
                },
                drop(event, status) {
                    const id = event.dataTransfer.getData('text/plain') || this.dragging;
                    this.over = null;
                    if (id) {
                        const card = document.getElementById('task-' + id);
                        this.flashDrop(status, card);
                        this.move(id, status);
                    }
                },

                move(id, status) {
                    const card = document.getElementById('task-' + id);
                    if (!this.canMove || !card || card.dataset.status === status || card.dataset.busy) return;

                    const form = card.querySelector('form[data-status-form]');
                    const input = form.querySelector('input[name="status"]');

                    // Use a normal Laravel form submission for board moves. This is
                    // deliberately not an optimistic fetch: the page only re-renders
                    // after the authenticated server-side status update succeeds.
                    card.dataset.busy = '1';
                    input.value = status;
                    form.submit();
                },
            };
        }
    </script>
</x-app-layout>
