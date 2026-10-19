{{-- View: projects.edit --}}

{{--
|--------------------------------------------------------------------------
| RBAC NOTES (dynamic roles & permissions — not wired up yet)
|--------------------------------------------------------------------------
| Planned: default roles "manager" and "team member", plus custom roles
| with custom permissions. Everything role/permission/assignee related is
| COMMENTED OUT below so this page works without any RBAC tables/policies.
|
| When RBAC exists, enable these (search for the RBAC-… markers):
|   1. RBAC-ASSIGNEE   : "Assignment" section (project lead dropdown)
|   2. RBAC-SQUAD      : "Assigned Squad" avatars in the preview card
|   3. RBAC-DELETE     : wrap the Danger Zone in @can('projects.delete')
|   4. RBAC-SAVE       : wrap Save buttons in @can('update', $project)
|      (an undefined ability returns false, so do NOT add @can before the
|      permission/policy exists, or those parts will disappear)
|   5. Controller edit():   pass 'assignableUsers' => User::orderBy('name')->get()
|   6. Controller update(): $this->authorize('update', $project);
|        'lead_id' => ['nullable', 'exists:users,id'],
|        and only accept lead_id if $request->user()->can('projects.assign')
--}}

@php
    $totalTasks     = $project->tasks()->count();
    $completedTasks = $project->tasks()->whereIn('status', ['completed', 'done'])->count();
    $closedPct      = $totalTasks > 0 ? (int) round(($completedTasks / $totalTasks) * 100) : 0;
@endphp

