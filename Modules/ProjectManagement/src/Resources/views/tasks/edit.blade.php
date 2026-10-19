<x-app-layout>
    {{-- Header: breadcrumb + actions. Buttons target the form via the `form` attribute. --}}
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <nav class="flex items-center gap-1.5 text-[13px] text-slate-500" aria-label="Breadcrumb">
                <a href="{{ route('projects.index') }}" class="hover:text-slate-900">Projects</a>
                <span class="text-slate-300">/</span>
                <a href="{{ route('projects.show', $task->project_id) }}" class="hover:text-slate-900">{{ $task->project->name }}</a>
                <span class="text-slate-300">/</span>
                <span class="font-medium text-slate-900">Edit task</span>
            </nav>

            <div class="flex items-center gap-2">
                <a href="{{ route('projects.show', $task->project_id) }}"
                   class="inline-flex h-9 items-center rounded-md border border-slate-200 bg-white px-3.5 text-sm font-medium text-slate-900 hover:border-slate-300 hover:bg-slate-50">
                    Cancel
                </a>
                <button type="submit" form="edit-task-form"
                        class="inline-flex h-9 items-center rounded-md border border-transparent bg-indigo-600 px-4 text-sm font-semibold text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:ring-offset-2">
                    Save changes
                </button>
            </div>
        </div>
    </x-slot>

    <div class="bg-slate-50 py-8"
         
