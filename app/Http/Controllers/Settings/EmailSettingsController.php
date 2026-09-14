<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\ClientEmailMessage;
use App\Models\Tenant;
use App\Support\ClientEmailSender;
use App\Support\ClientEmailTemplates;
use App\Support\EmailSender;
use App\Support\Gmail;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * App Settings → Email: how this business writes to its clients.
 *
 * Two provider cards and one switch. The switch is the important one: a salon
 * that has not set this up should not be able to put mail in a client's inbox
 * by accident, so the feature is opted into rather than out of.
 *
 * The screen speaks the owner's language throughout — "Connect Gmail", never
 * "Gmail SMTP". They are connecting their email account, not configuring a
 * mail server, and naming it after the protocol makes a simple thing sound
 * like a job for somebody else.
 */
class EmailSettingsController extends Controller
{
    public function show(Request $request): View
    {
        return view('settings.email.edit', $this->state($request->user()->tenant));
    }

    public function update(Request $request): RedirectResponse
    {
        $tenant = $request->user()->tenant;

        $data = $request->validate([
            'client_email_enabled' => ['required', 'boolean'],
            /* Only a provider that can actually send. Gmail is in the
               catalogue and unavailable until 1.1, so choosing it here would
               leave the business switched on and unable to send. */
            'email_provider' => ['required', Rule::in($this->availableProviders())],
            'email_sender_name' => ['nullable', 'string', 'max:120'],
            'email_reply_to' => ['nullable', 'email', 'max:255'],
        ]);

        $tenant->forceFill($data)->save();

        return back()->with('status', __('client_email.settings.saved'));
    }

    /**
     * Prove it works, to the person asking.
     *
     * Sent to the signed-in user rather than to an address they type: the
     * question this answers is "does our mail arrive", and letting somebody
     * send a test to any address they like turns a settings screen into a
     * small mail relay.
     */
    public function test(Request $request): RedirectResponse
    {
        $tenant = $request->user()->tenant;

        if (! ClientEmailSender::enabledFor($tenant)) {
            throw ValidationException::withMessages([
                'client_email_enabled' => __('client_email.errors.disabled'),
            ]);
        }

        $from = ClientEmailTemplates::sender($tenant);

        /* An unsaved stand-in, not a row: a test is not something a client was
           told, and it has no business in anybody's history. */
        $email = new ClientEmailMessage([
            'tenant_id' => $tenant->getTenantKey(),
            'recipient_email' => $request->user()->email,
            'sender_email' => $from['from'],
            'sender_name' => $from['name'],
            'subject' => __('client_email.settings.test_subject'),
            'message' => __('client_email.settings.test_body', ['name' => $from['name']]),
            'provider' => (string) ClientEmailSender::providerFor($tenant),
        ]);
        $email->setRelation('tenant', $tenant);

        try {
            /* Through the same path a client message takes, Gmail included:
               a test that proves StyleDesk Email works while the business
               sends through Gmail has proved nothing about the thing they
               asked about. */
            $email->recipient_email = $request->user()->email;
            ClientEmailSender::deliver($email);
        } catch (\Throwable $e) {
            throw ValidationException::withMessages([
                'client_email_enabled' => __('client_email.settings.test_failed', ['reason' => $e->getMessage()]),
            ]);
        }

        return back()->with('status', __('client_email.settings.test_sent', ['email' => $request->user()->email]));
    }

    /** @return array<int, string> */
    private function availableProviders(): array
    {
        return array_keys(array_filter(
            config('client_email.providers'),
            fn (array $provider) => $provider['available'] ?? false,
        ));
    }

    /** @return array<string, mixed> */
    private function state(Tenant $tenant): array
    {
        return [
            'tenant' => $tenant,
            'providers' => config('client_email.providers'),
            'activeProvider' => ClientEmailSender::providerFor($tenant),
            'senderName' => $tenant->email_sender_name ?: $tenant->name,
            /* What a client's inbox will actually show in From. Resolved
               through the same class every outgoing message uses, so the
               screen cannot claim one address while the mail carries
               another. */
            'fromAddress' => EmailSender::for($tenant)['address'],
            /* Null when nothing is connected. The card tells the three states
               apart — never connected, connected, connected and broken —
               because what the owner has to do differs in each. */
            'gmail' => Gmail::connectionFor($tenant),
            'gmailConfigured' => Gmail::isConfigured(),
            /* Connecting a mailbox and disconnecting one are their own
               permissions: handing somebody the sender name is not handing
               them the keys to the business's email. */
            'canConnectGmail' => (bool) auth()->user()?->hasPermission('email.connect_gmail', 'own'),
            'canDisconnectGmail' => (bool) auth()->user()?->hasPermission('email.disconnect_gmail', 'own'),
        ];
    }
}
