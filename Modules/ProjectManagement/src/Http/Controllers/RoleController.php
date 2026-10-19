<?php

namespace Modules\ProjectManagement\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleController extends Controller
{
    private function protectedRoles(): array
{
    return ['manager', 'team_member'];
}

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
            'Role Management' => [
                'roles.view',
                'roles.create',
                'roles.edit',
                'roles.delete',
                'roles.assign-permissions',
            ],
        ];
    }

    public function index()
    {
        $roles = Role::with('permissions')
            ->withCount('users')
            ->orderBy('name')
            ->paginate(10);

        return view('ProjectManagement::roles.index', compact('roles'));
    }

    public function create()
    {
        $permissionGroups = $this->permissionGroups();
        $assignablePermissions = request()->user()
        ->getAllPermissions()
        ->where('guard_name', 'web')
        ->pluck('name')
        ->all();

        return view(
            'ProjectManagement::roles.create', compact('permissionGroups','assignablePermissions'));
    }

    public function store(Request $request)
    {
        abort_unless(
            $request->user()->can('roles.assign-permissions'),
            403,
            'You are not allowed to assign permissions.'
        );
        $assignablePermissions = $request->user()
        ->getAllPermissions()
        ->where('guard_name', 'web')
        ->pluck('name')
        ->all();
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
                Rule::in($assignablePermissions),
            ],
        ]);

        $role = Role::create([
            'name' => $validated['name'],
            'guard_name' => 'web',
        ]);

        $role->syncPermissions($validated['permissions'] ?? []);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return redirect()
            ->route('projects.roles.index')
            ->with('success', 'Role created successfully.');
    }

    public function edit(Role $role)
    {
        $permissionGroups = $this->permissionGroups();
        $selectedPermissions = $role->permissions->pluck('name')->all();
        $assignablePermissions = request()->user()
        ->getAllPermissions()
        ->where('guard_name', 'web')
        ->pluck('name')
        ->all();

        return view(
            'ProjectManagement::roles.edit',
            compact('role', 'permissionGroups', 'selectedPermissions','assignablePermissions')
        );
    }

    public function update(Request $request, Role $role)
    {
        if ($request->exists('permissions')) {
            abort_unless(
                $request->user()->can('roles.assign-permissions'),
                403,
                'You are not allowed to assign permissions.'
            );
        }
        $assignablePermissions = $request->user()
        ->getAllPermissions()
        ->where('guard_name', 'web')
        ->pluck('name')
        ->all();
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:100',
                'regex:/^[A-Za-z0-9 _-]+$/',
                Rule::unique('roles', 'name')
                    ->where('guard_name', 'web')
                    ->ignore($role->id),
            ],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => [
                'required',
                'string',
                'distinct',
                Rule::in($assignablePermissions),
            ],
        ]);

        if (in_array($role->name, $this->protectedRoles(), true)) {
            abort_unless(
                $request->user()->can('roles.assign-permissions'),
                403
            );

            unset($validated['name']);
        }

        $role->update(['name' => $validated['name'] ?? $role->name]);
        if (array_key_exists('permissions', $validated)) {
            $role->syncPermissions($validated['permissions']);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return redirect()
            ->route('projects.roles.index')
            ->with('success', 'Role updated successfully.');
    }

    public function destroy(Role $role)
    {
        abort_if(
            in_array($role->name, $this->protectedRoles(), true),
            403,
            'Built-in roles cannot be deleted.'
        );

        abort_if(
            $role->users()->exists(),
            422,
            'This role is assigned to users. Reassign those users before deleting it.'
        );

        $role->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return redirect()
            ->route('projects.roles.index')
            ->with('success', 'Role deleted successfully.');
    }
}
