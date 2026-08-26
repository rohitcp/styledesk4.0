<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Fortify\PasswordValidationRules;
use App\Actions\Team\AcceptTeamInvitation;
use App\Mail\TeamInvitationMail;
use App\Models\TeamInvitation;
use App\Models\User;
use App\Support\InputCase;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * The page an invited colleague lands on.
 *
 * Deliberately outside every auth and tenancy middleware: the visitor has no
 * account yet and no tenant to resolve from, and the token is the only thing
 * that establishes who they are and which business they were invited to. The
 * tenant is read from the invitation, never from the request.
 */
class TeamInviteSignupController extends Controller
{
    use PasswordValidationRules;

    public function show(Request $request, string $token): View
    {
        $invitation = $this->find($token);

        // An unusable link is a dead end, so it is answered with an
        // explanation rather than a form the visitor cannot submit.
        if ($invitation === null || $invitation->effectiveStatus() !== TeamInvitation::STATUS_PENDING) {
            return $this->closed($invitation);
        }

        $existingUser = User::where('email', $invitation->email)->first();
        $signedIn = $request->user();

        /**
         * Someone signed in as the wrong person.
         *
         * Common enough to be worth naming: a shared computer, or an owner
         * clicking a link they forwarded. Saying which address the invitation
         * belongs to is the difference between a fixable situation and an
         * apparently broken link.
         */
        if ($signedIn !== null && mb_strtolower($signedIn->email) !== mb_strtolower($invitation->email)) {
            return view('invitations.wrong-account', [
                'invitation' => $invitation,
                'signedInAs' => $signedIn->email,
                'roleLabel' => TeamInvitationMail::roleLabel($invitation->role),
            ]);
        }

        return view('invitations.show', [
            'invitation' => $invitation,
            'token' => $token,
            'roleLabel' => TeamInvitationMail::roleLabel($invitation->role),
            'hasAccount' => $existingUser !== null,
            'isSignedIn' => $signedIn !== null,
        ]);
    }

    /**
     * Create the account and join, for someone with no StyleDesk login.
     */
    public function register(Request $request, string $token, AcceptTeamInvitation $accepter): RedirectResponse
    {
        $invitation = $this->findAcceptable($token);

        /**
         * The email is taken from the invitation, never from the form.
         *
         * The field is rendered read-only, but read-only is a rendering
         * decision and this is a POST body: accepting a submitted address
         * would let anyone with the link create an account under an email of
         * their choosing and have it verified by association.
         */
        if (User::where('email', $invitation->email)->exists()) {
            return redirect()->route('team-invite.show', $token);
        }

        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'password' => $this->passwordRules(),
            'avatar' => ['nullable', 'image', 'mimes:jpeg,png,webp', 'max:2048'],
            'terms' => ['accepted'],
        ], [
            'terms.accepted' => 'You must accept the Terms of Service and Privacy Policy.',
            'avatar.max' => 'The profile image must be 2 MB or smaller.',
        ]);

        $user = User::create([
            'first_name' => InputCase::sentence($data['first_name']),
            'last_name' => InputCase::sentence($data['last_name']),
            'email' => $invitation->email,
            'password' => $data['password'],
            'terms_accepted_at' => now(),
            'avatar_path' => $request->hasFile('avatar')
                ? $request->file('avatar')->store('avatars', 'brand')
                : null,
        ]);

        $accepter->accept($invitation, $user);

        Auth::login($user);
        $request->session()->regenerate();

        return $this->landing();
    }

    /**
     * Join, for someone who already has a StyleDesk login and is signed in.
     */
    public function accept(Request $request, string $token, AcceptTeamInvitation $accepter): RedirectResponse
    {
        $invitation = $this->findAcceptable($token);

        $accepter->accept($invitation, $request->user());

        // The tenant is resolved per-request from users.tenant_id, which has
        // just changed; regenerating avoids carrying a session fixed to the
        // pre-join state.
        $request->session()->regenerate();

        return $this->landing();
    }

    /**
     * Send an existing user to sign in, and back here afterwards.
     */
    public function login(Request $request, string $token): RedirectResponse
    {
        $this->findAcceptable($token);

        // The intended URL is what brings them back to this invitation rather
        // than to the dashboard of a business they are not yet part of.
        $request->session()->put('url.intended', route('team-invite.show', $token));

        return redirect()->route('login');
    }

    private function find(string $token): ?TeamInvitation
    {
        /**
         * Global scopes off.
         *
         * There is no tenant in scope on this route — that is the point of it
         * — so the BelongsToTenant scope would filter against a null tenant
         * and find nothing. Safety comes from the token, which is unguessable
         * and carries its own tenant.
         */
        return TeamInvitation::withoutGlobalScopes()->forToken($token)->first();
    }

    private function findAcceptable(string $token): TeamInvitation
    {
        $invitation = $this->find($token);

        if ($invitation === null || ! $invitation->isAcceptable()) {
            // 410 rather than 404: the link was real, it is simply finished.
            abort(Response::HTTP_GONE, 'This invitation is no longer valid.');
        }

        return $invitation;
    }

    /**
     * Where a new member goes once they are in.
     *
     * The dashboard, not the onboarding wizard: setup belongs to the owner,
     * and a colleague dropped into it would be editing the business's
     * addresses and opening hours on their first visit.
     */
    private function landing(): RedirectResponse
    {
        return redirect()->route('dashboard')->with('status', 'welcome-to-the-team');
    }

    /**
     * The page for a link that cannot be used.
     *
     * Expired gets its own wording because it is the one case with an obvious
     * remedy. The rest are deliberately vague: telling an unauthenticated
     * visitor whether a token was revoked, already accepted or never existed
     * tells them something about a business they have no relationship with.
     */
    private function closed(?TeamInvitation $invitation): View
    {
        $status = $invitation?->effectiveStatus();

        return view('invitations.closed', [
            'isExpired' => $status === TeamInvitation::STATUS_EXPIRED,
            'isAccepted' => $status === TeamInvitation::STATUS_ACCEPTED,
        ]);
    }
}
