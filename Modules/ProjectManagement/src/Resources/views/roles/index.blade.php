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
                    <a href="{{ route('projects.roles.create') }}"
                       class="inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-5 py-2 text-sm font-semibold text-white shadow-md shadow-indigo-600/20 transition hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-600 focus:ring-offset-2 active:scale-[0.98]">
                        <span class="material-symbols-outlined text-[18px]">add</span>
                        {{ __('Create New Role') }}
                    </a>
                @endcan
            </div>
        </div>
    </x-slot>

    @php
        $isPaginator = method_exists($roles, 'total');
        $roleTotal   = $isPaginator ? $roles->total() : $roles->count();
        $visibleChips = 6;
        $totalAssignedUsers = collect($roles->items() ?? $roles)->sum('users_count');
        $totalPermissionsAvailable = \Spatie\Permission\Models\Permission::count();
    @endphp

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

    <div class="py-8">
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
                                                <a href="{{ route('projects.roles.edit', $role) }}"
                                                   class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50 hover:text-slate-900 focus:outline-none focus:ring-2 focus:ring-indigo-600/30">
                                                    <span class="material-symbols-outlined text-[15px]">edit</span>
                                                    {{ __('Edit') }}
                                                </a>
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
                                                                class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-rose-600 transition hover:border-rose-200 hover:bg-rose-50 hover:text-rose-700 focus:outline-none focus:ring-2 focus:ring-rose-500/20">
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
                                            <a href="{{ route('projects.roles.create') }}"
                                               class="mt-4 inline-flex items-center gap-1.5 rounded-xl bg-indigo-600 px-4 py-2 text-xs font-semibold text-white shadow-sm hover:bg-indigo-700">
                                                <span class="material-symbols-outlined text-[16px]">add</span>
                                                {{ __('Create First Role') }}
                                            </a>
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
    </div>
</x-app-layout>
