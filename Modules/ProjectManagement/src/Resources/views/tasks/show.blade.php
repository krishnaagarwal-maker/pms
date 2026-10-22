{{-- View: projects.tasks.show --}}
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
    $isDone       = in_array($task->status, ['completed', 'done']);
    $statusText   = ucfirst(str_replace('_', ' ', $task->status));

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

    $pill     = 'inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-label-sm';
    $labelCls = 'text-label-sm uppercase tracking-wider text-on-surface-variant';
    $focus    = 'focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-container focus-visible:ring-offset-2';
    $card     = 'rounded-xl bg-surface-container-lowest shadow-sm ring-1 ring-black/5';
    $btnSoft  = 'flex items-center gap-1.5 rounded-xl bg-surface-container-lowest px-space-md py-2 text-label-md text-on-surface shadow-sm ring-1 ring-black/5 transition-all hover:bg-surface-container-low ' . $focus;

    $assignee = $task->assignee ?? null;
    $initials = fn ($name) => Str::upper(collect(preg_split('/\s+/', trim((string) $name)))->filter()->map(fn ($w) => Str::substr($w, 0, 1))->take(2)->implode(''));

    // Progress tracker: Pending -> In progress -> Completed
    $step  = $isDone ? 2 : ($task->status === 'in_progress' ? 1 : 0);
    $steps = [
        ['Pending',     'radio_button_unchecked'],
        ['In progress', 'timelapse'],
        ['Completed',   'check'],
    ];

    // Neighbouring tasks in this project, restricted to tasks the signed-in user can open.
    $visibleProjectTasks = $project->tasks()->visibleTo(auth()->user());
    $prevTask = (clone $visibleProjectTasks)->where('id', '<', $task->id)->orderByDesc('id')->first();
    $nextTask = (clone $visibleProjectTasks)->where('id', '>', $task->id)->orderBy('id')->first();
@endphp

