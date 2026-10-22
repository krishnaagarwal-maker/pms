<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <div>
                <p class="text-sm text-slate-500">{{ __('User access') }}</p>
                <h2 class="text-xl font-bold leading-tight tracking-tight text-[#0E1A2B]">
                    {{ __('Assign user roles') }}
                </h2>
            </div>
        </div>
    </x-slot>

    @php
        $formatRole = fn ($role) => str_replace(['_', '-'], ' ', ucwords(str_replace(['-', '_'], ' ', $role)));
        $assignable = collect($users)->reject(fn ($u) => $u->id === auth()->id());
    @endphp

    <div class="py-8">
        <div class="mx-auto max-w-6xl space-y-6 px-4 sm:px-6 lg:px-8">

            {{-- ───── Alerts ───── --}}
            @if (session('success'))
                <div class="flex items-start gap-3 rounded-xl border border-[#2F9E8F]/30 bg-[#2F9E8F]/10 p-4 text-sm text-[#1D6B60]" role="status">
                    <svg class="mt-0.5 h-5 w-5 shrink-0" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.7-9.3a1 1 0 00-1.4-1.4L9 10.6 7.7 9.3a1 1 0 00-1.4 1.4l2 2a1 1 0 001.4 0l4-4z" clip-rule="evenodd"/></svg>
                    <p>{{ session('success') }}</p>
                </div>
            @endif

            @if ($errors->any())
                <div class="flex items-start gap-3 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700" role="alert">
                    <svg class="mt-0.5 h-5 w-5 shrink-0" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-8-4a1 1 0 00-1 1v3a1 1 0 002 0V7a1 1 0 00-1-1zm0 8a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/></svg>
                    <ul class="list-disc space-y-1 pl-4">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- ───── Assign role ───── --}}
            <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="flex flex-col gap-3 border-b border-slate-200 p-6 sm:p-8 md:flex-row md:items-center md:justify-between">
                    <div>
                        <h3 class="text-base font-semibold text-[#0E1A2B]">{{ __('Change a role') }}</h3>
                        <p class="mt-1 text-sm text-slate-500">{{ __('Pick a person and set their access level.') }}</p>
                    </div>
                    <div class="flex gap-2 text-xs font-medium">
                        <span class="rounded-full border border-[#0E1A2B]/10 bg-[#0E1A2B]/5 px-3 py-1 text-[#0E1A2B]">{{ count($users) }} {{ __('users') }}</span>
                        <span class="rounded-full border border-[#E0A83B]/40 bg-[#E0A83B]/10 px-3 py-1 text-[#8A6414]">{{ count($roleNames) }} {{ __('roles') }}</span>
                    </div>
                </div>

                <form method="POST" action="{{ route('projects.users.roles.assign') }}" class="p-6 sm:p-8">
                    @csrf

                    <div class="grid gap-6 md:grid-cols-2">
                        <div>
                            <label for="user_id" class="block text-sm font-medium text-slate-700">{{ __('User') }}</label>
                            <select id="user_id" name="user_id" @disabled($assignable->isEmpty())
                                    class="mt-1.5 block w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-slate-900 shadow-sm transition focus:border-[#0E1A2B] focus:outline-none focus:ring-4 focus:ring-[#E0A83B]/30 disabled:cursor-not-allowed disabled:bg-slate-50 disabled:text-slate-400">
                                @forelse ($assignable as $user)
                                    <option value="{{ $user->id }}">{{ $user->name }} ({{ $user->email }})</option>
                                @empty
                                    <option value="">{{ __('No other users yet') }}</option>
                                @endforelse
                            </select>
                        </div>

                        <div>
                            <label for="role" class="block text-sm font-medium text-slate-700">{{ __('Role') }}</label>
                            <select id="role" name="role"
                                    class="mt-1.5 block w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-slate-900 shadow-sm transition focus:border-[#0E1A2B] focus:outline-none focus:ring-4 focus:ring-[#E0A83B]/30">
                                @foreach ($roleNames as $roleName)
                                    <option value="{{ $roleName }}">{{ $formatRole($roleName) }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="mt-6 flex justify-end">
                        <button type="submit" @disabled($assignable->isEmpty())
                                class="rounded-xl bg-[#0E1A2B] px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-[#0E1A2B]/20 transition hover:bg-[#16283F] focus:outline-none focus-visible:ring-4 focus-visible:ring-[#E0A83B]/50 active:scale-[0.99] disabled:cursor-not-allowed disabled:opacity-50 disabled:shadow-none">
                            {{ __('Save role') }}
                        </button>
                    </div>
                </form>
            </section>

            {{-- ───── Team access ───── --}}
            <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-6 py-5 sm:px-8">
                    <h3 class="text-base font-semibold text-[#0E1A2B]">{{ __('Current team access') }}</h3>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200">
                        <thead class="bg-slate-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 sm:px-8">{{ __('User') }}</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500">{{ __('Current role') }}</th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-slate-500 sm:px-8">{{ __('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            @foreach ($users as $user)
                                <tr class="transition hover:bg-slate-50/70">
                                    <td class="px-6 py-4 sm:px-8">
                                        <div class="flex items-center gap-3">
                                            <span class="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-[#0E1A2B] text-sm font-semibold text-[#E0A83B]" aria-hidden="true">
                                                {{ strtoupper(mb_substr($user->name, 0, 1)) }}
                                            </span>
                                            <div class="min-w-0">
                                                <div class="truncate text-sm font-medium text-slate-900">{{ $user->name }}</div>
                                                <div class="truncate text-sm text-slate-500">{{ $user->email }}</div>
                                            </div>
                                        </div>
                                    </td>

                                    <td class="px-6 py-4">
                                        <div class="flex flex-wrap gap-1.5">
                                            @forelse ($user->roles->pluck('name') as $role)
                                                <span class="rounded-full border border-[#2F9E8F]/30 bg-[#2F9E8F]/10 px-2.5 py-1 text-xs font-medium text-[#1D6B60]">
                                                    {{ $formatRole($role) }}
                                                </span>
                                            @empty
                                                <span class="rounded-full border border-slate-200 bg-slate-50 px-2.5 py-1 text-xs font-medium text-slate-500">
                                                    {{ __('No role assigned') }}
                                                </span>
                                            @endforelse
                                        </div>
                                    </td>

                                    <td class="px-6 py-4 text-right text-sm sm:px-8">
                                        @can('roles.delete')
                                            @if ($user->id !== auth()->id())
                                                <form action="{{ route('projects.users.destroy', $user) }}" method="POST" class="inline"
                                                      onsubmit="return confirm('Are you sure you want to delete {{ addslashes($user->name) }}?');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit"
                                                            class="rounded-lg border border-red-200 bg-white px-3 py-1.5 text-sm font-medium text-red-700 transition hover:bg-red-50 focus:outline-none focus-visible:ring-4 focus-visible:ring-red-200">
                                                        {{ __('Delete') }}
                                                    </button>
                                                </form>
                                            @else
                                                <span class="rounded-full bg-[#E0A83B]/15 px-2.5 py-1 text-xs font-medium text-[#8A6414]">{{ __('You') }}</span>
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
</x-app-layout>