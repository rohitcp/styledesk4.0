<?php

declare(strict_types=1);

namespace App\Http\Controllers\Backoffice;

use App\Http\Controllers\Controller;
use App\Models\BackofficeAuditLog;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

/**
 * An administrator's own account.
 *
 * Their name, their address and their password — and nothing about their role,
 * because a console where people can promote themselves has no roles at all.
 */
class ProfileController extends Controller
{
    public function edit(): View
    {
        return view('backoffice.profile', ['admin' => Auth::guard('backoffice')->user()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $admin = Auth::guard('backoffice')->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('backoffice_admins', 'email')->ignore($admin->id)],
        ]);

        $before = ['name' => $admin->name, 'email' => $admin->email];

        $admin->forceFill([
            'name' => $data['name'],
            'email' => mb_strtolower(trim($data['email'])),
        ])->save();

        BackofficeAuditLog::record(
            action: 'admin.profile_updated',
            actor: $admin,
            subject: $admin,
            before: $before,
            after: ['name' => $admin->name, 'email' => $admin->email],
            subjectLabel: $admin->name,
        );

        return back()->with('status', __('backoffice.profile.saved'));
    }

    /**
     * Change it, having proved you know the old one.
     *
     * The current password is asked for even though the reader is signed in:
     * this is the screen an unattended desk gets used on, and knowing the old
     * password is what tells the two apart.
     */
    public function password(Request $request): RedirectResponse
    {
        $admin = Auth::guard('backoffice')->user();

        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::min(12)->letters()->numbers()->symbols()->uncompromised()],
        ]);

        if (! Hash::check($data['current_password'], $admin->password)) {
            throw ValidationException::withMessages([
                'current_password' => __('backoffice.profile.wrong_password'),
            ]);
        }

        $admin->forceFill(['password' => $data['password']])->save();

        BackofficeAuditLog::record(action: 'admin.password_changed', actor: $admin, subject: $admin, subjectLabel: $admin->name);

        return back()->with('status', __('backoffice.profile.password_saved'));
    }
}