<x-app-layout>
    <style>
        @keyframes task-view-enter {
            from { opacity: 0; transform: translateY(12px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @keyframes task-view-step-pop {
            0% { opacity: 0; transform: scale(.65); }
            70% { opacity: 1; transform: scale(1.12); }
            100% { opacity: 1; transform: scale(1); }
        }

        @keyframes task-view-line-grow {
            from { transform: scaleX(0); }
            to { transform: scaleX(1); }
        }

        .task-view-enter {
            animation: task-view-enter .55s cubic-bezier(.22,.8,.25,1) both;
        }

        .task-view-step {
            animation: task-view-step-pop .42s cubic-bezier(.22,.8,.25,1) both;
        }

        .task-view-step-line {
            transform: scaleX(0);
            transform-origin: left center;
            animation: task-view-line-grow .55s cubic-bezier(.22,.8,.25,1) both;
        }

        .task-view-activity {
            animation: task-view-enter .4s cubic-bezier(.22,.8,.25,1) both;
        }

        @media (prefers-reduced-motion: reduce) {
            .task-view-enter,
            .task-view-step,
            .task-view-step-line,
            .task-view-activity { animation: none !important; transform: none !important; }
        }
    </style>

    <div class="relative w-full overflow-hidden font-inter"
         x-data="{
             copied: false,
             linkCopied: false,
             async copyText(text) {
                 try {
                     if (navigator.clipboard && window.isSecureContext) {
                         await navigator.clipboard.writeText(text);
                         return true;
                     }
                 } catch (e) {}
                 try {
                     const el = document.createElement('textarea');
                     el.value = text;
                     el.setAttribute('readonly', '');
                     el.style.position = 'fixed';
                     el.style.opacity = '0';
                     document.body.appendChild(el);
                     el.select();
                     const ok = document.execCommand('copy');
                     document.body.removeChild(el);
                     return ok;
                 } catch (e) { return false; }
             },
             async copySpec() {
                 if (await this.copyText(@js((string) $task->description))) {
                     this.copied = true;
                     setTimeout(() => this.copied = false, 1500);
                 }
             },
             async copyLink() {
                 if (await this.copyText(window.location.href)) {
                     this.linkCopied = true;
                     setTimeout(() => this.linkCopied = false, 1500);
                 }
             }
         }">

        {{-- Soft background glow --}}
        <div class="pointer-events-none absolute -left-20 -top-32 h-96 w-96 rounded-full bg-primary/5 blur-3xl"></div>
        <div class="pointer-events-none absolute right-10 top-20 h-80 w-80 rounded-full bg-tertiary-fixed/15 blur-3xl"></div>

        <div class="relative mx-auto max-w-7xl px-6 py-space-xl lg:px-12">

            {{-- Flash message --}}
            @if (session('success'))
                <div x-data="{ show: true }" x-show="show" x-transition.opacity role="status"
                     class="mb-space-lg flex items-center justify-between gap-4 rounded-xl bg-tertiary-fixed/30 px-4 py-3 text-tertiary">
                    <div class="flex items-center gap-3">
                        <span class="material-symbols-outlined text-[20px]" style="font-variation-settings: 'FILL' 1;" aria-hidden="true">check_circle</span>
                        <span class="text-body-md">{{ session('success') }}</span>
                    </div>
                    <button type="button" @click="show = false" aria-label="Dismiss"
                            class="rounded-lg p-1 transition-colors hover:bg-tertiary-fixed/50 {{ $focus }}">
                        <span class="material-symbols-outlined text-[18px]" aria-hidden="true">close</span>
                    </button>
                </div>
            @endif

            {{-- Top bar --}}
            <div class="task-view-enter mb-space-lg flex flex-wrap items-center justify-between gap-space-md">
                <nav class="flex flex-wrap items-center gap-2 text-label-md text-on-surface-variant" aria-label="Breadcrumb">
                    <a href="{{ route('projects.index') }}" class="flex items-center gap-1 rounded transition-colors hover:text-primary {{ $focus }}">
                        <span class="material-symbols-outlined text-[16px]" aria-hidden="true">folder_open</span>
                        Projects
                    </a>
                    <span class="material-symbols-outlined text-[14px]" aria-hidden="true">chevron_right</span>
                    <a href="{{ route('projects.show', $project) }}" class="max-w-[14rem] truncate rounded transition-colors hover:text-primary {{ $focus }}">{{ $project->name }}</a>
                    <span class="material-symbols-outlined text-[14px]" aria-hidden="true">chevron_right</span>
                    <span class="font-code-sm text-code-sm text-on-surface" aria-current="page">{{ $ticket }}</span>
                </nav>

                <div class="flex items-center gap-space-sm">
                    <a href="{{ route('projects.show', $project) }}" class="{{ $btnSoft }}">
                        <span class="material-symbols-outlined text-[18px]" aria-hidden="true">arrow_back</span>
                        Back to Project
                    </a>
                    <button type="button" @click="copyLink()" class="{{ $btnSoft }}">
                        <span class="material-symbols-outlined text-[18px]" aria-hidden="true" x-text="linkCopied ? 'check' : 'share'">share</span>
                        <span x-text="linkCopied ? 'Link copied' : 'Share'" aria-live="polite">Share</span>
                    </button>
                </div>
            </div>

            <div class="grid grid-cols-1 items-start gap-gutter lg:grid-cols-12">

                {{-- ============ Main column ============ --}}
                <div class="flex flex-col gap-space-lg lg:col-span-8">

                    {{-- Header card --}}
                    <section class="task-view-enter overflow-hidden {{ $card }}" style="animation-delay: 70ms">
                        <div class="h-1.5 w-full {{ $dueState === 'overdue' ? 'bg-error' : ($isDone ? 'bg-tertiary' : 'bg-gradient-to-r from-primary-container via-secondary-container to-tertiary-fixed') }}"></div>

                        <div class="p-space-lg">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="rounded-md bg-surface-container-high px-2 py-0.5 font-code-sm text-[11px] text-on-surface-variant">{{ $ticket }}</span>
                                <span class="{{ $pill }} {{ $statusPill }}">
                                    <span class="h-1.5 w-1.5 rounded-full {{ $statusDot }} {{ $task->status === 'in_progress' ? 'motion-safe:animate-pulse' : '' }}" aria-hidden="true"></span>
                                    {{ $statusText }}
                                </span>
                                <span class="{{ $pill }} {{ $priorityPill }}">
                                    <span class="material-symbols-outlined text-[13px]" aria-hidden="true">flag</span>
                                    {{ ucfirst($task->priority) }} 
                                </span>
                                @if ($dueState)
                                    <span class="{{ $pill }} ml-auto {{ $dueStyles[$dueState] }}">
                                        <span class="material-symbols-outlined text-[13px]" aria-hidden="true">schedule</span>
                                        {{ $dueLabel }} ({{ $task->due_date->format('M d') }})
                                    </span>
                                @endif
                            </div>

                            <h1 class="mt-space-md break-words text-headline-lg tracking-tight text-on-surface sm:text-headline-xl {{ $isDone ? 'text-on-surface-variant' : '' }}">
                                {{ $task->title }}
                            </h1>

                            <p class="mt-space-sm text-body-md text-on-surface-variant">
                                Created on {{ $task->created_at->format('M d, Y') }}
                                <span class="text-outline-variant" aria-hidden="true">·</span>
                                Last updated {{ $task->updated_at->diffForHumans() }}
                            </p>

                            {{-- Progress tracker --}}
                            <ol class="mt-space-lg flex items-center" aria-label="Task progress">
                                @foreach ($steps as $i => [$stepLabel, $stepIcon])
                                    @php
                                        if ($i < $step || ($i === $step && $step === 2)) {
                                            $dot = 'bg-tertiary text-white';
                                            $txt = 'text-on-surface';
                                        } elseif ($i === $step && $step === 1) {
                                            $dot = 'bg-secondary text-white ring-4 ring-secondary-fixed';
                                            $txt = 'font-semibold text-on-surface';
                                        } elseif ($i === $step) {
                                            $dot = 'bg-surface-container-lowest text-on-surface-variant ring-2 ring-outline';
                                            $txt = 'font-semibold text-on-surface';
                                        } else {
                                            $dot = 'bg-surface-container text-outline';
                                            $txt = 'text-on-surface-variant';
                                        }
                                    @endphp
                                    <li class="flex items-center {{ $loop->last ? '' : 'flex-1' }}" style="animation-delay: {{ 160 + ($i * 110) }}ms" @if ($i === $step) aria-current="step" @endif>
                                        <span class="flex items-center gap-2">
                                            <span class="task-view-step flex h-7 w-7 shrink-0 items-center justify-center rounded-full {{ $dot }}" style="animation-delay: {{ 160 + ($i * 110) }}ms">
                                                <span class="material-symbols-outlined text-[16px]" aria-hidden="true">{{ $i < $step ? 'check' : $stepIcon }}</span>
                                            </span>
                                            <span class="hidden text-label-md sm:inline {{ $txt }}">{{ $stepLabel }}</span>
                                        </span>
                                        @unless ($loop->last)
                                            <span class="task-view-step-line mx-3 h-0.5 flex-1 rounded-full {{ $i < $step ? 'bg-tertiary' : 'bg-surface-container-high' }}" style="animation-delay: {{ 250 + ($i * 110) }}ms" aria-hidden="true"></span>
                                        @endunless
                                    </li>
                                @endforeach
                            </ol>

                            {{-- Specification --}}
                            <div class="task-view-enter mt-space-lg rounded-xl bg-surface-container-low p-space-md" style="animation-delay: 440ms">
                                <div class="mb-space-sm flex items-center justify-between">
                                    <span class="flex items-center gap-1.5 text-body-md font-medium text-on-surface">
                                        <span class="material-symbols-outlined text-[18px] text-on-surface-variant" aria-hidden="true">description</span>
                                        Specification &amp; Scope
                                    </span>
                                    @if (filled($task->description))
                                        <button type="button" @click="copySpec()"
                                                class="flex items-center gap-1 rounded text-label-sm text-on-surface-variant transition-colors hover:text-primary {{ $focus }}">
                                            <span class="material-symbols-outlined text-[14px]" aria-hidden="true" x-text="copied ? 'check' : 'content_copy'">content_copy</span>
                                            <span x-text="copied ? 'Copied' : 'Copy Spec'" aria-live="polite">Copy Spec</span>
                                        </button>
                                    @endif
                                </div>

                                @if (filled($task->description))
                                    <div class="whitespace-pre-line break-words text-body-md leading-6 text-on-surface">{{ $task->description }}</div>
                                @else
                                    <p class="text-body-md text-on-surface-variant">No description provided.</p>
                                @endif
                            </div>
                        </div>
                    </section>

                    {{-- Role & permissions (renders only if the task has a role) --}}
                    @if ($task->assignedRole ?? null)
                        <section class="task-view-enter p-space-lg {{ $card }}" style="animation-delay: 160ms">
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

                    {{-- Recent Updates --}}
                    <section class="task-view-enter p-space-lg {{ $card }}" style="animation-delay: 220ms">
                        @php
                            $hasActivities = isset($activities);
                            $wasUpdated    = $task->updated_at->gt($task->created_at->copy()->addMinute());
                            $eventCount    = $hasActivities ? $activities->count() : ($wasUpdated ? 2 : 1);
                        @endphp

                        <div class="mb-space-md flex items-center gap-2">
                            <h2 class="text-title-md text-on-surface">Recent Updates</h2>
                            <span class="rounded-full bg-surface-container-high px-2 py-0.5 text-label-sm text-on-surface-variant">
                                {{ $eventCount }} {{ Str::plural('event', $eventCount) }}
                            </span>
                        </div>

                        <ol class="divide-y divide-surface-container-low">
                            @if ($hasActivities)
                                @forelse ($activities->take(8) as $activity)
                                    <li class="task-view-activity flex items-start justify-between gap-space-md py-space-sm" style="animation-delay: {{ 300 + ($loop->index * 80) }}ms">
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
                                        <span class="shrink-0 pt-1.5 text-body-sm text-on-surface-variant" title="{{ $activity->created_at->format('d M Y, H:i') }}">{{ $activity->created_at->diffForHumans() }}</span>
                                    </li>
                                @empty
                                    <li class="py-space-sm text-body-md text-on-surface-variant">No activity recorded yet.</li>
                                @endforelse
                            @else
                                @if ($wasUpdated)
                                    <li class="task-view-activity flex items-start justify-between gap-space-md py-space-sm" style="animation-delay: 300ms">
                                        <div class="flex items-start gap-space-sm">
                                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-primary-fixed text-primary">
                                                <span class="material-symbols-outlined text-[18px]" aria-hidden="true">sync</span>
                                            </span>
                                            <p class="pt-1.5 text-body-md text-on-surface-variant">
                                                <span class="font-semibold text-on-surface">Task updated</span>
                                                · status is now
                                                <span class="{{ $pill }} {{ $statusPill }} align-middle">{{ $statusText }}</span>
                                            </p>
                                        </div>
                                        <span class="shrink-0 pt-1.5 text-body-sm text-on-surface-variant" title="{{ $task->updated_at->format('d M Y, H:i') }}">{{ $task->updated_at->diffForHumans() }}</span>
                                    </li>
                                @endif
                                <li class="task-view-activity flex items-start justify-between gap-space-md py-space-sm" style="animation-delay: {{ $wasUpdated ? 380 : 300 }}ms">
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

                    {{-- Previous / next task --}}
                    @if ($prevTask || $nextTask)
                        <nav class="task-view-enter grid grid-cols-1 gap-space-sm sm:grid-cols-2" style="animation-delay: 300ms" aria-label="Neighbouring tasks">
                            @if ($prevTask)
                                <a href="{{ route('projects.tasks.show', [$project, $prevTask]) }}"
                                   class="group flex items-center gap-3 p-space-md transition-all hover:shadow-md {{ $card }} {{ $focus }}">
                                    <span class="material-symbols-outlined text-[20px] text-on-surface-variant transition-transform group-hover:-translate-x-0.5 motion-reduce:transition-none" aria-hidden="true">arrow_back</span>
                                    <span class="min-w-0">
                                        <span class="block {{ $labelCls }}">Previous task</span>
                                        <span class="block truncate text-body-md font-medium text-on-surface group-hover:text-primary">{{ $prevTask->title }}</span>
                                    </span>
                                </a>
                            @else
                                <span class="hidden sm:block"></span>
                            @endif

                            @if ($nextTask)
                                <a href="{{ route('projects.tasks.show', [$project, $nextTask]) }}"
                                   class="group flex items-center justify-between gap-3 p-space-md text-right transition-all hover:shadow-md {{ $card }} {{ $focus }}">
                                    <span class="min-w-0">
                                        <span class="block {{ $labelCls }}">Next task</span>
                                        <span class="block truncate text-body-md font-medium text-on-surface group-hover:text-primary">{{ $nextTask->title }}</span>
                                    </span>
                                    <span class="material-symbols-outlined text-[20px] text-on-surface-variant transition-transform group-hover:translate-x-0.5 motion-reduce:transition-none" aria-hidden="true">arrow_forward</span>
                                </a>
                            @endif
                        </nav>
                    @endif
                </div>

                {{-- ============ Sidebar ============ --}}
                <aside class="flex flex-col gap-space-lg lg:sticky lg:top-6 lg:col-span-4">

                    <section class="task-view-enter p-space-lg {{ $card }}" style="animation-delay: 140ms">
                        <h2 class="text-title-md text-on-surface">Task Details</h2>

                        <dl class="mt-space-md divide-y divide-surface-container-low">
                            <div class="pb-space-md">
                                <dt class="{{ $labelCls }}">Project</dt>
                                <dd class="mt-1">
                                    <a href="{{ route('projects.show', $project) }}"
                                       class="inline-flex items-center gap-1.5 rounded text-body-md font-medium text-primary hover:underline {{ $focus }}">
                                        <span class="material-symbols-outlined text-[18px]" aria-hidden="true">layers</span>
                                        {{ $project->name }}
                                    </a>
                                </dd>
                            </div>

                            <div class="py-space-md">
                                <dt class="{{ $labelCls }}">Assignee</dt>
                                <dd class="mt-2">
                                    @if ($assignee)
                                        <div class="flex items-center gap-3">
                                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-primary-fixed text-label-md font-bold text-on-primary-fixed" aria-hidden="true">
                                                {{ $initials($assignee->name) }}
                                            </span>
                                            <div class="min-w-0">
                                                <p class="truncate text-title-sm text-on-surface">{{ $assignee->name }}</p>
                                                @if ($assignee->email)
                                                    <a href="mailto:{{ $assignee->email }}" class="block truncate rounded text-body-sm text-on-surface-variant hover:text-primary hover:underline {{ $focus }}">{{ $assignee->email }}</a>
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
                                    <dt class="{{ $labelCls }}">Due date</dt>
                                    <dd class="mt-1 text-title-sm tabular-nums text-on-surface">{{ $task->due_date?->format('M d, Y') ?? 'Not set' }}</dd>
                                    @if ($task->due_date && ! $dueState && ! $isDone)
                                        <dd class="text-body-sm text-on-surface-variant">{{ $task->due_date->diffForHumans() }}</dd>
                                    @endif
                                </div>
                                @if ($dueState)
                                    <span class="{{ $pill }} {{ $dueStyles[$dueState] }}">
                                        <span class="material-symbols-outlined text-[13px]" aria-hidden="true">warning</span>
                                        {{ $dueState === 'overdue' ? $dueLabel : Str::replace('Due in ', '', $dueLabel) . ($dueLabel === 'Due today' ? '' : ' left') }}
                                    </span>
                                @endif
                            </div>

                            <div class="flex items-center justify-between gap-3 py-space-md">
                                <dt class="{{ $labelCls }}">Priority</dt>
                                <dd><span class="{{ $pill }} {{ $priorityPill }}">{{ ucfirst($task->priority) }}</span></dd>
                            </div>

                            <div class="flex items-center justify-between gap-3 py-space-md">
                                <dt class="{{ $labelCls }}">Status</dt>
                                <dd>
                                    <span class="{{ $pill }} {{ $statusPill }}">
                                        <span class="h-1.5 w-1.5 rounded-full {{ $statusDot }}" aria-hidden="true"></span>
                                        {{ $statusText }}
                                    </span>
                                </dd>
                            </div>

                            <div class="py-space-md">
                                <dt class="{{ $labelCls }}">Created</dt>
                                <dd class="mt-1 text-body-md tabular-nums text-on-surface">{{ $task->created_at->format('d M Y, H:i') }}</dd>
                            </div>

                            <div class="pt-space-md">
                                <dt class="{{ $labelCls }}">Last updated</dt>
                                <dd class="mt-1 text-body-md tabular-nums text-on-surface">{{ $task->updated_at->format('d M Y, H:i') }}</dd>
                            </div>
                        </dl>
                    </section>

                    <section class="task-view-enter p-space-lg {{ $card }}" style="animation-delay: 220ms">
                        <h2 class="mb-space-md text-title-md text-on-surface">Task Actions</h2>

                        <div class="flex flex-col gap-space-sm">
                            @can('tasks.edit')
                                <a href="{{ route('projects.tasks.edit', [$project, $task]) }}"
                                   class="flex items-center justify-center gap-2 rounded-xl bg-primary-container px-space-md py-2.5 text-label-md text-on-primary shadow-md transition-all hover:bg-primary hover:shadow-lg {{ $focus }}">
                                    <span class="material-symbols-outlined text-[18px]" aria-hidden="true">edit</span>
                                    Edit Specs
                                </a>
                            @endcan

                            @can('tasks.update-status')
                                @unless (auth()->user()?->can('tasks.edit'))
                                    <form action="{{ route('projects.tasks.update', [$project, $task]) }}" method="POST" class="w-full">
                                        @csrf
                                        @method('PUT')

                                        <fieldset class="mt-space-sm">
                                            <legend class="mb-space-xs flex items-center gap-1 text-label-md text-on-surface">
                                                <span class="material-symbols-outlined text-[16px]" aria-hidden="true">hourglass_empty</span>
                                                Execution Status <span class="text-error" aria-hidden="true">*</span>
                                            </legend>

                                            <div class="grid grid-cols-1 gap-1 rounded-xl bg-surface-container-low p-1 sm:grid-cols-3">
                                                @foreach ([
                                                    ['pending', 'Pending', 'radio_button_unchecked'],
                                                    ['in_progress', 'In Progress', 'timelapse'],
                                                    ['completed', 'Completed', 'check_circle'],
                                                ] as [$value, $optLabel, $optIcon])
                                                    <label class="cursor-pointer">
                                                        <input type="radio" name="status" value="{{ $value }}" {{ $task->status === $value ? 'checked' : '' }} required class="peer sr-only">
                                                        <span class="flex items-center justify-center gap-1.5 rounded-lg py-2.5 text-label-md transition-all peer-focus-visible:ring-2 peer-focus-visible:ring-primary-container {{ $task->status === $value ? (
                                                            $value === 'in_progress' ? 'bg-secondary-fixed text-on-secondary-fixed shadow-sm' :
                                                            ($value === 'completed' ? 'bg-tertiary-fixed text-on-tertiary-fixed shadow-sm' : 'bg-surface-container-lowest text-on-surface shadow-sm')
                                                        ) : 'text-on-surface-variant hover:text-on-surface' }}">
                                                            <span class="material-symbols-outlined text-[18px]" aria-hidden="true">{{ $optIcon }}</span>
                                                            {{ $optLabel }}
                                                        </span>
                                                    </label>
                                                @endforeach
                                            </div>

                                            @error('status')
                                                <p class="mt-1.5 flex items-center gap-1 text-body-sm text-error" role="alert">
                                                    <span class="material-symbols-outlined text-[14px]" aria-hidden="true">error</span>
                                                    {{ $message }}
                                                </p>
                                            @enderror
                                        </fieldset>

                                        <button type="submit"
                                                class="mt-space-sm flex w-full items-center justify-center gap-2 rounded-xl bg-secondary-container px-space-md py-2.5 text-label-md text-on-secondary-container transition-colors hover:bg-secondary hover:text-on-secondary focus:outline-none focus-visible:ring-2 focus-visible:ring-secondary focus-visible:ring-offset-2">
                                            <span class="material-symbols-outlined text-[18px]" aria-hidden="true">update</span>
                                            Update Status
                                        </button>
                                    </form>
                                @endunless
                            @endcan

                            @can('tasks.delete')
                                <form action="{{ route('projects.tasks.destroy', [$project, $task]) }}" method="POST"
                                      onsubmit="return confirm(@js('Delete “' . $task->title . '”? This can’t be undone.'))">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit"
                                            class="flex w-full items-center justify-center gap-2 rounded-xl bg-error-container/60 px-space-md py-2.5 text-label-md text-on-error-container transition-colors hover:bg-error hover:text-on-error focus:outline-none focus-visible:ring-2 focus-visible:ring-error focus-visible:ring-offset-2">
                                        <span class="material-symbols-outlined text-[18px]" aria-hidden="true">delete</span>
                                        Delete Task
                                    </button>
                                </form>
                            @endcan
                        </div>
                    </section>
                </aside>
            </div>
        </div>
    </div>
</x-app-layout>
