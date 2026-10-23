{{-- View: tasks.create (route: projects.tasks.create) --}}

{{--
|--------------------------------------------------------------------------
| RBAC NOTES (dynamic roles & permissions — partly wired up)
|--------------------------------------------------------------------------
| Planned: default roles "manager" and "team member", plus custom roles
| with custom permissions.
|
| ASSIGNEE: ACTIVE. Renders only when the user has the 'tasks.assign'
| permission AND the controller passes $users. Nothing breaks if either
| is missing; the section just doesn't show.
|
| ROLES / PERMISSIONS: COMMENTED OUT below (search for RBAC-ROLES) because
| they need roles/permissions tables that don't exist yet.
|
| When RBAC exists:
|   1. RBAC-ROLES     : enable the role + effective-permission section
|   2. RBAC-INSPECTOR : role summary card in the sidebar
|   3. RBAC-CREATE    : wrap the Create Task buttons in
|                       @can('create', [App\Models\Task::class, $project])
|      (an undefined ability returns false, so do NOT add @can before the
|      permission/policy exists, or the buttons will disappear)
|   4. Controller create(): pass
|        'users'       => $project->members (or User::orderBy('name')->get()),
|        'roles'       => Role::with('permissions')->get(),
|        'permissions' => Permission::where('scope', 'task')->get()
|   5. Controller store():  $this->authorize('create', [Task::class, $project]);
|        'assigned_to' => ['nullable', 'exists:users,id'],
|        'role_id'     => ['nullable', 'exists:roles,id'],
|        'permissions' => ['nullable', 'array'],
|        'permissions.*' => ['exists:permissions,id'],
--}}

