{{-- View: projects.index --}}

@php
    /*
     | Project status colour system (PlanFlow design tokens)
     | -----------------------------------------------------
     | Upcoming : secondary-container (planned, not started)
     | Active   : tertiary            (on track)
     | Due soon : secondary           (ends within 7 days)
     | Ended    : outline             (past the end date)
     */
    $statusFor = function ($project) {
        $today = now()->startOfDay();

        if ($project->start_date && $project->start_date->gt($today)) {
            return [
                'label'  => 'Upcoming',
                'pill'   => 'bg-secondary-container/20 text-on-secondary-container',
                'dot'    => 'bg-secondary-container',
                'accent' => 'bg-secondary-container',
                'bar'    => 'bg-secondary-container',
            ];
        }

        if ($project->end_date && $project->end_date->lt($today)) {
            return [
                'label'  => 'Ended',
                'pill'   => 'bg-surface-container text-on-surface-variant',
                'dot'    => 'bg-outline',
                'accent' => 'bg-outline',
                'bar'    => 'bg-outline',
            ];
        }

        if ($project->end_date && $today->diffInDays($project->end_date, false) <= 7) {
            return [
                'label'  => 'Due soon',
                'pill'   => 'bg-secondary-fixed/50 text-on-secondary-container',
                'dot'    => 'bg-secondary',
                'accent' => 'bg-secondary',
                'bar'    => 'bg-secondary',
            ];
        }

        return [
            'label'  => 'Active',
            'pill'   => 'bg-tertiary-fixed/40 text-tertiary',
            'dot'    => 'bg-tertiary',
            'accent' => 'bg-tertiary',
            'bar'    => 'bg-tertiary',
        ];
    };

    // How far through its timeline a project is (0–100), based on dates.
    $timelineFor = function ($project) {
        if (! $project->start_date || ! $project->end_date) {
            return null;
        }
        $total = max(1, $project->start_date->diffInDays($project->end_date));
        $done  = $project->start_date->diffInDays(now(), false);

        return (int) max(0, min(100, round(($done / $total) * 100)));
    };

    // Whole days left until the end date (used for "Due soon" projects).
    $daysLeftFor = function ($project) {
        return (int) ceil(now()->startOfDay()->diffInDays($project->end_date, false));
    };

    // Summary counts for the projects shown on this page.
    $counts = ['Active' => 0, 'Due soon' => 0, 'Upcoming' => 0, 'Ended' => 0];
    foreach ($projects as $p) {
        $counts[$statusFor($p)['label']]++;
    }

    // label, count, hint text, hint icon, hint colour, icon box classes, icon name
    $stats = [
        ['Active',   $counts['Active'],   'On track', 'trending_up',   'text-tertiary',             'bg-tertiary-fixed/30 text-tertiary',                  'bolt'],
        ['Due soon', $counts['Due soon'], '≤ 7 days', 'schedule',      'text-on-secondary-container','bg-secondary-fixed/50 text-secondary',               'alarm'],
        ['Upcoming', $counts['Upcoming'], 'Planned',  null,            'text-on-surface-variant',   'bg-secondary-container/20 text-on-secondary-container','calendar_month'],
        ['Ended',    $counts['Ended'],    'Archived', null,            'text-on-surface-variant',   'bg-surface-container text-outline',                   'check_circle'],
    ];

    $filters = ['All', 'Active', 'Due soon', 'Upcoming', 'Ended'];
@endphp

