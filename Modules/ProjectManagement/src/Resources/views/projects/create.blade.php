{{-- View: projects.create --}}

{{--
|--------------------------------------------------------------------------
| RBAC NOTES (dynamic roles & permissions — not wired up yet)
|--------------------------------------------------------------------------
| Planned: default roles "manager" and "team member", plus custom roles
| with custom permissions. Everything role/permission/assignee related is
| COMMENTED OUT below so this page works without any RBAC tables/policies.
|
| When RBAC exists, enable these in order:
|   1. Section 3 "Assignment & Governance"  (search for: RBAC-SECTION-3)
|   2. Wrap the Create buttons in @can('projects.create') … @endcan
|      (an undefined ability returns false, so do NOT add @can before the
|      permission is defined, or the buttons will disappear)
|   3. Controller create(): pass $assignableUsers, e.g.
|        'assignableUsers' => User::whereHas('roles')->orderBy('name')->get()
|   4. Controller store(): validate and authorize, e.g.
|        $this->authorize('create', Project::class);
|        'lead_id' => ['nullable', 'exists:users,id'],
|        and only accept lead_id if $request->user()->can('projects.assign')
--}}

<x-app-layout>
    <div class="relative w-full overflow-hidden font-inter"
         @keydown.window.ctrl.enter.prevent="$refs.form.requestSubmit()"
         @keydown.window.meta.enter.prevent="$refs.form.requestSubmit()"
         x-data="{
             name: @js(old('name', '')),
             description: @js(old('description', '')),
             start: @js(old('start_date', '')),
             end: @js(old('end_date', '')),
             preset: 'Custom',
             presets: {
                 'Sprint Roadmap': {
                     name: 'Sprint Roadmap',
                     description: 'Plan and track sprint goals, deliverables, and release milestones.\n\nGoals:\n- \n\nDefinition of done:\n- '
                 },
                 'Design System Migration': {
                     name: 'Design System Migration',
                     description: 'Unify design tokens, components, and documentation into a single shared system.\n\nScope:\n- \n\nNon-goals:\n- '
                 },
                 'Bug Bash': {
                     name: 'Bug Bash / Hardening',
                     description: 'Time-boxed effort to triage, fix, and verify high-impact bugs before release.\n\nTargets:\n- \n\nExit criteria:\n- '
                 },
                 'Security Audit': {
                     name: 'Security Audit',
                     description: 'Review access controls, dependencies, and infrastructure against compliance requirements.\n\nAreas in scope:\n- \n\nDeliverables:\n- '
                 }
             },
             applyPreset(key) {
                 if (key === 'Custom') {
                     if (this.preset !== 'Custom') { this.name = ''; this.description = ''; }
                 } else {
                     this.name = this.presets[key].name;
                     this.description = this.presets[key].description;
                 }
                 this.preset = key;
             },
             parse(v) { return v ? new Date(v + 'T00:00:00') : null; },
             fmt(v) {
                 const d = this.parse(v);
                 return d ? d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short' }) : '';
             },
             get range() {
                 if (this.start && this.end) return this.fmt(this.start) + ' – ' + this.fmt(this.end);
                 if (this.start) return 'Starts ' + this.fmt(this.start);
                 if (this.end) return 'Ends ' + this.fmt(this.end);
                 return 'No dates set';
             },
             get duration() {
                 const s = this.parse(this.start), e = this.parse(this.end);
                 if (!s || !e || e < s) return null;
                 let days = 0;
                 for (let d = new Date(s); d <= e; d.setDate(d.getDate() + 1)) {
                     const w = d.getDay();
                     if (w !== 0 && w !== 6) days++;
                 }
                 const weeks = Math.max(1, Math.ceil(((e - s) / 86400000 + 1) / 7));
                 return weeks + (weeks === 1 ? ' week' : ' weeks') + ' / ' + days + ' working ' + (days === 1 ? 'day' : 'days');
             }
         }">

        {{-- Soft background glow --}}
        <div class="pointer-events-none absolute -left-20 -top-32 h-96 w-96 rounded-full bg-primary/5 blur-3xl"></div>
        <div class="pointer-events-none absolute right-10 top-20 h-80 w-80 rounded-full bg-tertiary-fixed/15 blur-3xl"></div>

        <div class="relative mx-auto max-w-7xl px-6 py-space-xl lg:px-12">

            {{-- Top bar: breadcrumb, title, actions --}}
            <div class="mb-space-lg flex flex-col justify-between gap-space-md border-b border-outline-variant/40 pb-space-lg md:flex-row md:items-end">
                <div class="flex flex-col gap-space-xs">
                    <nav class="flex items-center gap-space-xs text-label-md text-on-surface-variant" aria-label="Breadcrumb">
                        <a href="{{ route('projects.index') }}" class="flex items-center gap-1 transition-colors hover:text-primary">
                            <span class="material-symbols-outlined text-[16px]" aria-hidden="true">folder</span>
                            Projects
                        </a>
                        <span class="font-code-sm text-outline-variant">/</span>
                        <span class="text-title-sm text-on-surface">Create Project</span>
                    </nav>
                    <h1 class="mt-1 text-headline-xl tracking-tight text-on-surface">Create Project</h1>
                    <p class="max-w-2xl text-body-md text-on-surface-variant">
                        Define the project timeline and core details. You can add tasks and adjust the plan right after the project is created.
                    </p>
                </div>

                <div class="flex shrink-0 items-center gap-space-sm">
                    <a href="{{ route('projects.index') }}"
                       class="flex items-center gap-1.5 rounded-xl bg-surface-container-lowest px-space-md py-2 text-label-md text-on-surface shadow-sm transition-all hover:bg-surface-container-low">
                        <span class="material-symbols-outlined text-[18px]" aria-hidden="true">close</span>
                        Cancel
                    </a>

                    {{-- RBAC: wrap in @can('projects.create') … @endcan once permissions exist --}}
                    <button type="submit" form="project-form"
                            class="group flex items-center gap-2 rounded-xl bg-primary-container px-space-lg py-2 text-label-md text-on-primary shadow-md transition-all hover:bg-primary hover:shadow-lg focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-container focus-visible:ring-offset-2">
                        <span class="material-symbols-outlined text-[18px] transition-transform group-hover:rotate-45" aria-hidden="true">add_circle</span>
                        <span>Create Project</span>
                        <kbd class="ml-1 hidden rounded bg-primary/30 px-1.5 py-0.5 font-code-sm text-[10px] text-on-primary-container sm:inline">Ctrl ↵</kbd>
                    </button>
                </div>
            </div>

            <div class="grid grid-cols-1 items-start gap-gutter lg:grid-cols-12">

                {{-- LEFT: form --}}
                <form id="project-form" x-ref="form" action="{{ route('projects.store') }}" method="POST"
                      class="flex flex-col gap-space-lg lg:col-span-8">
                    @csrf

                    {{-- SECTION 1: Core metadata --}}
                    <div class="relative overflow-hidden rounded-xl bg-surface-container-lowest p-space-lg shadow-sm">
                        <div class="absolute inset-x-0 top-0 h-1 bg-gradient-to-r from-primary via-primary-container to-secondary-container"></div>

                        <div class="mb-space-md flex items-center justify-between border-b border-surface-container-low pb-space-sm">
                            <div class="flex items-center gap-space-xs">
                                <span class="material-symbols-outlined text-[22px] text-primary" aria-hidden="true">assignment</span>
                                <h2 class="text-title-md text-on-surface">1. Core Details</h2>
                            </div>
                            <span class="font-code-sm text-code-sm uppercase tracking-wider text-on-surface-variant">Required *</span>
                        </div>

                        {{-- Presets (just pre-fill the fields below) --}}
                        <div class="mb-space-md">
                            <span class="mb-space-xs block text-label-sm uppercase tracking-wider text-on-surface-variant">Quick Template Presets</span>
                            <div class="flex flex-wrap gap-space-xs">
                                @foreach ([
                                    ['Custom',                  'Custom (Blank)',          'tune'],
                                    ['Sprint Roadmap',          'Sprint Roadmap',          'route'],
                                    ['Design System Migration', 'Design System Migration', 'palette'],
                                    ['Bug Bash',                'Bug Bash',                'pest_control'],
                                    ['Security Audit',          'Security Audit',          'security'],
                                ] as [$key, $label, $icon])
                                    <button type="button" @click="applyPreset(@js($key))"
                                            :class="preset === @js($key)
                                                ? 'bg-primary-fixed text-on-primary-fixed shadow-sm'
                                                : 'bg-surface-container-low text-on-surface-variant hover:bg-surface-container'"
                                            class="flex items-center gap-1 rounded-full px-space-sm py-1 text-label-md transition-colors">
                                        <span class="material-symbols-outlined text-[14px]" aria-hidden="true">{{ $icon }}</span>
                                        {{ $label }}
                                    </button>
                                @endforeach
                            </div>
                        </div>

                        {{-- Project name --}}
                        <div class="mb-space-md">
                            <div class="mb-space-xs flex items-center justify-between">
                                <label for="name" class="flex items-center gap-1 text-label-md text-on-surface">
                                    Project Title <span class="text-title-sm text-error">*</span>
                                </label>
                                <span class="font-code-sm text-code-sm text-on-surface-variant" x-text="name.length + ' / 255'">0 / 255</span>
                            </div>
                            <input type="text" id="name" name="name" x-model="name" required maxlength="255" autofocus
                                   placeholder="e.g., Q3 Mobile App Redesign & API Integration"
                                   class="w-full rounded-xl border-0 bg-surface-container-lowest px-space-md py-2.5 text-title-sm text-on-surface transition-shadow placeholder:font-normal placeholder:text-outline focus:outline-none focus:ring-0
                                          {{ $errors->has('name') ? 'shadow-[0_0_0_2px_rgba(186,26,26,1)]' : 'shadow-[0_0_0_1px_rgba(203,213,225,1)] focus:shadow-[0_0_0_2px_rgba(79,70,229,1)]' }}">
                            @error('name')
                                <p class="mt-1.5 flex items-center gap-1 text-body-sm text-error">
                                    <span class="material-symbols-outlined text-[14px]" aria-hidden="true">error</span>
                                    {{ $message }}
                                </p>
                            @else
                                <p class="mt-1.5 flex items-center gap-1 text-body-sm text-on-surface-variant">
                                    <span class="material-symbols-outlined text-[14px] text-primary" aria-hidden="true">info</span>
                                    A clear title helps everyone spot this project quickly in the list.
                                </p>
                            @enderror
                        </div>

                        {{-- Description --}}
                        <div>
                            <label for="description" class="mb-space-xs block text-label-md text-on-surface">Description</label>
                            <textarea id="description" name="description" rows="5" x-model="description"
                                      placeholder="Outline the objectives, scope, and what “done” looks like…"
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

                    {{-- SECTION 2: Timeline --}}
                    <div class="rounded-xl bg-surface-container-lowest p-space-lg shadow-sm">
                        <div class="mb-space-md flex items-center justify-between border-b border-surface-container-low pb-space-sm">
                            <div class="flex items-center gap-space-xs">
                                <span class="material-symbols-outlined text-[22px] text-primary" aria-hidden="true">calendar_month</span>
                                <h2 class="text-title-md text-on-surface">2. Timeline</h2>
                            </div>
                            <div x-show="duration" style="display: none;" x-transition.opacity
                                 class="flex items-center gap-1 rounded-full bg-secondary-fixed px-space-sm py-0.5 text-label-sm text-on-secondary-fixed shadow-sm">
                                <span class="material-symbols-outlined text-[14px]" aria-hidden="true">timelapse</span>
                                <span x-text="'Estimated: ' + duration"></span>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 gap-space-md sm:grid-cols-2">
                            <div>
                                <label for="start_date" class="mb-space-xs block text-label-md text-on-surface">Start Date</label>
                                <div class="relative flex items-center">
                                    <span class="material-symbols-outlined pointer-events-none absolute left-3 text-[18px] text-on-surface-variant" aria-hidden="true">date_range</span>
                                    <input type="date" id="start_date" name="start_date" x-model="start"
                                           class="w-full rounded-xl border-0 bg-surface-container-lowest py-2 pl-10 pr-3 text-body-md text-on-surface focus:outline-none focus:ring-0
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
                                <label for="end_date" class="mb-space-xs block text-label-md text-on-surface">Target Finish</label>
                                <div class="relative flex items-center">
                                    <span class="material-symbols-outlined pointer-events-none absolute left-3 text-[18px] text-on-surface-variant" aria-hidden="true">event_available</span>
                                    <input type="date" id="end_date" name="end_date" x-model="end" :min="start"
                                           class="w-full rounded-xl border-0 bg-surface-container-lowest py-2 pl-10 pr-3 text-body-md text-on-surface focus:outline-none focus:ring-0
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
                    </div>

                    {{--
                    |----------------------------------------------------------------------
                    | RBAC-SECTION-3: Assignment & Governance  (COMMENTED OUT)
                    |----------------------------------------------------------------------
                    | Enable after roles/permissions exist. Needs:
                    |   - $assignableUsers passed from ProjectController@create
                    |   - permission "projects.assign" (e.g. manager role has it,
                    |     team member does not; custom roles can be granted it)
                    |   - 'lead_id' validated + authorized in ProjectController@store
                    |   - a nullable lead_id column on projects (foreignId -> users)
                    |
                    | To enable: delete the Blade comment markers around this block.
                    |
                    | @can('projects.assign')
                    | <div class="rounded-xl bg-surface-container-lowest p-space-lg shadow-sm">
                    |     <div class="mb-space-md flex items-center justify-between border-b border-surface-container-low pb-space-sm">
                    |         <div class="flex items-center gap-space-xs">
                    |             <span class="material-symbols-outlined text-[22px] text-primary">hub</span>
                    |             <h2 class="text-title-md text-on-surface">3. Assignment & Governance</h2>
                    |         </div>
                    |         <span class="text-label-sm text-on-surface-variant">Team</span>
                    |     </div>
                    |
                    |     <label for="lead_id" class="mb-space-xs block text-label-md text-on-surface">
                    |         Project Lead / Owner
                    |     </label>
                    |     <div class="relative flex items-center">
                    |         <span class="material-symbols-outlined pointer-events-none absolute left-3 text-[18px] text-on-surface-variant">person</span>
                    |         <select id="lead_id" name="lead_id"
                    |                 class="w-full appearance-none rounded-xl border-0 bg-surface-container-lowest py-2 pl-10 pr-8 text-body-md text-on-surface shadow-[0_0_0_1px_rgba(203,213,225,1)] focus:outline-none focus:ring-0 focus:shadow-[0_0_0_2px_rgba(79,70,229,1)]">
                    |             <option value="">Unassigned</option>
                    |             @foreach ($assignableUsers as $user)
                    |                 <option value="{{ $user->id }}" @selected(old('lead_id') == $user->id)>
                    |                     {{ $user->name }}
                    |                 </option>
                    |             @endforeach
                    |         </select>
                    |         <span class="material-symbols-outlined pointer-events-none absolute right-2 text-[18px] text-on-surface-variant">expand_more</span>
                    |     </div>
                    |     @error('lead_id')
                    |         <p class="mt-1.5 text-body-sm text-error">{{ $message }}</p>
                    |     @enderror
                    | </div>
                    | @endcan
                    --}}

                    {{-- Bottom actions --}}
                    <div class="flex items-center justify-between gap-space-md pt-space-xs">
                        <a href="{{ route('projects.index') }}"
                           class="flex items-center gap-1.5 text-label-md text-on-surface-variant transition-colors hover:text-on-surface">
                            <span class="material-symbols-outlined text-[18px]" aria-hidden="true">arrow_back</span>
                            Discard and return to Projects
                        </a>

                        <div class="flex items-center gap-space-sm">
                            <span class="hidden font-code-sm text-[11px] text-on-surface-variant sm:inline">Press Ctrl + Enter to submit</span>
                            <button type="submit"
                                    class="flex h-11 items-center gap-2 rounded-xl bg-primary-container px-space-lg text-label-md text-on-primary shadow-md transition-all hover:bg-primary hover:shadow-lg focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-container focus-visible:ring-offset-2">
                                <span class="material-symbols-outlined text-[18px]" aria-hidden="true">rocket_launch</span>
                                Create Project
                            </button>
                        </div>
                    </div>
                </form>

                {{-- RIGHT: live preview & tips --}}
                <aside class="flex flex-col gap-space-lg lg:col-span-4">
                    <div class="relative overflow-hidden rounded-xl bg-surface-container-lowest p-space-md shadow-sm">
                        <div class="pointer-events-none absolute inset-0 bg-gradient-to-br from-tertiary-fixed/15 via-transparent to-transparent"></div>

                        <div class="relative">
                            <div class="mb-space-sm flex items-center justify-between border-b border-surface-container-low pb-space-sm">
                                <div class="flex items-center gap-space-xs">
                                    <span class="material-symbols-outlined text-[20px] text-primary" aria-hidden="true">preview</span>
                                    <h3 class="text-title-md text-on-surface">Preview</h3>
                                </div>
                                <span class="rounded-full bg-surface-container-high px-2 py-0.5 font-code-sm text-[10px] uppercase tracking-wider text-on-surface-variant">Draft</span>
                            </div>

                            <p class="mb-space-sm text-body-sm text-on-surface-variant">This is roughly how the project will look in your project list.</p>

                            <div class="rounded-xl bg-surface-container-lowest p-space-md shadow-[0_0_0_1px_rgba(226,232,240,1),0_1px_2px_rgba(15,23,42,0.04)]">
                                <div class="flex items-start gap-space-sm">
                                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary-fixed text-primary">
                                        <span class="material-symbols-outlined text-[22px]" aria-hidden="true">dashboard</span>
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <h4 class="truncate text-title-sm text-on-surface" x-text="name.trim() || 'Untitled Project'">Untitled Project</h4>
                                        <p class="font-code-sm text-code-sm text-on-surface-variant" x-text="range">No dates set</p>
                                    </div>
                                </div>

                                <p class="mt-space-sm line-clamp-3 whitespace-pre-line text-body-sm text-on-surface-variant"
                                   x-text="description.trim() || 'No description provided yet. Add some text in the form to see it here.'">
                                    No description provided yet.
                                </p>

                                <div class="mt-space-sm">
                                    <div class="mb-1 flex items-center justify-between text-label-sm text-on-surface-variant">
                                        <span>Progress</span>
                                        <span class="font-code-sm">0% complete</span>
                                    </div>
                                    <div class="h-1.5 w-full overflow-hidden rounded-full bg-surface-container-high">
                                        <div class="h-full w-0 rounded-full bg-primary-container"></div>
                                    </div>
                                </div>

                                @auth
                                    <div class="mt-space-sm flex items-center gap-2 border-t border-surface-container-low pt-space-sm text-body-sm text-on-surface-variant">
                                        <span class="flex h-6 w-6 items-center justify-center rounded-full bg-primary-fixed text-[11px] font-bold uppercase text-on-primary-fixed">
                                            {{ Str::substr(auth()->user()->name, 0, 1) }}
                                        </span>
                                        <span class="truncate">Created by {{ auth()->user()->name }}</span>
                                    </div>
                                @endauth
                            </div>
                        </div>
                    </div>

                    <div class="rounded-xl bg-primary-fixed/40 p-space-md shadow-sm">
                        <div class="mb-space-xs flex items-center gap-space-xs text-primary">
                            <span class="material-symbols-outlined text-[20px]" aria-hidden="true">lightbulb</span>
                            <h3 class="text-title-md">Sizing Best Practice</h3>
                        </div>
                        <p class="text-body-sm text-on-surface-variant">
                            Keep projects within a 3 to 6 week window where possible. Larger efforts are easier to track when split into phases, and you can always add more projects later.
                        </p>
                    </div>
                </aside>
            </div>
        </div>
    </div>
</x-app-layout>