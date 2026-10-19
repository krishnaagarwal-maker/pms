<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Edit role') }}: {{ $role->name }}
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
            <form method="POST" action="{{ route('projects.roles.update', $role) }}" class="space-y-6 rounded-lg bg-white p-6 shadow">
                @csrf
                @method('PUT')

                @php
                    $isBuiltIn = in_array($role->name, ['Manager', 'Team Member'], true);
                @endphp

                <div>
                    <x-input-label for="name" :value="__('Role name')" />
                    @if ($isBuiltIn)
                        <input type="hidden" name="name" value="{{ $role->name }}">
                        <x-text-input id="name"
                                      type="text"
                                      class="mt-1 block w-full bg-gray-50"
                                      :value="$role->name"
                                      disabled />
                        <p class="mt-1 text-sm text-gray-500">Built-in role names cannot be changed.</p>
                    @else
                        <x-text-input id="name"
                                      name="name"
                                      type="text"
                                      class="mt-1 block w-full"
                                      :value="old('name', $role->name)"
                                      required />
                    @endif
                    <x-input-error class="mt-2" :messages="$errors->get('name')" />
                </div>

                @can('roles.assign-permissions')
                    @include('ProjectManagement::roles._permission-fields', [
                        'permissionGroups' => $permissionGroups,
                        'selectedPermissions' => $selectedPermissions,
                    ])
                @else
                    <p class="text-sm text-gray-600">You do not have permission to change role permissions.</p>
                @endcan

                <x-input-error class="mt-2" :messages="$errors->get('permissions')" />
                <x-input-error class="mt-2" :messages="$errors->get('permissions.*')" />

                <div class="flex items-center gap-4">
                    <x-primary-button>{{ __('Update') }}</x-primary-button>
                    <a href="{{ route('projects.roles.index') }}" class="text-sm text-gray-600 hover:underline">
                        {{ __('Cancel') }}
                    </a>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
