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

    public function onboard(Request $request): RedirectResponse
    {
        abort_unless(
            $request->user()->can('users.create'),
            403,
            'You are not allowed to create users.'
        );

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class.',email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'role' => ['nullable', 'string'],
        ]);

        $requestRole = trim((string) ($validated['role'] ?? 'team_member'));
        if ($requestRole === '') {
            $requestRole = 'team_member';
        }

        $role = Role::query()
            ->where('guard_name', 'web')
            ->whereRaw('LOWER(name) = ?', [strtolower($requestRole)])
            ->first();

        if (! $role) {
            $role = Role::firstOrCreate(['name' => 'team_member', 'guard_name' => 'web']);
        }

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => \Illuminate\Support\Facades\Hash::make($validated['password']),
            'role' => in_array($role->name, ['manager', 'team_member'], true) ? $role->name : 'team_member',
        ]);

        $user->syncRoles([$role->name]);

        // Send welcoming onboarding email with initial credentials
        try {
            $user->notify(new \App\Notifications\UserOnboardedNotification(
                $user,
                $request->user(),
                $role->name,
                $validated['password']
            ));
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Onboarding email delivery skipped or failed: ' . $e->getMessage());
        }

        return redirect()->route('projects.users.roles.index')->with('success', 'User "' . $user->name . '" onboarded successfully with ' . $role->name . ' role.');
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
