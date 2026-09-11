<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Client;
use App\Models\ClientSettings;
use App\Models\Tenant;
use Illuminate\Support\Str;

/**
 * The walk-in who turns out to be a client.
 *
 * A walk-in is taken without a record, which is right for somebody who came in
 * once and wrong for everybody else: their name, number and address were typed
 * at the desk and then went nowhere, so the same person walking in twice was
 * two strangers, and nothing they were told could be followed up.
 *
 * What this does is narrow on purpose. It never merges two records and never
 * edits one it finds — matching is a warning everywhere else in this
 * application, and a wrongly merged history is not something a receptionist
 * can unpick. Finding somebody means attaching the booking to them and leaving
 * their record exactly as it was; the details typed at the desk are already
 * kept on the booking itself, where they can be read against the record later.
 */
class WalkInClients
{
    /**
     * The client this walk-in is, creating the record if they are new.
     *
     * Null when there is nothing to go on, which is the common case and not a
     * failure: a walk-in who gives only a first name cannot be matched to
     * anybody and cannot be matched against later either, so a record for them
     * is a row nobody can ever use and one more "John" between the desk and
     * the person it is looking for.
     *
     * @param  array{name?: string|null, phone?: string|null, email?: string|null}  $guest
     */
    public static function resolve(
        Tenant $tenant,
        array $guest,
        ?int $locationId = null,
        ?int $staffId = null,
        ?string $visitedOn = null,
    ): ?Client {
        $phone = trim((string) ($guest['phone'] ?? ''));
        $email = Str::lower(trim((string) ($guest['email'] ?? '')));
        $name = trim((string) ($guest['name'] ?? ''));

        /* A way to reach them, or nothing doing. Name alone is not identity:
           it cannot be matched on — the duplicate rules are all built from a
           number or an address — so a record made from one could never be
           found again by the person who needed it. */
        if ($phone === '' && $email === '') {
            return null;
        }

        $settings = ClientSettings::forTenant($tenant);

        /* The business decides which desks may put someone on the book. A
           salon that has switched walk-ins off has said it does not want the
           client list filling from the front door. */
        if (! self::mayCreateFromWalkIn($settings)) {
            return null;
        }

        $candidate = [
            'first_name' => self::firstName($name),
            'last_name' => self::lastName($name),
            'mobile' => $phone,
            'email' => $email,
        ];

        /* The same engine the client form runs, against the rules this
           business configured — one set of rules, in one place. Only the
           contact rules are consulted here: name_mobile would need a name the
           desk may not have taken, and its answer is already covered by the
           number on its own. */
        $existing = Client::possibleDuplicates(
            $tenant->getTenantKey(),
            $candidate,
            array_values(array_intersect($settings->duplicate_rules ?? [], ['email', 'mobile'])),
        )->first();

        if ($existing !== null) {
            return $existing;
        }

        return self::create($tenant, $candidate, $locationId, $staffId, $visitedOn);
    }

    /**
     * Whether the front door is one of the ways a client may be added.
     *
     * Defaults to allowed when the business has never said: the setting lists
     * walk-in bookings as available, and a business that has not opened that
     * screen has not opted out of anything.
     */
    private static function mayCreateFromWalkIn(ClientSettings $settings): bool
    {
        $sources = $settings->creation_sources;

        return $sources === null || in_array('walk_in', $sources, true);
    }

    /**
     * @param  array{first_name: string, last_name: string|null, mobile: string, email: string}  $candidate
     */
    private static function create(
        Tenant $tenant,
        array $candidate,
        ?int $locationId,
        ?int $staffId,
        ?string $visitedOn,
    ): Client {
        $client = $tenant->clients()->create([
            'client_ref' => Client::nextRef($tenant->getTenantKey()),
            'first_name' => $candidate['first_name'],
            'last_name' => $candidate['last_name'],
            'source' => 'walk_in',
            /* Written once, now. Today is the day they first came in, and
               that is not a fact any later event can change. */
            'first_visit_at' => $visitedOn ?? now()->toDateString(),
            /* Where they came and who saw them — a preference in the sense
               that matters, which is that it is the only branch and stylist
               anybody knows about them. */
            'preferred_location_id' => $locationId,
            'preferred_staff_id' => $staffId,
        ]);

        /* Through the contact rows, never straight into the columns. Those
           are a cache of the primary row: filled on their own they give a
           client whose number shows in the listing and nowhere on their own
           profile, and nothing errors while it happens. */
        if ($candidate['mobile'] !== '') {
            $client->syncPhones([[
                'number' => $candidate['mobile'],
                'type' => 'mobile',
                'is_primary' => true,
            ]]);
        }

        if ($candidate['email'] !== '') {
            $client->syncEmails([[
                'email' => $candidate['email'],
                'type' => 'personal',
                'is_primary' => true,
            ]]);
        }

        return $client->refresh();
    }

    /**
     * A typed name split the way a desk types it: given name first.
     *
     * Everything after the first space is the surname, so "Mary Jane Watson"
     * keeps Mary as the first name rather than inventing a middle one.
     */
    private static function firstName(string $name): string
    {
        if ($name === '') {
            /* A number with no name is still a person to attach a booking to,
               and the record has to be called something until somebody at the
               desk asks. */
            return __('clients.walk_in.unnamed');
        }

        return Str::before($name, ' ');
    }

    private static function lastName(string $name): ?string
    {
        $rest = trim(Str::after($name, ' '));

        return $name === '' || $rest === '' ? null : $rest;
    }
}
