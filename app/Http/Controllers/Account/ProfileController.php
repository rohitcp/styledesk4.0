<?php

declare(strict_types=1);

namespace App\Http\Controllers\Account;

use App\Contracts\TenantStorageContract;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\VerifyEmailChange;
use App\Support\AccountProfile;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * The signed-in person's own profile.
 *
 * Everything here reads and writes `$request->user()` and nothing else. There
 * is no {user} parameter to bind, no policy to check and no way to name
 * somebody else — which is the whole permission model for My Account, stated
 * as an absence rather than as a rule that could be forgotten. An owner
 * editing a colleague does it in Staff Management, against the staff record.
 */
class ProfileController extends Controller
{
    /** How long a link to prove a new address stays good for. */
    private const EMAIL_CHANGE_HOURS = 24;

    public function __construct(private readonly TenantStorageContract $storage) {}

    public function show(Request $request): View
    {
        return view('account.profile', [
            'user' => $request->user(),
            'facts' => AccountProfile::facts($request->user()),
        ]);
    }

    /**
     * Save the editable half of the profile.
     *
     * The address is deliberately not one of the fields this writes. A new
     * one is parked as a claim and proved separately — see requestEmailChange
     * — so a mistyped address cannot lock somebody out of the account they
     * are signed into.
     */
    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'display_name' => ['nullable', 'string', 'max:100'],
            'job_title' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:32'],
            'phone_country' => ['nullable', 'string', 'size:2'],
        ]);

        /* Blank means "use my first and last name" rather than an empty
           display name, so it is stored as null — the fallback in
           User::displayName() is then the only thing deciding.

           array_key_exists, not a null check: an absent field and a cleared
           one are different requests, and only the cleared one should erase
           what is stored. */
        if (array_key_exists('display_name', $validated)) {
            $validated['display_name'] = $validated['display_name'] ?: null;
        }

        $user->update($validated);

        return redirect()->route('account.profile')
            ->with('toast', __('account.profile.saved'));
    }

    /**
     * Replace the account photo.
     *
     * Its own endpoint rather than a field on the form, because the file is
     * uploaded as it is chosen: the preview a person approves before saving
     * has to be the image the server actually received, not a data URL the
     * browser drew from a file it never sent.
     */
    public function uploadPhoto(Request $request): RedirectResponse
    {
        $request->validate([
            /* Mirrors what the storage component enforces anyway. Stated here
               too so the answer is a message under the field rather than an
               exception from two layers down. */
            'photo' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ], [
            'photo.max' => __('account.profile.photo_too_large'),
            'photo.mimes' => __('account.profile.photo_wrong_type'),
        ]);

        $user = $request->user();
        $previous = $user->avatar_file_id;

        $file = $this->storage->uploadProfileImage($request->file('photo'), $user->id);

        $user->forceFill(['avatar_file_id' => $file->id])->save();

        /* The old one goes only after the new one is recorded. The other
           order leaves a person with no photo at all if the upload fails. */
        if ($previous !== null) {
            $this->storage->delete($previous);
        }

        return redirect()->route('account.profile')
            ->with('toast', __('account.profile.photo_saved'));
    }

    public function removePhoto(Request $request): RedirectResponse
    {
        $user = $request->user();
        $fileId = $user->avatar_file_id;

        $user->forceFill(['avatar_file_id' => null])->save();

        if ($fileId !== null) {
            $this->storage->delete($fileId);
        }

        return redirect()->route('account.profile')
            ->with('toast', __('account.profile.photo_removed'));
    }

    /**
     * Claim a new address, and prove it before it counts.
     *
     * Three things make this safe, and all three are the point:
     *
     *  - the current password is asked for, so a session somebody walked away
     *    from cannot be turned into an account takeover by changing where the
     *    reset emails go;
     *  - the existing address keeps working until the new one answers, so a
     *    typo is a failed change rather than a lost account;
     *  - the link goes to the new address only. Sending it anywhere else
     *    would prove nothing about the address being claimed.
     */
    public function requestEmailChange(Request $request): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'email' => [
                'required', 'string', 'email', 'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
            'current_password' => ['required', 'string'],
        ], [
            'email.unique' => __('account.profile.email_taken'),
        ]);

        if (! Hash::check($validated['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => __('account.password.current_wrong'),
            ]);
        }

        if (mb_strtolower($validated['email']) === mb_strtolower($user->email)) {
            throw ValidationException::withMessages([
                'email' => __('account.profile.email_unchanged'),
            ]);
        }

        $this->sendEmailChangeLink($user, $validated['email']);

        return redirect()->route('account.profile')
            ->with('toast', __('account.profile.email_pending', ['email' => $validated['email']]));
    }

    /** Send the link again, to the address already claimed. */
    public function resendEmailChange(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->pending_email === null) {
            return redirect()->route('account.profile');
        }

        /* A fresh token every time, so the newest email is the only one that
           works — a resend silently retires the link before it, which is what
           somebody who asked for a new email expects. */
        $this->sendEmailChangeLink($user, $user->pending_email);

        return redirect()->route('account.profile')
            ->with('toast', __('account.profile.email_resent'));
    }

    public function cancelEmailChange(Request $request): RedirectResponse
    {
        $request->user()->forceFill([
            'pending_email' => null,
            'pending_email_token' => null,
            'pending_email_expires_at' => null,
        ])->save();

        return redirect()->route('account.profile')
            ->with('toast', __('account.profile.email_cancelled'));
    }

    /**
     * The link in the email: move the claimed address across.
     *
     * Outside `auth` deliberately. The link is opened in whichever browser
     * the mail client hands it to, and that browser is very often not the one
     * holding the session — a 403 there reads as the product refusing an
     * email it sent itself. The token is the credential, and it is checked
     * against a hash, so nothing is trusted from the URL except the id.
     */
    public function confirmEmailChange(Request $request, User $user, string $token): RedirectResponse
    {
        $refused = $user->pending_email === null
            || $user->pending_email_token === null
            || $user->pending_email_expires_at === null
            || $user->pending_email_expires_at->isPast()
            || ! hash_equals($user->pending_email_token, hash('sha256', $token));

        if ($refused) {
            return redirect()->route('account.profile')
                ->with('toast', ['type' => 'danger', 'message' => __('account.profile.email_link_dead')]);
        }

        /* Claimed by somebody else while this link sat in an inbox. The
           address is a first-come credential, so this one simply loses. */
        if (User::where('email', $user->pending_email)->whereKeyNot($user->id)->exists()) {
            return redirect()->route('account.profile')
                ->with('toast', ['type' => 'danger', 'message' => __('account.profile.email_taken')]);
        }

        $user->forceFill([
            'email' => $user->pending_email,
            /* Verified by definition: proving the link is what verification
               is. Asking again for the same address would be asking a
               question that has just been answered. */
            'email_verified_at' => now(),
            'pending_email' => null,
            'pending_email_token' => null,
            'pending_email_expires_at' => null,
        ])->save();

        return redirect()->route('account.profile')
            ->with('toast', __('account.profile.email_changed'));
    }

    /**
     * Park the claim and post the link that proves it.
     *
     * Addressed on an anonymous route rather than to the user, because
     * notifying the user would send it to the address they are leaving —
     * which proves nothing about the one they are claiming.
     */
    private function sendEmailChangeLink(User $user, string $email): void
    {
        $token = $this->startEmailChange($user, $email);

        Notification::route('mail', $email)->notify(new VerifyEmailChange(
            $user->first_name ?: $user->email,
            $email,
            route('account.email.confirm', ['user' => $user->getKey(), 'token' => $token]),
            self::EMAIL_CHANGE_HOURS,
        ));
    }

    /** Park the claim and return the plain token that goes in the email. */
    private function startEmailChange(User $user, string $email): string
    {
        $token = Str::random(48);

        $user->forceFill([
            'pending_email' => $email,
            /* The hash, never the token: a leaked backup of this table must
               not be a set of working change-email links. */
            'pending_email_token' => hash('sha256', $token),
            'pending_email_expires_at' => now()->addHours(self::EMAIL_CHANGE_HOURS),
        ])->save();

        return $token;
    }
}
