<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <a href="{{ route('projects.roles.index') }}"
                   class="grid h-10 w-10 place-items-center rounded-xl border border-slate-200 bg-white text-slate-500 shadow-xs transition hover:border-slate-300 hover:bg-slate-50 hover:text-slate-900 focus:outline-none focus:ring-2 focus:ring-indigo-600 focus:ring-offset-2"
                   aria-label="{{ __('Back to roles') }}">
                    <span class="material-symbols-outlined text-[20px]">arrow_back</span>
                </a>
                <div>
                    <div class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-indigo-600">
                        <span class="material-symbols-outlined text-[16px]">admin_panel_settings</span>
                        {{ __('Role Management') }}
                    </div>
                    <h1 class="text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">
                        {{ __('Create New Role') }}
                    </h1>
                </div>
            </div>

            <a href="{{ route('projects.roles.index') }}"
               class="inline-flex items-center gap-2 rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-xs transition hover:bg-slate-50 hover:text-slate-900 focus:outline-none focus:ring-2 focus:ring-indigo-600 focus:ring-offset-2">
                <span class="material-symbols-outlined text-[18px]">close</span>
                {{ __('Cancel') }}
            </a>
        </div>
    </x-slot>

    @php
        $selectedOld = old('permissions', []);
        $assignable = $assignablePermissions ?? [];
        $formatPerm = fn ($p) => str_replace(['.', '-', '_'], ' ', ucwords(str_replace(['.', '-', '_'], ' ', $p)));
        $formatGroup = fn ($g) => ucwords($g);

        // Group icons
        $groupIcons = [
            'Projects' => 'folder_open',
            'Tasks' => 'checklist',
            'Attachments' => 'attach_file',
            'User & Role Management' => 'manage_accounts',
        ];
    @endphp

    <style>
        @keyframes fadeInSlideUp {
            from {
                opacity: 0;
                transform: translateY(12px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        .animate-enter {
            animation: fadeInSlideUp 0.4s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        }
    </style>

    <div class="py-8" x-data="{
        roleName: '{{ old('name', '') }}',
        selectAll(groupClass) {
            document.querySelectorAll('.' + groupClass).forEach(cb => {
                if (!cb.disabled) cb.checked = true;
            });
        },
        deselectAll(groupClass) {
            document.querySelectorAll('.' + groupClass).forEach(cb => {
                if (!cb.disabled) cb.checked = false;
            });
        },
        toggleAllGlobal(state) {
            document.querySelectorAll('.perm-checkbox').forEach(cb => {
                if (!cb.disabled) cb.checked = state;
            });
        }
    }">
        <div class="mx-auto max-w-4xl space-y-6 px-4 sm:px-6 lg:px-8 animate-enter">

            <form method="POST" action="{{ route('projects.roles.store') }}" class="space-y-6">
                @csrf

                {{-- ───── Role Profile Details Card ───── --}}
                <div class="overflow-hidden rounded-2xl border border-slate-200/90 bg-white shadow-xs">
                    <div class="border-b border-slate-100 bg-slate-50/70 p-6">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2.5">
                                <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600 ring-1 ring-indigo-500/20">
                                    <span class="material-symbols-outlined text-[20px]">badge</span>
                                </span>
                                <div>
                                    <h3 class="text-base font-bold text-slate-900">{{ __('Role Information') }}</h3>
                                    <p class="text-xs text-slate-500">{{ __('Define the identity and organizational title for this access level.') }}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="p-6 sm:p-8 space-y-6">
                        <div>
                            <label for="name" class="block text-sm font-semibold text-slate-700">
                                {{ __('Role Name') }} <span class="text-rose-500">*</span>
                            </label>
                            <p class="text-xs text-slate-500 mt-0.5">{{ __('Example: Quality Analyst, Scrum Master, Technical Reviewer') }}</p>
                            <div class="relative mt-2">
                                <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400">
                                    <span class="material-symbols-outlined text-[20px]">shield_person</span>
                                </div>
                                <input type="text"
                                       id="name"
                                       name="name"
                                       x-model="roleName"
                                       value="{{ old('name') }}"
                                       required
                                       placeholder="Enter unique role title..."
                                       class="block w-full rounded-xl border border-slate-200 bg-white py-3 pl-10 pr-4 text-sm font-medium text-slate-900 shadow-xs transition placeholder:text-slate-400 focus:border-indigo-600 focus:outline-none focus:ring-2 focus:ring-indigo-600/30 @error('name') border-rose-300 ring-2 ring-rose-500/20 @enderror">
                            </div>
                            <x-input-error class="mt-2" :messages="$errors->get('name')" />
                        </div>

                        {{-- Live Preview Pill --}}
                        <div class="rounded-xl border border-dashed border-slate-200 bg-slate-50/60 p-4 flex items-center justify-between">
                            <div class="text-xs text-slate-500">
                                <span class="font-semibold text-slate-700">{{ __('Preview Badge:') }}</span>
                                {{ __('This is how members assigned to this role will appear across projects.') }}
                            </div>
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-indigo-50 px-3 py-1 text-xs font-semibold text-indigo-700 ring-1 ring-inset ring-indigo-600/20">
                                <span class="h-1.5 w-1.5 rounded-full bg-indigo-600"></span>
                                <span x-text="roleName.trim() ? roleName : 'Custom Role'">Custom Role</span>
                            </span>
                        </div>
                    </div>
                </div>

                {{-- ───── Granular Permissions Section ───── --}}
                <div class="overflow-hidden rounded-2xl border border-slate-200/90 bg-white shadow-xs">
                    <div class="border-b border-slate-100 bg-slate-50/70 p-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div class="flex items-center gap-2.5">
                            <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600 ring-1 ring-indigo-500/20">
                                <span class="material-symbols-outlined text-[20px]">tune</span>
                            </span>
                            <div>
                                <h3 class="text-base font-bold text-slate-900">{{ __('Permissions & Capabilities') }}</h3>
                                <p class="text-xs text-slate-500">{{ __('Select the actions users holding this role are authorized to execute.') }}</p>
                            </div>
                        </div>

                        {{-- Global quick select buttons --}}
                        <div class="flex items-center gap-2">
                            <button type="button"
                                    @click="toggleAllGlobal(true)"
                                    class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-2.5 py-1 text-xs font-semibold text-slate-700 shadow-2xs transition hover:bg-slate-50 hover:text-slate-900 focus:outline-none">
                                <span class="material-symbols-outlined text-[14px]">select_all</span>
                                {{ __('Select All') }}
                            </button>
                            <button type="button"
                                    @click="toggleAllGlobal(false)"
                                    class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-2.5 py-1 text-xs font-semibold text-slate-700 shadow-2xs transition hover:bg-slate-50 hover:text-slate-900 focus:outline-none">
                                <span class="material-symbols-outlined text-[14px]">deselect</span>
                                {{ __('Clear All') }}
                            </button>
                        </div>
                    </div>

                    <div class="p-6 sm:p-8 space-y-6">
                        @foreach ($permissionGroups as $group => $permissions)
                            @php
                                $groupSlug = 'group-' . \Illuminate\Support\Str::slug($group);
                                $groupIcon = $groupIcons[$group] ?? 'lock';
                            @endphp

                            <div class="rounded-xl border border-slate-200/90 bg-slate-50/30 p-5 transition hover:border-slate-300">
                                <div class="flex items-center justify-between border-b border-slate-200/70 pb-3">
                                    <div class="flex items-center gap-2">
                                        <span class="material-symbols-outlined text-[18px] text-indigo-600">{{ $groupIcon }}</span>
                                        <h4 class="text-sm font-bold text-slate-800">{{ $formatGroup($group) }}</h4>
                                        <span class="rounded-full bg-slate-200/70 px-2 py-0.5 text-[10px] font-semibold text-slate-600">
                                            {{ count($permissions) }}
                                        </span>
                                    </div>

                                    <div class="flex items-center gap-2 text-xs">
                                        <button type="button"
                                                @click="selectAll('{{ $groupSlug }}')"
                                                class="text-indigo-600 hover:text-indigo-800 font-semibold transition">
                                            {{ __('All') }}
                                        </button>
                                        <span class="text-slate-300">|</span>
                                        <button type="button"
                                                @click="deselectAll('{{ $groupSlug }}')"
                                                class="text-slate-500 hover:text-slate-700 font-medium transition">
                                            {{ __('None') }}
                                        </button>
                                    </div>
                                </div>

                                <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2">
                                    @foreach ($permissions as $permission)
                                        @php
                                            $canAssign = in_array($permission, $assignable, true);
                                            $isChecked = in_array($permission, $selectedOld, true);
                                        @endphp
                                        <label class="group relative flex items-start gap-3 rounded-xl border border-slate-200/80 bg-white p-3 shadow-2xs transition-all {{ $canAssign ? 'hover:border-indigo-300 hover:shadow-xs cursor-pointer' : 'opacity-60 cursor-not-allowed bg-slate-50' }}">
                                            <input type="checkbox"
                                                   name="permissions[]"
                                                   value="{{ $permission }}"
                                                   @checked($isChecked)
                                                   @disabled(! $canAssign)
                                                   class="perm-checkbox {{ $groupSlug }} mt-0.5 h-4 w-4 rounded border-slate-300 text-indigo-600 transition focus:ring-2 focus:ring-indigo-600/30 disabled:cursor-not-allowed">
                                            <div class="min-w-0">
                                                <span class="block text-xs font-semibold text-slate-900 group-hover:text-indigo-600 transition-colors">
                                                    {{ $formatPerm($permission) }}
                                                </span>
                                                <code class="block font-mono text-[10px] text-slate-400 mt-0.5">
                                                    {{ $permission }}
                                                </code>
                                            </div>
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach

                        <x-input-error class="mt-2" :messages="$errors->get('permissions')" />
                        <x-input-error class="mt-2" :messages="$errors->get('permissions.*')" />
                    </div>
                </div>

                {{-- ───── Sticky/Footer Action Bar ───── --}}
                <div class="flex items-center justify-end gap-3 rounded-2xl border border-slate-200/90 bg-white p-5 shadow-xs">
                    <a href="{{ route('projects.roles.index') }}"
                       class="rounded-xl border border-slate-300 bg-white px-5 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-indigo-600 focus:ring-offset-2">
                        {{ __('Cancel') }}
                    </a>
                    <button type="submit"
                            class="inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-6 py-2.5 text-sm font-semibold text-white shadow-md shadow-indigo-600/20 transition hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-600 focus:ring-offset-2 active:scale-[0.98]">
                        <span class="material-symbols-outlined text-[18px]">add_circle</span>
                        {{ __('Create & Save Role') }}
                    </button>
                </div>
            </form>

        </div>
    </div>
</x-app-layout>
