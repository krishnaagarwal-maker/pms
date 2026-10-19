{{-- View: tasks.create (route: projects.tasks.create) --}}

{{--
|--------------------------------------------------------------------------
| RBAC NOTES (dynamic roles & permissions — not wired up yet)
|--------------------------------------------------------------------------
| Planned: default roles "manager" and "team member", plus custom roles
| with custom permissions. Everything role/permission/assignee related is
| COMMENTED OUT below so this page works without any RBAC tables/policies.
|
| When RBAC exists, enable these (search for the RBAC-… markers):
|   1. RBAC-ASSIGNEE  : assignee + role + effective-permission section
|   2. RBAC-PREVIEW   : assignee avatar in the live preview tile
|   3. RBAC-INSPECTOR : role summary card in the sidebar
|   4. RBAC-CREATE    : wrap the Create Task buttons in
|                       @can('create', [App\Models\Task::class, $project])
|      (an undefined ability returns false, so do NOT add @can before the
|      permission/policy exists, or the buttons will disappear)
|   5. Controller create(): pass
|        'assignableUsers' => $project->members (or User::orderBy('name')->get()),
|        'roles'          => Role::with('permissions')->get(),
|        'permissions'    => Permission::where('scope', 'task')->get()
|   6. Controller store():  $this->authorize('create', [Task::class, $project]);
|        'assignee_id' => ['nullable', 'exists:users,id'],
|        'role_id'     => ['nullable', 'exists:roles,id'],
|        'permissions' => ['nullable', 'array'],
|        'permissions.*' => ['exists:permissions,id'],
--}}

