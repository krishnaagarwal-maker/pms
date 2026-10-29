<?php

namespace Modules\ProjectManagement\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleController extends Controller
{
    private function permissionGroups(): array
    {
        return [
            'Projects' => [
                'projects.view',
                'projects.create',
                'projects.edit',
                'projects.delete',
            ],
            'Tasks' => [
                'tasks.view',
                'tasks.create',
                'tasks.edit',
                'tasks.delete',
                'tasks.assign',
                'tasks.update-status',
            ],
            'Attachments' => [
                'attachments.create',
                'attachments.delete',
            ],
            'User & Role Management' => [
                'roles.view',
                'roles.create',
                'roles.edit',
                'roles.delete',
                'roles.assign-permissions',
                'users.create',
                'users.delete',
            ],
        ];
    }

    private function assignablePermissions(?User $user): array
    {
        return $user
            ? $user->getAllPermissions()->where('guard_name', 'web')->pluck('name')->all()
            : [];
    }

    public function index(): View
    {
        $roles = Role::with('permissions')
            ->withCount('users')
            ->orderBy('name')
            ->paginate(10);

        $permissionGroups = $this->permissionGroups();
        $assignablePermissions = $this->assignablePermissions(request()->user());

        return view('ProjectManagement::roles.index', compact('roles', 'permissionGroups', 'assignablePermissions'));
    }

    public function create(): View
    {
        $permissionGroups = $this->permissionGroups();
        $assignablePermissions = $this->assignablePermissions(request()->user());

        return view('ProjectManagement::roles.create', compact('permissionGroups', 'assignablePermissions'));
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('roles.create'), 403, 'You are not allowed to create roles.');

        if ($request->exists('permissions')) {
            abort_unless($request->user()->can('roles.assign-permissions'), 403, 'You are not allowed to assign permissions.');
        }

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:100',
                'regex:/^[A-Za-z0-9 _-]+$/',
                Rule::unique('roles', 'name')->where('guard_name', 'web'),
            ],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => [
                'required',
                'string',
                'distinct',
                Rule::in($this->assignablePermissions($request->user())),
            ],
        ]);

        $role = Role::create([
            'name' => $validated['name'],
            'guard_name' => 'web',
        ]);

        $role->syncPermissions($validated['permissions'] ?? []);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return redirect()->route('projects.roles.index')->with('success', 'Role created successfully.');
    }

    public function edit(Role $role): View
    {
        $permissionGroups = $this->permissionGroups();
        $selectedPermissions = $role->permissions->pluck('name')->all();
        $assignablePermissions = $this->assignablePermissions(request()->user());

        return view('ProjectManagement::roles.edit', compact('role', 'permissionGroups', 'selectedPermissions', 'assignablePermissions'));
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        if ($request->exists('permissions')) {
            abort_unless($request->user()->can('roles.assign-permissions'), 403, 'You are not allowed to assign permissions.');
        }

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:100',
                'regex:/^[A-Za-z0-9 _-]+$/',
                Rule::unique('roles', 'name')->where('guard_name', 'web')->ignore($role->id),
            ],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => [
                'required',
                'string',
                'distinct',
                Rule::in($this->assignablePermissions($request->user())),
            ],
        ]);

        $role->update(['name' => $this->isBuiltIn($role) ? $role->name : ($validated['name'] ?? $role->name)]);
        if (array_key_exists('permissions', $validated)) {
            $role->syncPermissions($validated['permissions']);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return redirect()->route('projects.roles.index')->with('success', 'Role updated successfully.');
    }

    public function destroy(Role $role): RedirectResponse
    {
        abort_if($this->isBuiltIn($role), 422, 'Built-in roles cannot be deleted.');

        $fallbackRole = Role::query()
            ->where('guard_name', $role->guard_name)
            ->whereRaw("LOWER(REPLACE(REPLACE(name, ' ', '_'), '-', '_')) = ?", ['team_member'])
            ->first();

        abort_unless($fallbackRole, 422, 'The built-in Team member role is required before deleting custom roles.');

        $reassignedUsers = $role->users()->count();

        DB::transaction(function () use ($role, $fallbackRole) {
            foreach ($role->users as $user) {
                $user->removeRole($role);
                $user->assignRole($fallbackRole);
            }

            $role->delete();
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return redirect()->route('projects.roles.index')->with('success', $reassignedUsers
            ? "Role deleted. {$reassignedUsers} " . Str::plural('user', $reassignedUsers) . ' moved to Team member.'
            : 'Role deleted successfully.');
    }

    private function isBuiltIn(Role $role): bool
    {
        $key = strtolower(str_replace([' ', '-'], '_', $role->name));

        return in_array($key, ['manager', 'team_member'], true);
    }
}
