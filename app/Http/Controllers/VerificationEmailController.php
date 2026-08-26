<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Lets someone correct the address they signed up with.
 *
 * Without this, a typo in the email field is a dead end: the verification link
 * goes somewhere the user cannot read, and they cannot sign up again because
 * the address is already taken by the account they are locked out of.
 */
class VerificationEmailController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'email' => [
                'required', 'string', 'email', 'max:255',
                Rule::unique(User::class)->ignore($user->id),
            ],
        ]);

        // Changing the address invalidates any verification already granted —
        // otherwise a user could verify one address and then swap in another.
        $user->forceFill([
            'email' => $this->normalize($data['email']),
            'email_verified_at' => null,
        ])->save();

        $user->sendEmailVerificationNotification();

        return back()->with('status', 'verification-link-sent');
    }

    /**
     * Lower-case the address so the same inbox cannot register twice under
     * different casing, which the unique index alone would allow.
     */
    private function normalize(string $email): string
    {
        return mb_strtolower(trim($email));
    }
}