<x-app-layout>
    <div class="relative w-full overflow-hidden font-inter"
         @keydown.window.ctrl.enter.prevent="$refs.form.requestSubmit()"
         @keydown.window.meta.enter.prevent="$refs.form.requestSubmit()"
         x-data="{
             title: @js(old('title', '')),
             due: @js(old('due_date', '')),
             priority: @js(old('priority', 'medium')),
             status: @js(old('status', 'pending')),
             description: @js(old('description', '')),
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
                 pending:     { label: 'To Do',       pill: 'bg-surface-container text-on-surface-variant',  dot: 'bg-outline' },
                 in_progress: { label: 'In Progress', pill: 'bg-secondary-fixed text-on-secondary-fixed',    dot: 'bg-secondary' },
                 completed:   { label: 'Done',        pill: 'bg-tertiary-fixed text-on-tertiary-fixed',      dot: 'bg-tertiary' }
             },
             iso(d) { return d.toLocaleDateString('en-CA'); },
             setDue(offsetDays) {
                 const d = new Date();
                 d.setDate(d.getDate() + offsetDays);
                 this.due = this.iso(d);
             },
             get dueLabel() {
                 if (!this.due) return 'No due date';
                 return new Date(this.due + 'T00:00:00').toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
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
                        <a href="{{ route('projects.index') }}" class="transition-colors hover:text-primary">Projects</a>
                        <span class="material-symbols-outlined text-[14px]" aria-hidden="true">chevron_right</span>
                        <a href="{{ route('projects.show', $project) }}" class="max-w-[14rem] truncate transition-colors hover:text-primary">{{ $project->name }}</a>
                        <span class="material-symbols-outlined text-[14px]" aria-hidden="true">chevron_right</span>
                        <span class="text-title-sm text-on-surface">Create Task</span>
                    </nav>

                    <div class="mt-1 flex flex-wrap items-center gap-space-sm">
                        <h1 class="text-headline-lg tracking-tight text-on-surface">Create Task</h1>
                        <span class="inline-flex max-w-xs items-center gap-1.5 truncate rounded-full bg-surface-container-high px-2.5 py-0.5 font-code-sm text-[11px] text-on-surface-variant">
                            <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-primary-container" aria-hidden="true"></span>
                            <span class="truncate">PRJ-{{ str_pad($project->id, 3, '0', STR_PAD_LEFT) }} · {{ $project->name }}</span>
                        </span>
                    </div>
                </div>

                <div class="flex shrink-0 items-center gap-space-sm">
                    <a href="{{ route('projects.show', $project) }}"
                       class="flex items-center gap-1.5 rounded-xl bg-surface-container-lowest px-space-md py-2 text-label-md text-on-surface shadow-sm transition-all hover:bg-surface-container-low">
                        <span class="material-symbols-outlined text-[18px]" aria-hidden="true">close</span>
                        Cancel
                    </a>

                    {{-- RBAC-CREATE: wrap in @can(...) … @endcan once policies exist --}}
                    <button type="submit" form="task-form"
                            class="flex items-center gap-2 rounded-xl bg-primary-container px-space-lg py-2 text-label-md text-on-primary shadow-md transition-all hover:bg-primary hover:shadow-lg focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-container focus-visible:ring-offset-2">
                        <span class="material-symbols-outlined text-[18px]" aria-hidden="true">add_task</span>
                        Create Task
                        <kbd class="ml-1 hidden rounded bg-primary/30 px-1.5 py-0.5 font-code-sm text-[10px] text-on-primary-container sm:inline">Ctrl ↵</kbd>
                    </button>
                </div>
            </div>

            <div class="grid grid-cols-1 items-start gap-gutter lg:grid-cols-12">

                {{-- LEFT: form --}}
                <form id="task-form" x-ref="form" action="{{ route('projects.tasks.store', $project) }}" method="POST"
                      class="flex flex-col gap-space-lg lg:col-span-8">
                    @csrf

                    {{-- SECTION 1: Task overview --}}
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
                            <textarea id="description" name="description" rows="6" x-model="description"
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

                    {{-- SECTION 2: Planning & attributes --}}
                    <div class="rounded-xl bg-surface-container-lowest p-space-lg shadow-sm">
                        <div class="mb-space-md flex items-center gap-space-xs">
                            <span class="material-symbols-outlined text-[22px] text-primary" aria-hidden="true">tune</span>
                            <h2 class="text-title-md text-on-surface">Planning &amp; Attributes</h2>
                        </div>

                        <div class="grid grid-cols-1 gap-space-lg md:grid-cols-3">

                            {{-- Due date --}}
                            <div>
                                <div class="mb-space-xs flex items-center justify-between">
                                    <label for="due_date" class="text-label-md text-on-surface">Due Date</label>
                                    @if ($project->start_date || $project->end_date)
                                        <span class="font-code-sm text-[10px] text-secondary">
                                            Project: {{ $project->start_date?->format('d M') ?? '…' }} – {{ $project->end_date?->format('d M') ?? '…' }}
                                        </span>
                                    @endif
                                </div>
                                <div class="relative flex items-center">
                                    <span class="material-symbols-outlined pointer-events-none absolute left-3 text-[18px] text-on-surface-variant" aria-hidden="true">calendar_today</span>
                                    <input type="date" id="due_date" name="due_date" x-model="due"
                                           class="w-full rounded-xl border-0 bg-surface-container-lowest py-2.5 pl-10 pr-3 text-body-md text-on-surface focus:outline-none focus:ring-0
                                                  {{ $errors->has('due_date') ? 'shadow-[0_0_0_2px_rgba(186,26,26,1)]' : 'shadow-[0_0_0_1px_rgba(203,213,225,1)] focus:shadow-[0_0_0_2px_rgba(79,70,229,1)]' }}">
                                </div>
                                <div class="mt-space-xs flex flex-wrap gap-1.5">
                                    <button type="button" @click="setDue(0)"
                                            class="rounded-full bg-surface-container px-2.5 py-0.5 text-label-sm text-on-surface-variant transition-colors hover:bg-surface-container-high">Today</button>
                                    <button type="button" @click="setDue(7)"
                                            class="rounded-full bg-surface-container px-2.5 py-0.5 text-label-sm text-on-surface-variant transition-colors hover:bg-surface-container-high">+1 Wk</button>
                                    @if ($project->end_date)
                                        <button type="button" @click="due = @js($project->end_date->format('Y-m-d'))"
                                                class="rounded-full bg-primary-fixed px-2.5 py-0.5 text-label-sm text-on-primary-fixed-variant transition-colors hover:bg-primary-fixed-dim">Project End</button>
                                    @endif
                                </div>
                                @error('due_date')
                                    <p class="mt-1.5 flex items-center gap-1 text-body-sm text-error">
                                        <span class="material-symbols-outlined text-[14px]" aria-hidden="true">error</span>
                                        {{ $message }}
                                    </p>
                                @enderror
                            </div>

                            {{-- Priority --}}
                            <div>
                                <span class="mb-space-xs block text-label-md text-on-surface">Priority <span class="text-error">*</span></span>
                                <div class="flex gap-1 rounded-xl bg-surface-container-low p-1">
                                    @foreach ([['low', 'Low'], ['medium', 'Medium'], ['high', 'High']] as [$v, $l])
                                        <label class="flex-1 cursor-pointer">
                                            <input type="radio" name="priority" value="{{ $v }}" x-model="priority" class="peer sr-only" required>
                                            <span :class="priority === @js($v) ? priorityActive[@js($v)] : 'text-on-surface-variant hover:text-on-surface'"
                                                  class="block rounded-lg py-1.5 text-center text-label-md transition-all peer-focus-visible:ring-2 peer-focus-visible:ring-primary-container">
                                                {{ $l }}
                                            </span>
                                        </label>
                                    @endforeach
                                </div>
                                <p class="mt-space-xs text-body-sm text-on-surface-variant">Affects triage order on the board.</p>
                                @error('priority')
                                    <p class="mt-1.5 flex items-center gap-1 text-body-sm text-error">
                                        <span class="material-symbols-outlined text-[14px]" aria-hidden="true">error</span>
                                        {{ $message }}
                                    </p>
                                @enderror
                            </div>

                            {{-- Status --}}
                            <div>
                                <span class="mb-space-xs block text-label-md text-on-surface">Status <span class="text-error">*</span></span>
                                <div class="flex gap-1 rounded-xl bg-surface-container-low p-1">
                                    @foreach ([['pending', 'To Do'], ['in_progress', 'In Progress'], ['completed', 'Done']] as [$v, $l])
                                        <label class="flex-1 cursor-pointer">
                                            <input type="radio" name="status" value="{{ $v }}" x-model="status" class="peer sr-only" required>
                                            <span :class="status === @js($v) ? 'bg-surface-container-lowest text-on-surface shadow-sm' : 'text-on-surface-variant hover:text-on-surface'"
                                                  class="block rounded-lg px-1 py-1.5 text-center text-label-md leading-tight transition-all peer-focus-visible:ring-2 peer-focus-visible:ring-primary-container">
                                                {{ $l }}
                                            </span>
                                        </label>
                                    @endforeach
                                </div>
                                <p class="mt-space-xs text-body-sm text-on-surface-variant">Which column it starts in.</p>
                                @error('status')
                                    <p class="mt-1.5 flex items-center gap-1 text-body-sm text-error">
                                        <span class="material-symbols-outlined text-[14px]" aria-hidden="true">error</span>
                                        {{ $message }}
                                    </p>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <!-- {{--
                    |----------------------------------------------------------------------
                    | RBAC-ASSIGNEE: Assignee & Role-Based Governance (COMMENTED OUT)
                    |----------------------------------------------------------------------
                    | Needs: $assignableUsers, $roles (with permissions), $permissions,
                    | and nullable assignee_id / role_id columns (+ task_permission pivot
                    | if per-task overrides are saved). Default roles: manager and
                    | team member; admins can add custom roles with custom permissions.
                    |
                    | To enable: remove the Blade comment markers around this block.
                    |
                    | @can('tasks.assign')
                    | <div class="rounded-xl bg-surface-container-lowest p-space-lg shadow-sm"
                    |      x-data="{
                    |          role: @js(old('role_id', '')),
                    |          rolePermissions: @js($roles->mapWithKeys(fn ($r) => [$r->id => $r->permissions->pluck('id')])),
                    |          selected: @js(old('permissions', [])),
                    |          pickRole(id) { this.role = id; this.selected = [...(this.rolePermissions[id] ?? [])]; }
                    |      }">
                    |     <div class="mb-space-md flex items-center gap-space-xs">
                    |         <span class="material-symbols-outlined text-[22px] text-primary">verified_user</span>
                    |         <h2 class="text-title-md text-on-surface">Assignee & Role-Based Governance</h2>
                    |     </div>
                    |
                    |     <label for="assignee_id" class="mb-space-xs block text-label-md text-on-surface">Primary Assignee</label>
                    |     <select id="assignee_id" name="assignee_id"
                    |             class="mb-space-md w-full rounded-xl border-0 bg-surface-container-lowest py-2.5 pl-3 pr-8 text-body-md text-on-surface shadow-[0_0_0_1px_rgba(203,213,225,1)] focus:outline-none focus:ring-0 focus:shadow-[0_0_0_2px_rgba(79,70,229,1)]">
                    |         <option value="">Unassigned</option>
                    |         @foreach ($assignableUsers as $user)
                    |             <option value="{{ $user->id }}" @selected(old('assignee_id') == $user->id)>{{ $user->name }}</option>
                    |         @endforeach
                    |     </select>
                    |
                    |     <div class="mb-space-xs flex items-center justify-between">
                    |         <span class="text-label-md text-on-surface">Assigned Task Scope Role</span>
                    |         @can('roles.manage')
                    |             <a href="{{ route('roles.create') }}" class="text-label-sm text-primary hover:underline">+ Create custom role</a>
                    |         @endcan
                    |     </div>
                    |     <div class="mb-space-md grid grid-cols-1 gap-space-sm md:grid-cols-3">
                    |         @foreach ($roles as $role)
                    |             <label class="cursor-pointer">
                    |                 <input type="radio" name="role_id" value="{{ $role->id }}" class="peer sr-only" @click="pickRole({{ $role->id }})" @checked(old('role_id') == $role->id)>
                    |                 <span class="block rounded-xl bg-surface-container-low p-space-md transition-all peer-checked:bg-primary-fixed/50 peer-checked:shadow-[0_0_0_2px_rgba(79,70,229,1)]">
                    |                     <span class="block text-title-sm text-on-surface">{{ $role->name }}</span>
                    |                     <span class="block text-body-sm text-on-surface-variant">{{ $role->description }}</span>
                    |                     <span class="mt-1 block text-label-sm text-on-surface-variant">{{ $role->permissions->count() }} of {{ $permissions->count() }} permissions</span>
                    |                 </span>
                    |             </label>
                    |         @endforeach
                    |     </div>
                    |
                    |     <span class="mb-space-xs block text-label-md text-on-surface">Effective Permissions (task scope)</span>
                    |     <div class="grid grid-cols-1 gap-space-sm md:grid-cols-2">
                    |         @foreach ($permissions as $permission)
                    |             <label class="flex items-center justify-between gap-3 rounded-xl bg-surface-container-low p-space-sm">
                    |                 <span>
                    |                     <span class="block text-title-sm text-on-surface">{{ $permission->label }}</span>
                    |                     <span class="block text-body-sm text-on-surface-variant">{{ $permission->description }}</span>
                    |                 </span>
                    |                 <input type="checkbox" name="permissions[]" value="{{ $permission->id }}"
                    |                        :checked="selected.includes({{ $permission->id }})"
                    |                        class="h-4 w-4 rounded border-outline-variant text-primary-container focus:ring-primary-container/30">
                    |             </label>
                    |         @endforeach
                    |     </div>
                    |
                    |     @error('assignee_id') <p class="mt-1.5 text-body-sm text-error">{{ $message }}</p> @enderror
                    |     @error('role_id')     <p class="mt-1.5 text-body-sm text-error">{{ $message }}</p> @enderror
                    | </div>
                    | @endcan
                    --}} -->
                    @can('tasks.assign')
