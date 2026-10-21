@php
    $focus = 'focus:outline-none focus-visible:ring-2 focus-visible:ring-zinc-800 focus-visible:ring-offset-2';
    $btnPrimary   = 'inline-flex h-10 items-center gap-2 rounded-lg bg-zinc-800 px-4 text-sm font-semibold text-white shadow-[inset_0_1px_0_0_rgba(255,255,255,0.12)] transition hover:bg-zinc-700 motion-reduce:transition-none ' . $focus;
    $btnSecondary = 'inline-flex h-8 items-center gap-1.5 rounded-md border border-zinc-300 bg-white px-3 text-sm font-medium text-zinc-700 transition hover:border-zinc-400 hover:bg-zinc-100 hover:text-zinc-900 motion-reduce:transition-none ' . $focus;
    $btnDanger    = 'inline-flex h-8 items-center gap-1.5 rounded-md border border-transparent px-3 text-sm font-medium text-red-600 transition hover:bg-red-50 hover:text-red-700 motion-reduce:transition-none ' . $focus;

    $isPaginator = method_exists($roles, 'total');
    $roleTotal   = $isPaginator ? $roles->total() : $roles->count();
    $visibleChips = 5;
@endphp

<x-app-layout>
    <style>
        [x-cloak] { display: none !important; }
    </style>

    <div class="min-h-screen bg-white py-space-xl font-inter text-zinc-900">
        <div class="mx-auto max-w-[1600px] space-y-6 px-4 sm:px-6 lg:px-8">

            {{-- Page header --}}
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <h1 class="text-[32px] font-bold leading-10 tracking-[-0.025em] text-zinc-900">{{ __('Roles') }}</h1>
                    <p class="mt-1 text-sm text-zinc-500">
                        {{ number_format($roleTotal) }} {{ Str::plural('role', $roleTotal) }}.
                        Roles decide what each user can see and change.
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-3">
                    @can('roles.assign-permissions')
                        @if (\Illuminate\Support\Facades\Route::has('projects.users.roles.index'))
                            <a href="{{ route('projects.users.roles.index') }}" class="{{ $btnSecondary }} h-10 px-3.5">
                                <span class="material-symbols-outlined text-[18px]" aria-hidden="true">person_add</span>
                                Assign user roles
                            </a>
                        @endif
                    @endcan
                    @can('roles.create')
                        <a href="{{ route('projects.roles.create') }}" class="{{ $btnPrimary }}">
                            <span class="material-symbols-outlined text-[18px]" aria-hidden="true">add</span>
                            {{ __('Create role') }}
                        </a>
                    @endcan
                </div>
            </div>

            {{-- Flash messages --}}
            @if (session('success'))
                <div role="status" class="flex items-start gap-3 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                    <span class="material-symbols-outlined mt-0.5 text-[18px] text-emerald-600" aria-hidden="true">check_circle</span>
                    <p class="font-medium">{{ session('success') }}</p>
                </div>
            @endif
            @if (session('error'))
                <div role="alert" class="flex items-start gap-3 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                    <span class="material-symbols-outlined mt-0.5 text-[18px] text-red-600" aria-hidden="true">error</span>
                    <p class="font-medium">{{ session('error') }}</p>
                </div>
            @endif

            {{-- Roles table --}}
            <section class="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-[0_1px_2px_0_rgba(24,24,27,0.05)]" aria-label="Roles">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[820px] text-left">
                        <thead>
                            <tr class="h-10 border-b border-zinc-200 bg-zinc-50 text-xs font-semibold text-zinc-500">
                                <th scope="col" class="w-64 px-6">Role</th>
                                <th scope="col" class="px-4">Permissions</th>
                                <th scope="col" class="w-28 px-4">Users</th>
                                <th scope="col" class="w-52 px-6 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-100">
                            @forelse ($roles as $role)
                                @php
                                    $permNames = $role->permissions->pluck('name')->values();
                                    $permCount = $permNames->count();
                                    $shown     = $permNames->take($visibleChips);
                                    $hidden    = $permNames->slice($visibleChips);
                                @endphp
                                <tr class="align-top transition-colors hover:bg-zinc-50" x-data="{ expanded: false }">
                                    {{-- Role --}}
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-3">
                                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-zinc-900 text-sm font-bold uppercase text-white" aria-hidden="true">
                                                {{ Str::substr($role->name, 0, 1) }}
                                            </span>
                                            <span class="min-w-0 truncate text-sm font-semibold text-zinc-900">{{ $role->name }}</span>
                                        </div>
                                    </td>

                                    {{-- Permissions --}}
                                    <td class="px-4 py-4">
                                        @if ($permCount === 0)
                                            <span class="{{ 'inline-flex items-center gap-1.5 rounded-full bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-700' }}">
                                                <span class="material-symbols-outlined text-[14px]" aria-hidden="true">warning</span>
                                                No permissions assigned
                                            </span>
                                        @else
                                            <p class="mb-2 text-xs font-medium text-zinc-500">
                                                {{ $permCount }} {{ Str::plural('permission', $permCount) }}
                                            </p>
                                            <ul class="flex flex-wrap gap-1.5">
                                                @foreach ($shown as $perm)
                                                    <li class="rounded-md bg-zinc-100 px-2 py-0.5 font-code-sm text-[11px] text-zinc-700">{{ $perm }}</li>
                                                @endforeach
                                                @foreach ($hidden as $perm)
                                                    <li x-show="expanded" x-cloak class="rounded-md bg-zinc-100 px-2 py-0.5 font-code-sm text-[11px] text-zinc-700">{{ $perm }}</li>
                                                @endforeach
                                                @if ($hidden->isNotEmpty())
                                                    <li>
                                                        <button type="button"
                                                                x-on:click="expanded = !expanded"
                                                                x-bind:aria-expanded="expanded.toString()"
                                                                class="rounded-md px-2 py-0.5 text-[11px] font-semibold text-zinc-700 underline-offset-4 hover:bg-zinc-100 hover:text-zinc-900 hover:underline {{ $focus }}">
                                                            <span x-show="!expanded">+{{ $hidden->count() }} more</span>
                                                            <span x-show="expanded" x-cloak>Show less</span>
                                                        </button>
                                                    </li>
                                                @endif
                                            </ul>
                                        @endif
                                    </td>

                                    {{-- Users --}}
                                    <td class="px-4 py-4">
                                        <span class="inline-flex items-center gap-1.5 text-sm tabular-nums {{ $role->users_count > 0 ? 'font-semibold text-zinc-900' : 'text-zinc-400' }}">
                                            <span class="material-symbols-outlined text-[18px]" aria-hidden="true">group</span>
                                            {{ $role->users_count }}
                                            <span class="sr-only">{{ Str::plural('user', $role->users_count) }}</span>
                                        </span>
                                    </td>

                                    {{-- Actions --}}
                                    <td class="px-6 py-4">
                                        <div class="flex items-center justify-end gap-1">
                                            @can('roles.edit')
                                                <a href="{{ route('projects.roles.edit', $role) }}" class="{{ $btnSecondary }}">
                                                    <span class="material-symbols-outlined text-[16px]" aria-hidden="true">edit</span>
                                                    Edit
                                                    <span class="sr-only">{{ $role->name }}</span>
                                                </a>
                                            @endcan

                                            @can('roles.delete')
                                                @if ($role->users_count === 0)
                                                    <form action="{{ route('projects.roles.destroy', $role) }}"
                                                          method="POST"
                                                          onsubmit="return confirm('Delete the role &quot;{{ e($role->name) }}&quot;? This cannot be undone.');">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="{{ $btnDanger }}">
                                                            <span class="material-symbols-outlined text-[16px]" aria-hidden="true">delete</span>
                                                            Delete
                                                            <span class="sr-only">{{ $role->name }}</span>
                                                        </button>
                                                    </form>
                                                @else
                                                    <span class="inline-flex h-8 cursor-not-allowed items-center gap-1.5 px-3 text-sm font-medium text-zinc-400"
                                                          title="Reassign its {{ $role->users_count }} {{ Str::plural('user', $role->users_count) }} before deleting this role">
                                                        <span class="material-symbols-outlined text-[16px]" aria-hidden="true">lock</span>
                                                        In use
                                                    </span>
                                                @endif
                                            @endcan
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-6 py-14 text-center">
                                        <span class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-zinc-100 text-zinc-500">
                                            <span class="material-symbols-outlined text-[24px]" aria-hidden="true">admin_panel_settings</span>
                                        </span>
                                        <p class="mt-3 font-medium text-zinc-800">No roles yet</p>
                                        <p class="mt-1 text-sm text-zinc-500">Create a role to start assigning permissions to users.</p>
                                        @can('roles.create')
                                            <a href="{{ route('projects.roles.create') }}" class="{{ $btnPrimary }} mt-4">
                                                <span class="material-symbols-outlined text-[18px]" aria-hidden="true">add</span>
                                                {{ __('Create role') }}
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
                <div>{{ $roles->links() }}</div>
            @endif
        </div>
    </div>
</x-app-layout>