<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Tenant;
use Illuminate\Mail\Mailables\Address;

/**
 * Who a StyleDesk email comes from, and where a reply to it goes.
 *
 * One answer for every message a business sends — a booking confirmation, a
 * cancellation, a review request, a payment link, a note typed at the desk.
 * They were each falling back to the global `mail.from` with no reply-to,
 * which meant a business could set a sender name and a reply-to address in
 * App Settings and watch neither of them appear on anything except a manually
 * written client email. A client hitting Reply on their own booking
 * confirmation was writing to StyleDesk's noreply.
 *
 * The address is StyleDesk's and stays StyleDesk's on the SMTP provider.
 * Claiming to send from the salon's own domain without being authorised to
 * sign for it lands the message in spam, if it leaves at all — which is why
 * the From field is shown on the settings screen and not offered as a box to
 * type in. Reply-To is what carries the conversation back to the business,
 * and that is theirs to set.
 *
 * Gmail is the exception and the reason the distinction matters: a business
 * sending through its own connected mailbox genuinely is the sender, so the
 * address becomes theirs and replies land in that inbox without StyleDesk
 * arranging anything.
 */
class EmailSender
{
    /**
     * The identity to put on a message from this business.
     *
     * @return array{name: string, address: string, reply_to: ?string}
     */
    public static function for(?Tenant $tenant): array
    {
        $name = (string) ($tenant?->email_sender_name ?: $tenant?->name ?: config('app.name'));
        $address = (string) config('mail.from.address');
        $replyTo = $tenant?->email_reply_to ?: null;

        /* Sending through the business's own mailbox. The address is theirs,
           so the "via StyleDesk" qualifier would be a lie and a reply-to
           pointing anywhere else would be a redirection nobody asked for. */
        if ($tenant !== null && ClientEmailSender::providerFor($tenant) === 'gmail') {
            $connection = Gmail::connectionFor($tenant);

            if ($connection !== null && $connection->isUsable() && filled($connection->email)) {
                return [
                    'name' => $name,
                    'address' => (string) $connection->email,
                    'reply_to' => $replyTo,
                ];
            }
        }

        return ['name' => $name, 'address' => $address, 'reply_to' => $replyTo];
    }

    /**
     * The From header, with the qualifier where one is honest.
     *
     * "Smile Spa via StyleDesk" on StyleDesk's own address, because that is
     * what the client's inbox will show them anyway and a name that hid it
     * would read as a spoof. Plain "Smile Spa" when the business is sending
     * from its own mailbox, because then it simply is them.
     */
    public static function fromAddress(?Tenant $tenant): Address
    {
        $sender = self::for($tenant);
        $ours = $sender['address'] === (string) config('mail.from.address');

        return new Address(
            $sender['address'],
            $ours
                ? __('client_email.send.from_via', ['name' => $sender['name']])
                : $sender['name'],
        );
    }

    /**
     * Where a reply goes, or nothing.
     *
     * An array because that is what a Mailable's `replyTo` takes, and an
     * empty one is how it is told there is nobody to reply to.
     *
     * @return array<int, Address>
     */
    public static function replyTo(?Tenant $tenant): array
    {
        $sender = self::for($tenant);

        return $sender['reply_to'] === null
            ? []
            : [new Address($sender['reply_to'], $sender['name'])];
    }
}