<x-app-layout>
    <div class="mx-auto w-full max-w-7xl space-y-6 px-6 py-8 lg:px-12"
         x-data="{
             filter: 'All',
             q: '',
             matches(status, haystack) {
                 return (this.filter === 'All' || this.filter === status)
                     && (this.q === '' || haystack.includes(this.q.toLowerCase()));
             }
         }">

        {{-- Flash message --}}
        @if (session('success'))
            <div x-data="{ show: true }" x-show="show" x-transition.opacity role="status"
                 class="flex items-center justify-between gap-4 rounded-xl bg-tertiary-fixed/30 px-4 py-3.5 text-tertiary shadow-sm">
                <div class="flex items-center gap-3">
                    <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-tertiary-fixed text-on-tertiary-fixed-variant">
                        <span class="material-symbols-outlined text-[20px]" style="font-variation-settings: 'FILL' 1;">check_circle</span>
                    </div>
                    <div class="flex flex-wrap items-baseline gap-1.5">
                        <span class="text-title-sm font-semibold">Success:</span>
                        <span class="text-body-md">{{ session('success') }}</span>
                    </div>
                </div>
                <button type="button" @click="show = false" aria-label="Dismiss alert"
                        class="shrink-0 rounded-lg p-1 text-tertiary transition-colors hover:bg-tertiary-fixed/50">
                    <span class="material-symbols-outlined text-[18px]">close</span>
                </button>
            </div>
        @endif

        {{-- Page header --}}
        <div class="flex flex-col gap-4 pb-1 pt-2 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-headline-md font-bold tracking-tight text-on-surface">Projects</h1>
                <p class="mt-1 text-body-sm text-on-surface-variant">
                    @if ($projects->total() > 0)
                        {{ number_format($projects->total()) }} {{ Str::plural('project', $projects->total()) }} in total
                    @else
                        Plan and track your work in one place.
                    @endif
                </p>
            </div>

            @can('projects.create')
            <a href="{{ route('projects.create') }}"
               class="inline-flex items-center justify-center gap-2 rounded-xl bg-primary-container px-4 py-2.5 text-label-md font-semibold tracking-wide text-on-primary shadow-md shadow-primary-container/20 transition-all hover:bg-primary focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-container focus-visible:ring-offset-2">
                <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path d="M10.75 4.75a.75.75 0 0 0-1.5 0v4.5h-4.5a.75.75 0 0 0 0 1.5h4.5v4.5a.75.75 0 0 0 1.5 0v-4.5h4.5a.75.75 0 0 0 0-1.5h-4.5v-4.5Z" />
                </svg>
                New project
            </a>
            @endcan
        </div>

        @if ($projects->isEmpty())
            {{-- Empty state --}}
            <div class="rounded-xl border-2 border-dashed border-primary-fixed-dim bg-surface-container-lowest px-6 py-16 text-center">
                <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-br from-primary-container to-secondary-container text-on-primary shadow-lg shadow-primary-container/30">
                    <span class="material-symbols-outlined text-[28px]">folder_open</span>
                </div>
                <h3 class="mt-5 text-title-md font-semibold text-on-surface">No projects yet</h3>
                <p class="mx-auto mt-1 max-w-sm text-body-md text-on-surface-variant">
                    Create your first project to set dates, add a description, and start tracking progress.
                </p>
                <a href="{{ route('projects.create') }}"
                   class="mt-6 inline-flex items-center gap-2 rounded-xl bg-primary-container px-4 py-2.5 text-label-md font-semibold text-on-primary shadow-sm transition-all hover:bg-primary focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-container focus-visible:ring-offset-2">
                    Create project
                </a>
            </div>
        @else
            {{-- Status summary --}}
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($stats as [$label, $count, $hint, $hintIcon, $hintColor, $iconBox, $icon])
                    <div class="flex items-center justify-between rounded-xl bg-surface-container-lowest p-5 shadow-sm transition-shadow hover:shadow">
                        <div class="space-y-1">
                            <p class="text-label-sm font-semibold uppercase tracking-wider text-on-surface-variant">{{ $label }}</p>
                            <div class="flex items-baseline gap-2">
                                <span class="text-headline-lg font-bold text-on-surface">{{ $count }}</span>
                                <span class="flex items-center text-body-sm {{ $hintColor }}">
                                    @if ($hintIcon)
                                        <span class="material-symbols-outlined text-[14px]">{{ $hintIcon }}</span>
                                    @endif
                                    {{ $hint }}
                                </span>
                            </div>
                        </div>
                        <div class="flex h-12 w-12 items-center justify-center rounded-xl {{ $iconBox }}">
                            <span class="material-symbols-outlined text-[24px]">{{ $icon }}</span>
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- Filter & search toolbar (filters the projects on this page) --}}
            <div class="flex flex-col justify-between gap-3 rounded-xl bg-surface-container-lowest p-3 shadow-sm md:flex-row md:items-center">
                <div class="relative w-full max-w-md flex-1">
                    <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-[18px] text-on-surface-variant">search</span>
                    <input type="text" x-model="q" placeholder="Search projects by name or description..."
                           class="w-full rounded-lg border-0 bg-surface-container-low py-2 pl-9 pr-3 text-body-sm text-on-surface placeholder:text-outline transition-all focus:bg-surface-container-lowest focus:outline-none focus:ring-2 focus:ring-primary-container/30" />
                </div>

                <div class="flex flex-wrap items-center rounded-lg bg-surface-container-low p-1 sm:flex-nowrap">
                    @foreach ($filters as $f)
                        <button type="button" @click="filter = @js($f)"
                                :class="filter === @js($f)
                                    ? 'bg-surface-container-lowest text-on-surface font-semibold shadow-sm'
                                    : 'text-on-surface-variant hover:text-on-surface font-medium'"
                                class="rounded-md px-3 py-1 text-label-sm transition-colors">
                            {{ $f }}@if ($f === 'All') ({{ $projects->count() }})@endif
                        </button>
                    @endforeach
                </div>
            </div>

            <div class="overflow-hidden rounded-xl bg-surface-container-lowest shadow-sm">

                {{-- Desktop table --}}
                <div class="hidden overflow-x-auto md:block">
                    <table class="w-full text-left">
                        <thead>
                            <tr class="select-none bg-surface-container-low text-label-sm uppercase tracking-wider text-on-surface-variant">
                                <th scope="col" class="py-3 pl-6 pr-4">Project</th>
                                <th scope="col" class="px-4 py-3">Status</th>
                                <th scope="col" class="w-48 px-4 py-3">Timeline</th>
                                <th scope="col" class="px-4 py-3">Start Date</th>
                                <th scope="col" class="px-4 py-3">End Date</th>
                                <th scope="col" class="py-3 pl-4 pr-6 text-right">Actions</th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-surface-container-low text-body-sm text-on-surface">
                            @foreach ($projects as $project)
                                @php
                                    $s = $statusFor($project);
                                    $t = $timelineFor($project);
                                    $isDue = $s['label'] === 'Due soon';
                                    $haystack = Str::lower($project->name . ' ' . $project->description);
                                @endphp

                                <tr x-show="matches(@js($s['label']), @js($haystack))"
                                    class="group transition-colors hover:bg-surface-container-low/50 {{ $s['label'] === 'Ended' ? 'opacity-90' : '' }}">
                                    <td class="py-4 pl-6 pr-4">
                                        <div class="flex items-start gap-3">
                                            <div class="w-1.5 shrink-0 self-stretch rounded-full {{ $s['accent'] }}"></div>
                                            <div>
                                                <a href="{{ route('projects.show', $project) }}"
                                                   class="flex items-center gap-1.5 text-title-sm font-semibold text-on-surface transition-colors group-hover:text-primary focus:outline-none focus-visible:underline">
                                                    {{ $project->name }}
                                                    <span class="material-symbols-outlined text-[14px] text-outline opacity-0 transition-opacity group-hover:opacity-100">open_in_new</span>
                                                </a>
                                                @if ($project->description)
                                                    <p class="mt-0.5 max-w-md text-body-sm text-on-surface-variant line-clamp-2">{{ Str::limit($project->description, 100) }}</p>
                                                @endif
                                            </div>
                                        </div>
                                    </td>

                                    <td class="whitespace-nowrap px-4 py-4">
                                        <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-label-sm font-medium {{ $s['pill'] }}">
                                            <span class="h-1.5 w-1.5 rounded-full {{ $s['dot'] }}" aria-hidden="true"></span>
                                            {{ $s['label'] }}
                                        </span>
                                    </td>

                                    <td class="whitespace-nowrap px-4 py-4">
                                        @if (! is_null($t))
                                            <div class="flex items-center gap-3">
                                                <div class="h-2 w-24 overflow-hidden rounded-full bg-surface-container-high">
                                                    <div class="h-full rounded-full {{ $s['bar'] }}" style="width: {{ $t }}%"></div>
                                                </div>
                                                <span class="text-code-sm font-medium tabular-nums text-on-surface">{{ $t }}%</span>
                                            </div>
                                        @else
                                            <span class="text-code-sm text-outline">No dates</span>
                                        @endif
                                    </td>

                                    <td class="whitespace-nowrap px-4 py-4 text-code-sm text-on-surface-variant">
                                        {{ $project->start_date?->format('d M Y') ?? '—' }}
                                    </td>

                                    <td class="whitespace-nowrap px-4 py-4">
                                        @if ($isDue)
                                            @php $left = $daysLeftFor($project); @endphp
                                            <div class="flex items-center gap-1.5 text-code-sm font-semibold text-secondary">
                                                <span class="material-symbols-outlined text-[15px]">timer</span>
                                                <span>{{ $project->end_date->format('d M Y') }}</span>
                                                <span class="text-label-sm font-normal text-on-surface-variant">({{ $left <= 0 ? 'today' : 'in ' . $left . 'd' }})</span>
                                            </div>
                                        @else
                                            <span class="text-code-sm text-on-surface-variant">{{ $project->end_date?->format('d M Y') ?? '—' }}</span>
                                        @endif
                                    </td>

                                    <td class="whitespace-nowrap py-4 pl-4 pr-6 text-right">
                                        <div class="inline-flex items-center gap-1">

                                            @can('projects.edit')
                                            <a href="{{ route('projects.edit', $project) }}" title="Edit project"
                                               class="rounded-lg p-1.5 text-primary transition-colors hover:bg-primary-fixed focus:outline-none focus-visible:ring-2 focus-visible:ring-primary">
                                                <span class="sr-only">Edit</span>
                                                <span class="material-symbols-outlined text-[18px]" aria-hidden="true">edit</span>
                                            </a>
                                            @endcan
                                            

                                            @can('projects.delete')
                                            <form action="{{ route('projects.destroy', $project) }}" method="POST" class="inline"
                                                  onsubmit="return confirm(@js('Delete “' . $project->name . '”? This can’t be undone.'))">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" title="Delete project"
                                                        class="rounded-lg p-1.5 text-error transition-colors hover:bg-error-container focus:outline-none focus-visible:ring-2 focus-visible:ring-error">
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

                {{-- Mobile list --}}
                <ul class="divide-y divide-surface-container-low md:hidden">
                    @foreach ($projects as $project)
                        @php
                            $s = $statusFor($project);
                            $t = $timelineFor($project);
                            $haystack = Str::lower($project->name . ' ' . $project->description);
                        @endphp

                        <li x-show="matches(@js($s['label']), @js($haystack))" class="space-y-3 p-4">
                            <div class="flex items-start justify-between gap-3">
                                <div class="flex items-start gap-3">
                                    <div class="w-1.5 shrink-0 self-stretch rounded-full {{ $s['accent'] }}"></div>
                                    <a href="{{ route('projects.show', $project) }}" class="text-title-sm font-semibold text-on-surface hover:text-primary">
                                        {{ $project->name }}
                                    </a>
                                </div>
                                <span class="inline-flex shrink-0 items-center gap-1.5 rounded-full px-2.5 py-1 text-label-sm font-medium {{ $s['pill'] }}">
                                    <span class="h-1.5 w-1.5 rounded-full {{ $s['dot'] }}" aria-hidden="true"></span>
                                    {{ $s['label'] }}
                                </span>
                            </div>

                            @if ($project->description)
                                <p class="text-body-sm text-on-surface-variant">{{ Str::limit($project->description, 100) }}</p>
                            @endif

                            @if (! is_null($t))
                                <div class="flex items-center gap-3">
                                    <div class="h-2 flex-1 overflow-hidden rounded-full bg-surface-container-high">
                                        <div class="h-full rounded-full {{ $s['bar'] }}" style="width: {{ $t }}%"></div>
                                    </div>
                                    <span class="text-code-sm tabular-nums text-on-surface">{{ $t }}%</span>
                                </div>
                            @endif

                            <dl class="grid grid-cols-2 gap-3 text-body-md">
                                <div>
                                    <dt class="text-body-sm text-on-surface-variant">Start date</dt>
                                    <dd class="mt-0.5 text-on-surface">{{ $project->start_date?->format('d M Y') ?? '—' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-body-sm text-on-surface-variant">End date</dt>
                                    <dd class="mt-0.5 {{ $s['label'] === 'Due soon' ? 'font-semibold text-secondary' : 'text-on-surface' }}">
                                        {{ $project->end_date?->format('d M Y') ?? '—' }}
                                    </dd>
                                </div>
                            </dl>

                            <div class="flex items-center gap-2 pt-1">
                                @can('projects.edit')
                                    <a href="{{ route('projects.edit', $project) }}"
                                       class="flex-1 rounded-lg bg-primary-fixed/60 px-3 py-2 text-center text-label-md font-medium text-primary transition-colors hover:bg-primary-fixed focus:outline-none focus-visible:ring-2 focus-visible:ring-primary">
                                        Edit
                                    </a>
                                @endcan
                                @can('projects.delete')
                                    <form action="{{ route('projects.destroy', $project) }}" method="POST" class="flex-1"
                                          onsubmit="return confirm(@js('Delete “' . $project->name . '”? This can’t be undone.'))">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit"
                                                class="w-full rounded-lg bg-error-container/60 px-3 py-2 text-label-md font-medium text-error transition-colors hover:bg-error-container focus:outline-none focus-visible:ring-2 focus-visible:ring-error">
                                            Delete
                                        </button>
                                    </form>
                                @endcan
                            </div>
                        </li>
                    @endforeach
                </ul>

                {{-- Pagination --}}
                @if ($projects->hasPages())
                    <div class="flex flex-col items-center justify-between gap-4 bg-surface-container-lowest px-6 py-4 sm:flex-row">
                        <p class="text-body-sm text-on-surface-variant">
                            Showing
                            <span class="font-semibold text-on-surface">{{ $projects->firstItem() }}</span>
                            to
                            <span class="font-semibold text-on-surface">{{ $projects->lastItem() }}</span>
                            of
                            <span class="font-semibold text-on-surface">{{ $projects->total() }}</span>
                            results
                        </p>
                        <div>{{ $projects->links() }}</div>
                    </div>
                @endif
            </div>
        @endif

    </div>
</x-app-layout>