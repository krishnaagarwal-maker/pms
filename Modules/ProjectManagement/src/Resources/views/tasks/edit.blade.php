{{-- View: tasks.edit (route: projects.tasks.edit) --}}

{{--
|--------------------------------------------------------------------------
| RBAC NOTES (dynamic roles & permissions — not wired up yet)
|--------------------------------------------------------------------------
| Planned: default roles "manager" and "team member", plus custom roles
| with custom permissions.
|
| ASSIGNEE: kept ACTIVE but safe. The Assignment section only renders if the
| controller passes $users (@isset), and nothing breaks if it doesn't.
|
| ROLES / PERMISSIONS: COMMENTED OUT below (search for RBAC-ROLES) because
| they need roles/permissions tables that don't exist yet.
|
| When RBAC exists:
|   1. RBAC-ROLES  : enable the role + effective-permission section
|   2. RBAC-SAVE   : wrap Save buttons in @can('update', $task)
|   3. RBAC-DELETE : wrap the Delete action in @can('delete', $task)
|      (an undefined ability returns false, so do NOT add @can before the
|      policy/permission exists, or those parts will disappear)
|   4. Controller edit():   pass 'roles' => Role::with('permissions')->get(),
|                           'permissions' => Permission::where('scope', 'task')->get()
|   5. Controller update(): $this->authorize('update', $task);
|        'assigned_to' => ['nullable', 'exists:users,id'],
|        'role_id'     => ['nullable', 'exists:roles,id'],
|        'permissions' => ['nullable', 'array'],
|        'permissions.*' => ['exists:permissions,id'],
|
| ACTIVITY: Recent Activity works without $activities (fallback uses the
| task timestamps). For real history pass $activities from the controller,
| e.g. spatie/laravel-activitylog with logOnlyDirty():
|   'activities' => $task->activities()->with('causer')->latest()->take(15)->get()
--}}

@php
    $ticket = 'TSK-' . $project->id . '-' . str_pad($task->id, 2, '0', STR_PAD_LEFT);

    // Recent Activity helpers
    $eventStyles = [
        'created' => ['add_circle', 'bg-tertiary-fixed text-on-tertiary-fixed'],
        'updated' => ['edit',       'bg-primary-fixed text-on-primary-fixed'],
        'deleted' => ['delete',     'bg-error-container text-on-error-container'],
    ];
    $initials = fn ($name) => collect(preg_split('/\s+/', trim($name ?? '')))
        ->filter()
        ->map(fn ($w) => mb_substr($w, 0, 1))
        ->take(2)
        ->implode('');
@endphp

