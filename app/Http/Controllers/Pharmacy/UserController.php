<?php

namespace App\Http\Controllers\Pharmacy;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public const ROLES = ['owner', 'pharmacist', 'assistant', 'cashier'];

    public function index(Request $request): Response
    {
        return Inertia::render('users/Index', [
            'users' => User::query()
                ->where('branch_id', $request->user()?->branch_id)
                ->with('roles:id,name')
                ->orderBy('name')
                ->get(['id', 'name', 'email'])
                ->map(fn (User $u) => ['id' => $u->id, 'name' => $u->name, 'email' => $u->email, 'role' => $u->getRoleNames()->first()]),
            'roles' => self::ROLES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users'],
            'password' => ['required', Password::defaults()],
            'role' => ['required', 'in:'.implode(',', self::ROLES)],
        ]);

        $user = User::query()->create([...$data, 'branch_id' => $request->user()?->branch_id]);
        $user->forceFill(['email_verified_at' => now()])->save();
        $user->assignRole($data['role']);
        AuditLog::record('user.created', $user, ['role' => $data['role']]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':name added.', ['name' => $user->name])]);

        return back();
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        abort_unless($user->branch_id === $request->user()?->branch_id, 404);

        $data = $request->validate([
            'role' => ['required', 'in:'.implode(',', self::ROLES)],
            'password' => ['nullable', Password::defaults()],
        ]);

        if ($user->is($request->user()) && $data['role'] !== 'owner') {
            throw ValidationException::withMessages(['role' => __('You can’t remove your own owner role.')]);
        }

        $user->syncRoles([$data['role']]);
        AuditLog::record('user.updated', $user, ['role' => $data['role'], 'password_reset' => ! empty($data['password'])]);

        if (! empty($data['password'])) {
            $user->update(['password' => $data['password']]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':name updated.', ['name' => $user->name])]);

        return back();
    }
}
