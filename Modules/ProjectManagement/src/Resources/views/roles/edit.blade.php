@php
    $builtInKey = strtolower(str_replace([' ', '-'], '_', $role->name));
    $isBuiltIn = in_array($builtInKey, ['manager', 'team_member'], true);
    $roleLabel = $builtInKey === 'team_member' ? 'Team member' : $role->name;
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <a href="{{ route('projects.roles.index') }}"
                   class="grid h-9 w-9 place-items-center rounded-lg border border-slate-200 bg-white text-slate-500 transition hover:border-[#0E1A2B] hover:text-[#0E1A2B] focus:outline-none focus-visible:ring-4 focus-visible:ring-[#E0A83B]/30"
                   aria-label="{{ __('Back to roles') }}">
                    <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M12.7 4.3a1 1 0 010 1.4L8.4 10l4.3 4.3a1 1 0 01-1.4 1.4l-5-5a1 1 0 010-1.4l5-5a1 1 0 011.4 0z" clip-rule="evenodd"/></svg>
                </a>
                <div>
                    <p class="text-sm text-slate-500">{{ __('Roles') }}</p>
                    <h2 class="text-xl font-bold leading-tight tracking-tight text-[#0E1A2B]">
                        {{ __('Edit role') }}: {{ $roleLabel }}
                    </h2>
                </div>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
            <form method="POST" action="{{ route('projects.roles.update', $role) }}"
                  class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                @csrf
                @method('PUT')

                {{-- ───── Role details ───── --}}
                <section class="p-6 sm:p-8">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h3 class="text-base font-semibold text-[#0E1A2B]">{{ __('Role details') }}</h3>
                            <p class="mt-1 text-sm text-slate-500">{{ __('The name people see when this role is assigned.') }}</p>
                        </div>
                        @if ($isBuiltIn)
                            <span class="inline-flex shrink-0 items-center gap-1.5 rounded-full border border-[#E0A83B]/40 bg-[#E0A83B]/10 px-3 py-1 text-xs font-medium text-[#8A6414]">
                                <svg class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M10 1a4 4 0 00-4 4v2H5a2 2 0 00-2 2v7a2 2 0 002 2h10a2 2 0 002-2V9a2 2 0 00-2-2h-1V5a4 4 0 00-4-4zm2 6V5a2 2 0 10-4 0v2h4z" clip-rule="evenodd"/></svg>
                                {{ __('Built-in') }}
                            </span>
                        @endif
                    </div>

                    <div class="mt-5">
                        <label for="name" class="block text-sm font-medium text-slate-700">{{ __('Role name') }}</label>
                        @if ($isBuiltIn)
                            <input type="hidden" name="name" value="{{ $role->name }}">
                            <input id="name" type="text" value="{{ $roleLabel }}" disabled
                                   class="mt-1.5 block w-full cursor-not-allowed rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-slate-500">
                            <p class="mt-2 text-sm text-slate-500">{{ __('Built-in role names cannot be changed.') }}</p>
                        @else
                            <input id="name" name="name" type="text" required
                                   value="{{ old('name', $role->name) }}"
                                   class="mt-1.5 block w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-slate-900 shadow-sm transition focus:border-[#0E1A2B] focus:outline-none focus:ring-4 focus:ring-[#E0A83B]/30 @error('name') border-red-400 @enderror">
                        @endif
                        <x-input-error class="mt-2" :messages="$errors->get('name')" />
                    </div>
                </section>

                {{-- ───── Permissions ───── --}}
                <section class="border-t border-slate-200 bg-slate-50/60 p-6 sm:p-8">
                    <h3 class="text-base font-semibold text-[#0E1A2B]">{{ __('Permissions') }}</h3>
                    <p class="mt-1 text-sm text-slate-500">{{ __('Choose what people with this role can do.') }}</p>

                    <div class="mt-5">
                        @if ($isBuiltIn)
                            <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
                                {{ __('This built-in role name is fixed, but its permissions can be adjusted.') }}
                            </div>
                        @endif
                        @can('roles.assign-permissions')
                            <div class="rounded-xl border border-slate-200 bg-white p-5">
                                @include('ProjectManagement::roles._permission-fields', [
                                    'permissionGroups' => $permissionGroups,
                                    'selectedPermissions' => $selectedPermissions,
                                ])
                            </div>
                        @else
                            <div class="flex items-start gap-3 rounded-xl border border-slate-200 bg-white p-4 text-sm text-slate-600">
                                <svg class="mt-0.5 h-5 w-5 shrink-0 text-slate-400" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M10 1a4 4 0 00-4 4v2H5a2 2 0 00-2 2v7a2 2 0 002 2h10a2 2 0 002-2V9a2 2 0 00-2-2h-1V5a4 4 0 00-4-4zm2 6V5a2 2 0 10-4 0v2h4z" clip-rule="evenodd"/></svg>
                                <p>{{ __('You do not have permission to change role permissions.') }}</p>
                            </div>
                        @endcan

                        <x-input-error class="mt-3" :messages="$errors->get('permissions')" />
                        <x-input-error class="mt-3" :messages="$errors->get('permissions.*')" />
                    </div>
                </section>

                {{-- ───── Actions ───── --}}
                <div class="flex items-center justify-end gap-3 border-t border-slate-200 bg-white px-6 py-4 sm:px-8">
                    <a href="{{ route('projects.roles.index') }}"
                       class="rounded-lg px-4 py-2.5 text-sm font-medium text-slate-600 transition hover:bg-slate-100 hover:text-slate-900 focus:outline-none focus-visible:ring-4 focus-visible:ring-[#E0A83B]/30">
                        {{ __('Cancel') }}
                    </a>
                    <button type="submit"
                            class="rounded-xl bg-[#0E1A2B] px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-[#0E1A2B]/20 transition hover:bg-[#16283F] focus:outline-none focus-visible:ring-4 focus-visible:ring-[#E0A83B]/50 active:scale-[0.99]">
                        {{ __('Save changes') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
