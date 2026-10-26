@php
    $isPaginator = method_exists($roles, 'total');
    $roleTotal   = $isPaginator ? $roles->total() : $roles->count();
    $visibleChips = 6;
    $totalAssignedUsers = collect($roles->items() ?? $roles)->sum('users_count');
    $totalPermissionsAvailable = \Spatie\Permission\Models\Permission::count();
    $selectedOld = old('permissions', []);
    $assignable = $assignablePermissions ?? [];
    $formatPerm = fn ($p) => str_replace(['.', '-', '_'], ' ', ucwords(str_replace(['.', '-', '_'], ' ', $p)));
    $formatGroup = fn ($g) => ucwords($g);
    $groupIcons = [
        'Projects' => 'folder_open',
        'Tasks' => 'checklist',
        'Attachments' => 'attach_file',
        'User & Role Management' => 'manage_accounts',
    ];
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="space-y-1">
                <h1 class="text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">
                    {{ __('Roles & Permissions') }}
                </h1>
                <p class="text-sm text-slate-500">
                    {{ __('Configure user security tiers and fine-grained capability rules across the project module.') }}
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                @can('roles.assign-permissions')
                    @if (\Illuminate\Support\Facades\Route::has('projects.users.roles.index'))
                        <a href="{{ route('projects.users.roles.index') }}"
                           class="inline-flex items-center gap-2 rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50 hover:text-slate-900 focus:outline-none focus:ring-2 focus:ring-indigo-600 focus:ring-offset-2">
                            <span class="material-symbols-outlined text-[18px] text-slate-500">group</span>
                            {{ __('Team Members & Onboarding') }}
                        </a>
                    @endif
                @endcan
                @can('roles.create')
                    <button type="button"
                            onclick="window.dispatchEvent(new CustomEvent('open-role-modal'))"
                            class="inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-5 py-2 text-sm font-semibold text-white shadow-md shadow-indigo-600/20 transition hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-600 focus:ring-offset-2 active:scale-[0.98]">
                        <span class="material-symbols-outlined text-[18px]">add</span>
                        {{ __('Create New Role') }}
                    </button>
                @endcan
            </div>
        </div>
    </x-slot>

    <style>
        [x-cloak] { display: none !important; }
        @keyframes fadeInSlideUp {
            from {
                opacity: 0;
                transform: translateY(14px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        .animate-enter {
            animation: fadeInSlideUp 0.45s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        }
        .delay-100 { animation-delay: 80ms; }
        .delay-200 { animation-delay: 160ms; }
    </style>

    <div class="py-8"
         x-data="{
             modalOpen: {{ ($errors->has('name') || $errors->has('permissions') || $errors->has('permissions.*')) ? 'true' : 'false' }},
             modalMode: 'create', // 'create' or 'edit'
             activeTab: 'details',
             roleName: '{{ old('name', '') }}',
             description: '',
             isBuiltIn: false,
             formAction: '{{ route('projects.roles.store') }}',
             selectedPerms: {{ \Illuminate\Support\Js::from(old('permissions', [])) }},
             openCreateModal() {
                 this.modalMode = 'create';
                 this.activeTab = 'details';
                 this.roleName = '';
                 this.description = '';
                 this.isBuiltIn = false;
                 this.formAction = '{{ route('projects.roles.store') }}';
                 this.selectedPerms = [];
                 this.modalOpen = true;
             },
             openEditModal(role, perms, builtIn, updateUrl) {
                 this.modalMode = 'edit';
                 this.activeTab = 'details';
                 this.roleName = role.name;
                 this.description = '';
                 this.isBuiltIn = builtIn;
                 this.formAction = updateUrl;
                 this.selectedPerms = Array.isArray(perms) ? [...perms] : [];
                 this.modalOpen = true;
             },
             isPermChecked(perm) {
                 return this.selectedPerms.includes(perm);
             },
             togglePerm(perm) {
                 const idx = this.selectedPerms.indexOf(perm);
                 if (idx > -1) {
                     this.selectedPerms.splice(idx, 1);
                 } else {
                     this.selectedPerms.push(perm);
                 }
             },
             selectGroup(groupPerms) {
                 groupPerms.forEach(p => {
                     if (!this.selectedPerms.includes(p)) {
                         this.selectedPerms.push(p);
                     }
                 });
             },
             deselectGroup(groupPerms) {
                 this.selectedPerms = this.selectedPerms.filter(p => !groupPerms.includes(p));
             },
             toggleAllGlobal(state, allAssignable) {
                 if (state) {
                     this.selectedPerms = [...allAssignable];
                 } else {
                     this.selectedPerms = [];
                 }
             }
         }"
         x-on:open-role-modal.window="openCreateModal()"
         x-on:keydown.escape.window="modalOpen = false">
        <div class="mx-auto max-w-[1440px] space-y-8 px-4 sm:px-6 lg:px-8">

            {{-- ───── KPI Stat Overview Cards ───── --}}
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-3 animate-enter">
                <div class="relative overflow-hidden rounded-2xl border border-slate-200/80 bg-white p-6 shadow-sm transition hover:shadow-md">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs font-medium uppercase tracking-wider text-slate-400">{{ __('Configured Roles') }}</p>
                            <h3 class="mt-2 text-3xl font-extrabold text-slate-900">{{ $roleTotal }}</h3>
                            <p class="mt-1 text-xs text-slate-500">{{ __('Available access levels') }}</p>
                        </div>
                        <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-indigo-50 text-indigo-600 ring-1 ring-indigo-500/20">
                            <span class="material-symbols-outlined text-[26px]">security</span>
                        </div>
                    </div>
                </div>

                <div class="relative overflow-hidden rounded-2xl border border-slate-200/80 bg-white p-6 shadow-sm transition hover:shadow-md">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs font-medium uppercase tracking-wider text-slate-400">{{ __('Active Users Assigned') }}</p>
                            <h3 class="mt-2 text-3xl font-extrabold text-emerald-600">{{ $totalAssignedUsers }}</h3>
                            <p class="mt-1 text-xs text-slate-500">{{ __('Users mapped to active roles') }}</p>
                        </div>
                        <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-emerald-50 text-emerald-600 ring-1 ring-emerald-500/20">
                            <span class="material-symbols-outlined text-[26px]">how_to_reg</span>
                        </div>
                    </div>
                </div>

                <div class="relative overflow-hidden rounded-2xl border border-slate-200/80 bg-white p-6 shadow-sm transition hover:shadow-md">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs font-medium uppercase tracking-wider text-slate-400">{{ __('Permission Rules') }}</p>
                            <h3 class="mt-2 text-3xl font-extrabold text-amber-600">{{ $totalPermissionsAvailable }}</h3>
                            <p class="mt-1 text-xs text-slate-500">{{ __('Granular capability tokens') }}</p>
                        </div>
                        <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-amber-50 text-amber-600 ring-1 ring-amber-500/20">
                            <span class="material-symbols-outlined text-[26px]">lock_clock</span>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ───── Notifications / Alerts ───── --}}
            @if (session('success'))
                <div class="flex items-start gap-3.5 rounded-2xl border border-emerald-200 bg-emerald-50/80 p-4 text-emerald-900 shadow-sm backdrop-blur-sm animate-enter" role="status">
                    <div class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-emerald-500 text-white">
                        <span class="material-symbols-outlined text-[16px]">check</span>
                    </div>
                    <div class="text-sm font-medium pt-0.5">
                        {{ session('success') }}
                    </div>
                </div>
            @endif

            @if (session('error'))
                <div class="flex items-start gap-3.5 rounded-2xl border border-rose-200 bg-rose-50/80 p-4 text-rose-900 shadow-sm backdrop-blur-sm animate-enter" role="alert">
                    <div class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-rose-500 text-white">
                        <span class="material-symbols-outlined text-[16px]">priority_high</span>
                    </div>
                    <div class="text-sm font-medium pt-0.5">
                        {{ session('error') }}
                    </div>
                </div>
            @endif

            {{-- ───── Roles Table Section ───── --}}
            <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm animate-enter delay-100" aria-label="Roles">
                <div class="flex flex-col gap-2 border-b border-slate-100 bg-slate-50/70 px-6 py-5 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h3 class="text-base font-bold text-slate-900">{{ __('Security Roles Directory') }}</h3>
                        <p class="text-xs text-slate-500">{{ __('View permission allocations and connected user counts.') }}</p>
                    </div>
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-white px-3 py-1 text-xs font-semibold text-slate-700 shadow-sm ring-1 ring-slate-200">
                        <span class="h-2 w-2 rounded-full bg-indigo-500"></span>
                        {{ $roleTotal }} {{ Str::plural('Role', $roleTotal) }}
                    </span>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-100 text-left">
                        <thead class="bg-slate-50/50">
                            <tr>
                                <th scope="col" class="w-64 px-6 py-3.5 text-xs font-semibold uppercase tracking-wider text-slate-500">
                                    {{ __('Role Name') }}
                                </th>
                                <th scope="col" class="px-6 py-3.5 text-xs font-semibold uppercase tracking-wider text-slate-500">
                                    {{ __('Attached Permissions') }}
                                </th>
                                <th scope="col" class="w-28 px-6 py-3.5 text-xs font-semibold uppercase tracking-wider text-slate-500 text-center">
                                    {{ __('Users') }}
                                </th>
                                <th scope="col" class="w-48 px-6 py-3.5 text-xs font-semibold uppercase tracking-wider text-slate-500 text-right">
                                    {{ __('Actions') }}
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            @forelse ($roles as $role)
                                @php
                                    $permNames = $role->permissions->pluck('name')->values();
                                    $permCount = $permNames->count();
                                    $shown     = $permNames->take($visibleChips);
                                    $hidden    = $permNames->slice($visibleChips);
                                    $builtInKey = strtolower(str_replace([' ', '-'], '_', $role->name));
                                    $isBuiltIn = in_array($builtInKey, ['manager', 'team_member'], true);
                                    $roleLabel = str_replace(['_', '-'], ' ', ucwords(str_replace(['-', '_'], ' ', $role->name)));
                                    $isManager = str_contains(strtolower($role->name), 'manager');
                                @endphp
                                <tr class="transition hover:bg-slate-50/60 align-top" x-data="{ expanded: false }">
                                    {{-- Role Name --}}
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-3.5">
                                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl {{ $isManager ? 'bg-amber-500' : 'bg-slate-900' }} text-sm font-bold text-white shadow-sm ring-1 ring-slate-900/10" aria-hidden="true">
                                                {{ Str::substr($role->name, 0, 1) }}
                                            </span>
                                            <div class="min-w-0">
                                                <div class="flex items-center gap-2">
                                                    <span class="truncate font-bold text-slate-900 text-sm">{{ $roleLabel }}</span>
                                                    @if ($isBuiltIn)
                                                        <span class="inline-flex items-center rounded-md bg-slate-100 px-2 py-0.5 text-[10px] font-semibold text-slate-600 ring-1 ring-inset ring-slate-200">
                                                            {{ __('System Core') }}
                                                        </span>
                                                    @endif
                                                </div>
                                                <div class="truncate text-xs text-slate-400 mt-0.5">Guard: {{ $role->guard_name }}</div>
                                            </div>
                                        </div>
                                    </td>

                                    {{-- Permissions Chips --}}
                                    <td class="px-6 py-4">
                                        @if ($permCount === 0)
                                            <span class="inline-flex items-center gap-1.5 rounded-lg bg-amber-50 px-2.5 py-1 text-xs font-medium text-amber-700 ring-1 ring-inset ring-amber-600/20">
                                                <span class="material-symbols-outlined text-[15px]" aria-hidden="true">warning</span>
                                                {{ __('No permissions assigned') }}
                                            </span>
                                        @else
                                            <div class="flex flex-wrap items-center gap-1.5">
                                                @foreach ($shown as $perm)
                                                    <span class="inline-flex items-center rounded-md bg-slate-100 px-2 py-1 font-mono text-[11px] font-medium text-slate-700 ring-1 ring-inset ring-slate-200">
                                                        {{ $perm }}
                                                    </span>
                                                @endforeach
                                                @foreach ($hidden as $perm)
                                                    <span x-show="expanded" x-cloak class="inline-flex items-center rounded-md bg-slate-100 px-2 py-1 font-mono text-[11px] font-medium text-slate-700 ring-1 ring-inset ring-slate-200">
                                                        {{ $perm }}
                                                    </span>
                                                @endforeach
                                                @if ($hidden->isNotEmpty())
                                                    <button type="button"
                                                            x-on:click="expanded = !expanded"
                                                            x-bind:aria-expanded="expanded.toString()"
                                                            class="inline-flex items-center rounded-md bg-indigo-50 px-2.5 py-1 text-xs font-semibold text-indigo-700 transition hover:bg-indigo-100">
                                                        <span x-show="!expanded">+{{ $hidden->count() }} more</span>
                                                        <span x-show="expanded" x-cloak>Show less</span>
                                                    </button>
                                                @endif
                                            </div>
                                            <p class="mt-2 text-[11px] text-slate-400 font-medium">
                                                {{ $permCount }} total {{ Str::plural('capability', $permCount) }} enabled
                                            </p>
                                        @endif
                                    </td>

                                    {{-- Users count --}}
                                    <td class="px-6 py-4 text-center">
                                        <span class="inline-flex items-center justify-center gap-1.5 rounded-full px-3 py-1 text-xs font-bold {{ $role->users_count > 0 ? 'bg-indigo-50 text-indigo-700 ring-1 ring-indigo-500/20' : 'bg-slate-100 text-slate-400' }}">
                                            <span class="material-symbols-outlined text-[16px]">groups</span>
                                            {{ $role->users_count }}
                                        </span>
                                    </td>

                                    {{-- Actions --}}
                                    <td class="px-6 py-4 text-right">
                                        <div class="flex items-center justify-end gap-2">
                                            @can('roles.edit')
                                                <button type="button"
                                                        @click="openEditModal({ id: {{ $role->id }}, name: '{{ addslashes($role->name) }}' }, {{ \Illuminate\Support\Js::from($permNames) }}, {{ $isBuiltIn ? 'true' : 'false' }}, '{{ route('projects.roles.update', $role) }}')"
                                                        class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50 hover:text-slate-900 focus:outline-none focus:ring-2 focus:ring-indigo-600/30 active:scale-95">
                                                    <span class="material-symbols-outlined text-[15px]">edit</span>
                                                    {{ __('Edit') }}
                                                </button>
                                            @endcan

                                            @can('roles.delete')
                                                @if ($isBuiltIn)
                                                    <span class="inline-flex items-center gap-1 rounded-lg bg-slate-50 px-2.5 py-1.5 text-xs font-medium text-slate-400" title="Core roles are protected from deletion">
                                                        <span class="material-symbols-outlined text-[15px]">lock</span>
                                                        {{ __('Locked') }}
                                                    </span>
                                                @else
                                                    <form action="{{ route('projects.roles.destroy', $role) }}"
                                                          method="POST"
                                                          class="inline"
                                                          onsubmit="return confirm('Delete role &quot;{{ addslashes($role->name) }}&quot;? Users with this role will be safely moved to Team member.');">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit"
                                                                class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-rose-600 transition hover:border-rose-200 hover:bg-rose-50 hover:text-rose-700 focus:outline-none focus:ring-2 focus:ring-rose-500/20 active:scale-95">
                                                            <span class="material-symbols-outlined text-[15px]">delete</span>
                                                            {{ __('Delete') }}
                                                        </button>
                                                    </form>
                                                @endif
                                            @endcan
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-6 py-14 text-center">
                                        <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-indigo-50 text-indigo-600">
                                            <span class="material-symbols-outlined text-[28px]">admin_panel_settings</span>
                                        </div>
                                        <h4 class="mt-3 text-sm font-bold text-slate-900">{{ __('No roles established yet') }}</h4>
                                        <p class="mt-1 text-xs text-slate-500">{{ __('Create security roles to configure user capabilities.') }}</p>
                                        @can('roles.create')
                                            <button type="button"
                                                    @click="openCreateModal()"
                                                    class="mt-4 inline-flex items-center gap-1.5 rounded-xl bg-indigo-600 px-4 py-2 text-xs font-semibold text-white shadow-sm hover:bg-indigo-700 active:scale-95">
                                                <span class="material-symbols-outlined text-[16px]">add</span>
                                                {{ __('Create First Role') }}
                                            </button>
                                        @endcan
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            @if ($isPaginator && $roles->hasPages())
                <div class="pt-2">{{ $roles->links() }}</div>
            @endif

        </div>

        {{-- ───── Modal Dialog: In-Place Create & Edit Role ───── --}}
        <div x-show="modalOpen"
             x-cloak
             class="fixed inset-0 z-[100] flex items-center justify-center p-4 sm:p-6"
             role="dialog"
             aria-modal="true"
             aria-labelledby="role-modal-title">

            {{-- Frosted Backdrop with smooth fade --}}
            <div x-show="modalOpen"
                 x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="opacity-0"
                 x-transition:enter-end="opacity-100"
                 x-transition:leave="transition ease-in duration-200"
                 x-transition:leave-start="opacity-100"
                 x-transition:leave-end="opacity-0"
                 @click="modalOpen = false"
                 class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm"></div>

            {{-- Modal Container with dynamic spring/scale transition --}}
            <div x-show="modalOpen"
                 x-transition:enter="transition ease-out duration-300 transform"
                 x-transition:enter-start="opacity-0 scale-95 translate-y-3"
                 x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                 x-transition:leave="transition ease-in duration-200 transform"
                 x-transition:leave-start="opacity-100 scale-100 translate-y-0"
                 x-transition:leave-end="opacity-0 scale-95 translate-y-3"
                 class="relative z-10 w-full max-w-2xl overflow-hidden rounded-2xl bg-white shadow-2xl border border-slate-200 text-slate-900">

                <form method="POST" :action="formAction">
                    @csrf
                    <template x-if="modalMode === 'edit'">
                        <input type="hidden" name="_method" value="PUT">
                    </template>

                    {{-- Modal Header --}}
                    <div class="px-7 pt-7 pb-4 bg-gradient-to-b from-slate-50/70 to-white">
                        <div class="flex items-start justify-between">
                            <div class="flex items-center gap-3">
                                <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600 ring-1 ring-indigo-500/20 shadow-xs">
                                    <span class="material-symbols-outlined text-[24px]" x-text="modalMode === 'create' ? 'add_moderator' : 'admin_panel_settings'">add_moderator</span>
                                </div>
                                <div>
                                    <h3 id="role-modal-title" class="text-xl font-bold tracking-tight text-slate-900" x-text="modalMode === 'create' ? '{{ __('Create New Role') }}' : '{{ __('Edit Role Permissions') }}'">
                                        {{ __('Create New Role') }}
                                    </h3>
                                    <p class="mt-0.5 text-xs text-slate-500" x-text="modalMode === 'create' ? '{{ __("Define the role's permissions to control user access levels.") }}' : '{{ __('Update permissions and access capabilities for this security role.') }}'">
                                        {{ __("Define the role's permissions to control user access levels across the platform.") }}
                                    </p>
                                </div>
                            </div>
                            <button type="button"
                                    @click="modalOpen = false"
                                    class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-600 focus:outline-none transition">
                                <span class="material-symbols-outlined text-[20px]">close</span>
                            </button>
                        </div>

                        {{-- Tab Navigation with Animated Indicator --}}
                        <div class="mt-6 flex border-b border-slate-200 gap-2 text-sm relative">
                            <button type="button"
                                    @click="activeTab = 'details'"
                                    class="relative pb-3 px-3 font-semibold text-xs transition duration-200 flex items-center gap-2"
                                    :class="activeTab === 'details' ? 'text-indigo-600' : 'text-slate-500 hover:text-slate-800'">
                                <span class="material-symbols-outlined text-[16px]">badge</span>
                                {{ __('Details') }}
                                <span x-show="activeTab === 'details'"
                                      x-transition:enter="transition ease-out duration-250"
                                      x-transition:enter-start="opacity-0 scale-x-50"
                                      x-transition:enter-end="opacity-100 scale-x-100"
                                      class="absolute bottom-0 left-0 right-0 h-0.5 bg-indigo-600 rounded-full"></span>
                            </button>
                            <button type="button"
                                    @click="activeTab = 'permissions'"
                                    class="relative pb-3 px-3 font-semibold text-xs transition duration-200 flex items-center gap-2"
                                    :class="activeTab === 'permissions' ? 'text-indigo-600' : 'text-slate-500 hover:text-slate-800'">
                                <span class="material-symbols-outlined text-[16px]">verified_user</span>
                                {{ __('Permissions') }}
                                <span class="inline-flex items-center justify-center rounded-full bg-indigo-50 px-2 py-0.5 text-[10px] font-bold text-indigo-700 ring-1 ring-inset ring-indigo-600/20"
                                      x-text="selectedPerms.length">0</span>
                                <span x-show="activeTab === 'permissions'"
                                      x-transition:enter="transition ease-out duration-250"
                                      x-transition:enter-start="opacity-0 scale-x-50"
                                      x-transition:enter-end="opacity-100 scale-x-100"
                                      class="absolute bottom-0 left-0 right-0 h-0.5 bg-indigo-600 rounded-full"></span>
                            </button>
                        </div>
                    </div>

                    {{-- Modal Body with Smooth Tab Sliding Animation --}}
                    <div class="px-7 py-5 overflow-hidden">
                        {{-- Tab 1: Details --}}
                        <div x-show="activeTab === 'details'"
                             x-transition:enter="transition ease-out duration-250 transform"
                             x-transition:enter-start="opacity-0 -translate-x-3"
                             x-transition:enter-end="opacity-100 translate-x-0"
                             class="space-y-5">

                            {{-- Built-in Notice if applicable --}}
                            <template x-if="isBuiltIn">
                                <div class="flex items-center gap-3 rounded-xl border border-amber-200 bg-amber-50/70 p-3.5 text-xs text-amber-800">
                                    <span class="material-symbols-outlined text-[18px] text-amber-600 shrink-0">info</span>
                                    <span>{{ __('This is a core system role. Its name is locked to preserve application routing, but you can customize all of its granular permissions in the next tab.') }}</span>
                                </div>
                            </template>

                            <div>
                                <div class="flex items-center justify-between">
                                    <label for="modal_role_name" class="block text-xs font-semibold text-slate-700">
                                        {{ __('Role Name') }} <span class="text-rose-500">*</span>
                                    </label>
                                    <span class="text-[11px] text-slate-400 font-mono" x-text="roleName.length + '/100'">0/100</span>
                                </div>
                                <input type="text"
                                       id="modal_role_name"
                                       name="name"
                                       x-model="roleName"
                                       :disabled="isBuiltIn"
                                       maxlength="100"
                                       required
                                       placeholder="e.g. Administrator, HR Manager, Editor"
                                       class="mt-1.5 block w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-900 shadow-xs transition placeholder:text-slate-400 focus:border-indigo-600 focus:outline-none focus:ring-2 focus:ring-indigo-600/30 disabled:bg-slate-100 disabled:text-slate-500 disabled:cursor-not-allowed">
                                <template x-if="isBuiltIn">
                                    <input type="hidden" name="name" :value="roleName">
                                </template>
                                <x-input-error class="mt-1.5" :messages="$errors->get('name')" />
                            </div>

                            <div>
                                <div class="flex items-center justify-between">
                                    <label for="modal_role_desc" class="block text-xs font-semibold text-slate-700">
                                        {{ __('Description') }}
                                    </label>
                                    <span class="text-[11px] text-slate-400 font-mono" x-text="description.length + '/500'">0/500</span>
                                </div>
                                <textarea id="modal_role_desc"
                                          rows="3"
                                          x-model="description"
                                          maxlength="500"
                                          placeholder="Brief description of the role's purpose and access level"
                                          class="mt-1.5 block w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-900 shadow-xs transition placeholder:text-slate-400 focus:border-indigo-600 focus:outline-none focus:ring-2 focus:ring-indigo-600/30 resize-none"></textarea>
                            </div>

                            {{-- Live Preview Pill --}}
                            

                            <div class="pt-2 flex justify-end">
                                <button type="button"
                                        @click="activeTab = 'permissions'"
                                        class="inline-flex items-center gap-1.5 rounded-xl border border-indigo-200 bg-indigo-50/80 px-4 py-2 text-xs font-semibold text-indigo-700 transition hover:bg-indigo-100 hover:text-indigo-800">
                                    <span>{{ __('Next: Configure Permissions') }}</span>
                                    <span class="material-symbols-outlined text-[16px]">arrow_forward</span>
                                </button>
                            </div>
                        </div>

                        {{-- Tab 2: Permissions --}}
                        <div x-show="activeTab === 'permissions'"
                             x-transition:enter="transition ease-out duration-250 transform"
                             x-transition:enter-start="opacity-0 translate-x-3"
                             x-transition:enter-end="opacity-100 translate-x-0"
                             class="space-y-4 max-h-[50vh] overflow-y-auto pr-1">

                            <div class="flex items-center justify-between pb-2 border-b border-slate-100 sticky top-0 bg-white z-10 pt-1">
                                <div class="flex items-center gap-2">
                                    <span class="text-xs text-slate-500">{{ __('Choose permitted actions:') }}</span>
                                    <span class="text-xs font-bold text-slate-800 font-mono" x-text="'(' + selectedPerms.length + ' / {{ count($assignable) }} active)'"></span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <button type="button"
                                            @click="toggleAllGlobal(true, {{ \Illuminate\Support\Js::from($assignable) }})"
                                            class="text-xs font-semibold text-indigo-600 hover:text-indigo-800 transition">
                                        {{ __('Select All') }}
                                    </button>
                                    <span class="text-slate-300">|</span>
                                    <button type="button"
                                            @click="toggleAllGlobal(false)"
                                            class="text-xs font-medium text-slate-500 hover:text-slate-700 transition">
                                        {{ __('Clear All') }}
                                    </button>
                                </div>
                            </div>

                            @foreach ($permissionGroups as $group => $permissions)
                                @php
                                    $mIcon = $groupIcons[$group] ?? 'lock';
                                    $groupPermsJson = \Illuminate\Support\Js::from(array_values(array_intersect($permissions, $assignable)));
                                @endphp
                                <div class="rounded-xl border border-slate-200/80 bg-slate-50/40 p-3.5 transition hover:border-slate-300">
                                    <div class="flex items-center justify-between pb-2 border-b border-slate-200/60 mb-2.5">
                                        <div class="flex items-center gap-1.5">
                                            <span class="material-symbols-outlined text-[16px] text-indigo-600">{{ $mIcon }}</span>
                                            <span class="text-xs font-bold text-slate-800">{{ $formatGroup($group) }}</span>
                                        </div>
                                        <div class="flex items-center gap-1.5 text-[11px]">
                                            <button type="button"
                                                    @click="selectGroup({{ $groupPermsJson }})"
                                                    class="text-indigo-600 hover:underline font-semibold">
                                                {{ __('All') }}
                                            </button>
                                            <span class="text-slate-300">|</span>
                                            <button type="button"
                                                    @click="deselectGroup({{ $groupPermsJson }})"
                                                    class="text-slate-500 hover:underline">
                                                {{ __('None') }}
                                            </button>
                                        </div>
                                    </div>

                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                        @foreach ($permissions as $permission)
                                            @php
                                                $canAssign = in_array($permission, $assignable, true);
                                            @endphp
                                            <label class="flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-xs text-slate-700 transition hover:border-indigo-300 cursor-pointer"
                                                   :class="isPermChecked('{{ $permission }}') ? 'border-indigo-300 bg-indigo-50/40' : ''">
                                                <input type="checkbox"
                                                       name="permissions[]"
                                                       value="{{ $permission }}"
                                                       :checked="isPermChecked('{{ $permission }}')"
                                                       @click="togglePerm('{{ $permission }}')"
                                                       @disabled(! $canAssign)
                                                       class="rounded border-slate-300 text-indigo-600 shadow-2xs focus:ring-indigo-500">
                                                <span class="truncate font-medium">{{ $formatPerm($permission) }}</span>
                                            </label>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach

                            <x-input-error class="mt-2" :messages="$errors->get('permissions')" />
                            <x-input-error class="mt-2" :messages="$errors->get('permissions.*')" />
                        </div>
                    </div>

                    {{-- Modal Footer --}}
                    <div class="flex items-center justify-between border-t border-slate-100 bg-slate-50/70 px-7 py-4">
                        <div class="text-xs text-slate-500">
                            <span x-show="activeTab === 'permissions'" class="inline-flex items-center gap-1">
                                <span class="material-symbols-outlined text-[15px] text-indigo-600">verified</span>
                                <span x-text="selectedPerms.length + ' capabilities selected'"></span>
                            </span>
                        </div>
                        <div class="flex items-center gap-3">
                            <button type="button"
                                    @click="modalOpen = false"
                                    class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-xs font-semibold text-slate-700 transition hover:bg-slate-50 focus:outline-none active:scale-95">
                                {{ __('Cancel') }}
                            </button>
                            <button type="submit"
                                    class="inline-flex items-center gap-1.5 rounded-xl bg-indigo-600 px-5 py-2 text-xs font-semibold text-white shadow-sm shadow-indigo-600/20 transition hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-600 focus:ring-offset-2 active:scale-95">
                                <span class="material-symbols-outlined text-[16px]">check</span>
                                <span x-text="modalMode === 'create' ? '{{ __('Create Role') }}' : '{{ __('Save Changes') }}'">{{ __('Save Role') }}</span>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
