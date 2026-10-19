<?php

namespace Modules\ProjectManagement\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

class UserRoleController extends Controller
{
    public function index(Request $request)
    {
        abort_unless(
            $request->user()?->hasRole(['manager']) || $request->user()?->can('roles.assign-permissions'),
            403,
            'Only a manager can manage user roles.'
        );

        $users = User::query()
            ->with('roles')
            ->orderBy('name')
            ->get();

        $roleNames = Role::query()
            ->orderBy('name')
            ->pluck('name')
            ->all();

        return view('ProjectManagement::users.roles.index', compact('users', 'roleNames'));
    }

    public function assign(Request $request): RedirectResponse
    {
        abort_unless(
            $request->user()?->hasRole(['manager']) || $request->user()?->can('roles.assign-permissions'),
            403,
            'Only a manager can assign roles.'
        );

        $availableRoles = Role::query()->pluck('name')->all();

        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'role' => ['required', 'string', Rule::in($availableRoles)],
        ]);

        $selectedUser = User::findOrFail($validated['user_id']);
        $selectedUser->syncRoles([$validated['role']]);

        return redirect()->route('projects.users.roles.index')->with('success', 'User role updated to ' . $validated['role'] . '.');
    }
}
