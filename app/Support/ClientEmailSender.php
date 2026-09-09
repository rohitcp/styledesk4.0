<?php

declare(strict_types=1);

namespace App\Support;

use App\Mail\ClientMessageMail;
use App\Models\Booking;
use App\Models\Client;
use App\Models\ClientEmailMessage;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

/**
 * Putting a message in a client's inbox, and writing down that it happened.
 *
 * One place, because "may this be sent" has four answers and every caller
 * would otherwise check three of them. The drawer asks the same questions to
 * decide what to show; this asks them again to decide what to do, because a
 * screen is a convenience and a service is the rule.
 */
class ClientEmailSender
{
    /**
     * Whether this business can send at all.
     *
     * Two conditions, and they mean different things: the feature is switched
     * off, or it is on and pointed at a provider that cannot send. The drawer
     * needs to tell them apart to say the right thing; callers that only need
     * a yes or no get this.
     */
    public static function enabledFor(?Tenant $tenant): bool
    {
        return (bool) $tenant?->client_email_enabled && self::providerFor($tenant) !== null;
    }

    /**
     * Whether the chosen provider is actually ready to send today.
     *
     * Separate from `enabledFor` because a provider can be chosen and still be
     * unusable: Gmail is available on this deployment, the business picked it,
     * and nobody has connected a mailbox — or the one they connected has
     * stopped working. Answering "yes, enabled" there would send the desk to a
     * drawer that fails on submit.
     */
    public static function readyFor(?Tenant $tenant): bool
    {
        return self::blockingReason($tenant) === null;
    }

    /**
     * Why this business cannot send, in the order the reader can act on.
     *
     * The feature first, then the provider, then the provider's own state:
     * telling an owner their Gmail needs reconnecting when they never switched
     * client email on is answering a question they did not ask.
     */
    public static function blockingReason(?Tenant $tenant): ?string
    {
        if (! $tenant?->client_email_enabled) {
            return __('client_email.errors.disabled');
        }

        $provider = self::providerFor($tenant);

        if ($provider === null) {
            return __('client_email.errors.no_provider');
        }

        if ($provider !== 'gmail') {
            return null;
        }

        $connection = Gmail::connectionFor($tenant);

        if ($connection === null) {
            return __('client_email.errors.gmail_not_connected');
        }

        /* Connected and not working. Said as "reconnect" rather than "not
           connected", because the business believes it is set up and the
           difference is what they have to do about it. */
        return $connection->isUsable() ? null : __('client_email.errors.reconnect_gmail');
    }

    /**
     * The provider this business sends through, or null if it has none that
     * works.
     *
     * A business whose stored choice has since been switched off in config
     * falls back to nothing rather than to the other provider: sending from an
     * address the business did not choose is worse than not sending.
     */
    public static function providerFor(?Tenant $tenant): ?string
    {
        $chosen = $tenant?->email_provider ?: config('client_email.default_provider');

        return config('client_email.providers.'.$chosen.'.available') ? $chosen : null;
    }

    /**
     * Send one, record it, and log it against the client.
     *
     * The row is written *before* the send and left as `queued` if the send
     * throws. A message that failed on the wire is one the desk has to know
     * about — writing the record only on success would leave them believing a
     * client was told something they were never told.
     *
     * @throws ValidationException when the business or the client cannot receive it
     */
    public static function send(
        Client $client,
        string $subject,
        string $body,
        ?User $sender = null,
        ?Booking $booking = null,
        ?string $templateKey = null,
    ): ClientEmailMessage {
        $tenant = $client->tenant ?? tenant();

        if (! $tenant?->client_email_enabled) {
            throw ValidationException::withMessages(['subject' => __('client_email.errors.disabled')]);
        }

        $provider = self::providerFor($tenant);

        if ($provider === null) {
            throw ValidationException::withMessages(['subject' => __('client_email.errors.no_provider')]);
        }

        /* Asked again here, not only in the drawer. A screen is a convenience;
           this is the boundary. */
        if (($blocked = self::blockingReason($tenant)) !== null) {
            throw ValidationException::withMessages(['subject' => $blocked]);
        }

        /* The client's own address, and it has to be one. A send to nobody is
           a row in the history claiming a client was written to. */
        $recipient = (string) $client->email;

        if ($recipient === '' || ! filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            throw ValidationException::withMessages(['subject' => __('client_email.errors.no_address')]);
        }

        $from = ClientEmailTemplates::sender($tenant);

        $email = ClientEmailMessage::create([
            'tenant_id' => $tenant->getTenantKey(),
            'client_id' => $client->id,
            'recipient_email' => $recipient,
            'sender_email' => $from['from'],
            'sender_name' => $from['name'],
            'subject' => $subject,
            'message' => $body,
            'provider' => $provider,
            'template_key' => $templateKey,
            'booking_id' => $booking?->id,
            'sent_by' => $sender?->id,
            'status' => ClientEmailMessage::STATUS_QUEUED,
            'queued_at' => now(),
        ]);

        try {
            self::deliver($email);
            $email->markSent();
        } catch (\Throwable $e) {
            /* The reason is kept, the exception is not re-thrown: the message
               is on the record as failed, the history says so, and the desk
               can try again. Blowing up the request would lose the record of
               the attempt, which is the one thing worth keeping. */
            $email->markFailed($e->getMessage());
        }

        ClientActivityLog::emailSent($email);

        return $email;
    }

    /**
     * Put it on the wire, whichever way this business sends.
     *
     * Both paths render the same mailable, so a client cannot tell from the
     * message which provider carried it — only from the address it came from,
     * which is the whole point of connecting one.
     */
    public static function deliver(ClientEmailMessage $email): void
    {
        if ($email->provider !== 'gmail') {
            Mail::to($email->recipient_email)->send(new ClientMessageMail($email));

            return;
        }

        $connection = Gmail::connectionFor($email->tenant);

        if ($connection === null) {
            throw new \RuntimeException(__('client_email.errors.gmail_not_connected'));
        }

        Gmail::send(
            connection: $connection,
            to: $email->recipient_email,
            subject: $email->subject,
            /* The same template Laravel would have posted, rendered to a
               string: Gmail takes a whole message rather than fields. */
            html: (new ClientMessageMail($email))->render(),
            /*
             * No Reply-To, deliberately.
             *
             * The message goes out FROM the connected mailbox, so a client
             * pressing Reply writes straight back into it — which is the whole
             * point of connecting one, and what the brief asks for. Setting the
             * StyleDesk reply-to here would divert those replies away from the
             * inbox the salon is watching.
             *
             * That address still matters for StyleDesk Email, where the from
             * address is ours and a reply would otherwise reach nobody.
             */
            replyTo: null,
            senderName: $email->sender_name,
        );
    }
}