x-data="{
    title: @js(old('title', $task->title)),
    dueDate: @js(old('due_date', $task->due_date?->format('Y-m-d'))),
    priority: @js(old('priority', $task->priority)),
    status: @js(old('status', $task->status)),
    assignedTo: @js((string) old('assigned_to', $task->assigned_to)),
    users: @js($users->mapWithKeys(fn ($u) => [(string) $u->id => ['name' => $u->name, 'email' => $u->email]])),

    setDate(days) {
        const d = new Date();
        d.setDate(d.getDate() + days);
        this.dueDate = d.toISOString().slice(0, 10);
    },

    fmtDate() {
        return this.dueDate
            ? new Date(this.dueDate + 'T00:00').toLocaleDateString(
                undefined,
                { month: 'short', day: 'numeric' }
            )
            : 'No due date';
    },

    assignee() {
        return this.users[this.assignedTo] || null;
    },

    initials(name) {
        return (name || '')
            .split(/\s+/)
            .filter(Boolean)
            .map(word => word[0])
            .slice(0, 2)
            .join('')
            .toUpperCase();
    },

    statusLabel() {
        return {
            pending: 'Pending',
            in_progress: 'In progress',
            completed: 'Completed'
        }[this.status] || this.status;
    }
}" >
        <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
            <form id="edit-task-form"
                  action="{{ route('projects.tasks.update', [$project, $task]) }}"
                  method="POST"
                  class="grid grid-cols-1 gap-6 lg:grid-cols-12">
                @csrf
                @method('PUT')

                {{-- ============ Main column ============ --}}
                <div class="space-y-6 lg:col-span-8">

                    {{-- Task details --}}
                    <section class="rounded-lg bg-white p-6 shadow-[0_0_0_1px_#e2e8f0,0_1px_2px_0_rgba(15,23,42,0.04)]">
                        <h2 class="text-base font-semibold tracking-tight text-slate-900">Task details</h2>

                        <div class="mt-5 space-y-5">
                            <div>
                                <div class="flex items-baseline justify-between">
                                    <label for="title" class="text-[13px] font-medium text-slate-700">
                                        Title <span class="text-rose-500">*</span>
                                    </label>
                                    <span class="text-xs tabular-nums text-slate-400" x-text="title.length + ' / 255'"></span>
                                </div>
                                <input type="text" name="title" id="title" x-model="title" required maxlength="255"
                                       class="mt-1.5 block h-9 w-full rounded-md border-slate-300 px-3 text-sm text-slate-900 placeholder:text-slate-400 focus:border-indigo-600 focus:ring-2 focus:ring-indigo-500/20">
                                @error('title')
                                    <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="description" class="text-[13px] font-medium text-slate-700">Description</label>
                                <textarea name="description" id="description" rows="6"
                                          class="mt-1.5 block w-full rounded-md border-slate-300 px-3 py-2 text-sm text-slate-900 placeholder:text-slate-400 focus:border-indigo-600 focus:ring-2 focus:ring-indigo-500/20">{{ old('description', $task->description) }}</textarea>
                                @error('description')
                                    <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </section>

                    {{-- Planning --}}
                    <section class="rounded-lg bg-white p-6 shadow-[0_0_0_1px_#e2e8f0,0_1px_2px_0_rgba(15,23,42,0.04)]">
                        <h2 class="text-base font-semibold tracking-tight text-slate-900">Planning</h2>

                        <div class="mt-5 space-y-6">
                            {{-- Due date --}}
                            <div>
                                <label for="due_date" class="text-[13px] font-medium text-slate-700">Due date</label>
                                <div class="mt-1.5 flex flex-wrap items-center gap-2">
                                    <input type="date" name="due_date" id="due_date" x-model="dueDate"
                                           class="block h-9 w-full rounded-md border-slate-300 px-3 text-sm text-slate-900 focus:border-indigo-600 focus:ring-2 focus:ring-indigo-500/20 sm:w-52">
                                    <button type="button" @click="setDate(0)"
                                            class="h-7 rounded-md border border-slate-200 bg-white px-2.5 text-xs font-medium text-slate-600 hover:border-slate-300 hover:bg-slate-50">Today</button>
                                    <button type="button" @click="setDate(7)"
                                            class="h-7 rounded-md border border-slate-200 bg-white px-2.5 text-xs font-medium text-slate-600 hover:border-slate-300 hover:bg-slate-50">+1 week</button>
                                    <button type="button" x-show="dueDate" @click="dueDate = ''"
                                            class="h-7 rounded-md px-2.5 text-xs font-medium text-slate-500 hover:bg-slate-100 hover:text-slate-900">Clear</button>
                                </div>
                                @error('due_date')
                                    <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>
                                @enderror
                            </div>

                            {{-- Priority --}}
                            <fieldset>
                                <legend class="text-[13px] font-medium text-slate-700">
                                    Priority <span class="text-rose-500">*</span>
                                </legend>
                                <div class="mt-1.5 grid grid-cols-3 gap-1 rounded-lg bg-slate-100 p-1">
                                    @foreach (['low' => ['Low', 'bg-slate-400'], 'medium' => ['Medium', 'bg-amber-500'], 'high' => ['High', 'bg-rose-500']] as $value => [$label, $dot])
                                        <label class="relative cursor-pointer">
                                            <input type="radio" name="priority" value="{{ $value }}" x-model="priority" required class="peer sr-only">
                                            <span class="flex h-8 items-center justify-center gap-2 rounded-md text-[13px] font-medium text-slate-600 transition-colors hover:text-slate-900 peer-checked:bg-white peer-checked:text-slate-900 peer-checked:shadow-sm peer-checked:ring-1 peer-checked:ring-slate-200 peer-focus-visible:ring-2 peer-focus-visible:ring-indigo-500/40">
                                                <span class="h-1.5 w-1.5 rounded-full {{ $dot }}"></span>{{ $label }}
                                            </span>
                                        </label>
                                    @endforeach
                                </div>
                                @error('priority')
                                    <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>
                                @enderror
                            </fieldset>

                            {{-- Status --}}
                            <fieldset>
                                <legend class="text-[13px] font-medium text-slate-700">
                                    Status <span class="text-rose-500">*</span>
                                </legend>
                                <div class="mt-1.5 grid grid-cols-1 gap-1 rounded-lg bg-slate-100 p-1 sm:grid-cols-3">
                                    @foreach (['pending' => 'Pending', 'in_progress' => 'In progress', 'completed' => 'Completed'] as $value => $label)
                                        <label class="relative cursor-pointer">
                                            <input type="radio" name="status" value="{{ $value }}" x-model="status" required class="peer sr-only">
                                            <span class="flex h-8 items-center justify-center rounded-md text-[13px] font-medium text-slate-600 transition-colors hover:text-slate-900 peer-checked:bg-white peer-checked:text-slate-900 peer-checked:shadow-sm peer-checked:ring-1 peer-checked:ring-slate-200 peer-focus-visible:ring-2 peer-focus-visible:ring-indigo-500/40">
                                                {{ $label }}
                                            </span>
                                        </label>
                                    @endforeach
                                </div>
                                @error('status')
                                    <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>
                                @enderror
                            </fieldset>
                        </div>
                    </section>

                    {{-- Assignment: renders only if controller passes $users --}}
                    @isset($users)
                        <section class="rounded-lg bg-white p-6 shadow-[0_0_0_1px_#e2e8f0,0_1px_2px_0_rgba(15,23,42,0.04)]">
                            <h2 class="text-base font-semibold tracking-tight text-slate-900">Assignment</h2>
                            <p class="mt-0.5 text-[13px] text-slate-500">Who is responsible for this task.</p>

                            <div class="mt-5">
                                <label for="assigned_to" class="text-[13px] font-medium text-slate-700">Assignee</label>
                                <select name="assigned_to" id="assigned_to" x-model="assignedTo"
                                        class="mt-1.5 block h-9 w-full rounded-md border-slate-300 px-3 text-sm text-slate-900 focus:border-indigo-600 focus:ring-2 focus:ring-indigo-500/20">
                                    <option value="">Unassigned</option>
                                    
                                    @foreach ($users as $user)
                                        <option value="{{ $user->id }}" @selected(old('assigned_to', $task->assigned_to) == $user->id)>
                                            {{ $user->name }} ({{ $user->email }})
                                        </option>
                                    @endforeach
                                </select>
                                @error('assigned_to')
                                    <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>
                                @enderror
                            </div>

                            {{-- Selected assignee card --}}
                            <div class="mt-4 rounded-lg bg-slate-50 p-3.5 ring-1 ring-slate-200">
                                <div x-show="assignee()" class="flex items-center gap-3">
                                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-indigo-50 text-xs font-semibold text-indigo-700 ring-1 ring-indigo-200"
                                          x-text="initials(assignee()?.name)"></span>
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-semibold text-slate-900" x-text="assignee()?.name"></p>
                                        <p class="truncate text-xs text-slate-500" x-text="assignee()?.email"></p>
                                    </div>
                                </div>
                                <p x-show="!assignee()" class="text-sm text-slate-500">Unassigned</p>
                            </div>
                        </section>
                    @endisset

                    {{-- Access: driven entirely by DB roles (2 system roles + custom). Renders only if controller passes $roles. --}}
                   
                </div>

                {{-- ============ Sidebar ============ --}}
                <aside class="lg:col-span-4">
                    <div class="space-y-6 lg:sticky lg:top-6">
                        <section class="rounded-lg bg-white p-5 shadow-[0_0_0_1px_#e2e8f0,0_1px_2px_0_rgba(15,23,42,0.04)]">
                            <h2 class="text-[11px] font-medium uppercase tracking-wide text-slate-500">Board preview</h2>

                            <div class="mt-3 rounded-lg border border-slate-200 bg-white p-3.5 shadow-sm">
                                <div class="flex items-center justify-between gap-2">
                                    <span class="inline-flex h-6 items-center rounded-full border px-2 text-xs font-medium"
                                          :class="{
                                            'border-slate-200 bg-slate-100 text-slate-600': status === 'pending',
                                            'border-sky-200 bg-sky-50 text-sky-700': status === 'in_progress',
                                            'border-emerald-200 bg-emerald-50 text-emerald-800': status === 'completed',
                                          }"
                                          x-text="statusLabel()"></span>
                                    <span class="inline-flex h-6 items-center rounded-full border px-2 text-xs font-medium capitalize"
                                          :class="{
                                            'border-slate-200 bg-slate-100 text-slate-600': priority === 'low',
                                            'border-amber-200 bg-amber-50 text-amber-800': priority === 'medium',
                                            'border-rose-200 bg-rose-50 text-rose-700': priority === 'high',
                                          }"
                                          x-text="priority"></span>
                                </div>
                                <p class="mt-3 break-words text-sm font-semibold text-slate-900"
                                   x-text="title || 'Untitled task'"></p>
                                <div x-show="assignee()" class="mt-3 flex items-center gap-2 border-t border-slate-100 pt-3">
                                    <span class="flex h-6 w-6 items-center justify-center rounded-full bg-indigo-50 text-[10px] font-semibold text-indigo-700 ring-1 ring-indigo-200"
                                          x-text="initials(assignee()?.name)"></span>
                                    <span class="truncate text-xs text-slate-600" x-text="assignee()?.name"></span>
                                </div>
                                <p class="mt-3 flex items-center gap-1.5 text-xs text-slate-500">
                                    <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                        <path fill-rule="evenodd" d="M6 2a1 1 0 0 1 1 1v1h6V3a1 1 0 1 1 2 0v1h1a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h1V3a1 1 0 0 1 1-1Zm-2 6v8h12V8H4Z" clip-rule="evenodd"/>
                                    </svg>
                                    <span class="tabular-nums" x-text="fmtDate()"></span>
                                </p>
                            </div>
                            <p class="mt-3 text-xs text-slate-500">Updates as you edit. Nothing is saved until you click Save changes.</p>
                        </section>

                        <p class="px-1 text-xs text-slate-500">
                            Last updated <span class="tabular-nums">{{ $task->updated_at->diffForHumans() }}</span>
                        </p>
                    </div>
                </aside>
            </form>
        </div>
    </div>
</x-app-layout>