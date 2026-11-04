{{-- View: projects.index --}}

@php
    $today = now()->startOfDay();

    /*
     | Project status and visual token helper
     | -----------------------------------------------------
     | Ongoing   : indigo primary (in flight, on track)
     | Due soon  : amber warning  (ends within 7 days)
     | Overdue   : rose error     (past end date without completion)
     | Upcoming  : slate neutral  (start date in the future)
     | Completed : emerald        (all tasks completed)
     */
    $statusInfo = function ($project) use ($today) {
        $tasksCount = $project->tasks->count();
        $completedTasks = $project->tasks->whereIn('status', ['completed', 'done'])->count();

        // If project has tasks and all are marked done
        if ($tasksCount > 0 && $completedTasks === $tasksCount) {
            return [
                'key'         => 'completed',
                'label'       => 'Completed',
                'pill'        => 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-600/20',
                'dot'         => 'bg-emerald-500',
                'accent'      => 'bg-emerald-500',
                'badge_text'  => 'Shipped',
                'badge_color' => 'text-emerald-700',
                'pulse'       => false,
                'is_risk'     => false,
            ];
        }

        // Upcoming (start date in future)
        if ($project->start_date && $project->start_date->gt($today)) {
            $daysUntil = (int) $today->diffInDays($project->start_date, false);
            return [
                'key'         => 'upcoming',
                'label'       => 'Upcoming',
                'pill'        => 'bg-slate-100 text-slate-700 ring-1 ring-slate-400/20',
                'dot'         => 'bg-slate-400',
                'accent'      => 'bg-slate-400',
                'badge_text'  => 'Starts in ' . $daysUntil . 'd',
                'badge_color' => 'text-slate-500',
                'pulse'       => false,
                'is_risk'     => false,
            ];
        }

        // Overdue / Ended
        if ($project->end_date && $project->end_date->lt($today)) {
            $daysPast = abs((int) $today->diffInDays($project->end_date, false));
            return [
                'key'         => 'overdue',
                'label'       => 'Ended',
                'pill'        => 'bg-rose-50 text-rose-700 ring-1 ring-rose-600/20',
                'dot'         => 'bg-rose-500',
                'accent'      => 'bg-rose-500',
                'badge_text'  => 'Overdue by ' . $daysPast . 'd',
                'badge_color' => 'text-rose-600',
                'pulse'       => false,
                'is_risk'     => true,
            ];
        }

        // Due soon (within 7 days)
        if ($project->end_date && $today->diffInDays($project->end_date, false) <= 7) {
            $daysLeft = (int) ceil($today->diffInDays($project->end_date, false));
            return [
                'key'         => 'pending',
                'label'       => 'Due soon',
                'pill'        => 'bg-amber-50 text-amber-700 ring-1 ring-amber-600/20',
                'dot'         => 'bg-amber-500',
                'accent'      => 'bg-amber-500',
                'badge_text'  => $daysLeft <= 0 ? 'Due today' : $daysLeft . 'd remaining',
                'badge_color' => 'text-amber-700',
                'pulse'       => true,
                'is_risk'     => true,
            ];
        }

        // Active / On track
        $daysLeft = $project->end_date ? (int) ceil($today->diffInDays($project->end_date, false)) : null;
        return [
            'key'         => 'ongoing',
            'label'       => 'Active',
            'pill'        => 'bg-indigo-50 text-indigo-700 ring-1 ring-indigo-600/20',
            'dot'         => 'bg-indigo-600',
            'accent'      => 'bg-indigo-600',
            'badge_text'  => $daysLeft !== null ? $daysLeft . ' days left' : 'On Track',
            'badge_color' => 'text-indigo-600',
            'pulse'       => true,
            'is_risk'     => false,
        ];
    };

    // Calculate progress (0 - 100)
    $progressFor = function ($project) use ($today) {
        $total = $project->tasks->count();
        if ($total > 0) {
            $done = $project->tasks->whereIn('status', ['completed', 'done'])->count();
            return (int) round(($done / $total) * 100);
        }

        if ($project->start_date && $project->end_date) {
            $span = max(1, $project->start_date->diffInDays($project->end_date));
            $done = $project->start_date->diffInDays($today, false);
            return (int) max(0, min(100, round(($done / $span) * 100)));
        }

        return 0;
    };

    // Count of overdue tasks
    $overdueTasksCount = function ($project) use ($today) {
        return $project->tasks
            ->whereNotIn('status', ['completed', 'done'])
            ->filter(fn ($t) => $t->due_date && $t->due_date->lt($today))
            ->count();
    };

    // Pulse summaries: 4 primary states (active, due soon, upcoming, ended)
    $statsSource = $allProjects ?? $projects;
    $activeCount = $statsSource->filter(fn ($p) => $statusInfo($p)['key'] === 'ongoing')->count();
    $dueSoonCount = $statsSource->filter(fn ($p) => $statusInfo($p)['key'] === 'pending')->count();
    $upcomingCount = $statsSource->filter(fn ($p) => $statusInfo($p)['key'] === 'upcoming')->count();
    $endedCount = $statsSource->filter(fn ($p) => in_array($statusInfo($p)['key'], ['overdue', 'completed']))->count();

    // Color avatars generator palette
    $avatarColors = [
        'bg-indigo-100 text-indigo-700 ring-white',
        'bg-sky-100 text-sky-700 ring-white',
        'bg-emerald-100 text-emerald-700 ring-white',
        'bg-amber-100 text-amber-700 ring-white',
        'bg-purple-100 text-purple-700 ring-white',
        'bg-rose-100 text-rose-700 ring-white',
    ];

    // Client-side export & filter payload
    $clientProjects = $projects->map(function ($p) use ($statusInfo, $progressFor, $overdueTasksCount) {
        $info = $statusInfo($p);
        $prog = $progressFor($p);
        $overdue = $overdueTasksCount($p);
        $tasksCount = $p->tasks->count();
        $completedCount = $p->tasks->whereIn('status', ['completed', 'done'])->count();

        $members = $p->tasks->pluck('assignee')->filter()->unique('id')->map(function ($u) {
            return [
                'id' => $u->id,
                'name' => $u->name,
                'initials' => strtoupper(substr($u->name, 0, 2)),
            ];
        })->values();

        $lead = $members->first()['name'] ?? Auth::user()->name ?? 'Project Lead';

        return [
            'id'              => $p->id,
            'name'            => $p->name,
            'description'     => $p->description ?? '',
            'status_key'      => $info['key'],
            'status_label'    => $info['label'],
            'progress'        => $prog,
            'tasks_count'     => $tasksCount,
            'completed_count' => $completedCount,
            'overdue_count'   => $overdue,
            'start_date'      => $p->start_date ? $p->start_date->format('M d, Y') : null,
            'end_date'        => $p->end_date ? $p->end_date->format('M d, Y') : null,
            'lead'            => $lead,
            'members_count'   => $members->count(),
        ];
    });
