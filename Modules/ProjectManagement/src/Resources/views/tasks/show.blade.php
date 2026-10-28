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
    $latestComment = $task->comments->first();
    $isManager = auth()->user()?->can('tasks.edit');
    $canReplyToComments = $isManager || auth()->user()?->can('tasks.assign') || ((int) auth()->id() === (int) $task->assigned_to);
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

    // Time Tracker & Timesheet
    $canControlTimer = auth()->user()->can('tasks.edit')
        || ((int) $task->assigned_to === (int) auth()->id() && auth()->user()->can('tasks.update-status'));
    $timerConfig = [
        'startUrl'   => route('projects.tasks.timer.start', [$project, $task]),
        'stopUrl'    => route('projects.tasks.timer.stop', [$project, $task]),
        'deleteUrl'  => url('/projects/' . $project->id . '/tasks/' . $task->id . '/timer'),
        'csrf'       => csrf_token(),
        'canControl' => $canControlTimer,
        'state'      => $task->timerState(),
    ];
@endphp

<x-app-layout>
    <style>
        [x-cloak] { display: none !important; }

        @keyframes task-view-enter { from { opacity: 0; transform: translateY(12px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes task-view-step-pop { 0% { opacity: 0; transform: scale(.65); } 70% { opacity: 1; transform: scale(1.12); } 100% { opacity: 1; transform: scale(1); } }
        @keyframes task-view-line-grow { from { transform: scaleX(0); } to { transform: scaleX(1); } }
        @keyframes comment-enter { from { opacity: 0; transform: translateX(-10px); } to { opacity: 1; transform: translateX(0); } }
        @keyframes reply-slide-in { from { opacity: 0; transform: translateY(-8px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes reply-form-expand { from { opacity: 0; max-height: 0; } to { opacity: 1; max-height: 300px; } }

        .task-view-enter { animation: task-view-enter .55s cubic-bezier(.22,.8,.25,1) both; }
        .task-view-step { animation: task-view-step-pop .42s cubic-bezier(.22,.8,.25,1) both; }
        .task-view-step-line { transform: scaleX(0); transform-origin: left center; animation: task-view-line-grow .55s cubic-bezier(.22,.8,.25,1) both; }
        .task-view-activity { animation: task-view-enter .4s cubic-bezier(.22,.8,.25,1) both; }
        .comment-item { animation: comment-enter .4s cubic-bezier(.22,.8,.25,1) both; }
        .reply-item { animation: reply-slide-in .3s cubic-bezier(.22,.8,.25,1) both; }
        .reply-form-enter { animation: reply-form-expand .3s cubic-bezier(.22,.8,.25,1) both; }

        /* Timeline dots: light until the progress line reaches them, then they blend into the line colour */
        .timeline-dot {
            background-color: #e2e8f0;
            border-color: #cbd5e1;
            transition: background-color .35s ease, border-color .35s ease, transform .35s ease;
        }
        .timeline-dot .material-symbols-outlined { color: #64748b; transition: color .35s ease; }
        .timeline-dot.is-dark { background-color: #1e293b; border-color: #1e293b; transform: scale(1.08); }
        .timeline-dot.is-dark .material-symbols-outlined { color: #fff; }

        @media (prefers-reduced-motion: reduce) {
            .task-view-enter, .task-view-step, .task-view-activity,
            .comment-item, .reply-item, .reply-form-enter { animation: none !important; transform: none !important; }
            .task-view-step-line { animation: none !important; transform: none !important; }
            .timeline-dot { transition: none !important; }
        }
    </style>

    <div class="relative w-full overflow-hidden font-inter"
         x-data="{
             copied: false,
             linkCopied: false,
             editingComment: null,
             replyingComment: null,
             _raf: null,
             init() {
                 this.$nextTick(() => requestAnimationFrame(() => this.runTimeline(true)));
                 let t;
                 window.addEventListener('resize', () => {
                     clearTimeout(t);
                     t = setTimeout(() => this.runTimeline(false), 150);
                 });
             },
             runTimeline(animate = true) {
                 const wrap = this.$refs.timeline, track = this.$refs.track, fill = this.$refs.fill;
                 if (!wrap || !track || !fill) return;
                 cancelAnimationFrame(this._raf);

                 const dots = Array.from(wrap.querySelectorAll('.timeline-dot'));
                 if (!dots.length) { track.style.display = 'none'; fill.style.display = 'none'; return; }

                 // offsetTop is not affected by entry transforms, so positions are exact
                 const pts = dots.map(el => {
                     let y = 0, n = el;
                     while (n && n !== wrap) { y += n.offsetTop; n = n.offsetParent; }
                     const r = el.offsetHeight / 2;
                     return { el, r, y: y + r };
                 }).sort((a, b) => a.y - b.y);

                 const start = pts[0].y;
                 const len = pts[pts.length - 1].y - start;

                 track.style.display = fill.style.display = 'block';
                 track.style.top = fill.style.top = start + 'px';
                 track.style.height = len + 'px';

                 const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
                 if (!animate || reduce || len === 0) {
                     fill.style.height = len + 'px';
                     pts.forEach(p => p.el.classList.add('is-dark'));
                     return;
                 }

                 fill.style.height = '0px';
                 pts.forEach(p => p.el.classList.remove('is-dark'));

                 const duration = Math.min(4000, Math.max(1500, len * 6));
                 const t0 = performance.now();
                 const step = (now) => {
                     const p = Math.min((now - t0) / duration, 1);
                     const h = p * len;
                     fill.style.height = h + 'px';
                     // darken a dot the moment the line touches its top edge
                     pts.forEach(pt => { if (h >= (pt.y - start) - pt.r) pt.el.classList.add('is-dark'); });
                     if (p < 1) this._raf = requestAnimationFrame(step);
                 };
                 this._raf = requestAnimationFrame(step);
             },
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

                    {{-- Task Attachments --}}
                    @php
                        $canUploadTask = auth()->user()->can('attachments.create') && (
                            auth()->user()->can('tasks.edit') ||
                            ((int) $task->assigned_to === (int) auth()->id() && auth()->user()->can('tasks.update-status'))
                        );
                    @endphp
                    @include('ProjectManagement::attachments._section', [
                        'attachable' => $task,
                        'title' => 'Task Attachments',
                        'subtitle' => 'Reference documents, screenshots, logs, and deliverables for this task',
                        'uploadRoute' => route('projects.tasks.attachments.store', [$project, $task]),
                        'canUpload' => $canUploadTask,
                    ])

                    {{-- Daily work updates --}}
                    <section id="daily-updates" class="task-view-enter p-space-lg {{ $card }}" style="animation-delay: 200ms">
                        <div class="flex flex-wrap items-start justify-between gap-space-md">
                            <div>
                                <div class="flex items-center gap-2">
                                    <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-secondary-fixed text-on-secondary-fixed">
                                        <span class="material-symbols-outlined text-[20px]" aria-hidden="true">edit_note</span>
                                    </span>
                                    <div>
                                        <h2 class="text-title-md text-on-surface">Daily work updates</h2>
                                        <p class="text-body-sm text-on-surface-variant">A running record of work completed on this task</p>
                                    </div>
                                </div>
                            </div>
                            <span class="{{ $pill }} bg-surface-container-high text-on-surface-variant">
                                {{ $task->comments->count() }} {{ Str::plural('update', $task->comments->count()) }}
                            </span>
                        </div>

                        @php
                            $canAddComment = $isManager || ((int) auth()->id() === (int) $task->assigned_to);
                        @endphp

                        @if ($canAddComment)
                            <form action="{{ route('projects.tasks.comments.store', [$project, $task]) }}" method="POST" class="mt-space-lg rounded-xl bg-surface-container-low p-space-md">
                                @csrf
                                <label for="body" class="mb-space-xs block text-label-md text-on-surface">
                                    {{ $isManager && (int) auth()->id() !== (int) $task->assigned_to ? 'Add a comment or update to this task' : 'What did you work on today?' }}
                                </label>
                                <textarea id="body" name="body" rows="4" maxlength="2000" required
                                          placeholder="Summarise the work you completed, progress made, manager notes, or any blockers..."
                                          class="block w-full resize-y rounded-xl border-0 bg-surface-container-lowest px-3 py-2.5 text-body-md text-on-surface shadow-sm ring-1 ring-inset ring-outline/30 placeholder:text-on-surface-variant/70 focus:ring-2 focus:ring-primary {{ $focus }}">{{ old('body') }}</textarea>
                                <div class="mt-space-sm flex flex-wrap items-center justify-between gap-3">
                                    <p class="text-body-sm text-on-surface-variant">Your update is saved with the current date and time.</p>
                                    <button type="submit" class="flex items-center justify-center gap-2 rounded-xl bg-primary-container px-space-md py-2.5 text-label-md text-on-primary shadow-sm transition-all hover:bg-primary hover:shadow-md {{ $focus }}">
                                        <span class="material-symbols-outlined text-[18px]" aria-hidden="true">send</span>
                                        Add update
                                    </button>
                                </div>
                                @error('body')
                                    <p class="mt-space-sm flex items-center gap-1 text-body-sm text-error" role="alert">
                                        <span class="material-symbols-outlined text-[14px]" aria-hidden="true">error</span>
                                        {{ $message }}
                                    </p>
                                @enderror
                            </form>
                        @endif

                        <div id="daily-updates-chat" class="mt-space-lg max-h-[32rem] overflow-y-auto overscroll-contain rounded-xl bg-surface-container-low/40 p-space-sm pr-2 scroll-smooth">
                            @php
                                $allComments = $task->comments;
                                $byId  = $allComments->keyBy('id');
                                $isTop = fn ($c) => ! $c->parent_id || ! $byId->has($c->parent_id);
                                // Walk up to the top-level comment so replies-to-replies stay in the same thread
                                $rootId = function ($c) use ($byId, $isTop) {
                                    $guard = 0;
                                    while (! $isTop($c) && $guard++ < 50) { $c = $byId[$c->parent_id]; }
                                    return $c->id;
                                };
                                $parentComments  = $allComments->filter($isTop)->sortByDesc('created_at');
                                $repliesByParent = $allComments->reject($isTop)->sortByDesc('created_at')->groupBy($rootId);
                            @endphp

                            {{-- Scrolls together with the comments; the line is centred on x = 16px (the 32px parent dots) --}}
                            <div x-ref="timeline" class="relative flex flex-col">
                                <div x-ref="track" class="pointer-events-none absolute z-0 hidden w-0.5 rounded-full bg-slate-100" style="left:15px" aria-hidden="true"></div>
                                <div x-ref="fill"  class="pointer-events-none absolute z-0 hidden w-0.5 rounded-full bg-slate-400" style="left:15px" aria-hidden="true"></div>

                                @forelse ($parentComments as $parentComment)
                                    @php
                                        $canManageComment = $isManager || ((int) auth()->id() === (int) $parentComment->user_id);
                                        $replies = $repliesByParent->get($parentComment->id, collect());
                                    @endphp

                                    <article class="comment-item group relative py-space-sm" style="animation-delay: {{ $loop->index * 50 }}ms">
                                        <div class="flex items-start gap-4">
                                            <div class="timeline-dot relative z-10 flex h-8 w-8 shrink-0 items-center justify-center rounded-full border-2 shadow-sm" aria-hidden="true">
                                                <span class="material-symbols-outlined text-[16px]">edit_note</span>
                                            </div>

                                            <div class="min-w-0 flex-1">
                                                <p x-show="editingComment !== {{ $parentComment->id }}" class="whitespace-pre-line break-words text-body-md leading-6 text-on-surface">{{ $parentComment->body }}</p>

                                                @if ($canManageComment)
                                                    <form x-show="editingComment === {{ $parentComment->id }}" x-cloak action="{{ route('projects.tasks.comments.update', [$project, $task, $parentComment]) }}" method="POST" class="mt-space-sm rounded-xl bg-surface-container-low p-space-sm">
                                                        @csrf
                                                        @method('PUT')
                                                        <label for="comment-{{ $parentComment->id }}-body" class="sr-only">Edit daily update</label>
                                                        <textarea id="comment-{{ $parentComment->id }}-body" name="body" rows="3" maxlength="2000" required class="block w-full resize-y rounded-xl border-0 bg-surface-container-lowest px-3 py-2 text-body-md text-on-surface shadow-sm ring-1 ring-inset ring-outline/30 focus:ring-2 focus:ring-primary {{ $focus }}">{{ old('body', $parentComment->body) }}</textarea>
                                                        <div class="mt-2 flex justify-end gap-2">
                                                            <button type="button" @click="editingComment = null" class="rounded-lg px-3 py-1.5 text-label-sm text-on-surface-variant hover:bg-surface-container-high {{ $focus }}">Cancel</button>
                                                            <button type="submit" class="rounded-lg bg-primary-container px-3 py-1.5 text-label-sm text-on-primary hover:bg-primary {{ $focus }}">Save</button>
                                                        </div>
                                                    </form>
                                                @endif

                                                <div class="mt-3 flex flex-col gap-0.5">
                                                    <span class="text-[11px] font-medium text-on-surface-variant">Commented by: {{ $parentComment->user?->name ?? 'Deleted user' }}</span>
                                                    <time datetime="{{ $parentComment->created_at->toIso8601String() }}" title="{{ $parentComment->created_at->format('d M Y, H:i:s') }}" class="text-[10px] text-on-surface-variant/70">
                                                        Created on: {{ $parentComment->created_at->format('Y-m-d H:i:s') }}
                                                        @if ($parentComment->updated_at->gt($parentComment->created_at->copy()->addSecond()))
                                                             <span class="italic">(edited)</span>
                                                        @endif
                                                    </time>
                                                </div>

                                                <div class="mt-2 flex flex-wrap items-center gap-2">
                                                    @if ($canReplyToComments)
                                                        <button type="button" @click="replyingComment = replyingComment === {{ $parentComment->id }} ? null : {{ $parentComment->id }}" class="inline-flex items-center gap-1 rounded-md px-1.5 py-1 text-label-sm text-primary transition-colors hover:bg-primary-fixed {{ $focus }}">
                                                            <span class="material-symbols-outlined text-[15px]" aria-hidden="true">reply</span>
                                                            Reply
                                                        </button>
                                                    @endif
                                                    @if ($canManageComment)
                                                        <div class="flex items-center gap-1 border-l border-outline/20 pl-2 opacity-0 group-hover:opacity-100 focus-within:opacity-100 transition-opacity duration-150" x-show="editingComment !== {{ $parentComment->id }}">
                                                            <button type="button" @click="editingComment = {{ $parentComment->id }}" title="Edit update" aria-label="Edit update" class="flex h-7 w-7 items-center justify-center rounded-md text-on-surface-variant transition-colors hover:bg-primary-fixed hover:text-primary {{ $focus }}">
                                                                <span class="material-symbols-outlined text-[16px]" aria-hidden="true">edit</span>
                                                            </button>
                                                            <form action="{{ route('projects.tasks.comments.destroy', [$project, $task, $parentComment]) }}" method="POST" onsubmit="return confirm('Delete this daily update?')">
                                                                @csrf
                                                                @method('DELETE')
                                                                <button type="submit" title="Delete update" aria-label="Delete update" class="flex h-7 w-7 items-center justify-center rounded-md text-error transition-colors hover:bg-error-container {{ $focus }}">
                                                                    <span class="material-symbols-outlined text-[16px]" aria-hidden="true">delete</span>
                                                                </button>
                                                            </form>
                                                        </div>
                                                    @endif
                                                </div>

                                                @if ($canReplyToComments)
                                                    <div x-show="replyingComment === {{ $parentComment->id }}" x-cloak x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 -translate-y-2" x-transition:enter-end="opacity-100 translate-y-0" class="mt-space-sm">
                                                        <form action="{{ route('projects.tasks.comments.store', [$project, $task]) }}" method="POST" class="reply-form-enter rounded-xl border border-primary-fixed bg-primary-fixed/30 p-space-sm">
                                                            @csrf
                                                            <input type="hidden" name="parent_id" value="{{ $parentComment->id }}">
                                                            <label for="reply-{{ $parentComment->id }}-body" class="mb-1.5 block text-label-sm font-medium text-on-surface">Reply to this update</label>
                                                            <textarea id="reply-{{ $parentComment->id }}-body" name="body" rows="3" maxlength="2000" required placeholder="Write a response..." class="block w-full resize-y rounded-xl border-0 bg-surface-container-lowest px-3 py-2 text-body-md text-on-surface shadow-sm ring-1 ring-inset ring-primary/20 placeholder:text-on-surface-variant/70 focus:ring-2 focus:ring-primary {{ $focus }}"></textarea>
                                                            <div class="mt-2 flex justify-end gap-2">
                                                                <button type="button" @click="replyingComment = null" class="rounded-lg px-3 py-1.5 text-label-sm text-on-surface-variant hover:bg-surface-container-high {{ $focus }}">Cancel</button>
                                                                <button type="submit" class="inline-flex items-center gap-1.5 rounded-lg bg-primary-container px-3 py-1.5 text-label-sm text-on-primary hover:bg-primary {{ $focus }}">
                                                                    <span class="material-symbols-outlined text-[15px]" aria-hidden="true">send</span>
                                                                    Send reply
                                                                </button>
                                                            </div>
                                                        </form>
                                                    </div>
                                                @endif
                                            </div>
                                        </div>

                                        {{-- Replies: 24px dots with pl-1 => centre at 16px, same axis as the 32px parent dots --}}
                                        @if ($replies->isNotEmpty())
                                            <div class="mt-3 space-y-3">
                                                @foreach ($replies as $reply)
                                                    @php
                                                        $canManageReply = $isManager || ((int) auth()->id() === (int) $reply->user_id);
                                                        $replyTo = $byId->get($reply->parent_id);
                                                    @endphp
                                                    <div class="reply-item group flex items-start gap-5 pl-1" style="animation-delay: {{ ($loop->index * 50) + 100 }}ms">
                                                        <div class="timeline-dot relative z-10 flex h-6 w-6 shrink-0 items-center justify-center rounded-full border shadow-sm" aria-hidden="true">
                                                            <span class="material-symbols-outlined text-[14px]">reply</span>
                                                        </div>

                                                        <div class="min-w-0 flex-1 rounded-xl bg-surface-container-lowest p-3 shadow-sm ring-1 ring-black/5">
                                                            @if ($replyTo && $replyTo->id !== $parentComment->id)
                                                                <p class="mb-1 text-[10px] text-on-surface-variant">Replying to {{ $replyTo->user?->name ?? 'Deleted user' }}</p>
                                                            @endif

                                                            <p x-show="editingComment !== {{ $reply->id }}" class="whitespace-pre-line break-words text-body-sm leading-5 text-on-surface">{{ $reply->body }}</p>

                                                            @if ($canManageReply)
                                                                <form x-show="editingComment === {{ $reply->id }}" x-cloak action="{{ route('projects.tasks.comments.update', [$project, $task, $reply]) }}" method="POST" class="mt-space-sm rounded-xl bg-surface-container-low p-space-sm">
                                                                    @csrf
                                                                    @method('PUT')
                                                                    <label for="comment-{{ $reply->id }}-body" class="sr-only">Edit reply</label>
                                                                    <textarea id="comment-{{ $reply->id }}-body" name="body" rows="2" maxlength="2000" required class="block w-full resize-y rounded-xl border-0 bg-surface-container-lowest px-3 py-2 text-body-sm text-on-surface shadow-sm ring-1 ring-inset ring-outline/30 focus:ring-2 focus:ring-primary {{ $focus }}">{{ old('body', $reply->body) }}</textarea>
                                                                    <div class="mt-2 flex justify-end gap-2">
                                                                        <button type="button" @click="editingComment = null" class="rounded-lg px-3 py-1.5 text-label-sm text-on-surface-variant hover:bg-surface-container-high {{ $focus }}">Cancel</button>
                                                                        <button type="submit" class="rounded-lg bg-primary-container px-3 py-1.5 text-label-sm text-on-primary hover:bg-primary {{ $focus }}">Save</button>
                                                                    </div>
                                                                </form>
                                                            @endif

                                                            <div class="mt-2 flex flex-col gap-0.5">
                                                                <span class="text-[10px] font-medium text-on-surface-variant">Commented by: {{ $reply->user?->name ?? 'Deleted user' }}</span>
                                                                <time datetime="{{ $reply->created_at->toIso8601String() }}" title="{{ $reply->created_at->format('d M Y, H:i:s') }}" class="text-[9px] text-on-surface-variant/70">
                                                                    Created on: {{ $reply->created_at->format('Y-m-d H:i:s') }}
                                                                    @if ($reply->updated_at->gt($reply->created_at->copy()->addSecond()))
                                                                        <span class="italic">(edited)</span>
                                                                    @endif
                                                                </time>
                                                            </div>

                                                            <div class="mt-1.5 flex flex-wrap items-center gap-2">
                                                                @if ($canReplyToComments)
                                                                    <button type="button" @click="replyingComment = replyingComment === {{ $reply->id }} ? null : {{ $reply->id }}" class="inline-flex items-center gap-1 rounded-md px-1.5 py-1 text-label-sm text-primary transition-colors hover:bg-primary-fixed {{ $focus }}">
                                                                        <span class="material-symbols-outlined text-[14px]" aria-hidden="true">reply</span>
                                                                        Reply
                                                                    </button>
                                                                @endif
                                                                @if ($canManageReply)
                                                                    <div class="flex items-center gap-1 border-l border-outline/20 pl-2 opacity-0 group-hover:opacity-100 focus-within:opacity-100 transition-opacity duration-150" x-show="editingComment !== {{ $reply->id }}">
                                                                        <button type="button" @click="editingComment = {{ $reply->id }}" title="Edit reply" aria-label="Edit reply" class="flex h-6 w-6 items-center justify-center rounded-md text-on-surface-variant transition-colors hover:bg-primary-fixed hover:text-primary {{ $focus }}">
                                                                            <span class="material-symbols-outlined text-[14px]" aria-hidden="true">edit</span>
                                                                        </button>
                                                                        <form action="{{ route('projects.tasks.comments.destroy', [$project, $task, $reply]) }}" method="POST" onsubmit="return confirm('Delete this reply?')">
                                                                            @csrf
                                                                            @method('DELETE')
                                                                            <button type="submit" title="Delete reply" aria-label="Delete reply" class="flex h-6 w-6 items-center justify-center rounded-md text-error transition-colors hover:bg-error-container {{ $focus }}">
                                                                                <span class="material-symbols-outlined text-[14px]" aria-hidden="true">delete</span>
                                                                            </button>
                                                                        </form>
                                                                    </div>
                                                                @endif
                                                            </div>

                                                            @if ($canReplyToComments)
                                                                <div x-show="replyingComment === {{ $reply->id }}" x-cloak x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 -translate-y-2" x-transition:enter-end="opacity-100 translate-y-0" class="mt-space-sm">
                                                                    <form action="{{ route('projects.tasks.comments.store', [$project, $task]) }}" method="POST" class="reply-form-enter rounded-xl border border-primary-fixed bg-primary-fixed/30 p-space-sm">
                                                                        @csrf
                                                                        <input type="hidden" name="parent_id" value="{{ $reply->id }}">
                                                                        <label for="reply-{{ $reply->id }}-body" class="mb-1.5 block text-label-sm font-medium text-on-surface">Reply to this comment</label>
                                                                        <textarea id="reply-{{ $reply->id }}-body" name="body" rows="2" maxlength="2000" required placeholder="Write a response..." class="block w-full resize-y rounded-xl border-0 bg-surface-container-lowest px-3 py-2 text-body-sm text-on-surface shadow-sm ring-1 ring-inset ring-primary/20 placeholder:text-on-surface-variant/70 focus:ring-2 focus:ring-primary {{ $focus }}"></textarea>
                                                                        <div class="mt-2 flex justify-end gap-2">
                                                                            <button type="button" @click="replyingComment = null" class="rounded-lg px-3 py-1.5 text-label-sm text-on-surface-variant hover:bg-surface-container-high {{ $focus }}">Cancel</button>
                                                                            <button type="submit" class="inline-flex items-center gap-1.5 rounded-lg bg-primary-container px-3 py-1.5 text-label-sm text-on-primary hover:bg-primary {{ $focus }}">
                                                                                <span class="material-symbols-outlined text-[14px]" aria-hidden="true">send</span>
                                                                                Send reply
                                                                            </button>
                                                                        </div>
                                                                    </form>
                                                                </div>
                                                            @endif
                                                        </div>
                                                    </div>
                                                @endforeach
                                            </div>
                                        @endif
                                    </article>
                                @empty
                                    <div class="rounded-xl border border-dashed border-outline/40 px-space-md py-space-lg text-center">
                                        <span class="material-symbols-outlined text-[28px] text-on-surface-variant" aria-hidden="true">forum</span>
                                        <p class="mt-1 text-body-md text-on-surface-variant">No daily updates have been added yet.</p>
                                        @if (! $isManager && (int) auth()->id() !== (int) $task->assigned_to)
                                            <p class="mt-1 text-body-sm text-on-surface-variant">The assigned user can record progress here.</p>
                                        @endif
                                    </div>
                                @endforelse
                            </div>
                        </div>
                    </section>

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
                   
                </div>

                {{-- ============ Sidebar ============ --}}
                <aside class="flex flex-col gap-space-lg lg:sticky lg:top-6 lg:col-span-4">

                    <section class="task-view-enter p-space-lg {{ $card }}" style="animation-delay: 200ms">
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

                    {{-- Time Tracker & Timesheet --}}
                    <section class="task-view-enter p-space-lg {{ $card }}" style="animation-delay: 210ms"
                             x-data="taskTimer(@js($timerConfig))">
                        <div class="flex items-center justify-between gap-3">
                            <div class="flex items-center gap-2">
                                <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-secondary-fixed text-on-secondary-fixed shadow-sm">
                                    <span class="material-symbols-outlined text-[20px]" aria-hidden="true">schedule</span>
                                </span>
                                <div>
                                    <div class="flex items-center gap-2">
                                        <h2 class="text-title-md font-bold text-on-surface">Time tracking</h2>
                                        <span class="sr-only">Time Tracker &amp; Timesheet</span>
                                    </div>
                                    <p class="text-body-sm text-on-surface-variant">Start when you begin work, stop when you pause.</p>
                                </div>
                            </div>
                           
                        </div>

                        {{-- Tab Switcher: Timer vs History --}}
                        <div class="mt-space-md grid grid-cols-2 gap-1 rounded-xl bg-surface-container-low p-1">
                            <button type="button" @click="tab = 'timer'"
                                    :class="tab === 'timer' ? 'bg-surface shadow-sm text-primary font-semibold' : 'text-on-surface-variant hover:text-on-surface'"
                                    class="flex items-center justify-center gap-1.5 rounded-lg py-2 text-label-md transition-all {{ $focus }}">
                                <span class="material-symbols-outlined text-[18px]">timer</span>
                                <span>Timer</span>
                                <span x-show="running" x-cloak class="h-1.5 w-1.5 rounded-full bg-error animate-pulse"></span>
                            </button>
                            <button type="button" @click="tab = 'history'"
                                    :class="tab === 'history' ? 'bg-surface shadow-sm text-primary font-semibold' : 'text-on-surface-variant hover:text-on-surface'"
                                    class="flex items-center justify-center gap-1.5 rounded-lg py-2 text-label-md transition-all {{ $focus }}">
                                <span class="material-symbols-outlined text-[18px]">history</span>
                                <span>History</span>
                                <span class="rounded-full bg-surface-container-high px-1.5 py-0.2 text-[11px] font-semibold text-on-surface-variant" x-text="entries.length">0</span>
                            </button>
                        </div>

                        {{-- Alert Messages --}}
                        <div x-show="error" x-cloak role="alert" class="mt-space-sm flex items-center justify-between gap-2 rounded-xl bg-error-container/40 p-2.5 text-body-sm text-error">
                            <div class="flex items-center gap-1.5">
                                <span class="material-symbols-outlined text-[16px]" aria-hidden="true">error</span>
                                <span x-text="error"></span>
                            </div>
                            <button type="button" @click="error = null" class="text-error hover:opacity-80">
                                <span class="material-symbols-outlined text-[14px]">close</span>
                            </button>
                        </div>

                        <div x-show="success" x-cloak role="status" class="mt-space-sm flex items-center justify-between gap-2 rounded-xl bg-tertiary-fixed/30 p-2.5 text-body-sm text-tertiary">
                            <div class="flex items-center gap-1.5">
                                <span class="material-symbols-outlined text-[16px]" aria-hidden="true">check_circle</span>
                                <span x-text="success"></span>
                            </div>
                            <button type="button" @click="success = null" class="text-tertiary hover:opacity-80">
                                <span class="material-symbols-outlined text-[14px]">close</span>
                            </button>
                        </div>

                        {{-- ================= TAB 1: TIMER ================= --}}
                        <div x-show="tab === 'timer'" x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 translate-y-1" x-transition:enter-end="opacity-100 translate-y-0">
                            {{-- Small Cute Action Button: Green Start / Red Stop --}}
                            <div class="mt-space-md flex flex-col items-center justify-center py-1">
                                @if ($canControlTimer)
                                    {{-- Cute Green Start Button --}}
                                    <button type="button" @click="startTimer()" :disabled="loading"
                                            x-show="!running"
                                            x-transition:enter="transition ease-out duration-200"
                                            x-transition:enter-start="opacity-0 scale-90"
                                            x-transition:enter-end="opacity-100 scale-100"
                                            x-transition:leave="transition ease-in duration-150"
                                            x-transition:leave-start="opacity-100 scale-100"
                                            x-transition:leave-end="opacity-0 scale-90"
                                            class="group inline-flex items-center gap-2 rounded-full bg-emerald-500 px-5 py-2 text-label-md font-semibold text-white shadow-sm transition-all duration-200 hover:bg-emerald-600 hover:shadow-md hover:shadow-emerald-500/20 active:scale-95 disabled:opacity-60 cursor-pointer {{ $focus }}">
                                        <span class="material-symbols-outlined text-[18px] transition-transform duration-200 group-hover:scale-110">play_arrow</span>
                                        <span>Start </span>
                                    </button>

                                    {{--  Red Stop Button --}}
                                    <button type="button" @click="stopTimer()" :disabled="loading"
                                            x-show="running"
                                            x-cloak
                                            x-transition:enter="transition ease-out duration-200"
                                            x-transition:enter-start="opacity-0 scale-90"
                                            x-transition:enter-end="opacity-100 scale-100"
                                            x-transition:leave="transition ease-in duration-150"
                                            x-transition:leave-start="opacity-100 scale-100"
                                            x-transition:leave-end="opacity-0 scale-90"
                                            class="group relative inline-flex items-center gap-2 rounded-full bg-rose-500 px-5 py-2 text-label-md font-semibold text-white shadow-sm transition-all duration-200 hover:bg-rose-600 hover:shadow-md hover:shadow-rose-500/20 active:scale-95 disabled:opacity-60 cursor-pointer {{ $focus }}">
                                        <span class="relative flex h-2 w-2">
                                            <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-white opacity-75"></span>
                                      
                                        </span>
                                     
                                        <span>Stop </span>
                                    </button>

                                    <p x-show="running" x-cloak class="mt-2 text-[11px] font-medium text-on-surface-variant">
                                         active · Started by <span class="font-semibold text-on-surface" x-text="startedBy"></span>
                                    </p>
                                @else
                                    <div class="inline-flex items-center gap-1.5 rounded-full bg-surface-container-low px-3 py-1.5 text-body-xs text-on-surface-variant">
                                        <span class="material-symbols-outlined text-[14px]">lock</span>
                                        <span>Assignee or manager only</span>
                                    </div>
                                @endif
                            </div>

                            {{-- 3 Stat Boxes (Today / Productive / Idle) --}}
                            <div class="mt-space-md grid grid-cols-3 gap-2">
                                <div class="rounded-xl border border-surface-container-high bg-surface-container-low/70 p-2.5 text-center">
                                    <div class="flex items-center justify-center gap-1 text-on-surface-variant">
                                        <span class="material-symbols-outlined text-[15px]">schedule</span>
                                        <span class="text-[11px] font-medium uppercase tracking-wider">Today</span>
                                    </div>
                                    <p class="mt-1 text-label-md sm:text-title-sm font-bold tabular-nums text-on-surface" x-text="todayFormattedHMS">00:00:00</p>
                                </div>
                                <div class="rounded-xl border border-surface-container-high bg-surface-container-low/70 p-2.5 text-center">
                                    <div class="flex items-center justify-center gap-1 text-on-surface-variant">
                                        <span class="material-symbols-outlined text-[15px]">favorite_border</span>
                                        <span class="text-[11px] font-medium uppercase tracking-wider">Productive</span>
                                        <span class="sr-only">Total Time Spent</span>
                                    </div>
                                    <p class="mt-1 text-label-md sm:text-title-sm font-bold tabular-nums text-on-surface" x-text="productiveFormattedHMS">00:00:00</p>
                                </div>
                                <div class="rounded-xl border border-surface-container-high bg-surface-container-low/70 p-2.5 text-center">
                                    <div class="flex items-center justify-center gap-1 text-on-surface-variant">
                                        <span class="material-symbols-outlined text-[15px]">sentiment_satisfied</span>
                                        <span class="text-[11px] font-medium uppercase tracking-wider">Idle</span>
                                        <span class="sr-only">Timesheet Sessions</span>
                                    </div>
                                    <p class="mt-1 text-label-md sm:text-title-sm font-bold tabular-nums text-on-surface" x-text="idleDisplay">00:00:00</p>
                                </div>
                            </div>

                            {{-- Project Indicator Row --}}
                            <div class="mt-space-md flex items-center justify-between rounded-xl border border-outline/20 bg-surface-container-low px-3 py-2 text-body-sm text-on-surface">
                                <div class="flex items-center gap-2 min-w-0">
                                    <span class="material-symbols-outlined text-[18px] text-primary shrink-0" aria-hidden="true">layers</span>
                                    <span class="truncate font-medium">{{ $project->name }}</span>
                                </div>
                                <span class="text-[11px] font-semibold uppercase tracking-wider text-on-surface-variant shrink-0">Project</span>
                            </div>
                        </div>

                        {{-- ================= TAB 2: HISTORY (DATE-WISE TIMESHEET) ================= --}}
                        <div x-show="tab === 'history'" x-cloak x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 translate-y-1" x-transition:enter-end="opacity-100 translate-y-0">
                            <div class="mt-space-md">
                                {{-- Header & Calendar Button --}}
                                <div class="mb-2 flex items-center justify-between gap-2">
                                    <div>
                                        <span class="{{ $labelCls }}">Filter By Date</span>
                                        <span class="text-label-sm text-on-surface-variant ml-1" x-text="dateGroups.length ? ('(' + dateGroups.length + ' ' + (dateGroups.length === 1 ? 'day' : 'days') + ')') : ''"></span>
                                    </div>
                                    <button type="button" @click="showCalendar = !showCalendar"
                                            :class="showCalendar ? 'bg-primary text-on-primary shadow-sm ring-1 ring-primary' : 'bg-surface-container-low text-on-surface hover:bg-surface-container border border-surface-container-high'"
                                            class="inline-flex items-center gap-1.5 rounded-xl px-2.5 py-1.5 text-label-sm font-semibold transition-all cursor-pointer {{ $focus }}"
                                            title="Open full calendar for older dates">
                                        <span class="material-symbols-outlined text-[16px]">calendar_month</span>
                                        <span>Calendar</span>
                                        <span class="material-symbols-outlined text-[14px] transition-transform duration-200" :class="showCalendar ? 'rotate-180' : ''">expand_more</span>
                                    </button>
                                </div>

                                {{-- Calendar Popover for Selecting Older Dates --}}
                                <div x-show="showCalendar" x-cloak x-collapse
                                     class="mb-space-md rounded-2xl border border-outline/20 bg-surface-container-lowest p-3 shadow-lg">
                                    <div class="flex items-center justify-between pb-2 border-b border-surface-container">
                                        <button type="button" @click="calPrevMonth()" class="rounded-lg p-1 text-on-surface-variant hover:bg-surface-container-high transition-colors" title="Previous month">
                                            <span class="material-symbols-outlined text-[18px]">chevron_left</span>
                                        </button>
                                        <span class="text-title-sm font-bold text-on-surface" x-text="calMonthYearTitle"></span>
                                        <button type="button" @click="calNextMonth()" class="rounded-lg p-1 text-on-surface-variant hover:bg-surface-container-high transition-colors" title="Next month">
                                            <span class="material-symbols-outlined text-[18px]">chevron_right</span>
                                        </button>
                                    </div>

                                    {{-- Day-of-week headers --}}
                                    <div class="mt-2 grid grid-cols-7 gap-1 text-center text-[10px] font-bold text-on-surface-variant uppercase">
                                        <span>Su</span><span>Mo</span><span>Tu</span><span>We</span><span>Th</span><span>Fr</span><span>Sa</span>
                                    </div>

                                    {{-- Day cells grid --}}
                                    <div class="mt-1 grid grid-cols-7 gap-1">
                                        <template x-for="day in calGrid" :key="day.key">
                                            <div class="h-8">
                                                <template x-if="day.blank">
                                                    <span class="block h-full w-full"></span>
                                                </template>
                                                <template x-if="!day.blank">
                                                    <button type="button" @click="selectCalDate(day)"
                                                            :class="{
                                                                'bg-primary text-on-primary font-bold shadow-sm': day.isSelected,
                                                                'border border-primary font-semibold text-primary': day.isToday && !day.isSelected,
                                                                'hover:bg-surface-container-high text-on-surface': !day.isSelected && !day.isToday,
                                                                'font-semibold': day.hasSessions
                                                            }"
                                                            class="relative flex h-full w-full flex-col items-center justify-center rounded-lg text-label-xs transition-colors cursor-pointer"
                                                            :title="day.dateKey + (day.hasSessions ? ' (' + day.sessionCount + ' sessions)' : '')">
                                                        <span x-text="day.dayNumber"></span>
                                                        <span x-show="day.hasSessions"
                                                              :class="day.isSelected ? 'bg-on-primary' : 'bg-primary'"
                                                              class="absolute bottom-1 h-1 w-1 rounded-full"></span>
                                                    </button>
                                                </template>
                                            </div>
                                        </template>
                                    </div>

                                    {{-- Calendar Quick Jump & Footer Actions --}}
                                    <div class="mt-3 flex items-center justify-between border-t border-surface-container pt-2">
                                        <div class="flex items-center gap-1.5">
                                            <button type="button" @click="selectedDate = 'all'; showCalendar = false"
                                                    class="rounded-lg bg-surface-container-high px-2 py-1 text-[11px] font-medium text-on-surface hover:bg-surface-container-highest transition-colors">
                                                Show All
                                            </button>
                                            <button type="button" @click="selectedDate = todayKey; showCalendar = false"
                                                    class="rounded-lg bg-primary-container px-2 py-1 text-[11px] font-medium text-on-primary hover:bg-primary transition-colors">
                                                Today
                                            </button>
                                        </div>
                                        <div class="flex items-center gap-1">
                                            <label for="cal-quick-jump" class="sr-only">Jump to Date</label>
                                            <input id="cal-quick-jump" type="date" x-model="calJumpDate" @change="onCalJump()"
                                                   class="rounded-lg border-0 bg-surface-container-low px-2 py-1 text-[11px] text-on-surface ring-1 ring-inset ring-outline/30 focus:ring-2 focus:ring-primary {{ $focus }}"
                                                   title="Jump directly to any date">
                                        </div>
                                    </div>
                                </div>

                                {{-- Active Session Notice in History Tab (when running) --}}
                                <div x-show="running && selectedDate !== 'all' && selectedDate !== todayKey" x-cloak
                                     class="mb-space-sm flex items-center justify-between rounded-xl bg-secondary-fixed/40 px-3 py-2 text-body-xs text-on-secondary-fixed">
                                    <div class="flex items-center gap-1.5">
                                        <span class="h-2 w-2 rounded-full bg-error animate-ping"></span>
                                        <span>Live timer is active for today.</span>
                                    </div>
                                    <button type="button" @click="selectedDate = todayKey" class="font-bold underline hover:opacity-80">
                                        View Today's Session
                                    </button>
                                </div>

                                {{-- Round click date-wise selector ribbon --}}
                                <div class="flex items-center gap-2 overflow-x-auto pb-2 pt-1 no-scrollbar scroll-smooth">
                                    {{-- ALL round button --}}
                                    <button type="button" @click="selectedDate = 'all'"
                                            :class="selectedDate === 'all'
                                                ? 'bg-primary text-on-primary ring-2 ring-primary ring-offset-2 ring-offset-surface shadow-sm'
                                                : 'bg-surface-container-low text-on-surface hover:bg-surface-container border border-surface-container-high'"
                                            class="flex h-16 w-14 shrink-0 flex-col items-center justify-center rounded-2xl transition-all cursor-pointer {{ $focus }}"
                                            title="Show all recorded sessions">
                                        <span class="material-symbols-outlined text-[18px]">calendar_month</span>
                                        <span class="mt-0.5 text-[10px] font-extrabold uppercase tracking-tight">ALL</span>
                                        <span class="text-[10px] opacity-80" x-text="entries.length"></span>
                                    </button>

                                    {{-- Circular round date buttons for recorded dates --}}
                                    <template x-for="g in dateGroups" :key="g.key">
                                        <button type="button" @click="selectedDate = g.key"
                                                :class="selectedDate === g.key
                                                    ? 'bg-primary text-on-primary ring-2 ring-primary ring-offset-2 ring-offset-surface shadow-sm'
                                                    : 'bg-surface-container-low text-on-surface hover:bg-surface-container border border-surface-container-high'"
                                                class="relative flex h-16 w-14 shrink-0 flex-col items-center justify-center rounded-2xl transition-all cursor-pointer {{ $focus }}"
                                                :title="g.fullLabel">
                                            <span x-show="g.hasActive" class="absolute -right-0.5 -top-0.5 flex h-3 w-3" title="Session currently running">
                                                <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-error opacity-75"></span>
                                                <span class="relative inline-flex h-3 w-3 rounded-full bg-error"></span>
                                            </span>

                                            <span class="text-[10px] font-bold uppercase tracking-wider"
                                                  :class="selectedDate === g.key ? 'text-on-primary/90' : 'text-on-surface-variant'"
                                                  x-text="g.isToday ? 'TODAY' : g.dayName"></span>
                                            <span class="text-title-md font-extrabold leading-tight tabular-nums" x-text="g.dayNumber"></span>
                                            <span class="text-[10px] uppercase tracking-wide opacity-80" x-text="g.monthName"></span>
                                        </button>
                                    </template>

                                    {{-- If custom older date selected from calendar that has no entries, show it in ribbon --}}
                                    <template x-if="selectedDate !== 'all' && !dateGroups.some(g => g.key === selectedDate)">
                                        <button type="button"
                                                class="relative flex h-16 w-14 shrink-0 flex-col items-center justify-center rounded-2xl bg-primary text-on-primary ring-2 ring-primary ring-offset-2 ring-offset-surface shadow-sm"
                                                :title="selectedDateLabel">
                                            <span class="text-[9px] font-bold uppercase tracking-wider opacity-90">CUSTOM</span>
                                            <span class="text-label-sm font-extrabold leading-tight" x-text="selectedDate.split('-')[2] || '?'"></span>
                                            <span class="text-[9px] uppercase tracking-wide opacity-80" x-text="selectedDate.split('-')[1] || ''"></span>
                                        </button>
                                    </template>
                                </div>

                                {{-- Selected Date Summary Bar --}}
                                <div class="mt-space-sm flex items-center justify-between rounded-xl bg-surface-container-low/70 px-3 py-2 text-body-sm">
                                    <div class="flex items-center gap-1.5 min-w-0">
                                        <span class="material-symbols-outlined text-[18px] text-primary shrink-0">event</span>
                                        <span class="font-semibold text-on-surface truncate" x-text="selectedDateSummary.title"></span>
                                    </div>
                                    <div class="flex items-center gap-1.5 shrink-0">
                                        <span class="rounded-md bg-surface-container-high px-2 py-0.5 text-label-sm font-bold tabular-nums text-on-surface"
                                              x-text="selectedDateSummary.totalFormatted"></span>
                                        <span class="text-body-xs text-on-surface-variant" x-text="'(' + selectedDateSummary.count + ')'"></span>
                                    </div>
                                </div>

                                {{-- Date-wise Sessions List or Clear Empty State --}}
                                <div class="mt-space-sm">
                                    {{-- Clear Empty State When Selected Date Has No Sessions --}}
                                    <template x-if="!visibleEntries.length">
                                        <div class="rounded-2xl border border-dashed border-outline/30 bg-surface-container-low/40 px-4 py-8 text-center">
                                            <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-surface-container-high text-on-surface-variant">
                                                <span class="material-symbols-outlined text-[26px]">event_busy</span>
                                            </div>
                                            <h4 class="mt-3 text-title-sm font-semibold text-on-surface">No sessions on <span x-text="selectedDateLabel"></span></h4>
                                            <p class="mt-1 text-body-sm text-on-surface-variant">No time tracking sessions were recorded for this date.</p>
                                            <div class="mt-space-md flex flex-wrap items-center justify-center gap-2">
                                                <button type="button" @click="selectedDate = 'all'"
                                                        class="inline-flex items-center gap-1 rounded-xl bg-surface-container-high px-3 py-1.5 text-label-sm font-semibold text-on-surface hover:bg-surface-container-highest transition-colors {{ $focus }}">
                                                    <span class="material-symbols-outlined text-[16px]">history</span>
                                                    <span>View All History</span>
                                                </button>
                                                <button type="button" @click="selectedDate = todayKey" x-show="selectedDate !== todayKey"
                                                        class="inline-flex items-center gap-1 rounded-xl bg-primary-container px-3 py-1.5 text-label-sm font-semibold text-on-primary hover:bg-primary transition-colors {{ $focus }}">
                                                    <span class="material-symbols-outlined text-[16px]">today</span>
                                                    <span>Jump to Today</span>
                                                </button>
                                            </div>
                                        </div>
                                    </template>

                                    {{-- Expandable Sessions List --}}
                                    <ul class="max-h-80 space-y-2 overflow-y-auto pr-1" x-show="visibleEntries.length">
                                        <template x-for="e in visibleEntries" :key="e.id">
                                            <li class="group rounded-xl border border-surface-container-high bg-surface-container-low transition-all hover:bg-surface-container overflow-hidden">
                                                {{-- Click session row to toggle details --}}
                                                <div @click="expandedSession = (expandedSession === e.id ? null : e.id)"
                                                     class="flex items-center justify-between gap-2 p-3 cursor-pointer select-none">
                                                    <div class="min-w-0 flex-1">
                                                        <div class="flex items-center gap-1.5">
                                                            <span class="font-semibold text-on-surface truncate text-body-sm" x-text="e.started_by"></span>
                                                            <span x-show="e.running" class="inline-flex items-center gap-1 rounded-full bg-error-container px-1.5 py-0.2 text-[10px] font-bold text-on-error-container">
                                                                <span class="h-1.5 w-1.5 rounded-full bg-error animate-pulse"></span> ACTIVE
                                                            </span>
                                                        </div>
                                                        <p class="text-body-xs text-on-surface-variant"
                                                           x-text="e.time_range_label + ' · ' + (selectedDate === 'all' ? e.date_label : (e.started_label ? e.started_label.split('·')[0].trim() : ''))"></p>
                                                    </div>
                                                    <div class="flex items-center gap-2 shrink-0">
                                                        <span class="rounded-lg bg-surface-container-highest px-2 py-0.5 font-semibold tabular-nums text-on-surface text-label-sm"
                                                              x-text="e.running ? ('Active (' + display + ')') : e.duration_formatted"></span>
                                                        <span class="material-symbols-outlined text-[18px] text-on-surface-variant transition-transform duration-200"
                                                              :class="expandedSession === e.id ? 'rotate-180' : ''">expand_more</span>
                                                    </div>
                                                </div>

                                                {{-- Expanded Details Drawer --}}
                                                <div x-show="expandedSession === e.id" x-cloak x-collapse
                                                     class="border-t border-surface-container bg-surface-container-lowest p-3 text-body-xs">
                                                    <div class="grid grid-cols-2 gap-2 text-on-surface-variant">
                                                        <div>
                                                            <span class="block text-[10px] font-semibold uppercase tracking-wider text-on-surface-variant/70">User</span>
                                                            <span class="font-medium text-on-surface" x-text="e.started_by"></span>
                                                        </div>
                                                        <div>
                                                            <span class="block text-[10px] font-semibold uppercase tracking-wider text-on-surface-variant/70">Duration</span>
                                                            <span class="font-bold tabular-nums text-on-surface" x-text="e.running ? ('Running (' + display + ')') : e.duration_formatted"></span>
                                                        </div>
                                                        <div>
                                                            <span class="block text-[10px] font-semibold uppercase tracking-wider text-on-surface-variant/70">Start Time</span>
                                                            <span class="text-on-surface" x-text="e.started_label || 'Unknown'"></span>
                                                        </div>
                                                        <div>
                                                            <span class="block text-[10px] font-semibold uppercase tracking-wider text-on-surface-variant/70">End Time</span>
                                                            <span class="text-on-surface" x-text="e.running ? 'In progress...' : (e.stopped_label || 'Completed')"></span>
                                                        </div>
                                                        <template x-if="e.stopped_by && e.stopped_by !== e.started_by">
                                                            <div class="col-span-2">
                                                                <span class="block text-[10px] font-semibold uppercase tracking-wider text-on-surface-variant/70">Stopped By</span>
                                                                <span class="text-on-surface" x-text="e.stopped_by"></span>
                                                            </div>
                                                        </template>
                                                    </div>

                                                    <template x-if="e.can_delete && !e.running">
                                                        <div class="mt-3 flex justify-end border-t border-surface-container pt-2">
                                                            <button type="button" @click.stop="deleteEntry(e)"
                                                                    class="inline-flex items-center gap-1 rounded-lg px-2.5 py-1 text-label-xs font-semibold text-error hover:bg-error-container/40 transition-colors {{ $focus }}">
                                                                <span class="material-symbols-outlined text-[15px]">delete</span>
                                                                <span>Delete Session</span>
                                                            </button>
                                                        </div>
                                                    </template>
                                                </div>
                                            </li>
                                        </template>
                                    </ul>
                                </div>
                            </div>
                        </div>
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

                                            <div class="grid grid-cols-1 gap-1 rounded-xl bg-surface-container-low p-1 sm:grid-cols-3" x-data="{ status: '{{ $task->status }}' }">
                                                @foreach ([
                                                    ['pending', 'Pending', 'radio_button_unchecked'],
                                                    ['in_progress', 'In Progress', 'timelapse'],
                                                    ['completed', 'Completed', 'check_circle'],
                                                ] as [$value, $optLabel, $optIcon])
                                                    <label class="cursor-pointer">
                                                        <input type="radio" name="status" value="{{ $value }}" x-model="status" required class="peer sr-only">
                                                        <span class="flex items-center justify-center gap-1.5 rounded-lg py-2.5 text-label-md transition-all peer-focus-visible:ring-2 peer-focus-visible:ring-primary-container"
                                                              :class="status === '{{ $value }}' ? (
                                                                  '{{ $value }}' === 'in_progress' ? 'bg-secondary-fixed text-on-secondary-fixed shadow-sm' :
                                                                  ('{{ $value }}' === 'completed' ? 'bg-tertiary-fixed text-on-tertiary-fixed shadow-sm' : 'bg-surface-container-lowest text-on-surface shadow-sm')
                                                              ) : 'text-on-surface-variant hover:text-on-surface'">
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
                                                class="mt-space-sm flex w-full items-center justify-center gap-2 rounded-xl bg-primary-container px-space-md py-2.5 text-label-md text-on-primary transition-colors hover:bg-primary hover:shadow-md focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-container focus-visible:ring-offset-2">
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
                </aside>
            </div>

        </div>
    </div>
    
    <script>
        // Alpine component for the Time Tracker & Timesheet card
        window.taskTimer = function (cfg) {
            return {
                tab: 'timer',           // 'timer' or 'history'
                selectedDate: 'all',    // 'all' or 'YYYY-MM-DD'
                expandedSession: null,  // ID of currently expanded session card
                showCalendar: false,    // toggles full month calendar
                calYear: new Date().getFullYear(),
                calMonth: new Date().getMonth(),
                calJumpDate: '',
                running: false,
                startedAt: null,
                startedBy: null,
                totalSeconds: 0,
                totalFormatted: '0m',
                byUser: [],
                entries: [],
                offset: 0,              // server time minus device time (ms)
                now: Date.now(),
                loading: false,
                error: null,
                success: null,
                _tick: null,

                init() {
                    this.apply(cfg.state);
                    this._tick = setInterval(() => { this.now = Date.now() + this.offset; }, 250);
                },
                destroy() { clearInterval(this._tick); },

                apply(state) {
                    if (!state) return;
                    this.running = !!state.running;
                    this.startedAt = state.started_at ? Date.parse(state.started_at) : null;
                    this.startedBy = state.started_by || null;
                    this.totalSeconds = state.total_seconds || 0;
                    this.totalFormatted = state.total_formatted || this.fmtDuration(this.totalSeconds);
                    this.byUser = state.by_user || [];
                    this.entries = state.entries || [];
                    this.offset = state.server_now ? state.server_now - Date.now() : 0;
                    this.now = Date.now() + this.offset;
                },

                get elapsed() {
                    if (!this.running || !this.startedAt) return 0;
                    return Math.max(0, Math.floor((this.now - this.startedAt) / 1000));
                },
                get display() { return this.fmtHMS(this.elapsed); },

                get todayKey() {
                    var d = new Date(this.now);
                    return this.fmtDateKey(d);
                },

                fmtDateKey(d) {
                    var y = d.getFullYear();
                    var m = String(d.getMonth() + 1).padStart(2, '0');
                    var day = String(d.getDate()).padStart(2, '0');
                    return y + '-' + m + '-' + day;
                },

                get todaySeconds() {
                    var today = this.todayKey;
                    var sum = 0;
                    for (var i = 0; i < this.entries.length; i++) {
                        var e = this.entries[i];
                        if (e.date_key === today) {
                            sum += Number(e.duration_seconds || 0);
                        }
                    }
                    if (this.running && this.startedAt) {
                        var startedDateKey = this.fmtDateKey(new Date(this.startedAt));
                        if (startedDateKey === today) {
                            sum += this.elapsed;
                        }
                    }
                    return sum;
                },

                get todayFormattedHMS() {
                    return this.fmtHMS(this.todaySeconds);
                },

                get productiveSeconds() {
                    var sum = 0;
                    for (var i = 0; i < this.entries.length; i++) {
                        sum += Number(this.entries[i].duration_seconds || 0);
                    }
                    if (this.running) {
                        sum += this.elapsed;
                    }
                    return sum;
                },

                get productiveFormattedHMS() {
                    return this.fmtHMS(this.productiveSeconds);
                },

                get idleDisplay() {
                    return '00:00:00';
                },

                // Calendar navigation & grid helpers
                calPrevMonth() {
                    if (this.calMonth === 0) {
                        this.calMonth = 11;
                        this.calYear--;
                    } else {
                        this.calMonth--;
                    }
                },

                calNextMonth() {
                    if (this.calMonth === 11) {
                        this.calMonth = 0;
                        this.calYear++;
                    } else {
                        this.calMonth++;
                    }
                },

                get calMonthYearTitle() {
                    var dt = new Date(this.calYear, this.calMonth, 1);
                    return dt.toLocaleString('default', { month: 'long', year: 'numeric' });
                },

                get calGrid() {
                    var firstDay = new Date(this.calYear, this.calMonth, 1).getDay();
                    var daysInMonth = new Date(this.calYear, this.calMonth + 1, 0).getDate();
                    var days = [];
                    for (var i = 0; i < firstDay; i++) {
                        days.push({ blank: true, key: 'blank-' + i });
                    }
                    var todayStr = this.todayKey;
                    for (var d = 1; d <= daysInMonth; d++) {
                        var mStr = String(this.calMonth + 1).padStart(2, '0');
                        var dStr = String(d).padStart(2, '0');
                        var dateKey = this.calYear + '-' + mStr + '-' + dStr;
                        var count = 0;
                        for (var k = 0; k < this.entries.length; k++) {
                            if (this.entries[k].date_key === dateKey) count++;
                        }
                        days.push({
                            blank: false,
                            key: dateKey,
                            dayNumber: d,
                            dateKey: dateKey,
                            hasSessions: count > 0,
                            sessionCount: count,
                            isToday: dateKey === todayStr,
                            isSelected: dateKey === this.selectedDate
                        });
                    }
                    return days;
                },

                selectCalDate(day) {
                    this.selectedDate = day.dateKey;
                    this.showCalendar = false;
                },

                onCalJump() {
                    if (this.calJumpDate) {
                        this.selectedDate = this.calJumpDate;
                        this.showCalendar = false;
                    }
                },

                // Computed date-wise groupings for the round click ribbon
                get dateGroups() {
                    var map = new Map();
                    for (var i = 0; i < this.entries.length; i++) {
                        var e = this.entries[i];
                        var key = e.date_key || 'unknown';
                        if (!map.has(key)) {
                            map.set(key, {
                                key: key,
                                label: e.date_label || key,
                                fullLabel: e.date_full || e.date_label || key,
                                dayNumber: e.day_number || '',
                                dayName: e.day_name || '',
                                monthName: e.month_name || '',
                                isToday: !!e.is_today,
                                hasActive: false,
                                totalSeconds: 0,
                                count: 0,
                            });
                        }
                        var g = map.get(key);
                        g.count++;
                        g.totalSeconds += Number(e.duration_seconds || 0);
                        if (e.running) {
                            g.hasActive = true;
                        }
                    }
                    var list = Array.from(map.values());
                    for (var j = 0; j < list.length; j++) {
                        list[j].totalFormatted = this.fmtDuration(list[j].totalSeconds);
                    }
                    return list;
                },

                // Filtered timesheet entries for the chosen date (or all)
                get visibleEntries() {
                    if (this.selectedDate === 'all') {
                        return this.entries;
                    }
                    return this.entries.filter(e => e.date_key === this.selectedDate);
                },

                get selectedDateLabel() {
                    if (this.selectedDate === 'all') return 'All Recorded History';
                    var g = this.dateGroups.find(item => item.key === this.selectedDate);
                    if (g) return g.fullLabel + (g.isToday ? ' (Today)' : '');
                    var parts = this.selectedDate.split('-');
                    if (parts.length === 3) {
                        var d = new Date(Number(parts[0]), Number(parts[1]) - 1, Number(parts[2]));
                        if (!isNaN(d.getTime())) {
                            return d.toLocaleDateString(undefined, { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' });
                        }
                    }
                    return this.selectedDate;
                },

                // Summary stats for the currently selected date
                get selectedDateSummary() {
                    if (this.selectedDate === 'all') {
                        return {
                            title: 'All Recorded History',
                            count: this.entries.length,
                            totalFormatted: this.totalFormatted
                        };
                    }
                    var g = this.dateGroups.find(item => item.key === this.selectedDate);
                    if (!g) {
                        return {
                            title: this.selectedDateLabel,
                            count: 0,
                            totalFormatted: '0m'
                        };
                    }
                    return {
                        title: g.fullLabel + (g.isToday ? ' (Today)' : ''),
                        count: g.count,
                        totalFormatted: g.totalFormatted
                    };
                },

                fmtHMS(sec) {
                    sec = Math.max(0, Math.floor(Number(sec) || 0));
                    var h = Math.floor(sec / 3600), m = Math.floor((sec % 3600) / 60), s = sec % 60;
                    var p = function (n) { return String(n).padStart(2, '0'); };
                    return p(h) + ':' + p(m) + ':' + p(s);
                },

                fmtDuration(sec) {
                    sec = Math.max(0, Math.floor(Number(sec) || 0));
                    if (sec === 0) return '0m';
                    var h = Math.floor(sec / 3600), m = Math.floor((sec % 3600) / 60);
                    var parts = [];
                    if (h > 0) parts.push(h + 'h');
                    if (m > 0) parts.push(m + 'm');
                    return parts.length ? parts.join(' ') : sec + 's';
                },

                startTimer() { return this.send(cfg.startUrl); },
                stopTimer() { return this.send(cfg.stopUrl); },

                async deleteEntry(entry) {
                    if (!confirm('Are you sure you want to delete this timer session?')) return;
                    await this.send(cfg.deleteUrl + '/' + entry.id, {}, 'DELETE');
                },

                async send(url, body = {}, method = 'POST') {
                    if (this.loading) return false;
                    this.loading = true;
                    this.error = null;
                    this.success = null;
                    try {
                        var opts = {
                            method: method,
                            credentials: 'same-origin',
                            headers: {
                                'Accept': 'application/json',
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': cfg.csrf,
                                'X-Requested-With': 'XMLHttpRequest'
                            }
                        };
                        if (method !== 'GET' && method !== 'HEAD') {
                            opts.body = JSON.stringify(body);
                        }
                        var res = await fetch(url, opts);
                        var data = {};
                        try { data = await res.json(); } catch (e) {}
                        if (data.state) this.apply(data.state);
                        if (!res.ok) {
                            this.error = data.message || 'Something went wrong. Please try again.';
                            return false;
                        }
                        if (data.message) {
                            this.success = data.message;
                            setTimeout(() => { this.success = null; }, 3000);
                        }
                        return true;
                    } catch (e) {
                        this.error = 'Network error. Please try again.';
                        return false;
                    } finally {
                        this.loading = false;
                    }
                }
            };
        };
    </script>
</x-app-layout>