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
                                {{ __('Update Permissions') }}
                            </button>
                        </form>
                    </section>
                </div>

                {{-- Right Column: Active Team Members Interactive Cards --}}
                <div class="lg:col-span-8" x-data="{
                    search: '',
                    selectedFilter: 'all',
                    quickSelectUser(id) {
                        const sel = document.getElementById('user_id');
                        if (sel) {
                            sel.value = id;
                            sel.dispatchEvent(new Event('change'));
                            sel.scrollIntoView({ behavior: 'smooth', block: 'center' });
                            sel.classList.add('ring-2', 'ring-indigo-600');
                            setTimeout(() => sel.classList.remove('ring-2', 'ring-indigo-600'), 1500);
                        }
                    }
                }">
                    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                        {{-- Directory Header with Search & Filter Controls --}}
                        <div class="flex flex-col gap-4 border-b border-slate-100 bg-slate-50/70 px-6 py-5 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <div class="flex items-center gap-2">
                                    <h3 class="text-base font-bold text-slate-900">{{ __('Current Team Directory') }}</h3>
                                    <span class="inline-flex items-center gap-1.5 rounded-full bg-white px-2.5 py-0.5 text-xs font-semibold text-slate-700 shadow-xs ring-1 ring-slate-200">
                                        <span class="h-2 w-2 rounded-full bg-emerald-500 animate-pulse"></span>
                                        {{ count($users) }} {{ __('Total') }}
                                    </span>
                                </div>
                                <p class="text-xs text-slate-500 mt-0.5">{{ __('Interactive team cards with quick permissions and account controls.') }}</p>
                            </div>

                            {{-- Search and Filter Controls --}}
                            <div class="flex flex-wrap items-center gap-2">
                                <div class="relative">
                                    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-2.5 text-slate-400">
                                        <span class="material-symbols-outlined text-[16px]">search</span>
                                    </span>
                                    <input type="text"
                                           x-model="search"
                                           placeholder="Search member..."
                                           class="h-9 w-40 sm:w-48 rounded-xl border border-slate-200 bg-white pl-8 pr-3 text-xs text-slate-900 placeholder:text-slate-400 shadow-xs transition focus:border-indigo-600 focus:outline-none focus:ring-2 focus:ring-indigo-600/30">
                                </div>

                                <div class="inline-flex rounded-xl bg-slate-200/60 p-1 text-xs">
                                    <button type="button"
                                            @click="selectedFilter = 'all'"
                                            :class="selectedFilter === 'all' ? 'bg-white text-slate-900 shadow-xs font-semibold' : 'text-slate-600 hover:text-slate-900'"
                                            class="rounded-lg px-2.5 py-1 transition">
                                        {{ __('All') }}
                                    </button>
                                    <button type="button"
                                            @click="selectedFilter = 'manager'"
                                            :class="selectedFilter === 'manager' ? 'bg-white text-slate-900 shadow-xs font-semibold' : 'text-slate-600 hover:text-slate-900'"
                                            class="rounded-lg px-2.5 py-1 transition">
                                        {{ __('Managers') }}
                                    </button>
                                    <button type="button"
                                            @click="selectedFilter = 'member'"
                                            :class="selectedFilter === 'member' ? 'bg-white text-slate-900 shadow-xs font-semibold' : 'text-slate-600 hover:text-slate-900'"
                                            class="rounded-lg px-2.5 py-1 transition">
                                        {{ __('Members') }}
                                    </button>
                                </div>
                            </div>
                        </div>

                        {{-- Cards Grid Container --}}
                        <div class="p-6 bg-slate-50/40">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                @foreach ($users as $user)
                                    @php
                                        $primaryRole = $user->roles->first()?->name ?? $user->role ?? 'member';
                                        $isManager   = strtolower((string)$primaryRole) === 'manager';
                                        $isTeamLead  = strtolower((string)$primaryRole) === 'team lead';
                                        $isCurrentUser = $user->id === auth()->id();

                                        // Vibrant gradient palettes based on user identifier or role
                                        $avatarGradients = [
                                            'from-indigo-600 to-violet-700',
                                            'from-blue-600 to-cyan-600',
                                            'from-emerald-600 to-teal-700',
                                            'from-slate-800 to-slate-950',
                                            'from-purple-600 to-pink-600',
                                        ];
                                        $avatarGradient = $isManager
                                            ? 'from-amber-500 to-orange-600'
                                            : ($isTeamLead ? 'from-cyan-600 to-blue-700' : $avatarGradients[$user->id % count($avatarGradients)]);
                                    @endphp

                                    <div x-show="
                                            (selectedFilter === 'all' ||
                                             (selectedFilter === 'manager' && {{ $isManager ? 'true' : 'false' }}) ||
                                             (selectedFilter === 'member' && {{ ! $isManager ? 'true' : 'false' }})) &&
                                            ('{{ strtolower(addslashes($user->name)) }}'.includes(search.toLowerCase()) ||
                                             '{{ strtolower(addslashes($user->email)) }}'.includes(search.toLowerCase()) ||
                                             '{{ strtolower(addslashes($primaryRole)) }}'.includes(search.toLowerCase()))
                                         "
                                         x-transition:enter="transition ease-out duration-200"
                                         x-transition:enter-start="opacity-0 scale-95"
                                         x-transition:enter-end="opacity-100 scale-100"
                                         class="group relative flex flex-col justify-between rounded-2xl border border-slate-200/90 bg-white p-5 shadow-xs transition-all duration-300 hover:-translate-y-1 hover:border-indigo-300 hover:shadow-xl hover:shadow-indigo-500/10">

                                        {{-- Top Decorative Corner Glow on Hover --}}
                                        <div class="pointer-events-none absolute -right-10 -top-10 h-28 w-28 rounded-full bg-gradient-to-br from-indigo-500/10 to-transparent blur-xl opacity-0 transition-opacity duration-300 group-hover:opacity-100"></div>

                                        <div>
                                            {{-- Card Header: Avatar + Identity + Status badge --}}
                                            <div class="flex items-start justify-between gap-3">
                                                <div class="flex items-center gap-3">
                                                    {{-- Animated Avatar with Initials --}}
                                                    <div class="relative">
                                                        <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br {{ $avatarGradient }} text-base font-bold text-white shadow-md shadow-slate-900/10 ring-2 ring-white transition-transform duration-300 group-hover:scale-105 group-hover:rotate-1">
                                                            {{ strtoupper(mb_substr($user->name, 0, 1)) }}
                                                        </span>
                                                        <span class="absolute -bottom-0.5 -right-0.5 h-3.5 w-3.5 rounded-full border-2 border-white bg-emerald-500" title="{{ __('Active') }}"></span>
                                                    </div>

                                                    <div class="min-w-0">
                                                        <div class="flex items-center gap-1.5 flex-wrap">
                                                            <h4 class="truncate text-sm font-bold text-slate-900 group-hover:text-indigo-600 transition-colors">
                                                                {{ $user->name }}
                                                            </h4>
                                                            @if ($isCurrentUser)
                                                                <span class="inline-flex items-center rounded-md bg-indigo-50 px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wider text-indigo-700 ring-1 ring-inset ring-indigo-600/20">
                                                                    {{ __('You') }}
                                                                </span>
                                                            @endif
                                                        </div>
                                                        <p class="truncate text-xs text-slate-500 mt-0.5" title="{{ $user->email }}">
                                                            {{ $user->email }}
                                                        </p>
                                                    </div>
                                                </div>

                                                {{-- Role Badge --}}
                                                <div>
                                                    @if ($isManager)
                                                        <span class="inline-flex items-center gap-1 rounded-full bg-amber-50 px-2.5 py-1 text-[11px] font-semibold text-amber-800 ring-1 ring-inset ring-amber-600/20 shadow-xs">
                                                            <span class="h-1.5 w-1.5 rounded-full bg-amber-500"></span>
                                                            {{ $formatRole($primaryRole) }}
                                                        </span>
                                                    @elseif ($isTeamLead)
                                                        <span class="inline-flex items-center gap-1 rounded-full bg-sky-50 px-2.5 py-1 text-[11px] font-semibold text-sky-800 ring-1 ring-inset ring-sky-600/20 shadow-xs">
                                                            <span class="h-1.5 w-1.5 rounded-full bg-sky-500"></span>
                                                            {{ $formatRole($primaryRole) }}
                                                        </span>
                                                    @else
                                                        <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2.5 py-1 text-[11px] font-semibold text-emerald-800 ring-1 ring-inset ring-emerald-600/20 shadow-xs">
                                                            <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                                                            {{ $formatRole($primaryRole) }}
                                                        </span>
                                                    @endif
                                                </div>
                                            </div>

                                            {{-- Mini Info Pills --}}
                                            <div class="mt-4 flex flex-wrap items-center gap-2 text-[11px] text-slate-500">
                                                <span class="inline-flex items-center gap-1 rounded-lg bg-slate-100/80 px-2 py-0.5 font-medium text-slate-600">
                                                    <span class="material-symbols-outlined text-[14px] text-slate-400">badge</span>
                                                    ID #{{ $user->id }}
                                                </span>
                                                <span class="inline-flex items-center gap-1 rounded-lg bg-slate-100/80 px-2 py-0.5 font-medium text-slate-600">
                                                    <span class="material-symbols-outlined text-[14px] text-slate-400">calendar_today</span>
                                                    {{ $user->created_at ? $user->created_at->format('M Y') : 'Active' }}
                                                </span>
                                            </div>
                                        </div>

                                        {{-- Card Footer Action Bar --}}
                                        <div class="mt-4 pt-3.5 border-t border-slate-100 flex items-center justify-between gap-2">
                                            @if (! $isCurrentUser)
                                                <button type="button"
                                                        @click="quickSelectUser({{ $user->id }})"
                                                        class="inline-flex items-center gap-1.5 text-xs font-semibold text-indigo-600 hover:text-indigo-800 transition-colors">
                                                    <span class="material-symbols-outlined text-[16px]">tune</span>
                                                    {{ __('Edit Role') }}
                                                </button>
                                            @else
                                                <span class="text-xs text-slate-400 italic">
                                                    {{ __('Logged in account') }}
                                                </span>
                                            @endif

                                            @can('roles.delete')
                                                @if (! $isCurrentUser)
                                                    <form action="{{ route('projects.users.destroy', $user) }}" method="POST" class="inline"
                                                          onsubmit="return confirm('Are you sure you want to remove {{ addslashes($user->name) }} from the system?');">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit"
                                                                title="{{ __('Delete Member') }}"
                                                                class="inline-flex items-center gap-1 rounded-lg px-2.5 py-1 text-xs font-semibold text-rose-600 transition hover:bg-rose-50 hover:text-rose-700 focus:outline-none focus:ring-2 focus:ring-rose-500/20">
                                                            <span class="material-symbols-outlined text-[15px]">delete</span>
                                                            <span>{{ __('Delete') }}</span>
                                                        </button>
                                                    </form>
                                                @endif
                                            @endcan
                                        </div>

                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </section>
                </div>

            </div>

        </div>
    </div>
</x-app-layout>