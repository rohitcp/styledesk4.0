<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Client;

/**
 * What happened when a walk-in's details were put to the client list.
 *
 * A client and a boolean would not carry it. The desk has to be told apart
 * four ways — nothing to go on yet, put on file, recognised as somebody
 * already on file, or matching two different people — and the last of those
 * is the whole reason this is a value object: a conflict has no client to
 * return, is not a failure, and must reach the screen with both records
 * attached so a person can choose between them.
 *
 * @see WalkInClients::resolve()
 */
class WalkInClient
{
    /**
     * @param  array<int, Client>  $conflicts
     */
    private function __construct(
        public readonly ?Client $client,
        public readonly bool $created,
        public readonly bool $matched,
        public readonly array $conflicts = [],
        public readonly ?string $reason = null,
    ) {}

    /** Nobody yet: no valid way to reach them, or the business said no. */
    public static function none(?string $reason = null): self
    {
        return new self(null, false, false, [], $reason);
    }

    public static function created(Client $client): self
    {
        return new self($client, true, false);
    }

    /** Already on file. Their record is left exactly as it was. */
    public static function matched(Client $client): self
    {
        return new self($client, false, true);
    }

    /**
     * The address is one person and the number is another.
     *
     * Deliberately carries no client. Picking one would be a merge decided by
     * whichever field the desk happened to type first, and a wrongly joined
     * history is not something a receptionist can unpick.
     *
     * @param  array<int, Client>  $clients
     */
    public static function conflict(array $clients): self
    {
        return new self(null, false, false, array_values($clients), 'conflict');
    }

    public function hasConflict(): bool
    {
        return $this->conflicts !== [];
    }

    /** The id to hang a lead or a booking on, where there is one. */
    public function clientId(): ?int
    {
        return $this->client?->id;
    }
}
