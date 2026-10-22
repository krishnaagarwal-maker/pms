<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Create role') }}
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
            <form method="POST" action="{{ route('projects.roles.store') }}" class="space-y-6 rounded-lg bg-white p-6 shadow">
                @csrf

                <div>
                    <x-input-label for="name" :value="__('Role name')" />
                    <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name')" required />
                    <x-input-error class="mt-2" :messages="$errors->get('name')" />
                </div>

                @include('ProjectManagement::roles._permission-fields', [
                    'permissionGroups' => $permissionGroups,
                    'assignablePermissions' => $assignablePermissions,
                ])

                <x-input-error class="mt-2" :messages="$errors->get('permissions')" />
                <x-input-error class="mt-2" :messages="$errors->get('permissions.*')" />

                <div class="flex items-center gap-4">   
                    <x-primary-button>{{ __('Save') }}</x-primary-button>
                    <a href="{{ route('projects.roles.index') }}" class="text-sm text-gray-600 hover:underline">
                        {{ __('Cancel') }}
                    </a>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