<div class="rounded-xl bg-white p-6 shadow-sm border border-gray-200"
     x-data="{
         selected: @js(old('permissions', []))
     }">
    <div class="mb-4 flex items-center gap-2">
        <span class="text-xl text-indigo-600">🛡️</span>
        <h2 class="text-lg font-semibold text-gray-900">Assignee & Task Governance</h2>
    </div>

    <!-- Primary Assignee Select -->
    <div class="mb-4">
        <label for="assigned_to" class="mb-2 block text-sm font-medium text-gray-700">Primary Assignee</label>
        <select id="assigned_to" name="assigned_to"
                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            <option value="">Unassigned</option>
            @foreach ($users as $user)
                <option value="{{ $user->id }}" @selected(old('assigned_to', $task->assigned_to ?? '') == $user->id)>
                    {{ $user->name }} ({{ $user->email }})
                </option>
            @endforeach
        </select>
        @error('assigned_to') 
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p> 
        @enderror
    </div>

    <!-- Static Task Scope Role Display (Since global Spatie roles are handled via routes) -->
    <div class="mb-2 flex items-center justify-between">
        <span class="text-sm font-medium text-gray-700">Assigned Task Scope Role</span>
    </div>
    
    <div class="mb-4 grid grid-cols-1 gap-4 md:grid-cols-2">
        <div class="rounded-xl border border-indigo-200 bg-indigo-50/50 p-4">
            <span class="block font-semibold text-indigo-900 text-sm">Standard Assignment Scope</span>
            <span class="block text-xs text-indigo-700 mt-0.5">
                Task visibility and access privileges are controlled automatically by global system routing safeguards.
            </span>
        </div>
    </div>
