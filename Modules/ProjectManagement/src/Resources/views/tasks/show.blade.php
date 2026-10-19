{{-- View: tasks.show (route: projects.tasks.show) --}}

{{--
|--------------------------------------------------------------------------
| RBAC / DYNAMIC DATA NOTES
|--------------------------------------------------------------------------
| ROLE & PERMISSIONS card: kept ACTIVE but safe. It only renders when
| $task->assignedRole exists, so nothing breaks before RBAC is built.
| It reads: assignedRole->name, ->is_system, ->permissions[]->name
| (default roles: manager, team member; custom roles supported).
|
| AUDIT TRAIL: uses the task's own timestamps by default. Pass $activities
| from the controller for a full log (e.g. spatie/laravel-activitylog:
| $task->activities()->latest()->take(10)->get()). Each item needs
| description, created_at, optional causer (user).
|
| COMMENTS: commented out (search for RBAC-COMMENTS). Needs a comments table,
| a store route and a permission such as tasks.comment.
|
| RBAC-EDIT / RBAC-DELETE: wrap the Edit / Delete buttons in
| @can('update', $task) / @can('delete', $task) once policies exist.
| (an undefined ability returns false, so do NOT add @can earlier, or the
| buttons will disappear)
--}}

@php
    $project = $project ?? $task->project;
    $ticket  = 'TSK-' . $project->id . '-' . str_pad($task->id, 2, '0', STR_PAD_LEFT);

    $statusStyles = [
        'pending'     => ['bg-surface-container text-on-surface-variant', 'bg-outline'],
        'todo'        => ['bg-surface-container text-on-surface-variant', 'bg-outline'],
        'in_progress' => ['bg-secondary-fixed text-on-secondary-fixed',   'bg-secondary'],
        'completed'   => ['bg-tertiary-fixed text-on-tertiary-fixed',     'bg-tertiary'],
        'done'        => ['bg-tertiary-fixed text-on-tertiary-fixed',     'bg-tertiary'],
    ];
    $priorityStyles = [
        'low'    => 'bg-surface-container-high text-on-surface-variant',
        'medium' => 'bg-secondary-fixed text-on-secondary-fixed',
        'high'   => 'bg-error-container text-on-error-container',
    ];
    [$statusPill, $statusDot] = $statusStyles[$task->status] ?? $statusStyles['pending'];
    $priorityPill = $priorityStyles[$task->priority] ?? $priorityStyles['low'];
    $isDone = in_array($task->status, ['completed', 'done']);

    // Due-date state: only meaningful while the task is still open.
    $dueState = null;
    $dueLabel = null;
    if ($task->due_date && ! $isDone) {
        $days = (int) now()->startOfDay()->diffInDays($task->due_date->copy()->startOfDay(), false);
        if ($days < 0) {
            $dueState = 'overdue';
            $dueLabel = abs($days) . ' ' . Str::plural('day', abs($days)) . ' overdue';
        } elseif ($days === 0) {
            $dueState = 'soon';
            $dueLabel = 'Due today';
        } elseif ($days <= 3) {
            $dueState = 'soon';
            $dueLabel = 'Due in ' . $days . ' ' . Str::plural('day', $days);
        }
    }
    $dueStyles = [
        'overdue' => 'bg-error-container text-on-error-container',
        'soon'    => 'bg-amber-100 text-amber-800',
    ];

    $pill  = 'inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-label-sm';
    $label = 'text-label-sm uppercase tracking-wider text-on-surface-variant';

    $assignee = $task->assignee ?? null;
    $initials = fn ($name) => Str::upper(collect(preg_split('/\s+/', trim((string) $name)))->filter()->map(fn ($w) => Str::substr($w, 0, 1))->take(2)->implode(''));
@endphp