@endphp

<x-app-layout>
    <style>
       

        @keyframes projects-enter {
            from {
                opacity: 0;
                transform: translateY(14px) scale(0.985);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        @keyframes projects-stat-enter {
            from {
                opacity: 0;
                transform: translateY(18px) scale(0.96);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        @keyframes projects-card-enter {
            from {
                opacity: 0;
                transform: translateY(20px) scale(0.97);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        @keyframes progress-fill {
            from { transform: scaleX(0); }
            to   { transform: scaleX(1); }
        }

        @keyframes soft-pulse {
            0%, 100% { opacity: 1; }
            50%      { opacity: 0.55; }
        }

        .projects-enter {
            animation: projects-enter 0.55s cubic-bezier(0.22, 1, 0.36, 1) both;
        }

        .projects-stat {
            animation: projects-stat-enter 0.6s cubic-bezier(0.22, 1, 0.36, 1) both;
        }

        .projects-card {
            animation: projects-card-enter 0.55s cubic-bezier(0.22, 1, 0.36, 1) both;
        }

        .progress-bar-fill {
            transform-origin: left;
            animation: progress-fill 0.9s cubic-bezier(0.22, 1, 0.36, 1) both;
        }

        .status-dot-pulse {
            animation: soft-pulse 2.2s ease-in-out infinite;
        }

        /* Hover lift utility */
        .hover-lift {
            transition: transform 0.25s cubic-bezier(0.22, 1, 0.36, 1),
                        box-shadow 0.25s cubic-bezier(0.22, 1, 0.36, 1),
                        border-color 0.2s ease;
        }
        .hover-lift:hover {
            transform: translateY(-3px);
        }

        /* Respect reduced motion */
        @media (prefers-reduced-motion: reduce) {
            .projects-enter,
            .projects-stat,
            .projects-card,
            .progress-bar-fill,
            .status-dot-pulse {
                animation: none !important;
                transform: none !important;
                opacity: 1 !important;
            }
            .hover-lift:hover {
                transform: none;
            }
        }
    </style>

    <div class="mx-auto w-full max-w-[1600px] px-4 py-8 sm:px-6 lg:px-8 space-y-7"
         x-data="{
             search: '',
             statusFilter: 'all',
             sortBy: 'deadline',
             viewMode: (localStorage.getItem('projects_view_mode') || 'grid'),
             activeMenu: null,
             deleteModalOpen: false,
             deleteData: { id: null, name: '', tasksCount: 0, url: '' },
             
             setViewMode(mode) {
                 this.viewMode = mode;
                 localStorage.setItem('projects_view_mode', mode);
             },

             toggleMenu(id) {
                 this.activeMenu = this.activeMenu === id ? null : id;
             },

             closeMenus() {
                 this.activeMenu = null;
             },

             openDeleteModal(id, name, tasksCount, url) {
                 this.activeMenu = null;
                 this.deleteData = { id, name, tasksCount, url };
                 this.deleteModalOpen = true;
             },

             closeDeleteModal() {
                 this.deleteModalOpen = false;
             },

             matches(status, haystack) {
                 const q = this.search.trim().toLowerCase();
                 const matchesSearch = !q || haystack.includes(q);
                 const matchesStatus = this.statusFilter === 'all'
                     || this.statusFilter === status
                     || (this.statusFilter === 'overdue' && (status === 'overdue' || status === 'completed'));
                 return matchesSearch && matchesStatus;
             },

             exportCsv() {
                 const items = {{ \Illuminate\Support\Js::from($clientProjects) }};
                 if (!items.length) {
                     alert('No projects to export.');
                     return;
                 }
                 let csv = 'Project ID,Name,Status,Progress,Tasks Completed,Total Tasks,Start Date,End Date,Lead\n';
                 items.forEach(p => {
                     csv += `&quot;${p.id}&quot;,&quot;${(p.name || '').replace(/&quot;/g, '&quot;&quot;')}&quot;,&quot;${p.status_label}&quot;,&quot;${p.progress}%&quot;,&quot;${p.completed_count}&quot;,&quot;${p.tasks_count}&quot;,&quot;${p.start_date || '—'}&quot;,&quot;${p.end_date || '—'}&quot;,&quot;${p.lead}&quot;\n`;
                 });
                 const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
                 const url = URL.createObjectURL(blob);
                 const a = document.createElement('a');
                 a.href = url;
                 a.download = `projects_export_${new Date().toISOString().slice(0, 10)}.csv`;
                 document.body.appendChild(a);
                 a.click();
                 document.body.removeChild(a);
                 URL.revokeObjectURL(url);
             }
         }"
         @click.away="closeMenus()"
         @keydown.escape.window="closeMenus(); closeDeleteModal()"
         @keydown.window="if (($event.key === '/' || ($event.ctrlKey && $event.key === '/')) && !['INPUT', 'TEXTAREA'].includes($event.target.tagName)) { $event.preventDefault(); $refs.searchInput.focus(); }">

        {{-- Flash message --}}
        @if (session('success'))
            <div x-data="{ show: true }" 
                 x-show="show" 
                 x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="opacity-0 -translate-y-2"
                 x-transition:enter-end="opacity-100 translate-y-0"
                 x-transition:leave="transition ease-in duration-200"
                 x-transition:leave-start="opacity-100"
                 x-transition:leave-end="opacity-0"
                 role="status"
                 class="projects-enter flex items-center justify-between gap-4 rounded-2xl bg-emerald-50/90 border border-emerald-200/70 px-5 py-3.5 text-emerald-800 shadow-sm backdrop-blur-sm">
                <div class="flex items-center gap-3">
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-emerald-500 text-white shadow-md shadow-emerald-500/25">
                        <span class="material-symbols-outlined text-[20px]">check_circle</span>
                    </div>
                    <div class="flex flex-wrap items-baseline gap-1.5">
                        <span class="text-sm font-semibold">Success</span>
                        <span class="text-sm text-emerald-700/90">{{ session('success') }}</span>
                    </div>
                </div>
                <button type="button" @click="show = false" aria-label="Dismiss"
                        class="shrink-0 rounded-lg p-1.5 text-emerald-600/80 transition hover:bg-emerald-100 hover:text-emerald-700">
                    <span class="material-symbols-outlined text-[18px]">close</span>
                </button>
            </div>
        @endif

        {{-- ===================== STATS STRIP ===================== --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
            {{-- Active --}}
            <div role="button" tabindex="0"
                 @click="statusFilter = (statusFilter === 'ongoing' ? 'all' : 'ongoing')"
                 @keydown.enter="statusFilter = (statusFilter === 'ongoing' ? 'all' : 'ongoing')"
                 :class="statusFilter === 'ongoing' 
                    ? 'ring-2 ring-indigo-500/70 border-indigo-300 bg-indigo-50/40 shadow-md shadow-indigo-500/10 -translate-y-0.5' 
                    : 'hover:border-slate-300 hover:shadow-md hover:-translate-y-0.5'"
                 class="projects-stat group relative overflow-hidden bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm flex items-center justify-between transition-all duration-300 cursor-pointer select-none"
                 style="animation-delay: 0ms">
                <div class="absolute inset-0 bg-gradient-to-br from-indigo-500/[0.03] to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>
                <div class="relative flex flex-col">
                    <span class="text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Active</span>
                    <div class="flex items-baseline gap-2 mt-1.5">
                        <span class="text-3xl font-bold tracking-tight text-slate-900 tabular-nums">{{ $activeCount }}</span>
                        <span class="font-mono text-xs font-medium text-indigo-600">
                            {{ $activeCount === 1 ? 'on track' : 'on track' }}
                        </span>
                    </div>
                </div>
                <div class="relative w-12 h-12 rounded-2xl bg-indigo-50 border border-indigo-100/80 flex items-center justify-center text-indigo-600 shadow-sm shadow-indigo-600/5 group-hover:scale-105 transition-transform duration-300">
                    <span class="material-symbols-outlined text-[26px]">rocket_launch</span>
                </div>
            </div>

            {{-- Due Soon --}}
            <div role="button" tabindex="0"
                 @click="statusFilter = (statusFilter === 'pending' ? 'all' : 'pending')"
                 @keydown.enter="statusFilter = (statusFilter === 'pending' ? 'all' : 'pending')"
                 :class="statusFilter === 'pending' 
                    ? 'ring-2 ring-amber-500/70 border-amber-300 bg-amber-50/40 shadow-md shadow-amber-500/10 -translate-y-0.5' 
                    : 'hover:border-slate-300 hover:shadow-md hover:-translate-y-0.5'"
                 class="projects-stat group relative overflow-hidden bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm flex items-center justify-between transition-all duration-300 cursor-pointer select-none"
                 style="animation-delay: 60ms">
                <div class="absolute inset-0 bg-gradient-to-br from-amber-500/[0.04] to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>
                <div class="relative flex flex-col">
                    <span class="text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Due Soon</span>
                    <div class="flex items-baseline gap-2 mt-1.5">
                        <span class="text-3xl font-bold tracking-tight tabular-nums {{ $dueSoonCount > 0 ? 'text-amber-600' : 'text-slate-900' }}">
                            {{ $dueSoonCount }}
                        </span>
                        <span class="font-mono text-xs {{ $dueSoonCount > 0 ? 'text-amber-600 font-medium' : 'text-slate-500' }}">
                            {{ $dueSoonCount > 0 ? 'within 7d' : 'none' }}
                        </span>
                    </div>
                </div>
                <div class="relative w-12 h-12 rounded-2xl bg-amber-50 border border-amber-100/80 flex items-center justify-center text-amber-600 shadow-sm shadow-amber-600/5 group-hover:scale-105 transition-transform duration-300">
                    <span class="material-symbols-outlined text-[26px]">schedule</span>
                </div>
            </div>

            {{-- Upcoming --}}
            <div role="button" tabindex="0"
                 @click="statusFilter = (statusFilter === 'upcoming' ? 'all' : 'upcoming')"
                 @keydown.enter="statusFilter = (statusFilter === 'upcoming' ? 'all' : 'upcoming')"
                 :class="statusFilter === 'upcoming' 
                    ? 'ring-2 ring-sky-500/70 border-sky-300 bg-sky-50/40 shadow-md shadow-sky-500/10 -translate-y-0.5' 
                    : 'hover:border-slate-300 hover:shadow-md hover:-translate-y-0.5'"
                 class="projects-stat group relative overflow-hidden bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm flex items-center justify-between transition-all duration-300 cursor-pointer select-none"
                 style="animation-delay: 120ms">
                <div class="absolute inset-0 bg-gradient-to-br from-sky-500/[0.04] to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>
                <div class="relative flex flex-col">
                    <span class="text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Upcoming</span>
                    <div class="flex items-baseline gap-2 mt-1.5">
                        <span class="text-3xl font-bold tracking-tight text-slate-900 tabular-nums">{{ $upcomingCount }}</span>
                        <span class="font-mono text-xs {{ $upcomingCount > 0 ? 'text-sky-600 font-medium' : 'text-slate-500' }}">
                            {{ $upcomingCount > 0 ? 'starting soon' : 'none' }}
                        </span>
                    </div>
                </div>
                <div class="relative w-12 h-12 rounded-2xl bg-sky-50 border border-sky-100/80 flex items-center justify-center text-sky-600 shadow-sm shadow-sky-600/5 group-hover:scale-105 transition-transform duration-300">
                    <span class="material-symbols-outlined text-[26px]">event_upcoming</span>
                </div>
            </div>

            {{-- Ended --}}
            <div role="button" tabindex="0"
                 @click="statusFilter = (statusFilter === 'overdue' ? 'all' : 'overdue')"
                 @keydown.enter="statusFilter = (statusFilter === 'overdue' ? 'all' : 'overdue')"
                 :class="statusFilter === 'overdue' 
                    ? 'ring-2 ring-rose-500/70 border-rose-300 bg-rose-50/40 shadow-md shadow-rose-500/10 -translate-y-0.5' 
                    : 'hover:border-slate-300 hover:shadow-md hover:-translate-y-0.5'"
                 class="projects-stat group relative overflow-hidden bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm flex items-center justify-between transition-all duration-300 cursor-pointer select-none"
                 style="animation-delay: 180ms">
                <div class="absolute inset-0 bg-gradient-to-br from-rose-500/[0.04] to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>
                <div class="relative flex flex-col">
                    <span class="text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Ended</span>
                    <div class="flex items-baseline gap-2 mt-1.5">
                        <span class="text-3xl font-bold tracking-tight tabular-nums {{ $endedCount > 0 ? 'text-rose-600' : 'text-slate-900' }}">
                            {{ $endedCount }}
                        </span>
                        <span class="font-mono text-xs {{ $endedCount > 0 ? 'text-rose-600 font-medium' : 'text-slate-500' }}">
                            {{ $endedCount > 0 ? 'past deadline' : 'none' }}
                        </span>
                    </div>
                </div>
                <div class="relative w-12 h-12 rounded-2xl {{ $endedCount > 0 ? 'bg-rose-50 border-rose-100/80 text-rose-600' : 'bg-slate-50 border-slate-100 text-slate-500' }} border flex items-center justify-center shadow-sm group-hover:scale-105 transition-transform duration-300">
                    <span class="material-symbols-outlined text-[26px]">task_alt</span>
                </div>
            </div>
        </div>

        {{-- ===================== HEADER ===================== --}}
        <div class="projects-enter flex flex-col md:flex-row md:items-end justify-between gap-5 pt-1" style="animation-delay: 220ms">
            <div>
                <div class="flex items-center gap-3">
                    <h1 class="text-3xl font-bold text-slate-900 tracking-tight">Projects</h1>
                    @if ($projects->total() > 0)
                        <span class="inline-flex items-center rounded-full bg-slate-100/90 px-2.5 py-0.5 text-xs font-semibold text-slate-600 font-mono ring-1 ring-slate-200/60">
                            {{ number_format($projects->total()) }}
                        </span>
                    @endif
                </div>
                <p class="text-sm text-slate-500 mt-1.5 max-w-2xl leading-relaxed">
                    Manage, track, and coordinate all company initiatives across engineering and design squads.
                </p>
            </div>

            <div class="flex items-center gap-2.5 flex-shrink-0">
                <button type="button" @click="exportCsv()"
                        class="inline-flex items-center gap-2 bg-white hover:bg-slate-50 text-slate-700 px-4 py-2.5 rounded-xl text-sm font-semibold border border-slate-200/90 shadow-sm transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 active:scale-[0.98]">
                    <span class="material-symbols-outlined text-[18px]">download</span>
                    <span>Export CSV</span>
                </button>

                @can('projects.create')
                <a href="{{ route('projects.create') }}"
                   class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2.5 rounded-xl text-sm font-semibold shadow-md shadow-indigo-600/20 transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-indigo-600 focus:ring-offset-2 active:scale-[0.98]">
                    <span class="material-symbols-outlined text-[18px]">add</span>
                    <span>Create Project</span>
                </a>
                @endcan
            </div>
        </div>

        {{-- ===================== FILTER BAR ===================== --}}
        <div class="projects-enter bg-white/80 backdrop-blur-sm rounded-2xl p-3 border border-slate-200/80 shadow-sm flex flex-col lg:flex-row gap-3 items-center justify-between" style="animation-delay: 260ms">
            <div class="relative w-full lg:w-[26rem]">
                <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                    <span class="material-symbols-outlined text-[18px]">search</span>
                </div>
                <input type="text" 
                       x-model="search" 
                       x-ref="searchInput"
                       placeholder="Search projects by name or description…"
                       class="w-full bg-slate-50/80 pl-10 pr-12 py-2.5 rounded-xl text-sm text-slate-800 placeholder:text-slate-400 border border-slate-200/70 focus:outline-none focus:bg-white focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/15 transition-all duration-200" />
                <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                    <kbd class="font-mono text-[10px] bg-white text-slate-400 border border-slate-200/80 px-1.5 py-0.5 rounded-md shadow-xs">/</kbd>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2.5 w-full lg:w-auto justify-between lg:justify-end">
                <div class="relative">
                    <select x-model="statusFilter"
                            class="bg-slate-50/80 text-slate-700 text-sm font-medium py-2.5 pl-3.5 pr-9 rounded-xl border border-slate-200/70 appearance-none cursor-pointer focus:outline-none focus:border-indigo-500 focus:bg-white focus:ring-2 focus:ring-indigo-500/15 transition-all duration-200">
                        <option value="all">All Statuses</option>
                        <option value="ongoing">Active</option>
                        <option value="pending">Due Soon</option>
                        <option value="upcoming">Upcoming</option>
                        <option value="overdue">Ended</option>
                        <option value="completed">Completed</option>
                    </select>
                    <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2.5 text-slate-400">
                        <span class="material-symbols-outlined text-[18px]">expand_more</span>
                    </div>
                </div>

                <div class="h-7 w-px bg-slate-200/80 hidden sm:block"></div>

                <div class="flex items-center bg-slate-100/80 p-1 rounded-xl border border-slate-200/60">
                    <button type="button" 
                            @click="setViewMode('grid')"
                            :class="viewMode === 'grid' ? 'bg-white text-indigo-600 shadow-sm font-semibold' : 'text-slate-500 hover:text-slate-800'"
                            class="flex items-center justify-center p-1.5 rounded-lg transition-all duration-200"
                            title="Grid View">
                        <span class="material-symbols-outlined text-[18px]">grid_view</span>
                    </button>
                    <button type="button" 
                            @click="setViewMode('table')"
                            :class="viewMode === 'table' ? 'bg-white text-indigo-600 shadow-sm font-semibold' : 'text-slate-500 hover:text-slate-800'"
                            class="flex items-center justify-center p-1.5 rounded-lg transition-all duration-200"
                            title="Table View">
                        <span class="material-symbols-outlined text-[18px]">view_agenda</span>
                    </button>
                </div>
            </div>
        </div>

        @if ($projects->isEmpty())
            {{-- Empty state --}}
            <div class="projects-enter rounded-2xl border-2 border-dashed border-slate-200/80 bg-white px-6 py-24 text-center shadow-xs" style="animation-delay: 300ms">
                <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-indigo-50 text-indigo-600 shadow-sm border border-indigo-100/80">
                    <span class="material-symbols-outlined text-[32px]">folder_open</span>
                </div>
                <h3 class="mt-5 text-lg font-bold text-slate-900 tracking-tight">No projects yet</h3>
                <p class="mx-auto mt-2 max-w-md text-sm text-slate-500 leading-relaxed">
                    Create your first initiative to set delivery timelines, track assignees, and measure execution progress.
                </p>
                @can('projects.create')
                <div class="mt-7">
                    <a href="{{ route('projects.create') }}"
                       class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2.5 rounded-xl text-sm font-semibold shadow-md shadow-indigo-600/20 transition-all duration-200">
                        <span class="material-symbols-outlined text-[18px]">add</span>
                        <span>Create Project</span>
                    </a>
                </div>
                @endcan
            </div>
        @else
            {{-- ===================== GRID VIEW ===================== --}}
            <div x-show="viewMode === 'grid'"
                 x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="opacity-0"
                 x-transition:enter-end="opacity-100"
                 class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5">
                @foreach ($projects as $project)
                    @php
                        $info = $statusInfo($project);
                        $prog = $progressFor($project);
                        $overdueCount = $overdueTasksCount($project);
                        $totalTasks = $project->tasks->count();
                        $completedTasks = $project->tasks->whereIn('status', ['completed', 'done'])->count();
                        $haystack = Str::lower($project->name . ' ' . ($project->description ?? ''));
                        $members = $project->tasks->pluck('assignee')->filter()->unique('id')->values();
                        $leadName = $members->first()?->name ?? Auth::user()->name;
                    @endphp

                    <div x-show="matches(@js($info['key']), @js($haystack))"
                         class="projects-card group relative bg-white rounded-2xl p-5 border border-slate-200/80 shadow-sm hover:shadow-lg hover:border-slate-300/90 transition-all duration-300 flex flex-col justify-between overflow-hidden hover-lift"
                         style="animation-delay: {{ 300 + ($loop->index * 45) }}ms">

                        {{-- Accent stripe --}}
                        <div class="absolute top-0 left-0 right-0 h-[3px] {{ $info['accent'] }}"></div>

                        <div>
                            {{-- Status + Menu --}}
                            <div class="flex items-start justify-between gap-2 mb-4 pt-0.5">
                                <div class="flex items-center flex-wrap gap-1.5">
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold {{ $info['pill'] }}">
                                       
                                        {{ $info['label'] }}
                                    </span>

                                    @if ($overdueCount > 0)
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-semibold bg-rose-50 text-rose-700 ring-1 ring-rose-600/15">
                                            {{ $overdueCount }} overdue
                                        </span>
                                    @endif
                                </div>

                                <div class="relative" @click.stop>
                                    <button type="button" 
                                            @click="toggleMenu('proj-menu-{{ $project->id }}')"
                                            class="p-1.5 rounded-lg text-slate-400 hover:text-slate-700 hover:bg-slate-100 transition-colors focus:outline-none"
                                            title="Options">
                                        <span class="material-symbols-outlined text-[20px]">more_horiz</span>
                                    </button>

                                    <div x-show="activeMenu === 'proj-menu-{{ $project->id }}'"
                                         x-transition:enter="transition ease-out duration-150"
                                         x-transition:enter-start="opacity-0 scale-95 translate-y-1"
                                         x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                                         x-transition:leave="transition ease-in duration-100"
                                         x-transition:leave-start="opacity-100 scale-100"
                                         x-transition:leave-end="opacity-0 scale-95"
                                         class="absolute right-0 mt-1.5 w-48 bg-white rounded-xl shadow-xl border border-slate-200/90 py-1.5 z-30 origin-top-right"
                                         style="display: none;">
                                        <div class="py-0.5">
                                            <a href="{{ route('projects.show', $project) }}"
                                               class="flex items-center gap-2.5 px-3.5 py-2 text-xs font-medium text-slate-700 hover:bg-slate-50 transition-colors">
                                                <span class="material-symbols-outlined text-[16px] text-slate-400">visibility</span>
                                                View Overview
                                            </a>
                                            @can('projects.edit')
                                            <a href="{{ route('projects.edit', $project) }}"
                                               class="flex items-center gap-2.5 px-3.5 py-2 text-xs font-medium text-slate-700 hover:bg-slate-50 transition-colors">
                                                <span class="material-symbols-outlined text-[16px] text-slate-400">edit</span>
                                                Edit Project
                                            </a>
                                            @endcan
                                        </div>
                                        @can('projects.delete')
                                        <div class="border-t border-slate-100 py-0.5">
                                            <button type="button"
                                                    @click="openDeleteModal(@js($project->id), @js($project->name), {{ $totalTasks }}, @js(route('projects.destroy', $project)))"
                                                    class="w-full flex items-center gap-2.5 px-3.5 py-2 text-xs font-medium text-rose-600 hover:bg-rose-50 transition-colors text-left">
                                                <span class="material-symbols-outlined text-[16px] text-rose-500">delete</span>
                                                Delete Project
                                            </button>
                                        </div>
                                        @endcan
                                    </div>
                                </div>
                            </div>

                            {{-- Title & description --}}
                            <h2 class="text-[17px] font-bold text-slate-900 group-hover:text-indigo-600 transition-colors duration-200 tracking-tight line-clamp-1">
                                <a href="{{ route('projects.show', $project) }}" class="focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 rounded">
                                    {{ $project->name }}
                                </a>
                            </h2>

                            <p class="text-xs text-slate-500 mt-1.5 line-clamp-2 leading-relaxed min-h-[2.25rem]">
                                {{ $project->description ?: 'No detailed scope provided. Click to view timeline and deliverables.' }}
                            </p>

                            {{-- Lead + Due --}}
                            <div class="flex items-center justify-between mt-4 py-2.5 px-3 bg-slate-50/80 border border-slate-100/80 rounded-xl">
                                <div class="flex items-center gap-2.5 min-w-0">
                                    <div class="w-7 h-7 rounded-full bg-indigo-100 text-indigo-700 font-semibold text-[11px] flex items-center justify-center shrink-0 ring-2 ring-white">
                                        {{ strtoupper(substr($leadName, 0, 2)) }}
                                    </div>
                                    <div class="flex flex-col min-w-0">
                                        <span class="text-xs font-semibold text-slate-800 truncate">{{ $leadName }}</span>
                                        <span class="text-[10px] text-slate-400 uppercase tracking-wider font-medium">Lead</span>
                                    </div>
                                </div>
                                <div class="flex flex-col text-right shrink-0">
                                    <span class="font-mono text-xs font-semibold text-slate-800">
                                        {{ $project->end_date ? $project->end_date->format('M d, Y') : '—' }}
                                    </span>
                                    <span class="text-[10px] font-semibold uppercase tracking-wider {{ $info['badge_color'] }}">
                                        {{ $info['badge_text'] }}
                                    </span>
                                </div>
                            </div>

                            {{-- Progress --}}
                            <div class="mt-4">
                                <div class="flex items-center justify-between mb-1.5 text-xs">
                                    <span class="text-slate-500 font-medium">Progress</span>
                                    <span class="font-mono font-semibold text-slate-800">
                                        {{ $prog }}%
                                        <span class="text-slate-400 font-normal">
                                            @if ($totalTasks > 0)
                                                · {{ $completedTasks }}/{{ $totalTasks }}
                                            @else
                                                · timeline
                                            @endif
                                        </span>
                                    </span>
                                </div>
                                <div class="w-full h-2 bg-slate-100 rounded-full overflow-hidden">
                                    <div class="h-full rounded-full progress-bar-fill {{ $info['accent'] }}" 
                                         style="width: {{ $prog }}%; animation-delay: {{ 400 + ($loop->index * 45) }}ms"></div>
                                </div>
                            </div>
                        </div>

                        {{-- Footer --}}
                        <div class="mt-5 pt-4 border-t border-slate-100/80 flex items-center justify-between">
                            <div class="flex items-center -space-x-1.5">
                                @forelse ($members->take(4) as $mIdx => $member)
                                    <div class="inline-flex items-center justify-center h-6 w-6 rounded-full text-[10px] font-bold ring-2 ring-white {{ $avatarColors[$mIdx % count($avatarColors)] }}"
                                         title="{{ $member->name }}">
                                        {{ strtoupper(substr($member->name, 0, 2)) }}
                                    </div>
                                @empty
                                    <div class="inline-flex items-center justify-center h-6 w-6 rounded-full text-[10px] font-bold ring-2 ring-white bg-slate-100 text-slate-500">
                                        —
                                    </div>
                                @endforelse
                                @if ($members->count() > 4)
                                    <span class="inline-flex items-center justify-center h-6 w-6 rounded-full bg-slate-100 text-slate-600 font-mono text-[10px] font-semibold ring-2 ring-white">
                                        +{{ $members->count() - 4 }}
                                    </span>
                                @endif
                            </div>

                            <a href="{{ route('projects.show', $project) }}"
                               class="text-xs font-semibold text-indigo-600 hover:text-indigo-700 flex items-center gap-1 group-hover:gap-1.5 transition-all duration-200">
                                <span>View Details</span>
                                <span class="material-symbols-outlined text-[14px] transition-transform duration-200 group-hover:translate-x-0.5">arrow_forward</span>
                            </a>
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- ===================== TABLE VIEW ===================== --}}
            <div x-show="viewMode === 'table'"
                 x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="opacity-0"
                 x-transition:enter-end="opacity-100"
                 class="projects-enter overflow-hidden rounded-2xl bg-white border border-slate-200/80 shadow-sm" 
                 style="animation-delay: 300ms">
                
                <div class="hidden md:block overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-50/80 border-b border-slate-200/80 text-[11px] font-semibold uppercase tracking-wider text-slate-500 select-none">
                                <th scope="col" class="py-3.5 pl-6 pr-4">Project</th>
                                <th scope="col" class="px-4 py-3.5">Status</th>
                                <th scope="col" class="px-4 py-3.5 w-52">Progress</th>
                                <th scope="col" class="px-4 py-3.5">Start</th>
                                <th scope="col" class="px-4 py-3.5">Target Due</th>
                                <th scope="col" class="px-4 py-3.5">Team</th>
                                <th scope="col" class="py-3.5 pl-4 pr-6 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100/80 text-sm text-slate-800">
                            @foreach ($projects as $project)
                                @php
                                    $info = $statusInfo($project);
                                    $prog = $progressFor($project);
                                    $overdueCount = $overdueTasksCount($project);
                                    $totalTasks = $project->tasks->count();
                                    $completedTasks = $project->tasks->whereIn('status', ['completed', 'done'])->count();
                                    $haystack = Str::lower($project->name . ' ' . ($project->description ?? ''));
                                    $members = $project->tasks->pluck('assignee')->filter()->unique('id')->values();
                                @endphp

                                <tr x-show="matches(@js($info['key']), @js($haystack))"
                                    class="group hover:bg-slate-50/60 transition-colors duration-150">
                                    <td class="py-4 pl-6 pr-4">
                                        <div class="flex items-start gap-3">
                                            <div class="w-1 shrink-0 self-stretch rounded-full {{ $info['accent'] }}"></div>
                                            <div>
                                                <a href="{{ route('projects.show', $project) }}"
                                                   class="font-semibold text-slate-900 group-hover:text-indigo-600 transition-colors duration-150 focus:outline-none">
                                                    {{ $project->name }}
                                                </a>
                                                @if ($project->description)
                                                    <p class="text-xs text-slate-500 mt-0.5 line-clamp-1 max-w-xs">
                                                        {{ $project->description }}
                                                    </p>
                                                @endif
                                            </div>
                                        </div>
                                    </td>

                                    <td class="whitespace-nowrap px-4 py-4">
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold {{ $info['pill'] }}">
                                            <span class="h-1.5 w-1.5 rounded-full {{ $info['dot'] }} {{ $info['pulse'] ? 'status-dot-pulse' : '' }}"></span>
                                            {{ $info['label'] }}
                                        </span>
                                    </td>

                                    <td class="whitespace-nowrap px-4 py-4">
                                        <div class="flex items-center gap-2.5">
                                            <div class="w-28 h-2 bg-slate-100 rounded-full overflow-hidden">
                                                <div class="h-full rounded-full {{ $info['accent'] }}" style="width: {{ $prog }}%;"></div>
                                            </div>
                                            <span class="font-mono text-xs font-semibold text-slate-700 tabular-nums">{{ $prog }}%</span>
                                        </div>
                                        <span class="text-[11px] text-slate-400 block mt-0.5">
                                            {{ $completedTasks }}/{{ $totalTasks }} tasks
                                        </span>
                                    </td>

                                    <td class="whitespace-nowrap px-4 py-4 font-mono text-xs text-slate-600">
                                        {{ $project->start_date ? $project->start_date->format('M d, Y') : '—' }}
                                    </td>

                                    <td class="whitespace-nowrap px-4 py-4">
                                        <div class="font-mono text-xs font-semibold text-slate-800">
                                            {{ $project->end_date ? $project->end_date->format('M d, Y') : '—' }}
                                        </div>
                                        <span class="text-[11px] font-semibold {{ $info['badge_color'] }}">
                                            {{ $info['badge_text'] }}
                                        </span>
                                    </td>

                                    <td class="whitespace-nowrap px-4 py-4">
                                        <div class="flex items-center -space-x-1.5">
                                            @forelse ($members->take(3) as $mIdx => $member)
                                                <div class="inline-flex items-center justify-center h-6 w-6 rounded-full text-[10px] font-bold ring-2 ring-white {{ $avatarColors[$mIdx % count($avatarColors)] }}"
                                                     title="{{ $member->name }}">
                                                    {{ strtoupper(substr($member->name, 0, 2)) }}
                                                </div>
                                            @empty
                                                <span class="text-xs text-slate-400">—</span>
                                            @endforelse
                                            @if ($members->count() > 3)
                                                <span class="inline-flex items-center justify-center h-6 w-6 rounded-full bg-slate-100 text-slate-600 font-mono text-[10px] font-semibold ring-2 ring-white">
                                                    +{{ $members->count() - 3 }}
                                                </span>
                                            @endif
                                        </div>
                                    </td>

                                    <td class="whitespace-nowrap py-4 pl-4 pr-6 text-right">
                                        <div class="inline-flex items-center gap-0.5 opacity-70 group-hover:opacity-100 transition-opacity">
                                            <a href="{{ route('projects.show', $project) }}"
                                               title="View"
                                               class="p-1.5 rounded-lg text-slate-500 hover:text-indigo-600 hover:bg-indigo-50 transition-colors">
                                                <span class="material-symbols-outlined text-[18px]">visibility</span>
                                            </a>
                                            @can('projects.edit')
                                            <a href="{{ route('projects.edit', $project) }}"
                                               title="Edit"
                                               class="p-1.5 rounded-lg text-slate-500 hover:text-indigo-600 hover:bg-indigo-50 transition-colors">
                                                <span class="material-symbols-outlined text-[18px]">edit</span>
                                            </a>
                                            @endcan
                                            @can('projects.delete')
                                            <button type="button"
                                                    @click="openDeleteModal(@js($project->id), @js($project->name), {{ $totalTasks }}, @js(route('projects.destroy', $project)))"
                                                    title="Delete"
                                                    class="p-1.5 rounded-lg text-slate-400 hover:text-rose-600 hover:bg-rose-50 transition-colors">
                                                <span class="material-symbols-outlined text-[18px]">delete</span>
                                            </button>
                                            @endcan
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- Mobile list --}}
                <div class="divide-y divide-slate-100 md:hidden">
                    @foreach ($projects as $project)
                        @php
                            $info = $statusInfo($project);
                            $prog = $progressFor($project);
                            $haystack = Str::lower($project->name . ' ' . ($project->description ?? ''));
                        @endphp
                        <div x-show="matches(@js($info['key']), @js($haystack))"
                             class="p-4 space-y-3">
                            <div class="flex items-start justify-between gap-2">
                                <a href="{{ route('projects.show', $project) }}" class="font-bold text-slate-900 hover:text-indigo-600 text-sm">
                                    {{ $project->name }}
                                </a>
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold {{ $info['pill'] }}">
                                    {{ $info['label'] }}
                                </span>
                            </div>
                            <div class="flex items-center justify-between text-xs text-slate-500">
                                <span>Progress: <strong class="text-slate-800 font-mono">{{ $prog }}%</strong></span>
                                <span>Due: <strong class="text-slate-800 font-mono">{{ $project->end_date ? $project->end_date->format('M d, Y') : '—' }}</strong></span>
                            </div>
                            <div class="flex items-center justify-end gap-2 pt-1 border-t border-slate-100">
                                <a href="{{ route('projects.show', $project) }}" class="text-xs font-semibold text-indigo-600 py-1.5 px-3 rounded-lg bg-indigo-50">
                                    Overview
                                </a>
                                @can('projects.edit')
                                <a href="{{ route('projects.edit', $project) }}" class="text-xs font-semibold text-slate-700 py-1.5 px-3 rounded-lg bg-slate-100">
                                    Edit
                                </a>
                                @endcan
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Empty filter results --}}
            <div x-show="(search.length > 0 || statusFilter !== 'all') && $el.closest('[x-data]').querySelectorAll('[x-show*=\'matches\']:not([style*=\'display: none\'])').length === 0"
                 class="hidden bg-white rounded-2xl p-10 border border-slate-200 text-center"
                 :class="{ 'hidden': false }">
                <span class="material-symbols-outlined text-4xl text-slate-300">search_off</span>
                <p class="mt-3 text-sm font-semibold text-slate-800">No projects match your filters</p>
                <p class="text-xs text-slate-500 mt-1">Try adjusting your search or status filter.</p>
                <button type="button" @click="search = ''; statusFilter = 'all'"
                        class="mt-5 px-4 py-2 text-xs font-semibold bg-indigo-50 text-indigo-600 rounded-xl hover:bg-indigo-100 transition-colors">
                    Clear filters
                </button>
            </div>

            {{-- Pagination --}}
            <div class="projects-enter bg-white rounded-2xl p-4 border border-slate-200/80 shadow-sm flex flex-col sm:flex-row items-center justify-between gap-4" style="animation-delay: 340ms">
                <div class="text-xs font-medium text-slate-500">
                    Showing <span class="font-bold text-slate-800">{{ $projects->firstItem() ?? 1 }}</span>–
                    <span class="font-bold text-slate-800">{{ $projects->lastItem() ?? $projects->count() }}</span> of
                    <span class="font-bold text-slate-800">{{ $projects->total() }}</span> projects
                </div>
                <div class="flex items-center">
                    {{ $projects->links() }}
                </div>
            </div>
        @endif

        {{-- ===================== DELETE MODAL ===================== --}}
        <div x-show="deleteModalOpen"
             x-cloak
             x-transition:enter="transition ease-out duration-250"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-180"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             class="fixed inset-0 z-50 flex items-center justify-center p-4"
             role="dialog" aria-modal="true">
            
            <div class="fixed inset-0 bg-slate-900/50 backdrop-blur-[2px]"
                 @click="closeDeleteModal()"></div>

            <div class="relative bg-white rounded-2xl shadow-2xl border border-slate-200/90 w-full max-w-md p-6 z-10 overflow-hidden"
                 x-transition:enter="transition ease-out duration-250"
                 x-transition:enter-start="opacity-0 scale-[0.96] translate-y-2"
                 x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                 x-transition:leave="transition ease-in duration-180"
                 x-transition:leave-start="opacity-100 scale-100"
                 x-transition:leave-end="opacity-0 scale-[0.96]">

                <div class="flex items-start gap-4">
                    <div class="w-12 h-12 rounded-2xl bg-rose-50 border border-rose-100 flex items-center justify-center text-rose-600 shrink-0">
                        <span class="material-symbols-outlined text-[26px]">warning</span>
                    </div>
                    <div class="flex-1">
                        <h3 class="text-lg font-bold text-slate-900 tracking-tight">Delete Project?</h3>
                        <p class="text-sm text-slate-600 mt-1.5 leading-relaxed">
                            Are you sure you want to delete <strong class="text-slate-900" x-text="deleteData.name"></strong>?
                        </p>

                        <div class="bg-rose-50/80 border border-rose-100 rounded-xl p-3 mt-4">
                            <span class="text-xs text-rose-700 font-medium flex items-center gap-1.5">
                                <span class="material-symbols-outlined text-[16px]">info</span>
                                All <span class="font-bold" x-text="deleteData.tasksCount"></span> associated tasks will be permanently removed.
                            </span>
                        </div>
                    </div>
                </div>

                <div class="mt-6 flex items-center justify-end gap-3">
                    <button type="button" @click="closeDeleteModal()"
                            class="px-4 py-2.5 rounded-xl text-sm font-semibold bg-slate-100 text-slate-700 hover:bg-slate-200 transition-colors">
                        Cancel
                    </button>

                    <form :action="deleteData.url" method="POST" class="inline">
                        @csrf
                        @method('DELETE')
                        <button type="submit"
                                class="px-4 py-2.5 rounded-xl text-sm font-semibold bg-rose-600 hover:bg-rose-700 text-white shadow-md shadow-rose-600/20 transition-all focus:outline-none focus:ring-2 focus:ring-rose-500 focus:ring-offset-2 active:scale-[0.98]">
                            Delete Project
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>