<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="space-y-1">
                <div class="flex items-center gap-2">

                      
                </div>
                <h1 class="text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">
                    {{ __('Team Members & Role Access') }}
                </h1>
                <p class="text-sm text-slate-500">
                    {{ __('Onboard new staff, modify organizational permissions, and maintain team security.') }}
                </p>
            </div>

            <div class="flex items-center gap-2.5">
                <a href="{{ route('projects.roles.index') }}"
                   class="inline-flex items-center gap-2 rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50 hover:text-slate-900 focus:outline-none focus:ring-2 focus:ring-indigo-600 focus:ring-offset-2">
                    <span class="material-symbols-outlined text-[18px] text-slate-500">security</span>
                    {{ __('Manage Roles') }}
                </a>
            </div>
        </div>
    </x-slot>

    @php
        $formatRole = fn ($role) => str_replace(['_', '-'], ' ', ucwords(str_replace(['-', '_'], ' ', $role)));
        $assignable = collect($users)->reject(fn ($u) => $u->id === auth()->id());
        $totalUsers = count($users);
        $totalManagers = collect($users)->filter(function ($u) {
            $userRoles = $u->roles->pluck('name')->map(fn ($r) => strtolower($r))->all();
            return in_array('manager', $userRoles, true) || strtolower((string)$u->role) === 'manager';
        })->count();
        $totalMembers = $totalUsers - $totalManagers;
    @endphp

    <style>
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
        .delay-300 { animation-delay: 240ms; }
    </style>

    <div class="py-8">
        <div class="mx-auto max-w-[1440px] space-y-8 px-4 sm:px-6 lg:px-8">

            {{-- ───── KPI Stat Summary Cards ───── --}}
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-3 animate-enter">
                <div class="relative overflow-hidden rounded-2xl border border-slate-200/80 bg-white p-6 shadow-sm transition hover:shadow-md">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs font-medium uppercase tracking-wider text-slate-400">{{ __('Total Users') }}</p>
                            <h3 class="mt-2 text-3xl font-extrabold text-slate-900">{{ $totalUsers }}</h3>
                            <p class="mt-1 text-xs text-slate-500">{{ __('Active registered accounts') }}</p>
                        </div>
                        <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-indigo-50 text-indigo-600 ring-1 ring-indigo-500/20">
                            <span class="material-symbols-outlined text-[26px]">groups</span>
                        </div>
                    </div>
                </div>

                <div class="relative overflow-hidden rounded-2xl border border-slate-200/80 bg-white p-6 shadow-sm transition hover:shadow-md">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs font-medium uppercase tracking-wider text-slate-400">{{ __('Team Members') }}</p>
                            <h3 class="mt-2 text-3xl font-extrabold text-emerald-600">{{ $totalMembers }}</h3>
                            <p class="mt-1 text-xs text-slate-500">{{ __('Standard access users') }}</p>
                        </div>
                        <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-emerald-50 text-emerald-600 ring-1 ring-emerald-500/20">
                            <span class="material-symbols-outlined text-[26px]">person</span>
                        </div>
                    </div>
                </div>

                <div class="relative overflow-hidden rounded-2xl border border-slate-200/80 bg-white p-6 shadow-sm transition hover:shadow-md">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs font-medium uppercase tracking-wider text-slate-400">{{ __('Managers / Admins') }}</p>
                            <h3 class="mt-2 text-3xl font-extrabold text-amber-600">{{ $totalManagers }}</h3>
                            <p class="mt-1 text-xs text-slate-500">{{ __('Full project controls') }}</p>
                        </div>
                        <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-amber-50 text-amber-600 ring-1 ring-amber-500/20">
                            <span class="material-symbols-outlined text-[26px]">shield_person</span>
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

            @if ($errors->any())
                <div class="flex items-start gap-3.5 rounded-2xl border border-rose-200 bg-rose-50/80 p-4 text-rose-900 shadow-sm backdrop-blur-sm animate-enter" role="alert">
                    <div class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-rose-500 text-white">
                        <span class="material-symbols-outlined text-[16px]">priority_high</span>
                    </div>
                    <div class="text-sm">
                        <p class="font-semibold">{{ __('Please address the following errors:') }}</p>
                        <ul class="mt-1 list-disc space-y-0.5 pl-5 text-rose-800">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            @endif

            {{-- ───── Onboard user card ───── --}}
            @can('users.create')
                <section class="overflow-hidden rounded-2xl border border-indigo-100 bg-gradient-to-br from-white via-indigo-50/30 to-white shadow-sm ring-1 ring-indigo-500/10 animate-enter delay-100"
                         x-data="{ open: {{ $errors->has('name') || $errors->has('email') || $errors->has('password') || ($errors->has('role') && old('onboard_form')) ? 'true' : 'false' }} }">
                    <div class="flex flex-col gap-4 border-b border-indigo-100/70 p-6 sm:p-8 md:flex-row md:items-center md:justify-between">
                        <div class="space-y-1">
                            <h3 class="text-lg font-bold text-slate-900">{{ __('Onboard New Team Member') }}</h3>
                            <p class="text-sm text-slate-500">{{ __('Directly provision user credentials into the project management workspace.') }}</p>
                        </div>
                        <div>
                            <button type="button" @click="open = !open"
                                    class="inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white shadow-md shadow-indigo-600/20 transition hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-600 focus:ring-offset-2 active:scale-[0.98]">
                                <span class="material-symbols-outlined text-[18px]" x-text="open ? 'expand_less' : 'person_add'"></span>
                                <span x-text="open ? '{{ __('Collapse Onboarding Form') }}' : '{{ __('Onboard User') }}'"></span>
                            </button>
                        </div>
                    </div>

                    <div x-show="open" x-cloak x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 -translate-y-2" x-transition:enter-end="opacity-100 translate-y-0">
                        <form method="POST" action="{{ route('projects.users.onboard') }}" class="p-6 sm:p-8">
                            @csrf
                            <input type="hidden" name="onboard_form" value="1">

                            <div class="grid gap-6 md:grid-cols-2">
                                <div>
                                    <label for="onboard_name" class="block text-sm font-semibold text-slate-700">
                                        {{ __('Full Name') }} <span class="text-rose-500">*</span>
                                    </label>
                                    <div class="relative mt-2">
                                        <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400">
                                            <span class="material-symbols-outlined text-[18px]">badge</span>
                                        </div>
                                        <input type="text" id="onboard_name" name="name" value="{{ old('name') }}" required
                                               placeholder="Alex Morgan"
                                               class="block w-full rounded-xl border border-slate-200 bg-white py-3 pl-10 pr-4 text-sm text-slate-900 shadow-sm transition placeholder:text-slate-400 focus:border-indigo-600 focus:outline-none focus:ring-2 focus:ring-indigo-600/30">
                                    </div>
                                </div>

                                <div>
                                    <label for="onboard_email" class="block text-sm font-semibold text-slate-700">
                                        {{ __('Email Address') }} <span class="text-rose-500">*</span>
                                    </label>
                                    <div class="relative mt-2">
                                        <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400">
                                            <span class="material-symbols-outlined text-[18px]">mail</span>
                                        </div>
                                        <input type="email" id="onboard_email" name="email" value="{{ old('email') }}" required
                                               placeholder="alex.morgan@company.com"
                                               class="block w-full rounded-xl border border-slate-200 bg-white py-3 pl-10 pr-4 text-sm text-slate-900 shadow-sm transition placeholder:text-slate-400 focus:border-indigo-600 focus:outline-none focus:ring-2 focus:ring-indigo-600/30">
                                    </div>
                                </div>

                                <div>
                                    <label for="onboard_role" class="block text-sm font-semibold text-slate-700">
                                        {{ __('Assigned Access Role') }}
                                    </label>
                                    <div class="relative mt-2">
                                        <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400">
                                            <span class="material-symbols-outlined text-[18px]">workspace_premium</span>
                                        </div>
                                        <select id="onboard_role" name="role"
                                                class="block w-full appearance-none rounded-xl border border-slate-200 bg-white py-3 pl-10 pr-10 text-sm text-slate-900 shadow-sm transition focus:border-indigo-600 focus:outline-none focus:ring-2 focus:ring-indigo-600/30">
                                            @foreach ($roleNames as $roleName)
                                                <option value="{{ $roleName }}" @selected(old('role', 'team_member') === $roleName)>
                                                    {{ $formatRole($roleName) }}
                                                </option>
                                            @endforeach
                                        </select>
                                        <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3.5 text-slate-400">
                                            <span class="material-symbols-outlined text-[18px]">unfold_more</span>
                                        </div>
                                    </div>
                                </div>

                                <div>
                                    <label for="onboard_password" class="block text-sm font-semibold text-slate-700">
                                        {{ __('Initial Account Password') }} <span class="text-rose-500">*</span>
                                    </label>
                                    <div class="relative mt-2">
                                        <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400">
                                            <span class="material-symbols-outlined text-[18px]">lock</span>
                                        </div>
                                        <input type="password" id="onboard_password" name="password" required minlength="8"
                                               placeholder="Minimum 8 characters"
                                               class="block w-full rounded-xl border border-slate-200 bg-white py-3 pl-10 pr-4 text-sm text-slate-900 shadow-sm transition placeholder:text-slate-400 focus:border-indigo-600 focus:outline-none focus:ring-2 focus:ring-indigo-600/30">
                                    </div>
                                </div>

                                <div class="md:col-span-2">
                                    <label for="onboard_password_confirmation" class="block text-sm font-semibold text-slate-700">
                                        {{ __('Confirm Password') }} <span class="text-rose-500">*</span>
                                    </label>
                                    <div class="relative mt-2">
                                        <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400">
                                            <span class="material-symbols-outlined text-[18px]">lock_reset</span>
                                        </div>
                                        <input type="password" id="onboard_password_confirmation" name="password_confirmation" required minlength="8"
                                               placeholder="Re-enter password for verification"
                                               class="block w-full rounded-xl border border-slate-200 bg-white py-3 pl-10 pr-4 text-sm text-slate-900 shadow-sm transition placeholder:text-slate-400 focus:border-indigo-600 focus:outline-none focus:ring-2 focus:ring-indigo-600/30">
                                    </div>
                                </div>
                            </div>

                            <div class="mt-8 flex items-center justify-end gap-3 border-t border-slate-200/70 pt-6">
                                <button type="button" @click="open = false"
                                        class="rounded-xl border border-slate-300 bg-white px-5 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 focus:outline-none">
                                    {{ __('Cancel') }}
                                </button>
                                <button type="submit"
                                        class="inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-6 py-2.5 text-sm font-semibold text-white shadow-md shadow-indigo-600/20 transition hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-600 focus:ring-offset-2 active:scale-[0.98]">
                                    <span class="material-symbols-outlined text-[18px]">how_to_reg</span>
                                    {{ __('Complete Onboarding') }}
                                </button>
                            </div>
                        </form>
                    </div>
                </section>
            @endcan

            {{-- ───── Main Grid: Role Assignment & Current Team Members ───── --}}
            <div class="grid grid-cols-1 gap-8 lg:grid-cols-12 animate-enter delay-200">

                {{-- Left Column: Role Reassignment Form --}}
                <div class="lg:col-span-4">
                    <section class="sticky top-24 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                        <div class="border-b border-slate-100 bg-slate-50/70 p-6">
                            <div class="flex items-center gap-2 text-indigo-600">
                                <span class="material-symbols-outlined text-[20px]">manage_accounts</span>
                                <h3 class="text-base font-bold text-slate-900">{{ __('Update Role Access') }}</h3>
                            </div>
                            <p class="mt-1 text-xs text-slate-500">{{ __('Switch permissions for existing team members.') }}</p>
                        </div>

                        <form method="POST" action="{{ route('projects.users.roles.assign') }}" class="p-6 space-y-5">
                            @csrf

                            <div>
                                <label for="user_id" class="block text-xs font-semibold uppercase tracking-wider text-slate-500">
                                    {{ __('Select User') }}
                                </label>
                                <select id="user_id" name="user_id" @disabled($assignable->isEmpty())
                                        class="mt-1.5 block w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-900 shadow-sm transition focus:border-indigo-600 focus:outline-none focus:ring-2 focus:ring-indigo-600/30 disabled:cursor-not-allowed disabled:bg-slate-50 disabled:text-slate-400">
                                    @forelse ($assignable as $user)
                                        <option value="{{ $user->id }}">{{ $user->name }} ({{ $user->email }})</option>
                                    @empty
                                        <option value="">{{ __('No other users available') }}</option>
                                    @endforelse
                                </select>
                            </div>

                            <div>
                                <label for="role" class="block text-xs font-semibold uppercase tracking-wider text-slate-500">
                                    {{ __('New Role') }}
                                </label>
                                <select id="role" name="role"
                                        class="mt-1.5 block w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-900 shadow-sm transition focus:border-indigo-600 focus:outline-none focus:ring-2 focus:ring-indigo-600/30">
                                    @foreach ($roleNames as $roleName)
                                        <option value="{{ $roleName }}">{{ $formatRole($roleName) }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <button type="submit" @disabled($assignable->isEmpty())
                                    class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-slate-900 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-slate-900 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50">
                                <span class="material-symbols-outlined text-[18px]">published_with_changes</span>
                                {{ __('Update Permissions') }}
                            </button>
                        </form>
                    </section>
                </div>

                {{-- Right Column: Active Team Members Table --}}
                <div class="lg:col-span-8">
                    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                        <div class="flex flex-col gap-2 border-b border-slate-100 bg-slate-50/70 px-6 py-5 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <h3 class="text-base font-bold text-slate-900">{{ __('Current Team Directory') }}</h3>
                                <p class="text-xs text-slate-500">{{ __('Overview of all user accounts and current security tiers.') }}</p>
                            </div>
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-white px-3 py-1 text-xs font-semibold text-slate-700 shadow-sm ring-1 ring-slate-200">
                                <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
                                {{ count($users) }} {{ __('Total Members') }}
                            </span>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-slate-100">
                                <thead class="bg-slate-50/50">
                                    <tr>
                                        <th scope="col" class="px-6 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                                            {{ __('Member') }}
                                        </th>
                                        <th scope="col" class="px-6 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                                            {{ __('Role Status') }}
                                        </th>
                                        <th scope="col" class="px-6 py-3.5 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">
                                            {{ __('Action') }}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 bg-white">
                                    @foreach ($users as $user)
                                        <tr class="transition hover:bg-slate-50/60">
                                            <td class="px-6 py-4">
                                                <div class="flex items-center gap-3.5">
                                                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-slate-900 text-sm font-bold text-white shadow-sm ring-1 ring-slate-900/10" aria-hidden="true">
                                                        {{ strtoupper(mb_substr($user->name, 0, 1)) }}
                                                    </span>
                                                    <div class="min-w-0">
                                                        <div class="flex items-center gap-2">
                                                            <span class="truncate font-semibold text-slate-900 text-sm">{{ $user->name }}</span>
                                                            @if ($user->id === auth()->id())
                                                                <span class="inline-flex items-center rounded-md bg-indigo-50 px-2 py-0.5 text-[11px] font-semibold text-indigo-700 ring-1 ring-inset ring-indigo-600/20">
                                                                    {{ __('You') }}
                                                                </span>
                                                            @endif
                                                        </div>
                                                        <div class="truncate text-xs text-slate-500">{{ $user->email }}</div>
                                                    </div>
                                                </div>
                                            </td>

                                            <td class="px-6 py-4">
                                                <div class="flex flex-wrap gap-1.5">
                                                    @forelse ($user->roles->pluck('name') as $role)
                                                        @php
                                                            $isManager = strtolower($role) === 'manager';
                                                            $badgeStyle = $isManager
                                                                ? 'bg-amber-50 text-amber-800 ring-amber-600/20'
                                                                : 'bg-emerald-50 text-emerald-800 ring-emerald-600/20';
                                                        @endphp
                                                        <span class="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1 text-xs font-medium ring-1 ring-inset {{ $badgeStyle }}">
                                                            <span class="h-1.5 w-1.5 rounded-full {{ $isManager ? 'bg-amber-500' : 'bg-emerald-500' }}"></span>
                                                            {{ $formatRole($role) }}
                                                        </span>
                                                    @empty
                                                        <span class="inline-flex items-center rounded-lg bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-500 ring-1 ring-inset ring-slate-400/20">
                                                            {{ __('No role assigned') }}
                                                        </span>
                                                    @endforelse
                                                </div>
                                            </td>

                                            <td class="px-6 py-4 text-right text-sm">
                                                @can('roles.delete')
                                                    @if ($user->id !== auth()->id())
                                                        <form action="{{ route('projects.users.destroy', $user) }}" method="POST" class="inline"
                                                              onsubmit="return confirm('Are you sure you want to remove {{ addslashes($user->name) }} from the system?');">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit"
                                                                    class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-rose-600 transition hover:border-rose-200 hover:bg-rose-50 hover:text-rose-700 focus:outline-none focus:ring-2 focus:ring-rose-500/20">
                                                                <span class="material-symbols-outlined text-[15px]">delete</span>
                                                                {{ __('Delete') }}
                                                            </button>
                                                        </form>
                                                    @else
                                                        <span class="text-xs text-slate-400 italic">{{ __('Current user') }}</span>
                                                    @endif
                                                @endcan
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </section>
                </div>

            </div>

        </div>
    </div>
</x-app-layout>