</div>
@endcan


                    {{-- Bottom actions --}}
                    <div class="flex items-center justify-between gap-space-md pt-space-xs">
                        <a href="{{ route('projects.show', $project) }}"
                           class="flex items-center gap-1.5 text-label-md text-on-surface-variant transition-colors hover:text-on-surface">
                            <span class="material-symbols-outlined text-[18px]" aria-hidden="true">arrow_back</span>
                            Discard and back to project
                        </a>

                        {{-- RBAC-CREATE: wrap in @can(...) … @endcan once policies exist --}}
                        <button type="submit"
                                class="flex h-11 items-center gap-2 rounded-xl bg-primary-container px-space-lg text-label-md text-on-primary shadow-md transition-all hover:bg-primary hover:shadow-lg focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-container focus-visible:ring-offset-2">
                            <span class="material-symbols-outlined text-[18px]" aria-hidden="true">add_task</span>
                            Create Task
                        </button>
                    </div>
                </form>

                {{-- RIGHT: live preview --}}
                <aside class="flex flex-col gap-space-lg lg:col-span-4">
                    <div class="rounded-xl bg-surface-container-lowest p-space-lg shadow-sm">
                        <div class="mb-space-md flex items-center justify-between">
                            <div class="flex items-center gap-space-xs">
                                <span class="material-symbols-outlined text-[20px] text-primary" aria-hidden="true">view_kanban</span>
                                <h3 class="text-title-md text-on-surface">Live Preview</h3>
                            </div>
                            <span class="flex items-center gap-1 text-label-sm text-tertiary">
                             
                        </div>

                        <div class="rounded-xl bg-surface-container-lowest p-space-md shadow-[0_0_0_1px_rgba(226,232,240,1),0_1px_2px_rgba(15,23,42,0.04)]">
                            <div class="mb-space-sm flex items-center justify-between gap-2">
                                <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-label-sm"
                                      :class="statusPill[status].pill">
                                    <span class="h-1.5 w-1.5 rounded-full" :class="statusPill[status].dot" aria-hidden="true"></span>
                                    <span x-text="statusPill[status].label"></span>
                                </span>
                                <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-label-sm capitalize"
                                      :class="priorityPill[priority].pill">
                                    <span class="h-1.5 w-1.5 rounded-full" :class="priorityPill[priority].dot" aria-hidden="true"></span>
                                    <span x-text="priority"></span>
                                </span>
                            </div>

                            <h4 class="mb-space-sm text-title-sm text-on-surface" x-text="title.trim() || 'Untitled task'">Untitled task</h4>

                            <p class="mb-space-sm line-clamp-2 whitespace-pre-line text-body-sm text-on-surface-variant"
                               x-show="description.trim()" style="display: none;" x-text="description"></p>

                            <div class="mb-space-sm">
                                <span class="inline-block max-w-full truncate rounded bg-surface-container-low px-2 py-0.5 font-code-sm text-[10px] text-on-surface-variant">
                                    PRJ-{{ str_pad($project->id, 3, '0', STR_PAD_LEFT) }}
                                </span>
                            </div>

                            <div class="flex items-center justify-between border-t border-surface-container-low pt-space-sm text-body-sm text-on-surface-variant">
                                <span class="flex items-center gap-1">
                                    <span class="material-symbols-outlined text-[16px]" aria-hidden="true">calendar_today</span>
                                    <span x-text="dueLabel"></span>
                                </span>

                                {{--
                                | RBAC-PREVIEW: assignee avatar (COMMENTED OUT)
                                | Show the selected assignee's initial here once assignee_id is live.
                                |
                                | <span class="flex h-7 w-7 items-center justify-center rounded-full bg-primary-fixed text-[11px] font-bold uppercase text-on-primary-fixed">?</span>
                                --}}
                            </div>
                        </div>

                        <p class="mt-space-sm text-body-sm text-on-surface-variant">
                            The tile updates as you change the title, due date, priority, or status.
                        </p>
                    </div>

                    {{--
                    | RBAC-INSPECTOR: role summary card (COMMENTED OUT)
                    | Lists each role (manager, team member, custom roles) with a short
                    | description of what it can do on this task.
                    |
                    | <div class="rounded-xl bg-surface-container-lowest p-space-lg shadow-sm">
                    |     <div class="mb-space-md flex items-center justify-between">
                    |         <h3 class="text-title-md text-on-surface">Role Summary</h3>
                    |         <span class="rounded-full bg-primary-fixed px-2 py-0.5 font-code-sm text-[10px] text-on-primary-fixed">PRJ-{{ str_pad($project->id, 3, '0', STR_PAD_LEFT) }}</span>
                    |     </div>
                    |     @foreach ($roles as $role)
                    |         <div class="mb-space-sm rounded-xl bg-surface-container-low p-space-sm">
                    |             <div class="text-title-sm text-on-surface">{{ $role->name }}</div>
                    |             <p class="text-body-sm text-on-surface-variant">{{ $role->description }}</p>
                    |         </div>
                    |     @endforeach
                    | </div>
                    --}}

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
        </div>
    </div>
</x-app-layout>