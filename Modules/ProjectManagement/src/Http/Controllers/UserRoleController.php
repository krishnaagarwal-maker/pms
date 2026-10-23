<?php

namespace Modules\ProjectManagement\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

class UserRoleController extends Controller
{
    public function index(Request $request)
    {
        abort_unless(
            $request->user()->can('roles.view'),
            403,
            'You are not allowed to view user roles.'
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
            $request->user()->can('roles.assign-permissions'),
            403,
            'You are not allowed to assign roles.'
        );

        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'role' => ['required', 'string'],
        ]);

        $requestRole = trim((string) $validated['role']);
        $role = Role::query()
            ->where('guard_name', 'web')
            ->whereRaw('LOWER(name) = ?', [strtolower($requestRole)])
            ->first();

        if (! $role) {
            throw ValidationException::withMessages([
                'role' => ['The selected role is invalid.'],
            ]);
        }

        $selectedUser = User::findOrFail($validated['user_id']);

        if ($selectedUser->is(auth()->user())) {
            return back()->withErrors(['user_id' => 'You cannot change your own role from this screen.']);
        }

        $selectedUser->syncRoles([$role->name]);

        return redirect()->route('projects.users.roles.index')->with('success', 'User role updated to ' . $role->name . '.');
    }

public function destroy(Request $request, User $user): RedirectResponse
    {
        abort_unless(
            $request->user()->can('users.delete'),
            403,
            'You do not have permission to delete users.'
        );

        if ($user->is($request->user())) {
            return back()->withErrors(['user' => 'You cannot delete your own account from this screen.']);
        }

        $user->delete();

        return redirect()->route('projects.users.roles.index')->with('success', 'User deleted successfully.');
    }
}