<x-app-layout>
    <div class="relative w-full overflow-hidden font-inter"
         @keydown.window.ctrl.s.prevent="$refs.form.requestSubmit()"
         @keydown.window.meta.s.prevent="$refs.form.requestSubmit()"
         x-data="{
             title: @js(old('title', $task->title)),
             description: @js(old('description', $task->description ?? '')),
             dueDate: @js(old('due_date', $task->due_date?->format('Y-m-d') ?? '')),
             priority: @js(old('priority', $task->priority)),
             status: @js(old('status', $task->status)),
             assignedTo: @js((string) old('assigned_to', $task->assigned_to ?? '')),
             users: @js(($users ?? collect())->mapWithKeys(fn ($u) => [(string) $u->id => ['name' => $u->name, 'email' => $u->email]])),

             {{-- Values as stored in the database, used to diff against unsaved edits --}}
             initial: @js([
                 'title'       => $task->title,
                 'description' => $task->description ?? '',
                 'due_date'    => $task->due_date?->format('Y-m-d') ?? '',
                 'priority'    => $task->priority,
                 'status'      => $task->status,
                 'assigned_to' => (string) ($task->assigned_to ?? ''),
             ]),
             fieldLabels: { title: 'Title', description: 'Description', due_date: 'Due date', priority: 'Priority', status: 'Status', assigned_to: 'Assignee' },

             priorityActive: {
                 low:    'bg-surface-container-lowest text-on-surface shadow-sm',
                 medium: 'bg-secondary-fixed text-on-secondary-fixed shadow-sm',
                 high:   'bg-error-container text-on-error-container shadow-sm'
             },
             priorityPill: {
                 low:    { pill: 'bg-surface-container-high text-on-surface-variant', dot: 'bg-outline' },
                 medium: { pill: 'bg-secondary-fixed text-on-secondary-fixed',        dot: 'bg-secondary' },
                 high:   { pill: 'bg-error-container text-on-error-container',        dot: 'bg-error' }
             },
             statusPill: {
                 pending:     { label: 'Pending',     pill: 'bg-surface-container text-on-surface-variant', dot: 'bg-outline' },
                 in_progress: { label: 'In Progress', pill: 'bg-secondary-fixed text-on-secondary-fixed',   dot: 'bg-secondary' },
                 completed:   { label: 'Completed',   pill: 'bg-tertiary-fixed text-on-tertiary-fixed',     dot: 'bg-tertiary' }
             },

             iso(d) { return d.toLocaleDateString('en-CA'); },
             setDate(days) {
                 const d = new Date();
                 d.setDate(d.getDate() + days);
                 this.dueDate = this.iso(d);
             },
             today() { const t = new Date(); t.setHours(0, 0, 0, 0); return t; },
             get dueLabel() {
                 return this.dueDate
                     ? new Date(this.dueDate + 'T00:00:00').toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' })
                     : 'No due date';
             },
             get daysLeft() {
                 if (!this.dueDate) return null;
                 return Math.round((new Date(this.dueDate + 'T00:00:00') - this.today()) / 86400000);
             },
             get dueNote() {
                 const d = this.daysLeft;
                 if (d === null || this.status === 'completed') return null;
                 if (d < 0) return 'Overdue by ' + Math.abs(d) + (Math.abs(d) === 1 ? ' day' : ' days');
                 if (d === 0) return 'Due today';
                 return d + (d === 1 ? ' day left' : ' days left');
             },
             assignee() { return this.users[this.assignedTo] || null; },
             initials(name) {
                 return (name || '').split(/\s+/).filter(Boolean).map(w => w[0]).slice(0, 2).join('').toUpperCase();
             },

             fmt(k, v) {
                 if (k === 'status') return this.statusPill[v]?.label ?? v;
                 if (k === 'assigned_to') return this.users[v]?.name ?? 'Unassigned';
                 if (k === 'priority') return v ? v.charAt(0).toUpperCase() + v.slice(1) : '—';
                 if (k === 'due_date') return v ? new Date(v + 'T00:00:00').toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }) : 'None';
                 return v || '—';
             },
             get changes() {
                 const cur = {
                     title: this.title,
                     description: this.description,
                     due_date: this.dueDate,
                     priority: this.priority,
                     status: this.status,
                     assigned_to: this.assignedTo
                 };
                 return Object.keys(cur)
                     .filter(k => cur[k] !== this.initial[k])
                     .map(k => ({ key: k, label: this.fieldLabels[k], from: this.fmt(k, this.initial[k]), to: this.fmt(k, cur[k]) }));
             }
         }">

        {{-- Soft background glow --}}
        <div class="pointer-events-none absolute -left-20 -top-32 h-96 w-96 rounded-full bg-primary/5 blur-3xl"></div>
        <div class="pointer-events-none absolute right-10 top-20 h-80 w-80 rounded-full bg-tertiary-fixed/15 blur-3xl"></div>

        <div class="relative mx-auto max-w-7xl px-6 py-space-xl lg:px-12">

            {{-- Top bar --}}
            <div class="mb-space-lg flex flex-col justify-between gap-space-md md:flex-row md:items-end">
                <div class="flex min-w-0 flex-col gap-space-xs">
                    <nav class="flex flex-wrap items-center gap-2 text-label-md text-on-surface-variant" aria-label="Breadcrumb">
                        <a href="{{ route('projects.index') }}" class="flex items-center gap-1 transition-colors hover:text-primary">
                            <span class="material-symbols-outlined text-[16px]" aria-hidden="true">folder_open</span>
                            Projects
                        </a>
                        <span class="material-symbols-outlined text-[14px]" aria-hidden="true">chevron_right</span>
                        <a href="{{ route('projects.show', $project) }}" class="max-w-[14rem] truncate transition-colors hover:text-primary">{{ $project->name }}</a>
                        <span class="material-symbols-outlined text-[14px]" aria-hidden="true">chevron_right</span>
                        <span class="text-title-sm text-on-surface">Edit Task</span>
                    </nav>

                    <h1 class="mt-1 text-headline-lg tracking-tight text-on-surface">Edit Task</h1>

                    <div class="flex flex-wrap items-center gap-2">
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-surface-container-high px-2.5 py-0.5 font-code-sm text-[11px] text-on-surface-variant">
                            <span class="h-1.5 w-1.5 rounded-full bg-primary-container" aria-hidden="true"></span>
                            PRJ-{{ str_pad($project->id, 3, '0', STR_PAD_LEFT) }} · {{ Str::limit($project->name, 32) }}
                        </span>
                        <span class="rounded-full bg-primary-fixed px-2.5 py-0.5 font-code-sm text-[11px] font-semibold text-on-primary-fixed">{{ $ticket }}</span>
                    </div>
                </div>

                <div class="flex shrink-0 items-center gap-space-sm">
                    <a href="{{ route('projects.show', $project) }}"
                       class="rounded-xl bg-surface-container-lowest px-space-md py-2 text-label-md text-on-surface shadow-sm transition-all hover:bg-surface-container-low">
                        Cancel
                    </a>
                    {{-- RBAC-SAVE: wrap in @can('update', $task) … @endcan once policies exist --}}
                    <button type="submit" form="edit-task-form"
                            class="flex items-center gap-2 rounded-xl bg-primary-container px-space-lg py-2 text-label-md text-on-primary shadow-md transition-all hover:bg-primary hover:shadow-lg focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-container focus-visible:ring-offset-2">
                        <span class="material-symbols-outlined text-[18px]" aria-hidden="true">check</span>
                        Save Changes
                        <kbd class="ml-1 hidden rounded bg-primary/30 px-1.5 py-0.5 font-code-sm text-[10px] text-on-primary-container sm:inline">Ctrl S</kbd>
                    </button>
                </div>
            </div>

            <div class="grid grid-cols-1 items-start gap-gutter lg:grid-cols-12">

                {{-- LEFT: form --}}
                <form id="edit-task-form" x-ref="form"
                      action="{{ route('projects.tasks.update', [$project, $task]) }}" method="POST"
                      class="flex flex-col gap-space-lg lg:col-span-8">
                    @csrf
                    @method('PUT')

                    {{-- Task overview --}}
                    <div class="rounded-xl bg-surface-container-lowest p-space-lg shadow-sm">
                        <div class="mb-space-md flex items-center justify-between">
                            <div class="flex items-center gap-space-xs">
                                <span class="material-symbols-outlined text-[22px] text-primary" aria-hidden="true">assignment</span>
                                <h2 class="text-title-md text-on-surface">Task Overview</h2>
                            </div>
                            <span class="font-code-sm text-[11px] text-on-surface-variant">ID: {{ $ticket }}</span>
                        </div>

                        {{-- Title --}}
                        <div class="mb-space-md">
                            <div class="mb-space-xs flex items-center justify-between">
                                <label for="title" class="flex items-center gap-1 text-label-md text-on-surface">
                                    Task Title <span class="text-title-sm text-error">*</span>
                                </label>
                                <span class="font-code-sm text-code-sm text-on-surface-variant" x-text="title.length + ' / 255'">0 / 255</span>
                            </div>
                            <input type="text" id="title" name="title" x-model="title" required maxlength="255"
                                   class="w-full rounded-xl border-0 bg-surface-container-lowest px-space-md py-2.5 text-body-lg text-on-surface transition-shadow placeholder:text-outline focus:outline-none focus:ring-0
                                          {{ $errors->has('title') ? 'shadow-[0_0_0_2px_rgba(186,26,26,1)]' : 'shadow-[0_0_0_1px_rgba(203,213,225,1)] focus:shadow-[0_0_0_2px_rgba(79,70,229,1)]' }}">
                            @error('title')
                                <p class="mt-1.5 flex items-center gap-1 text-body-sm text-error">
                                    <span class="material-symbols-outlined text-[14px]" aria-hidden="true">error</span>
                                    {{ $message }}
                                </p>
                            @enderror
                        </div>

                        {{-- Description --}}
                        <div>
                            <label for="description" class="mb-space-xs block text-label-md text-on-surface">Description &amp; Specifications</label>
                            <textarea id="description" name="description" rows="7" x-model="description"
                                      class="w-full resize-y rounded-xl border-0 bg-surface-container-lowest p-space-md text-body-md text-on-surface transition-shadow placeholder:text-outline focus:outline-none focus:ring-0
                                             {{ $errors->has('description') ? 'shadow-[0_0_0_2px_rgba(186,26,26,1)]' : 'shadow-[0_0_0_1px_rgba(203,213,225,1)] focus:shadow-[0_0_0_2px_rgba(79,70,229,1)]' }}"></textarea>
                            @error('description')
                                <p class="mt-1.5 flex items-center gap-1 text-body-sm text-error">
                                    <span class="material-symbols-outlined text-[14px]" aria-hidden="true">error</span>
                                    {{ $message }}
                                </p>
                            @enderror
                        </div>
                    </div>

                    {{-- Planning & attributes --}}
                    <div class="rounded-xl bg-surface-container-lowest p-space-lg shadow-sm">
                        <div class="mb-space-md flex items-center justify-between">
                            <div class="flex items-center gap-space-xs">
                                <span class="material-symbols-outlined text-[22px] text-primary" aria-hidden="true">tune</span>
                                <h2 class="text-title-md text-on-surface">Planning &amp; Attributes</h2>
                            </div>
                            <span class="text-label-sm text-on-surface-variant">Core task</span>
                        </div>

                        <div class="grid grid-cols-1 gap-space-lg md:grid-cols-2">

                            {{-- Due date --}}
                            <div>
                                <div class="mb-space-xs flex items-center justify-between">
                                    <label for="due_date" class="flex items-center gap-1 text-label-md text-on-surface">
                                        <span class="material-symbols-outlined text-[16px]" aria-hidden="true">event</span>
                                        Target Due Date
                                    </label>
                                    @if ($project->start_date || $project->end_date)
                                        <span class="font-code-sm text-[10px] text-secondary">
                                            Project: {{ $project->start_date?->format('d M') ?? '…' }} – {{ $project->end_date?->format('d M') ?? '…' }}
                                        </span>
                                    @endif
                                </div>
                                <input type="date" id="due_date" name="due_date" x-model="dueDate"
                                       class="w-full rounded-xl border-0 bg-surface-container-lowest px-space-md py-2.5 text-body-md text-on-surface focus:outline-none focus:ring-0
                                              {{ $errors->has('due_date') ? 'shadow-[0_0_0_2px_rgba(186,26,26,1)]' : 'shadow-[0_0_0_1px_rgba(203,213,225,1)] focus:shadow-[0_0_0_2px_rgba(79,70,229,1)]' }}">

                                <div class="mt-space-xs flex flex-wrap items-center gap-1.5">
                                    <button type="button" @click="setDate(0)"
                                            class="rounded-full bg-surface-container px-2.5 py-0.5 text-label-sm text-on-surface-variant transition-colors hover:bg-surface-container-high">Today</button>
                                    <button type="button" @click="setDate(7)"
                                            class="rounded-full bg-surface-container px-2.5 py-0.5 text-label-sm text-on-surface-variant transition-colors hover:bg-surface-container-high">+1 Wk</button>
                                    @if ($project->end_date)
                                        <button type="button" @click="dueDate = @js($project->end_date->format('Y-m-d'))"
                                                class="rounded-full bg-primary-fixed px-2.5 py-0.5 text-label-sm text-on-primary-fixed-variant transition-colors hover:bg-primary-fixed-dim">Project End</button>
                                    @endif
                                    <button type="button" x-show="dueDate" @click="dueDate = ''" style="display: none;"
                                            class="rounded-full px-2.5 py-0.5 text-label-sm text-on-surface-variant transition-colors hover:bg-surface-container">Clear</button>
                                    <span x-show="dueNote" style="display: none;"
                                          :class="daysLeft < 0 ? 'text-error' : (daysLeft <= 2 ? 'text-amber-700' : 'text-on-surface-variant')"
                                          class="ml-auto flex items-center gap-1 text-label-sm font-medium">
                                        <span class="material-symbols-outlined text-[14px]" aria-hidden="true">alarm</span>
                                        <span x-text="dueNote"></span>
                                    </span>
                                </div>
                                @error('due_date')
                                    <p class="mt-1.5 flex items-center gap-1 text-body-sm text-error">
                                        <span class="material-symbols-outlined text-[14px]" aria-hidden="true">error</span>
                                        {{ $message }}
                                    </p>
                                @enderror
                            </div>

                            {{-- Priority --}}
                            <fieldset>
                                <legend class="mb-space-xs flex items-center gap-1 text-label-md text-on-surface">
                                    <span class="material-symbols-outlined text-[16px]" aria-hidden="true">flag</span>
                                    Priority Level <span class="text-error">*</span>
                                </legend>
                                <div class="flex gap-1 rounded-xl bg-surface-container-low p-1">
                                    @foreach (['low' => 'Low', 'medium' => 'Medium', 'high' => 'High'] as $value => $label)
                                        <label class="flex-1 cursor-pointer">
                                            <input type="radio" name="priority" value="{{ $value }}" x-model="priority" required class="peer sr-only">
                                            <span :class="priority === @js($value) ? priorityActive[@js($value)] : 'text-on-surface-variant hover:text-on-surface'"
                                                  class="flex items-center justify-center gap-1.5 rounded-lg py-2 text-label-md transition-all peer-focus-visible:ring-2 peer-focus-visible:ring-primary-container">
                                                <span class="h-1.5 w-1.5 rounded-full" :class="priorityPill[@js($value)].dot"></span>
                                                {{ $label }}
                                            </span>
                                        </label>
                                    @endforeach
                                </div>
                                @error('priority')
                                    <p class="mt-1.5 flex items-center gap-1 text-body-sm text-error">
                                        <span class="material-symbols-outlined text-[14px]" aria-hidden="true">error</span>
                                        {{ $message }}
                                    </p>
                                @enderror
                            </fieldset>
                        </div>

                        {{-- Status --}}
                        <fieldset class="mt-space-lg">
                            <legend class="mb-space-xs flex items-center gap-1 text-label-md text-on-surface">
                                <span class="material-symbols-outlined text-[16px]" aria-hidden="true">hourglass_empty</span>
                                Execution Status <span class="text-error">*</span>
                            </legend>
                            <div class="grid grid-cols-1 gap-1 rounded-xl bg-surface-container-low p-1 sm:grid-cols-3">
                                @foreach ([
                                    ['pending',     'Pending',     'radio_button_unchecked'],
                                    ['in_progress', 'In Progress', 'timelapse'],
                                    ['completed',   'Completed',   'check_circle'],
                                ] as [$value, $label, $icon])
                                    <label class="cursor-pointer">
                                        <input type="radio" name="status" value="{{ $value }}" x-model="status" required class="peer sr-only">
                                        <span :class="status === @js($value)
                                                    ? (@js($value) === 'in_progress' ? 'bg-secondary-fixed text-on-secondary-fixed shadow-sm'
                                                       : (@js($value) === 'completed' ? 'bg-tertiary-fixed text-on-tertiary-fixed shadow-sm'
                                                       : 'bg-surface-container-lowest text-on-surface shadow-sm'))
                                                    : 'text-on-surface-variant hover:text-on-surface'"
                                              class="flex items-center justify-center gap-1.5 rounded-lg py-2.5 text-label-md transition-all peer-focus-visible:ring-2 peer-focus-visible:ring-primary-container">
                                            <span class="material-symbols-outlined text-[18px]" aria-hidden="true">{{ $icon }}</span>
                                            {{ $label }}
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                            @error('status')
                                <p class="mt-1.5 flex items-center gap-1 text-body-sm text-error">
                                    <span class="material-symbols-outlined text-[14px]" aria-hidden="true">error</span>
                                    {{ $message }}
                                </p>
                            @enderror
                        </fieldset>
                    </div>

                    {{-- Assignment: renders only if the controller passes $users --}}
                    @isset($users)
                        <div class="rounded-xl bg-surface-container-lowest p-space-lg shadow-sm">
                            <div class="mb-space-md flex items-center gap-space-xs">
                                <span class="material-symbols-outlined text-[22px] text-primary" aria-hidden="true">person_add</span>
                                <div>
                                    <h2 class="text-title-md text-on-surface">Assignment</h2>
                                    <p class="text-body-sm text-on-surface-variant">Who is responsible for this task.</p>
                                </div>
                            </div>

                            <label for="assigned_to" class="mb-space-xs block text-label-md text-on-surface">Primary Assignee</label>
                            <div class="relative">
                                <select name="assigned_to" id="assigned_to" x-model="assignedTo"
                                        class="w-full appearance-none rounded-xl border-0 bg-surface-container-lowest py-2.5 pl-space-md pr-10 text-body-md text-on-surface focus:outline-none focus:ring-0
                                               {{ $errors->has('assigned_to') ? 'shadow-[0_0_0_2px_rgba(186,26,26,1)]' : 'shadow-[0_0_0_1px_rgba(203,213,225,1)] focus:shadow-[0_0_0_2px_rgba(79,70,229,1)]' }}">
                                    <option value="">Unassigned</option>
                                    @foreach ($users as $user)
                                        <option value="{{ $user->id }}" @selected(old('assigned_to', $task->assigned_to) == $user->id)>
                                            {{ $user->name }} ({{ $user->email }})
                                        </option>
                                    @endforeach
                                </select>
                                <span class="material-symbols-outlined pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-[18px] text-on-surface-variant" aria-hidden="true">expand_more</span>
                            </div>
                            @error('assigned_to')
                                <p class="mt-1.5 flex items-center gap-1 text-body-sm text-error">
                                    <span class="material-symbols-outlined text-[14px]" aria-hidden="true">error</span>
                                    {{ $message }}
                                </p>
                            @enderror

                            <div class="mt-space-md rounded-xl bg-surface-container-low p-space-md">
                                <div x-show="assignee()" class="flex items-center gap-3">
                                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-primary-fixed text-label-md font-bold text-on-primary-fixed"
                                          x-text="initials(assignee()?.name)"></span>
                                    <div class="min-w-0">
                                        <p class="truncate text-title-sm text-on-surface" x-text="assignee()?.name"></p>
                                        <p class="truncate text-body-sm text-on-surface-variant" x-text="assignee()?.email"></p>
                                    </div>
                                </div>
                                <p x-show="!assignee()" style="display: none;" class="text-body-md text-on-surface-variant">Unassigned</p>
                            </div>
                        </div>
                    @endisset

                </form>

                {{-- RIGHT: preview, activity, delete --}}
                <aside class="flex flex-col gap-space-lg lg:sticky lg:top-6 lg:col-span-4">

                    {{-- Board preview --}}
                    <div class="rounded-xl bg-surface-container-lowest p-space-lg shadow-sm">
                        <div class="mb-space-md flex items-center justify-between">
                            <div class="flex items-center gap-space-xs">
                                <span class="material-symbols-outlined text-[20px] text-primary" aria-hidden="true">view_kanban</span>
                                <h3 class="text-title-md text-on-surface"> Preview</h3>
                            </div>
                        </div>

                        <div class="rounded-xl bg-surface-container-lowest p-space-md shadow-[0_0_0_1px_rgba(226,232,240,1),0_1px_2px_rgba(15,23,42,0.04)]">
                            <div class="mb-space-sm flex items-center justify-between gap-2">
                                <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-label-sm"
                                      :class="statusPill[status]?.pill">
                                    <span class="h-1.5 w-1.5 rounded-full" :class="statusPill[status]?.dot" aria-hidden="true"></span>
                                    <span x-text="statusPill[status]?.label"></span>
                                </span>
                                <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-label-sm capitalize"
                                      :class="priorityPill[priority]?.pill">
                                    <span class="material-symbols-outlined text-[12px]" aria-hidden="true"></span>
                                    <span x-text="priority"></span>
                                </span>
                            </div>

                            <h4 class="mb-space-sm break-words text-title-sm text-on-surface" x-text="title.trim() || 'Untitled task'">Untitled task</h4>

                            <div class="mb-space-sm">
                                <span class="rounded bg-surface-container-low px-2 py-0.5 font-code-sm text-[10px] text-on-surface-variant">{{ $ticket }}</span>
                            </div>

                            <div class="flex items-center justify-between border-t border-surface-container-low pt-space-sm text-body-sm text-on-surface-variant">
                                <span class="flex items-center gap-1"
                                      :class="dueNote && daysLeft < 0 ? 'text-error font-medium' : ''">
                                    <span class="material-symbols-outlined text-[16px]" aria-hidden="true">calendar_today</span>
                                    <span x-text="dueLabel"></span>
                                </span>
                                <span x-show="assignee()" style="display: none;"
                                      class="flex h-7 w-7 items-center justify-center rounded-full bg-primary-fixed text-[10px] font-bold text-on-primary-fixed"
                                      :title="assignee()?.name"
                                      x-text="initials(assignee()?.name)"></span>
                            </div>
                        </div>

                        <p class="mt-space-sm text-center text-body-sm text-on-surface-variant">
                            Updates as you edit. Nothing is saved until you click Save Changes.
                        </p>
                    </div>

                    {{-- Recent activity --}}
                    <div class="rounded-xl bg-surface-container-lowest p-space-lg shadow-sm" x-data="{ showAll: false }">
                        <div class="mb-space-md flex items-center justify-between">
                            <div class="flex items-center gap-space-xs">
                                <span class="material-symbols-outlined text-[20px] text-primary" aria-hidden="true">history</span>
                                <h3 class="text-title-md text-on-surface">Recent Activity</h3>
                            </div>
                            @isset($activities)
                                <span class="rounded-full bg-surface-container px-2 py-0.5 text-label-sm text-on-surface-variant">{{ $activities->count() }}</span>
                            @endisset
                        </div>

                        <ol class="relative space-y-space-md before:absolute before:bottom-3 before:left-[11px] before:top-3 before:w-px before:bg-surface-container-high">

                            {{-- Live: unsaved edits on this page --}}
                            <li x-show="changes.length" style="display: none;" class="relative flex gap-3">
                                <span class="relative z-10 flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-amber-100 text-amber-800 ring-2 ring-surface-container-lowest">
                                    <span class="material-symbols-outlined text-[14px]" aria-hidden="true">edit_note</span>
                                </span>
                                <div class="min-w-0 flex-1 rounded-lg border border-dashed border-amber-300 bg-amber-50/50 p-2">
                                    <p class="text-title-sm text-on-surface">
                                        Unsaved changes <span class="text-on-surface-variant" x-text="'(' + changes.length + ')'"></span>
                                    </p>
                                    <ul class="mt-1 space-y-1">
                                        <template x-for="c in changes" :key="c.key">
                                            <li class="flex flex-wrap items-center gap-1 text-body-sm text-on-surface-variant">
                                                <span class="font-medium text-on-surface" x-text="c.label"></span>
                                                <template x-if="c.key !== 'description'">
                                                    <span class="flex items-center gap-1">
                                                        <span class="rounded bg-surface-container px-1.5 line-through" x-text="c.from"></span>
                                                        <span class="material-symbols-outlined text-[12px]" aria-hidden="true">arrow_forward</span>
                                                        <span class="rounded bg-primary-fixed px-1.5 text-on-primary-fixed" x-text="c.to"></span>
                                                    </span>
                                                </template>
                                                <template x-if="c.key === 'description'"><span>edited</span></template>
                                            </li>
                                        </template>
                                    </ul>
                                </div>
                            </li>

                            @isset($activities)
                                @forelse ($activities as $activity)
                                    @php
                                        [$icon, $tone] = $eventStyles[$activity->event ?? 'updated'] ?? ['bolt', 'bg-surface-container text-on-surface-variant'];
                                        $old = $activity->properties->get('old', []);
                                        $new = $activity->properties->get('attributes', []);
                                    @endphp
                                    <li class="relative flex gap-3" @if ($loop->index >= 5) x-show="showAll" style="display: none;" @endif>
                                        <span class="relative z-10 flex h-6 w-6 shrink-0 items-center justify-center rounded-full ring-2 ring-surface-container-lowest {{ $tone }}">
                                            <span class="material-symbols-outlined text-[14px]" aria-hidden="true">{{ $icon }}</span>
                                        </span>
                                        <div class="min-w-0 flex-1">
                                            <p class="text-title-sm text-on-surface">{{ $activity->description }}</p>

                                            @if ($activity->event === 'updated' && $new)
                                                <ul class="mt-1 space-y-0.5">
                                                    @foreach ($new as $field => $value)
                                                        <li class="flex flex-wrap items-center gap-1 text-body-sm text-on-surface-variant">
                                                            <span class="font-medium">{{ Str::headline($field) }}</span>
                                                            @if ($field !== 'description')
                                                                <span class="rounded bg-surface-container px-1.5 line-through">{{ Str::limit((string) ($old[$field] ?? '—'), 24) }}</span>
                                                                <span class="material-symbols-outlined text-[12px]" aria-hidden="true">arrow_forward</span>
                                                                <span class="rounded bg-primary-fixed px-1.5 text-on-primary-fixed">{{ Str::limit((string) ($value ?? '—'), 24) }}</span>
                                                            @else
                                                                <span>edited</span>
                                                            @endif
                                                        </li>
                                                    @endforeach
                                                </ul>
                                            @endif

                                            <div class="mt-1 flex items-center gap-1.5 text-label-sm text-on-surface-variant/70">
                                                @if ($activity->causer?->name)
                                                    <span class="flex h-4 w-4 items-center justify-center rounded-full bg-primary-fixed text-[8px] font-bold text-on-primary-fixed"
                                                          title="{{ $activity->causer->name }}">{{ $initials($activity->causer->name) }}</span>
                                                    <span class="truncate">{{ $activity->causer->name }}</span>
                                                    <span aria-hidden="true">·</span>
                                                @endif
                                                <time datetime="{{ $activity->created_at->toIso8601String() }}"
                                                      title="{{ $activity->created_at->format('d M Y, H:i') }}">{{ $activity->created_at->diffForHumans() }}</time>
                                            </div>
                                        </div>
                                    </li>
                                @empty
                                    <li class="pl-9 text-body-sm text-on-surface-variant">No activity recorded yet.</li>
                                @endforelse
                            @else
                                @if ($task->updated_at->gt($task->created_at->copy()->addMinute()))
                                    <li class="relative flex gap-3">
                                        <span class="relative z-10 flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-primary-fixed text-on-primary-fixed ring-2 ring-surface-container-lowest">
                                            <span class="material-symbols-outlined text-[14px]" aria-hidden="true">edit</span>
                                        </span>
                                        <div class="min-w-0">
                                            <p class="text-title-sm text-on-surface">Last modified</p>
                                            <time class="text-label-sm text-on-surface-variant/70" title="{{ $task->updated_at->format('d M Y, H:i') }}">{{ $task->updated_at->diffForHumans() }}</time>
                                        </div>
                                    </li>
                                @endif
                                <li class="relative flex gap-3">
                                    <span class="relative z-10 flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-tertiary-fixed text-on-tertiary-fixed ring-2 ring-surface-container-lowest">
                                        <span class="material-symbols-outlined text-[14px]" aria-hidden="true">add_circle</span>
                                    </span>
                                    <div class="min-w-0">
                                        <p class="text-title-sm text-on-surface">Task created</p>
                                        <p class="text-body-sm text-on-surface-variant">In {{ $project->name }}</p>
                                        <time class="text-label-sm text-on-surface-variant/70" title="{{ $task->created_at->format('d M Y, H:i') }}">{{ $task->created_at->diffForHumans() }}</time>
                                    </div>
                                </li>
                            @endisset
                        </ol>

                        @isset($activities)
                            @if ($activities->count() > 5)
                                <button type="button" @click="showAll = !showAll"
                                        class="mt-space-md w-full rounded-lg py-1.5 text-label-md text-primary transition-colors hover:bg-primary-fixed/40">
                                    <span x-text="showAll ? 'Show less' : 'Show all {{ $activities->count() }}'"></span>
                                </button>
                            @endif
                        @endisset
                    </div>

                    @can('tasks.delete')
                        <form action="{{ route('projects.tasks.destroy', [$project, $task]) }}" method="POST"
                              onsubmit="return confirm(@js('Delete “' . $task->title . '”? This can’t be undone.'))">
                            @csrf
                            @method('DELETE')
                            <button type="submit"
                                    class="flex items-center gap-1.5 rounded-lg px-space-xs py-1.5 text-label-md text-on-surface-variant transition-colors hover:text-error focus:outline-none focus-visible:ring-2 focus-visible:ring-error">
                                <span class="material-symbols-outlined text-[18px]" aria-hidden="true">delete</span>
                                Delete task
                            </button>
                        </form>
                    @endcan
                </aside>
            </div>

            {{-- Bottom action bar --}}
            <div class="mt-space-lg flex flex-col items-center justify-between gap-space-sm rounded-xl bg-surface-container-lowest px-space-lg py-space-md shadow-sm sm:flex-row">
                <span class="flex items-center gap-2 text-body-sm text-on-surface-variant">
                    <span class="h-2 w-2 rounded-full bg-tertiary" aria-hidden="true"></span>
                    Last saved {{ $task->updated_at->diffForHumans() }}
                </span>
               
            </div>
        </div>
    </div>
</x-app-layout>