<x-app-layout>
    <div class="relative w-full overflow-hidden font-inter"
         @keydown.window.ctrl.s.prevent="$refs.form.requestSubmit()"
         @keydown.window.meta.s.prevent="$refs.form.requestSubmit()"
         x-data="{
             name: @js(old('name', $project->name)),
             description: @js(old('description', $project->description ?? '')),
             start: @js(old('start_date', $project->start_date?->format('Y-m-d') ?? '')),
             end: @js(old('end_date', $project->end_date?->format('Y-m-d') ?? '')),
             tasks: {{ $totalTasks }},
             done: {{ $completedTasks }},
             statusMap: {
                 'Upcoming': { dot: 'bg-secondary-container', pulse: false },
                 'Ended':    { dot: 'bg-outline',             pulse: false },
                 'Due soon': { dot: 'bg-amber-500',           pulse: true  },
                 'Active':   { dot: 'bg-tertiary',            pulse: false }
             },
             parse(v) { return v ? new Date(v + 'T00:00:00') : null; },
             today() { const t = new Date(); t.setHours(0, 0, 0, 0); return t; },
             fmt(v) {
                 const d = this.parse(v);
                 return d ? d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }) : '';
             },
             get span() {
                 const s = this.parse(this.start), e = this.parse(this.end);
                 if (!s || !e || e < s) return null;
                 return Math.max(1, Math.round((e - s) / 86400000));
             },
             get elapsed() {
                 const s = this.parse(this.start);
                 if (!s || this.span === null) return null;
                 return Math.max(0, Math.min(this.span, Math.round((this.today() - s) / 86400000)));
             },
             get pct() { return this.span === null ? 0 : Math.round((this.elapsed / this.span) * 100); },
             get daysLeft() {
                 const e = this.parse(this.end);
                 return e ? Math.round((e - this.today()) / 86400000) : null;
             },
             get status() {
                 const s = this.parse(this.start);
                 if (s && s > this.today()) return 'Upcoming';
                 if (this.daysLeft !== null && this.daysLeft < 0) return 'Ended';
                 if (this.daysLeft !== null && this.daysLeft <= 7) return 'Due soon';
                 return 'Active';
             },
             get remaining() {
                 const d = this.daysLeft;
                 if (d === null) return null;
                 if (d < 0) return 'Overdue by ' + Math.abs(d) + (Math.abs(d) === 1 ? ' day' : ' days');
                 if (d === 0) return 'Due today';
                 return d + (d === 1 ? ' day remaining' : ' days remaining');
             }
         }">

        {{-- Soft background glow --}}
        <div class="pointer-events-none absolute -left-20 -top-32 h-96 w-96 rounded-full bg-primary/5 blur-3xl"></div>
        <div class="pointer-events-none absolute right-10 top-20 h-80 w-80 rounded-full bg-tertiary-fixed/15 blur-3xl"></div>

        <div class="relative mx-auto max-w-7xl px-6 py-space-xl lg:px-12">

            {{-- Top bar --}}
            <div class="mb-space-lg flex flex-col justify-between gap-space-md md:flex-row md:items-end">
                <div class="flex min-w-0 flex-col gap-space-xs">
                    <nav class="flex items-center gap-2 text-label-md text-on-surface-variant" aria-label="Breadcrumb">
                        <a href="{{ route('projects.index') }}" class="flex items-center gap-1 transition-colors hover:text-primary">
                            <span class="material-symbols-outlined text-[16px]" aria-hidden="true">folder_open</span>
                            Projects
                        </a>
                        <span class="material-symbols-outlined text-[14px]" aria-hidden="true">chevron_right</span>
                        <a href="{{ route('projects.show', $project) }}" class="max-w-[14rem] truncate transition-colors hover:text-primary">{{ $project->name }}</a>
                        <span class="material-symbols-outlined text-[14px]" aria-hidden="true">chevron_right</span>
                        <span class="text-title-sm text-on-surface">Edit Project</span>
                    </nav>

                    <div class="mt-1 flex flex-wrap items-center gap-2">
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-surface-container-high px-2.5 py-0.5 font-code-sm text-[11px] uppercase text-on-surface-variant">
                            <span class="h-1.5 w-1.5 rounded-full bg-primary-container" aria-hidden="true"></span>
                            Project #{{ $project->id }}
                        </span>
                    </div>

                    <h1 class="text-headline-xl tracking-tight text-on-surface">Edit Project</h1>
                    <p class="max-w-2xl text-body-md text-on-surface-variant">
                        Update the project timeline and core details. Changes apply to the project as soon as you save.
                    </p>
                </div>

                <div class="flex shrink-0 items-center gap-space-sm">
                    <a href="{{ route('projects.show', $project) }}"
                       class="rounded-xl bg-surface-container-lowest px-space-md py-2 text-label-md text-on-surface shadow-sm transition-all hover:bg-surface-container-low">
                        Cancel
                    </a>

                    {{-- RBAC-SAVE: wrap in @can('update', $project) … @endcan once policies exist --}}
                    <button type="submit" form="project-form"
                            class="flex items-center gap-2 rounded-xl bg-primary-container px-space-lg py-2 text-label-md text-on-primary shadow-md transition-all hover:bg-primary hover:shadow-lg focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-container focus-visible:ring-offset-2">
                        <span class="material-symbols-outlined text-[18px]" aria-hidden="true">check</span>
                        Save Changes
                        <kbd class="ml-1 hidden rounded bg-primary/30 px-1.5 py-0.5 font-code-sm text-[10px] text-on-primary-container sm:inline">Ctrl S</kbd>
                    </button>
                </div>
            </div>

            <div class="grid grid-cols-1 items-start gap-gutter lg:grid-cols-12">

                {{-- LEFT: form --}}
                <form id="project-form" x-ref="form" action="{{ route('projects.update', $project) }}" method="POST"
                      class="relative overflow-hidden rounded-xl bg-surface-container-lowest shadow-sm lg:col-span-8">
                    @csrf
                    @method('PUT')

                    <div class="h-1.5 w-full bg-gradient-to-r from-primary-container via-secondary to-tertiary-fixed-dim"></div>

                    <div class="flex flex-col gap-space-lg p-space-lg">

                        {{-- Section heading --}}
                        <div class="flex items-start justify-between gap-space-md">
                            <div>
                                <h2 class="text-title-md text-on-surface">Project Details</h2>
                                <p class="text-body-sm text-on-surface-variant">General project specifications and documentation.</p>
                            </div>
                            <span class="rounded-md bg-surface-container-low px-2 py-1 font-code-sm text-[11px] text-on-surface-variant">
                                ID: PRJ-{{ str_pad($project->id, 3, '0', STR_PAD_LEFT) }}
                            </span>
                        </div>

                        {{-- Name --}}
                        <div>
                            <div class="mb-space-xs flex items-center justify-between">
                                <label for="name" class="flex items-center gap-1 text-label-md text-on-surface">
                                    Project Name <span class="text-title-sm text-error">*</span>
                                </label>
                                <span class="font-code-sm text-code-sm text-on-surface-variant" x-text="name.length + '/255'">0/255</span>
                            </div>
                            <input type="text" id="name" name="name" x-model="name" required maxlength="255"
                                   class="w-full rounded-xl border-0 bg-surface-container-lowest px-space-md py-2.5 text-body-lg text-on-surface transition-shadow placeholder:text-outline focus:outline-none focus:ring-0
                                          {{ $errors->has('name') ? 'shadow-[0_0_0_2px_rgba(186,26,26,1)]' : 'shadow-[0_0_0_1px_rgba(203,213,225,1)] focus:shadow-[0_0_0_2px_rgba(79,70,229,1)]' }}">
                            @error('name')
                                <p class="mt-1.5 flex items-center gap-1 text-body-sm text-error">
                                    <span class="material-symbols-outlined text-[14px]" aria-hidden="true">error</span>
                                    {{ $message }}
                                </p>
                            @else
                                <p class="mt-1.5 text-body-sm text-on-surface-variant">Must be explicit and understandable by cross-functional team members.</p>
                            @enderror
                        </div>

                        {{-- Description --}}
                        <div>
                            <label for="description" class="mb-space-xs block text-label-md text-on-surface">Description &amp; Scope</label>
                            <textarea id="description" name="description" rows="6" x-model="description"
                                      class="w-full resize-y rounded-xl border-0 bg-surface-container-lowest p-space-md text-body-lg text-on-surface transition-shadow placeholder:text-outline focus:outline-none focus:ring-0
                                             {{ $errors->has('description') ? 'shadow-[0_0_0_2px_rgba(186,26,26,1)]' : 'shadow-[0_0_0_1px_rgba(203,213,225,1)] focus:shadow-[0_0_0_2px_rgba(79,70,229,1)]' }}"></textarea>
                            @error('description')
                                <p class="mt-1.5 flex items-center gap-1 text-body-sm text-error">
                                    <span class="material-symbols-outlined text-[14px]" aria-hidden="true">error</span>
                                    {{ $message }}
                                </p>
                            @enderror
                        </div>

                        {{-- Dates --}}
                        <div class="grid grid-cols-1 gap-space-md sm:grid-cols-2">
                            <div>
                                <div class="mb-space-xs flex items-center justify-between">
                                    <label for="start_date" class="text-label-md text-on-surface">Start Date</label>
                                    <span class="font-code-sm text-[11px] text-on-surface-variant" x-text="fmt(start)"></span>
                                </div>
                                <div class="relative flex items-center">
                                    <span class="material-symbols-outlined pointer-events-none absolute left-3 text-[18px] text-on-surface-variant" aria-hidden="true">calendar_today</span>
                                    <input type="date" id="start_date" name="start_date" x-model="start"
                                           class="w-full rounded-xl border-0 bg-surface-container-lowest py-2.5 pl-10 pr-3 text-body-md text-on-surface focus:outline-none focus:ring-0
                                                  {{ $errors->has('start_date') ? 'shadow-[0_0_0_2px_rgba(186,26,26,1)]' : 'shadow-[0_0_0_1px_rgba(203,213,225,1)] focus:shadow-[0_0_0_2px_rgba(79,70,229,1)]' }}">
                                </div>
                                @error('start_date')
                                    <p class="mt-1.5 flex items-center gap-1 text-body-sm text-error">
                                        <span class="material-symbols-outlined text-[14px]" aria-hidden="true">error</span>
                                        {{ $message }}
                                    </p>
                                @enderror
                            </div>

                            <div>
                                <div class="mb-space-xs flex items-center justify-between">
                                    <label for="end_date" class="text-label-md text-on-surface">End Date</label>
                                    <span x-show="remaining" style="display: none;"
                                          :class="daysLeft < 0 ? 'bg-error-container text-on-error-container' : (daysLeft <= 7 ? 'bg-amber-100 text-amber-800' : 'bg-surface-container text-on-surface-variant')"
                                          class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-label-sm">
                                        <span class="material-symbols-outlined text-[13px]" aria-hidden="true">alarm</span>
                                        <span x-text="remaining"></span>
                                    </span>
                                </div>
                                <div class="relative flex items-center">
                                    <span class="material-symbols-outlined pointer-events-none absolute left-3 text-[18px] text-on-surface-variant" aria-hidden="true">event_available</span>
                                    <input type="date" id="end_date" name="end_date" x-model="end" :min="start"
                                           class="w-full rounded-xl border-0 bg-surface-container-lowest py-2.5 pl-10 pr-3 text-body-md text-on-surface focus:outline-none focus:ring-0
                                                  {{ $errors->has('end_date') ? 'shadow-[0_0_0_2px_rgba(186,26,26,1)]' : 'shadow-[0_0_0_1px_rgba(203,213,225,1)] focus:shadow-[0_0_0_2px_rgba(79,70,229,1)]' }}">
                                </div>
                                @error('end_date')
                                    <p class="mt-1.5 flex items-center gap-1 text-body-sm text-error">
                                        <span class="material-symbols-outlined text-[14px]" aria-hidden="true">error</span>
                                        {{ $message }}
                                    </p>
                                @enderror
                            </div>
                        </div>

                        {{--
                        |------------------------------------------------------------------
                        | RBAC-ASSIGNEE: Assignment (COMMENTED OUT)
                        |------------------------------------------------------------------
                        | Enable after roles/permissions exist. Needs:
                        |   - $assignableUsers passed from ProjectController@edit
                        |   - permission "projects.assign" (manager has it by default;
                        |     team member does not; custom roles can be granted it)
                        |   - a nullable lead_id column on projects (foreignId -> users)
                        |   - 'lead_id' validated + authorized in ProjectController@update
                        |
                        | To enable: remove the Blade comment markers around this block.
                        |
                        | @can('projects.assign')
                        | <div>
                        |     <h3 class="mb-space-sm text-title-sm text-on-surface">Assignment</h3>
                        |     <div class="rounded-xl bg-surface-container-low p-space-md">
                        |         <label for="lead_id" class="mb-space-xs block text-label-sm uppercase tracking-wider text-on-surface-variant">
                        |             Project Lead
                        |         </label>
                        |         <select id="lead_id" name="lead_id"
                        |                 class="w-full rounded-xl border-0 bg-surface-container-lowest py-2 pl-3 pr-8 text-body-md text-on-surface shadow-[0_0_0_1px_rgba(203,213,225,1)] focus:outline-none focus:ring-0 focus:shadow-[0_0_0_2px_rgba(79,70,229,1)]">
                        |             <option value="">Unassigned</option>
                        |             @foreach ($assignableUsers as $user)
                        |                 <option value="{{ $user->id }}" @selected(old('lead_id', $project->lead_id) == $user->id)>
                        |                     {{ $user->name }}
                        |                 </option>
                        |             @endforeach
                        |         </select>
                        |         @error('lead_id')
                        |             <p class="mt-1.5 text-body-sm text-error">{{ $message }}</p>
                        |         @enderror
                        |     </div>
                        | </div>
                        | @endcan
                        --}}
                    </div>

                    {{-- Form footer --}}
                    <div class="flex flex-col items-center justify-between gap-space-sm bg-surface-container-low px-space-lg py-space-md sm:flex-row">
                        <span class="flex items-center gap-2 text-body-sm text-on-surface-variant">
                            <span class="material-symbols-outlined text-[18px] text-tertiary" aria-hidden="true">cloud_done</span>
                            Last saved {{ $project->updated_at->diffForHumans() }}
                        </span>

                        <div class="flex items-center gap-space-sm">
                            <a href="{{ route('projects.show', $project) }}"
                               class="rounded-xl bg-surface-container-lowest px-space-md py-2 text-label-md text-on-surface shadow-sm transition-all hover:bg-surface-container">
                                Cancel
                            </a>
                            {{-- RBAC-SAVE: wrap in @can('update', $project) … @endcan once policies exist --}}
                            <button type="submit"
                                    class="flex items-center gap-2 rounded-xl bg-primary-container px-space-lg py-2 text-label-md text-on-primary shadow-md transition-all hover:bg-primary hover:shadow-lg focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-container focus-visible:ring-offset-2">
                                <span class="material-symbols-outlined text-[18px]" aria-hidden="true">check_circle</span>
                                Save Changes
                            </button>
                        </div>
                    </div>
                </form>

                {{-- RIGHT: live health preview + danger zone --}}
                <aside class="flex flex-col gap-space-lg lg:col-span-4">

                    <div class="rounded-xl bg-surface-container-lowest p-space-lg shadow-sm">
                        <div class="mb-space-md flex items-center justify-between">
                            <h3 class="text-title-md text-on-surface">Live Health Preview</h3>
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-surface-container-high px-2.5 py-0.5 text-label-sm text-on-surface">
                                <span class="h-2 w-2 rounded-full"
                                      :class="statusMap[status].dot + (statusMap[status].pulse ? ' animate-pulse' : '')"
                                      aria-hidden="true"></span>
                                <span x-text="status"></span>
                            </span>
                        </div>

                        {{-- Timeline burnup --}}
                        <div>
                            <div class="mb-space-xs flex items-center justify-between text-body-sm text-on-surface-variant">
                                <span>Timeline Burnup</span>
                                <template x-if="span !== null">
                                    <span class="font-semibold tabular-nums text-on-surface" x-text="elapsed + ' / ' + span + ' Days (' + pct + '%)'"></span>
                                </template>
                                <template x-if="span === null">
                                    <span class="text-on-surface-variant">Set both dates</span>
                                </template>
                            </div>
                            <div class="h-2 w-full overflow-hidden rounded-full bg-surface-container-high">
                                <div class="h-full rounded-full bg-gradient-to-r from-primary-container to-secondary transition-all duration-500"
                                     :style="'width: ' + pct + '%'"></div>
                            </div>
                            <p class="mt-space-xs text-body-sm text-on-surface-variant" x-show="end" style="display: none;">
                                Target completion:
                                <span class="font-semibold text-on-surface" x-text="fmt(end)"></span>
                                <span x-show="remaining" x-text="'(' + remaining.toLowerCase() + ')'"></span>
                            </p>
                        </div>

                        {{-- Task stats (live from the database) --}}
                        <div class="mt-space-lg grid grid-cols-3 gap-space-sm">
                            <div class="rounded-xl bg-surface-container-low p-space-sm text-center">
                                <div class="text-headline-sm font-bold text-on-surface">{{ $totalTasks }}</div>
                                <div class="text-label-sm text-on-surface-variant">{{ Str::plural('Task', $totalTasks) }}</div>
                            </div>
                            <div class="rounded-xl bg-surface-container-low p-space-sm text-center">
                                <div class="text-headline-sm font-bold text-tertiary">{{ $completedTasks }}</div>
                                <div class="text-label-sm text-on-surface-variant">Closed ({{ $closedPct }}%)</div>
                            </div>
                            <div class="rounded-xl bg-surface-container-low p-space-sm text-center">
                                <div class="text-headline-sm font-bold text-primary">{{ $totalTasks - $completedTasks }}</div>
                                <div class="text-label-sm text-on-surface-variant">Open</div>
                            </div>
                        </div>

                        {{--
                        | RBAC-SQUAD: "Assigned Squad" (COMMENTED OUT)
                        | Enable once projects have members (e.g. project_user pivot).
                        |
                        | <div class="mt-space-lg flex items-center justify-between">
                        |     <span class="text-body-sm text-on-surface-variant">Assigned Squad</span>
                        |     <div class="flex -space-x-2">
                        |         @foreach ($project->members->take(3) as $member)
                        |             <span class="flex h-8 w-8 items-center justify-center rounded-full bg-primary-fixed text-[11px] font-bold uppercase text-on-primary-fixed ring-2 ring-surface-container-lowest">
                        |                 {{ Str::substr($member->name, 0, 1) }}
                        |             </span>
                        |         @endforeach
                        |         @if ($project->members->count() > 3)
                        |             <span class="flex h-8 w-8 items-center justify-center rounded-full bg-surface-container-high text-[11px] font-semibold text-on-surface-variant ring-2 ring-surface-container-lowest">
                        |                 +{{ $project->members->count() - 3 }}
                        |             </span>
                        |         @endif
                        |     </div>
                        | </div>
                        --}}
                    </div>

                    {{-- RBAC-DELETE: wrap this card in @can('projects.delete') … @endcan once permissions exist --}}
                    <div class="rounded-xl bg-surface-container-lowest p-space-lg shadow-sm">
                        <div class="mb-space-sm flex items-center gap-space-xs text-error">
                            <span class="material-symbols-outlined text-[22px]" aria-hidden="true">warning</span>
                            <h3 class="text-title-md">Danger Zone</h3>
                        </div>
                        <p class="mb-space-md text-body-md text-on-surface-variant">
                            Once deleted, all
                            <span class="font-semibold text-on-surface">{{ $totalTasks }} associated {{ Str::plural('task', $totalTasks) }}</span>
                            and related data will be permanently removed. This action cannot be reversed.
                        </p>

                        <form action="{{ route('projects.destroy', $project) }}" method="POST"
                              onsubmit="return confirm(@js('Delete “' . $project->name . '”? All its tasks will be removed. This can’t be undone.'))">
                            @csrf
                            @method('DELETE')
                            <button type="submit"
                                    class="flex w-full items-center justify-center gap-2 rounded-xl bg-error-container px-space-md py-2.5 text-label-md text-on-error-container transition-colors hover:bg-error hover:text-on-error focus:outline-none focus-visible:ring-2 focus-visible:ring-error focus-visible:ring-offset-2">
                                <span class="material-symbols-outlined text-[18px]" aria-hidden="true">delete</span>
                                Delete Project…
                            </button>
                        </form>
                    </div>
                </aside>
            </div>
        </div>
    </div>
</x-app-layout>