<x-app-layout>
    <style>
        @keyframes task-create-enter { from { opacity: 0; transform: translateY(12px); } to { opacity: 1; transform: translateY(0); } }
        .task-create-enter { animation: task-create-enter .5s cubic-bezier(.22,.8,.25,1) both; }
        @media (prefers-reduced-motion: reduce) { .task-create-enter { animation: none !important; transform: none !important; } }
    </style>
    <div class="relative w-full overflow-hidden font-inter task-create-enter"
         @keydown.window.ctrl.enter.prevent="$refs.form.requestSubmit()"
         @keydown.window.meta.enter.prevent="$refs.form.requestSubmit()"
         x-data="{
             title: @js(old('title', '')),
             due: @js(old('due_date', '')),
             priority: @js(old('priority', 'medium')),
             status: @js(old('status', 'pending')),
             description: @js(old('description', '')),
             assignedTo: @js((string) old('assigned_to', '')),
             users: @js(($users ?? collect())->mapWithKeys(fn ($u) => [(string) $u->id => ['name' => $u->name, 'email' => $u->email]])),

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
                 pending:     { label: 'To Do',       pill: 'bg-surface-container text-on-surface-variant', dot: 'bg-outline' },
                 in_progress: { label: 'In Progress', pill: 'bg-secondary-fixed text-on-secondary-fixed',   dot: 'bg-secondary' },
                 completed:   { label: 'Done',        pill: 'bg-tertiary-fixed text-on-tertiary-fixed',     dot: 'bg-tertiary' }
             },

             iso(d) { return d.toLocaleDateString('en-CA'); },
             setDue(offsetDays) {
                 const d = new Date();
                 d.setDate(d.getDate() + offsetDays);
                 this.due = this.iso(d);
             },
             today() { const t = new Date(); t.setHours(0, 0, 0, 0); return t; },
             get dueLabel() {
                 return this.due
                     ? new Date(this.due + 'T00:00:00').toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' })
                     : 'No due date';
             },
             get daysLeft() {
                 if (!this.due) return null;
                 return Math.round((new Date(this.due + 'T00:00:00') - this.today()) / 86400000);
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
                        <span class="text-title-sm text-on-surface">Create Task</span>
                    </nav>

                    <h1 class="mt-1 text-headline-lg tracking-tight text-on-surface">Create Task</h1>

                    <div class="flex flex-wrap items-center gap-2">
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-surface-container-high px-2.5 py-0.5 font-code-sm text-[11px] text-on-surface-variant">
                            <span class="h-1.5 w-1.5 rounded-full bg-primary-container" aria-hidden="true"></span>
                            PRJ-{{ str_pad($project->id, 3, '0', STR_PAD_LEFT) }} · {{ Str::limit($project->name, 32) }}
                        </span>
                    </div>
                </div>

                <div class="flex shrink-0 items-center gap-space-sm">
                    <a href="{{ route('projects.show', $project) }}"
                       class="rounded-xl bg-surface-container-lowest px-space-md py-2 text-label-md text-on-surface shadow-sm transition-all hover:bg-surface-container-low">
                        Cancel
                    </a>
                    @can('tasks.create')
                    <button type="submit" form="task-form"
                            class="flex items-center gap-2 rounded-xl bg-primary-container px-space-lg py-2 text-label-md text-on-primary shadow-md transition-all hover:bg-primary hover:shadow-lg focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-container focus-visible:ring-offset-2">
                        
                        + Create Task
                        <kbd class="ml-1 hidden rounded bg-primary/30 px-1.5 py-0.5 font-code-sm text-[10px] text-on-primary-container sm:inline">Ctrl ↵</kbd>
                    </button>
                    @endcan
                </div>
            </div>

            <div class="grid grid-cols-1 items-start gap-gutter lg:grid-cols-12">

                {{-- LEFT: form --}}
                <form id="task-form" x-ref="form"
                      action="{{ route('projects.tasks.store', $project) }}" method="POST" enctype="multipart/form-data"
                      class="flex flex-col gap-space-lg lg:col-span-8">
                    @csrf

                    {{-- Task overview --}}
                    <div class="rounded-xl bg-surface-container-lowest p-space-lg shadow-sm">
                        <div class="mb-space-md flex items-center justify-between">
                            <div class="flex items-center gap-space-xs">
                                <span class="material-symbols-outlined text-[22px] text-primary" aria-hidden="true">assignment</span>
                                <h2 class="text-title-md text-on-surface">Task Overview</h2>
                            </div>
                            <span class="text-label-sm text-on-surface-variant"><span class="text-error">*</span> Required fields</span>
                        </div>

                        {{-- Title --}}
                        <div class="mb-space-md">
                            <div class="mb-space-xs flex items-center justify-between">
                                <label for="title" class="flex items-center gap-1 text-label-md text-on-surface">
                                    Task Title <span class="text-title-sm text-error">*</span>
                                </label>
                                <span class="font-code-sm text-code-sm text-on-surface-variant" x-text="title.length + ' / 255'">0 / 255</span>
                            </div>
                            <input type="text" id="title" name="title" x-model="title" required maxlength="255" autofocus
                                   placeholder="e.g., Implement core UI component library"
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
                                      placeholder="Describe the goal, requirements, and acceptance criteria…"
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
                            <span class="text-label-sm text-on-surface-variant">Core task metadata</span>
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
                                <input type="date" id="due_date" name="due_date" x-model="due"
                                       class="w-full rounded-xl border-0 bg-surface-container-lowest px-space-md py-2.5 text-body-md text-on-surface focus:outline-none focus:ring-0
                                              {{ $errors->has('due_date') ? 'shadow-[0_0_0_2px_rgba(186,26,26,1)]' : 'shadow-[0_0_0_1px_rgba(203,213,225,1)] focus:shadow-[0_0_0_2px_rgba(79,70,229,1)]' }}">

                                <div class="mt-space-xs flex flex-wrap items-center gap-1.5">
                                    <button type="button" @click="setDue(0)"
                                            class="rounded-full bg-surface-container px-2.5 py-0.5 text-label-sm text-on-surface-variant transition-colors hover:bg-surface-container-high">Today</button>
                                    <button type="button" @click="setDue(7)"
                                            class="rounded-full bg-surface-container px-2.5 py-0.5 text-label-sm text-on-surface-variant transition-colors hover:bg-surface-container-high">+1 Wk</button>
                                    @if ($project->end_date)
                                        <button type="button" @click="due = @js($project->end_date->format('Y-m-d'))"
                                                class="rounded-full bg-primary-fixed px-2.5 py-0.5 text-label-sm text-on-primary-fixed-variant transition-colors hover:bg-primary-fixed-dim">Project End</button>
                                    @endif
                                    <button type="button" x-show="due" @click="due = ''" style="display: none;"
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
                                Starting Status <span class="text-error">*</span>
                            </legend>
                            <div class="grid grid-cols-1 gap-1 rounded-xl bg-surface-container-low p-1 sm:grid-cols-3">
                                @foreach ([
                                    ['pending',     'To Do',       'radio_button_unchecked'],
                                    ['in_progress', 'In Progress', 'timelapse'],
                                    ['completed',   'Done',        'check_circle'],
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
                            <p class="mt-space-xs text-body-sm text-on-surface-variant">Which board column the task starts in.</p>
                            @error('status')
                                <p class="mt-1.5 flex items-center gap-1 text-body-sm text-error">
                                    <span class="material-symbols-outlined text-[14px]" aria-hidden="true">error</span>
                                    {{ $message }}
                                </p>
                            @enderror
                        </fieldset>
                    </div>

                   
                    @can('tasks.assign')
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
                                            <option value="{{ $user->id }}" @selected(old('assigned_to') == $user->id)>
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
                    @endcan

                    {{-- Attachments --}}
                    <div class="rounded-xl bg-surface-container-lowest p-space-lg shadow-sm">
                        <div class="mb-space-md flex items-center justify-between">
                            <div class="flex items-center gap-space-xs">
                                <span class="material-symbols-outlined text-[22px] text-primary" aria-hidden="true">attach_file</span>
                                <h2 class="text-title-md text-on-surface">Task Attachments</h2>
                            </div>
                          
                        </div>

                        @include('ProjectManagement::attachments._form-upload', [
                            'label' => 'Attach Screenshots, Logs, Specs or Files',
                        ])
                    </div>

                </form>

                {{-- RIGHT: live preview --}}
                <aside class="flex flex-col gap-space-lg lg:sticky lg:top-6 lg:col-span-4">

                    <div class="rounded-xl bg-surface-container-lowest p-space-lg shadow-sm">
                        <div class="mb-space-md flex items-center justify-between">
                            <div class="flex items-center gap-space-xs">
                                <span class="material-symbols-outlined text-[20px] text-primary" aria-hidden="true">view_kanban</span>
                                <h3 class="text-title-md text-on-surface">Preview</h3>
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

                            <p class="mb-space-sm line-clamp-2 whitespace-pre-line text-body-sm text-on-surface-variant"
                               x-show="description.trim()" style="display: none;" x-text="description"></p>

                            <div class="mb-space-sm">
                                <span class="inline-block max-w-full truncate rounded bg-surface-container-low px-2 py-0.5 font-code-sm text-[10px] text-on-surface-variant">
                                    PRJ-{{ str_pad($project->id, 3, '0', STR_PAD_LEFT) }}
                                </span>
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
                            Updates as you type. Nothing is saved until you click Create Task.
                        </p>
                    </div>

                    

                    <div class="rounded-xl bg-primary-fixed/40 p-space-md shadow-sm">
                        <div class="mb-space-xs flex items-center gap-space-xs text-primary">
                            <span class="material-symbols-outlined text-[20px]" aria-hidden="true">lightbulb</span>
                            <h3 class="text-title-md">Tip</h3>
                        </div>
                        <p class="text-body-sm text-on-surface-variant">
                            Keep tasks small enough to finish in a day or two. Larger work is easier to track when broken into several tasks.
                        </p>
                    </div>
                </aside>
            </div>

            {{-- Bottom action bar --}}
            <div class="mt-space-lg flex flex-col items-center justify-between gap-space-sm rounded-xl bg-surface-container-lowest px-space-lg py-space-md shadow-sm sm:flex-row">
                <a href="{{ route('projects.show', $project) }}"
                   class="flex items-center gap-1.5 text-label-md text-on-surface-variant transition-colors hover:text-on-surface">
                    <span class="material-symbols-outlined text-[18px]" aria-hidden="true">arrow_back</span>
                    Discard and back to project
                </a>
                <!-- <div class="flex items-center gap-space-sm">
                    <a href="{{ route('projects.show', $project) }}"
                       class="rounded-xl bg-surface-container-low px-space-md py-2 text-label-md text-on-surface transition-all hover:bg-surface-container">
                        Cancel
                    </a>
                    {{-- RBAC-CREATE: wrap in @can(...) … @endcan once policies exist --}}
                    <button type="submit" form="task-form"
                            class="flex items-center gap-2 rounded-xl bg-primary-container px-space-lg py-2 text-label-md text-on-primary shadow-md transition-all hover:bg-primary hover:shadow-lg focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-container focus-visible:ring-offset-2">
                        <span class="material-symbols-outlined text-[18px]" aria-hidden="true">add_task</span>
                        Create Task
                    </button>
                </div> -->
            </div>
        </div>
    </div>
</x-app-layout>