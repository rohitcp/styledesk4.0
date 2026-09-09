<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\TenantGmailConnection;
use App\Support\Gmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Connecting and disconnecting a business's Gmail account.
 *
 * Three steps and no jargon on any of them: press Connect, say yes to Google,
 * come back to a card showing the address. The words OAuth, scope and token
 * appear nowhere the owner can see them.
 *
 * Connecting and disconnecting are their own permissions, separate from
 * managing the email settings: handing somebody the sender name is not handing
 * them the keys to the business's mailbox.
 */
class GmailConnectionController extends Controller
{
    /** Where the state token lives between the two halves of the handshake. */
    private const STATE = 'gmail_oauth_state';

    public function connect(Request $request): RedirectResponse
    {
        $this->allow($request, 'email.connect_gmail');

        abort_unless(Gmail::isConfigured(), 404);

        /**
         * A one-time value, kept in the session and checked on the way back.
         *
         * Without it anybody can hand a signed-in owner a link that completes
         * a handshake against an attacker's Google account, and the salon
         * starts sending its client mail from a stranger's mailbox.
         */
        $state = Str::random(40);
        $request->session()->put(self::STATE, $state);

        return redirect()->away(Gmail::authorisationUrl($state));
    }

    /**
     * Back from Google.
     *
     * Every failure lands on the settings screen with a sentence saying what
     * happened. A blank page or a stack trace here is the worst possible
     * moment for one: the owner has just been sent away from the product and
     * back, and has no idea which half went wrong.
     */
    public function callback(Request $request): RedirectResponse
    {
        $this->allow($request, 'email.connect_gmail');

        $expected = $request->session()->pull(self::STATE);

        if ($expected === null || ! hash_equals($expected, (string) $request->query('state'))) {
            return $this->failed(__('client_email.gmail.state_mismatch'));
        }

        /* The owner pressed Cancel on Google's screen. Not an error — say
           nothing alarming and leave them where they were. */
        if ($request->query('error') !== null) {
            return redirect()->route('settings.email.show');
        }

        $code = (string) $request->query('code');

        if ($code === '') {
            return $this->failed(__('client_email.gmail.no_code'));
        }

        try {
            $granted = Gmail::exchangeCode($code);
        } catch (\Throwable $e) {
            return $this->failed($e->getMessage());
        }

        $tenant = $request->user()->tenant;

        /* Replaced, not added to: a business sends as one address, and a stale
           token that still works sitting behind the live one is a second
           answer to "who did that come from". */
        TenantGmailConnection::updateOrCreate(
            ['tenant_id' => $tenant->getTenantKey()],
            [
                'email' => $granted['email'],
                'google_name' => $granted['name'],
                'access_token' => $granted['access_token'],
                /* Kept only when Google sends one. It declines on a re-consent
                   that reuses an existing grant, and overwriting a good token
                   with null would break a working connection. */
                'refresh_token' => $granted['refresh_token']
                    ?: Gmail::connectionFor($tenant)?->refresh_token,
                'access_expires_at' => now()->addSeconds($granted['expires_in']),
                'scopes' => $granted['scope'],
                'status' => TenantGmailConnection::STATUS_CONNECTED,
                'last_error' => null,
                'connected_by' => $request->user()->id,
                'connected_at' => now(),
            ]
        );

        /* Connecting is choosing. An owner who has just connected their
           mailbox and finds StyleDesk Email still sending would reasonably
           call that broken. */
        $tenant->forceFill(['email_provider' => 'gmail'])->save();

        return redirect()->route('settings.email.show')
            ->with('status', __('client_email.gmail.connected', ['email' => $granted['email']]));
    }

    public function disconnect(Request $request): RedirectResponse
    {
        $this->allow($request, 'email.disconnect_gmail');

        $tenant = $request->user()->tenant;

        Gmail::connectionFor($tenant)?->delete();

        /* Fall back rather than leave the business pointed at a mailbox it no
           longer has. Disconnecting Gmail must not silently stop client email
           altogether. */
        if ($tenant->email_provider === 'gmail') {
            $tenant->forceFill(['email_provider' => 'styledesk'])->save();
        }

        return redirect()->route('settings.email.show')
            ->with('status', __('client_email.gmail.disconnected'));
    }

    private function failed(string $reason): RedirectResponse
    {
        return redirect()->route('settings.email.show')
            ->withErrors(['gmail' => __('client_email.gmail.failed', ['reason' => $reason])]);
    }

    private function allow(Request $request, string $permission): void
    {
        abort_unless($request->user()?->hasPermission($permission, 'own'), 403);
    }
}
