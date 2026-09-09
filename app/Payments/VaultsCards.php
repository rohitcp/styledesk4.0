<?php

declare(strict_types=1);

namespace App\Payments;

use App\Models\Client;
use App\Models\ClientPaymentMethod;

/**
 * A gateway that can hold a card and charge it again later.
 *
 * Its own contract rather than more methods on PaymentGateway, because most
 * gateways are not one. Cash cannot be charged again next month and neither
 * can a card taken on the terminal beside the till — those are recorders, and
 * forcing them to implement a vault would mean four methods that throw.
 *
 * The architecture this exists to enforce: StyleDesk stores payment
 * REFERENCES, not cards. The card goes from the client's browser to the
 * gateway's own secure component and never passes through this application.
 * What comes back is a token, and a token is all StyleDesk keeps.
 *
 * That means no method here ever takes a card number, an expiry or a CVC as
 * an argument. If one ever needs to, the design is wrong.
 */
interface VaultsCards
{
    /**
     * Start a card being added, and hand back what the browser needs.
     *
     * The client secret returned is what the gateway's own component uses to
     * collect the card directly. StyleDesk never sees what is typed into it.
     *
     * @return array{client_secret: string, customer_id: string, publishable_key: ?string}
     */
    public function startCardSetup(Client $client): array;

    /**
     * Record a card the gateway has already stored.
     *
     * Called after the browser's exchange with the gateway has succeeded, and
     * given only the id it returned. The safe display details — brand, last
     * four, expiry — are read back FROM the gateway rather than accepted from
     * the browser: what the client's page claims a card is, and what the
     * gateway will actually charge, have to be the same thing, and only one
     * of the two can be trusted.
     *
     * @throws PaymentFailed when the gateway does not recognise the method
     */
    public function rememberCard(Client $client, string $gatewayPaymentMethodId, string $gatewayCustomerId): ClientPaymentMethod;

    /**
     * Charge a saved card with nobody present.
     *
     * This is what a renewal is. The client is not at the desk and cannot be
     * asked for a code, so a gateway that needs one has to say so as a
     * failure with a reason — silently succeeding and settling later would
     * have StyleDesk issue credits nobody paid for.
     *
     * @return array{reference: string, status: string}
     *
     * @throws PaymentFailed with the reason the card was refused
     */
    public function chargeSavedCard(ClientPaymentMethod $method, int $amountMinor, string $currency, string $description): array;

    /**
     * Tell the gateway to let the card go.
     *
     * StyleDesk keeps its own row — a membership renewed on this card last
     * month still points at it — but the gateway should stop holding a card
     * nobody is going to charge again. A failure here is not the caller's
     * problem: the card is out of use either way.
     */
    public function forgetCard(ClientPaymentMethod $method): void;
}
