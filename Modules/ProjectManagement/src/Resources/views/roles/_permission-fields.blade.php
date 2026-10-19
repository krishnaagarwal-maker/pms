@php
    $selected = old('permissions', $selectedPermissions ?? []);
    $assignable = $assignablePermissions ?? [];
@endphp
<div class="space-y-6">
    @foreach ($permissionGroups as $group => $permissions)
    
        <fieldset class="rounded-lg border border-gray-200 p-4">
            <legend class="px-2 text-sm font-semibold text-gray-800">{{ $group }}</legend>
            <div class="mt-2 grid gap-2 sm:grid-cols-2">
                @foreach ($permissions as $permission)
                @if (in_array($permission, $assignable, true))
                    <label class="flex items-center gap-2 text-sm text-gray-700">
                        <input type="checkbox"
                               name="permissions[]"
                               value="{{ $permission }}"
                               @checked(in_array($permission, $selected, true))
                               class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                        <span>{{ $permission }}</span>
                    </label>
                    @endif
                @endforeach
            </div>
        </fieldset>
    @endforeach
</div>