<x-app-layout>
    <div class="relative w-full overflow-hidden font-inter"
         x-data="{ copied: false, linkCopied: false,
                   copySpec() {
                       navigator.clipboard.writeText(@js((string) $task->description));
                       this.copied = true; setTimeout(() => this.copied = false, 1500);
                   },
                   copyLink() {
                       navigator.clipboard.writeText(window.location.href);
                       this.linkCopied = true; setTimeout(() => this.linkCopied = false, 1500);
                   } }">

        {{-- Soft background glow --}}
        <div class="pointer-events-none absolute -left-20 -top-32 h-96 w-96 rounded-full bg-primary/5 blur-3xl"></div>
        <div class="pointer-events-none absolute right-10 top-20 h-80 w-80 rounded-full bg-tertiary-fixed/15 blur-3xl"></div>

        <div class="relative mx-auto max-w-7xl px-6 py-space-xl lg:px-12">

            {{-- Top bar --}}
            <div class="mb-space-lg flex flex-wrap items-center justify-between gap-space-md">
                <nav class="flex flex-wrap items-center gap-2 text-label-md text-on-surface-variant" aria-label="Breadcrumb">
                    <a href="{{ route('projects.index') }}" class="flex items-center gap-1 transition-colors hover:text-primary">
                        <span class="material-symbols-outlined text-[16px]" aria-hidden="true">folder_open</span>
                        Projects
                    </a>
                    <span class="material-symbols-outlined text-[14px]" aria-hidden="true">chevron_right</span>
                    <a href="{{ route('projects.show', $project) }}" class="max-w-[14rem] truncate transition-colors hover:text-primary">{{ $project->name }}</a>
                    <span class="material-symbols-outlined text-[14px]" aria-hidden="true">chevron_right</span>
                    <span class="font-code-sm text-code-sm text-on-surface">{{ $ticket }}</span>
                </nav>

                <div class="flex items-center gap-space-sm">
                    <a href="{{ route('projects.show', $project) }}"
                       class="flex items-center gap-1.5 rounded-xl bg-surface-container-lowest px-space-md py-2 text-label-md text-on-surface shadow-sm transition-all hover:bg-surface-container-low">
                        <span class="material-symbols-outlined text-[18px]" aria-hidden="true">arrow_back</span>
                        Back to Project
                    </a>
                    <button type="button" @click="copyLink()"
                            class="flex items-center gap-1.5 rounded-xl bg-surface-container-lowest px-space-md py-2 text-label-md text-on-surface shadow-sm transition-all hover:bg-surface-container-low">
                        <span class="material-symbols-outlined text-[18px]" aria-hidden="true" x-text="linkCopied ? 'check' : 'share'">share</span>
                        <span x-text="linkCopied ? 'Link copied' : 'Share'">Share</span>
                    </button>
                    {{-- RBAC-EDIT: wrap in @can('update', $task) … @endcan once policies exist --}}
                    <a href="{{ route('projects.tasks.edit', [$project, $task]) }}"
                       class="flex items-center gap-1.5 rounded-xl bg-primary-container px-space-lg py-2 text-label-md text-on-primary shadow-md transition-all hover:bg-primary hover:shadow-lg focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-container focus-visible:ring-offset-2">
                        <span class="material-symbols-outlined text-[18px]" aria-hidden="true">edit</span>
                        Edit Task
                    </a>
                </div>
            </div>

            <div class="grid grid-cols-1 items-start gap-gutter lg:grid-cols-12">

                {{-- ============ Main column ============ --}}
                <div class="flex flex-col gap-space-lg lg:col-span-8">

                    {{-- Header card --}}
                    <section class="rounded-xl bg-surface-container-lowest p-space-lg shadow-sm">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="rounded-md bg-surface-container-high px-2 py-0.5 font-code-sm text-[11px] text-on-surface-variant">{{ $ticket }}</span>
                            <span class="{{ $pill }} {{ $statusPill }}">
                                <span class="h-1.5 w-1.5 rounded-full {{ $statusDot }} {{ $task->status === 'in_progress' ? 'animate-pulse' : '' }}" aria-hidden="true"></span>
                                {{ ucfirst(str_replace('_', ' ', $task->status)) }}
                            </span>
                            <span class="{{ $pill }} {{ $priorityPill }}">
                                <span class="material-symbols-outlined text-[13px]" aria-hidden="true">flag</span>
                                {{ ucfirst($task->priority) }} Priority
                            </span>
                            @if ($dueState)
                                <span class="{{ $pill }} ml-auto {{ $dueStyles[$dueState] }}">
                                    <span class="material-symbols-outlined text-[13px]" aria-hidden="true">schedule</span>
                                    {{ $dueLabel }} ({{ $task->due_date->format('M d') }})
                                </span>
                            @endif
                        </div>

                        <h1 class="mt-space-md break-words text-headline-lg tracking-tight text-on-surface sm:text-headline-xl">
                            {{ $task->title }}
                        </h1>

                        <p class="mt-space-sm text-body-md text-on-surface-variant">
                            Created on {{ $task->created_at->format('M d, Y') }}
                            <span class="text-outline-variant">·</span>
                            Last updated {{ $task->updated_at->diffForHumans() }}
                        </p>

                        {{-- Specification --}}
                        <div class="mt-space-lg rounded-xl bg-surface-container-low p-space-md">
                            <div class="mb-space-sm flex items-center justify-between">
                                <span class="flex items-center gap-1.5 text-body-md text-on-surface">
                                    <span class="material-symbols-outlined text-[18px] text-on-surface-variant" aria-hidden="true">description</span>
                                    Specification &amp; Scope
                                </span>
                                @if (filled($task->description))
                                    <button type="button" @click="copySpec()"
                                            class="flex items-center gap-1 text-label-sm text-on-surface-variant transition-colors hover:text-primary">
                                        <span class="material-symbols-outlined text-[14px]" aria-hidden="true" x-text="copied ? 'check' : 'content_copy'">content_copy</span>
                                        <span x-text="copied ? 'Copied' : 'Copy Spec'">Copy Spec</span>
                                    </button>
                                @endif
                            </div>

                            @if (filled($task->description))
                                <div class="whitespace-pre-line break-words text-body-md leading-6 text-on-surface">{{ $task->description }}</div>
                            @else
                                <p class="text-body-md text-on-surface-variant">No description provided.</p>
                            @endif
                        </div>
                    </section>

                    {{-- Role & permissions (renders only if the task has a role) --}}
                    @if ($task->assignedRole ?? null)
                        <section class="rounded-xl bg-surface-container-lowest p-space-lg shadow-sm">
                            <div class="flex flex-wrap items-start justify-between gap-space-md">
                                <div class="flex items-center gap-space-sm">
                                    <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-primary-fixed text-primary">
                                        <span class="material-symbols-outlined text-[22px]" aria-hidden="true">verified_user</span>
                                    </span>
                                    <div>
                                        <h2 class="text-title-md text-on-surface">Role &amp; Task Governance</h2>
                                        <p class="text-body-sm text-on-surface-variant">Access privileges granted for this task scope</p>
                                    </div>
                                </div>
                                <span class="{{ $pill }} {{ $task->assignedRole->is_system ? 'bg-surface-container-high text-on-surface-variant' : 'bg-primary-fixed text-on-primary-fixed-variant' }}">
                                    <span class="material-symbols-outlined text-[13px]" aria-hidden="true">shield</span>
                                    {{ $task->assignedRole->name }} · {{ $task->assignedRole->is_system ? 'Default' : 'Custom' }}
                                    @if ($task->assignedRole->permissions->count())
                                        · {{ $task->assignedRole->permissions->count() }}
                                    @endif
                                </span>
                            </div>

                            <div class="mt-space-md flex flex-wrap gap-space-xs">
                                @forelse ($task->assignedRole->permissions as $permission)
                                    <span class="inline-flex items-center gap-1.5 rounded-xl bg-surface-container-low px-3 py-1.5 text-label-md text-on-surface">
                                        <span class="material-symbols-outlined text-[16px] text-tertiary" aria-hidden="true">check</span>
                                        {{ $permission->name }}
                                    </span>
                                @empty
                                    <span class="text-body-md text-on-surface-variant">This role has no permissions.</span>
                                @endforelse
                            </div>

                            <div class="mt-space-md flex items-center gap-2 rounded-xl bg-surface-container-low p-space-sm text-body-sm text-on-surface-variant">
                                <span class="material-symbols-outlined text-[18px] text-primary" aria-hidden="true">info</span>
                                <span><span class="font-semibold text-on-surface">Role-based access:</span> permissions come from the role assigned to this task.</span>
                            </div>
                        </section>
                    @endif

                    {{-- Audit trail --}}
                    <section class="rounded-xl bg-surface-container-lowest p-space-lg shadow-sm">
                        @php
                            $hasActivities = isset($activities);
                            $wasUpdated    = $task->updated_at->gt($task->created_at->copy()->addMinute());
                            $eventCount    = $hasActivities ? $activities->count() : ($wasUpdated ? 2 : 1);
                        @endphp

                        <div class="mb-space-md flex items-center gap-2">
                            <h2 class="text-title-md text-on-surface">Audit Trail</h2>
                            <span class="rounded-full bg-surface-container-high px-2 py-0.5 text-label-sm text-on-surface-variant">
                                {{ $eventCount }} {{ Str::plural('event', $eventCount) }}
                            </span>
                        </div>

                        {{--
                        | RBAC-COMMENTS: comment composer (COMMENTED OUT)
                        | Needs: comments table, route 'projects.tasks.comments.store',
                        | permission tasks.comment. Then enable:
                        |
                        | @can('tasks.comment')
                        | <form action="{{ route('projects.tasks.comments.store', [$project, $task]) }}" method="POST" class="mb-space-lg">
                        |     @csrf
                        |     <textarea name="body" rows="3" placeholder="Write a comment…"
                        |               class="w-full resize-y rounded-xl border-0 bg-surface-container-low p-space-md text-body-md text-on-surface placeholder:text-outline focus:outline-none focus:ring-2 focus:ring-primary-container/30"></textarea>
                        |     <div class="mt-space-sm flex justify-end">
                        |         <button type="submit" class="rounded-xl bg-primary-container px-space-md py-2 text-label-md text-on-primary shadow-sm hover:bg-primary">Post Update</button>
                        |     </div>
                        | </form>
                        | @endcan
                        --}}

                        <ol class="divide-y divide-surface-container-low">
                            @if ($hasActivities)
                                @forelse ($activities->take(8) as $activity)
                                    <li class="flex items-start justify-between gap-space-md py-space-sm">
                                        <div class="flex items-start gap-space-sm">
                                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-primary-fixed text-primary">
                                                <span class="material-symbols-outlined text-[18px]" aria-hidden="true">sync</span>
                                            </span>
                                            <p class="pt-1.5 text-body-md text-on-surface-variant">
                                                @if ($activity->causer?->name)
                                                    <span class="font-semibold text-on-surface">{{ $activity->causer->name }}</span>
                                                @endif
                                                {{ $activity->description }}
                                            </p>
                                        </div>
                                        <span class="shrink-0 pt-1.5 text-body-sm text-on-surface-variant">{{ $activity->created_at->diffForHumans() }}</span>
                                    </li>
                                @empty
                                    <li class="py-space-sm text-body-md text-on-surface-variant">No activity recorded yet.</li>
                                @endforelse
                            @else
                                @if ($wasUpdated)
                                    <li class="flex items-start justify-between gap-space-md py-space-sm">
                                        <div class="flex items-start gap-space-sm">
                                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-primary-fixed text-primary">
                                                <span class="material-symbols-outlined text-[18px]" aria-hidden="true">sync</span>
                                            </span>
                                            <p class="pt-1.5 text-body-md text-on-surface-variant">
                                                <span class="font-semibold text-on-surface">Task updated</span>
                                                · status is now
                                                <span class="{{ $pill }} {{ $statusPill }} align-middle">{{ ucfirst(str_replace('_', ' ', $task->status)) }}</span>
                                            </p>
                                        </div>
                                        <span class="shrink-0 pt-1.5 text-body-sm text-on-surface-variant">{{ $task->updated_at->diffForHumans() }}</span>
                                    </li>
                                @endif
                                <li class="flex items-start justify-between gap-space-md py-space-sm">
                                    <div class="flex items-start gap-space-sm">
                                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-tertiary-fixed/40 text-tertiary">
                                            <span class="material-symbols-outlined text-[18px]" aria-hidden="true">add_task</span>
                                        </span>
                                        <p class="pt-1.5 text-body-md text-on-surface-variant">
                                            <span class="font-semibold text-on-surface">Task created</span>
                                            in {{ $project->name }}
                                        </p>
                                    </div>
                                    <span class="shrink-0 pt-1.5 text-body-sm text-on-surface-variant">{{ $task->created_at->format('d M Y, H:i') }}</span>
                                </li>
                            @endif
                        </ol>
                    </section>
                </div>

                {{-- ============ Sidebar ============ --}}
                <aside class="flex flex-col gap-space-lg lg:col-span-4">

                    <section class="rounded-xl bg-surface-container-lowest p-space-lg shadow-sm">
                        <h2 class="text-title-md text-on-surface">Task Details</h2>

                        <dl class="mt-space-md divide-y divide-surface-container-low">
                            <div class="pb-space-md">
                                <dt class="{{ $label }}">Project</dt>
                                <dd class="mt-1">
                                    <a href="{{ route('projects.show', $project) }}"
                                       class="inline-flex items-center gap-1.5 text-body-md font-medium text-primary hover:underline">
                                        <span class="material-symbols-outlined text-[18px]" aria-hidden="true">layers</span>
                                        {{ $project->name }}
                                    </a>
                                </dd>
                            </div>

                            <div class="py-space-md">
                                <dt class="{{ $label }}">Assignee</dt>
                                <dd class="mt-2">
                                    @if ($assignee)
                                        <div class="flex items-center gap-3">
                                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-primary-fixed text-label-md font-bold text-on-primary-fixed">
                                                {{ $initials($assignee->name) }}
                                            </span>
                                            <div class="min-w-0">
                                                <p class="truncate text-title-sm text-on-surface">{{ $assignee->name }}</p>
                                                @if ($assignee->email)
                                                    <p class="truncate text-body-sm text-on-surface-variant">{{ $assignee->email }}</p>
                                                @endif
                                            </div>
                                        </div>
                                    @else
                                        <p class="text-body-md text-on-surface-variant">Unassigned</p>
                                    @endif
                                </dd>
                            </div>

                            <div class="flex items-center justify-between gap-3 py-space-md">
                                <div>
                                    <dt class="{{ $label }}">Due date</dt>
                                    <dd class="mt-1 text-title-sm tabular-nums text-on-surface">{{ $task->due_date?->format('M d, Y') ?? 'Not set' }}</dd>
                                </div>
                                @if ($dueState)
                                    <span class="{{ $pill }} {{ $dueStyles[$dueState] }}">
                                        <span class="material-symbols-outlined text-[13px]" aria-hidden="true">warning</span>
                                        {{ $dueState === 'overdue' ? $dueLabel : Str::replace('Due in ', '', $dueLabel) . ($dueLabel === 'Due today' ? '' : ' left') }}
                                    </span>
                                @endif
                            </div>

                            <div class="flex items-center justify-between gap-3 py-space-md">
                                <dt class="{{ $label }}">Priority</dt>
                                <dd><span class="{{ $pill }} {{ $priorityPill }}">{{ ucfirst($task->priority) }}</span></dd>
                            </div>

                            <div class="flex items-center justify-between gap-3 py-space-md">
                                <dt class="{{ $label }}">Status</dt>
                                <dd>
                                    <span class="{{ $pill }} {{ $statusPill }}">
                                        <span class="h-1.5 w-1.5 rounded-full {{ $statusDot }}" aria-hidden="true"></span>
                                        {{ ucfirst(str_replace('_', ' ', $task->status)) }}
                                    </span>
                                </dd>
                            </div>

                            <div class="py-space-md">
                                <dt class="{{ $label }}">Created</dt>
                                <dd class="mt-1 text-body-md tabular-nums text-on-surface">{{ $task->created_at->format('d M Y, H:i') }}</dd>
                            </div>

                            <div class="pt-space-md">
                                <dt class="{{ $label }}">Last updated</dt>
                                <dd class="mt-1 text-body-md tabular-nums text-on-surface">{{ $task->updated_at->format('d M Y, H:i') }}</dd>
                            </div>
                        </dl>
                    </section>

                    <section class="rounded-xl bg-surface-container-lowest p-space-lg shadow-sm">
                        <h2 class="mb-space-md text-title-md text-on-surface">Task Actions</h2>

                        <div class="flex flex-col gap-space-sm">
                            {{-- RBAC-EDIT: wrap in @can('update', $task) … @endcan once policies exist --}}
                            <a href="{{ route('projects.tasks.edit', [$project, $task]) }}"
                               class="flex items-center justify-center gap-2 rounded-xl bg-primary-container px-space-md py-2.5 text-label-md text-on-primary shadow-md transition-all hover:bg-primary hover:shadow-lg focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-container focus-visible:ring-offset-2">
                                <span class="material-symbols-outlined text-[18px]" aria-hidden="true">edit</span>
                                Edit Full Specifications
                            </a>

                            {{-- RBAC-DELETE: wrap in @can('delete', $task) … @endcan once policies exist --}}
                            <form action="{{ route('projects.tasks.destroy', [$project, $task]) }}" method="POST"
                                  onsubmit="return confirm(@js('Delete “' . $task->title . '”? This can’t be undone.'))">
                                @csrf
                                @method('DELETE')
                                <button type="submit"
                                        class="flex w-full items-center justify-center gap-2 rounded-xl bg-error-container/60 px-space-md py-2.5 text-label-md text-on-error-container transition-colors hover:bg-error hover:text-on-error focus:outline-none focus-visible:ring-2 focus-visible:ring-error focus-visible:ring-offset-2">
                                    <span class="material-symbols-outlined text-[18px]" aria-hidden="true">delete</span>
                                    Delete Task…
                                </button>
                            </form>
                        </div>
                    </section>
                </aside>
            </div>
        </div>
    </div>
</x-app-layout>