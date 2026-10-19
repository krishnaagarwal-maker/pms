<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Roles') }}
            </h2>
            @can('roles.create')
                <a href="{{ route('projects.roles.create') }}"
                   class="inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                    {{ __('Create role') }}
                </a>
            @endcan
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">
            @if (session('success'))
                <div class="rounded-md bg-green-50 p-4 text-sm text-green-800">
                    {{ session('success') }}
                </div>
            @endif

            <div class="overflow-hidden rounded-lg bg-white shadow">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Name</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Permissions</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Users</th>
                            <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white">
                        @forelse ($roles as $role)
                            <tr>
                                <td class="whitespace-nowrap px-6 py-4 text-sm font-medium text-gray-900">
                                    {{ $role->name }}
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-600">
                                <div>{{ $role->permissions->count() }} permissions</div>

                                <div class="mt-1 text-xs text-gray-500">
                                    {{ $role->permissions->pluck('name')->implode(', ') ?: 'No permissions assigned' }}
                                </div>
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-600">
                                    {{ $role->users_count }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-right text-sm">
                                    @can('roles.edit')
                                        <a href="{{ route('projects.roles.edit', $role) }}"
                                           class="text-indigo-600 hover:underline">
                                            Edit
                                        </a>
                                    @endcan
                                    @can('roles.delete')
                                        @if (! in_array($role->name, ['Manager', 'team_member'], true) && $role->users_count === 0)
                                            <form action="{{ route('projects.roles.destroy', $role) }}"
                                                  method="POST"
                                                  class="inline ms-3"
                                                  onsubmit="return confirm('Delete this role?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="text-red-600 hover:underline">
                                                    Delete
                                                </button>
                                            </form>
                                        @endif
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-6 py-8 text-center text-sm text-gray-500">
                                    No roles found.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $roles->links() }}
        </div>
    </div>
</x-app-layout>
