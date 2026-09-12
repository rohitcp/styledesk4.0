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
 * This runs the moment the details are worth keeping — while the booking is
 * still a lead — rather than at the end. A booking that is never finished is
 * the case where the record matters most: somebody stood at the desk, gave
 * their number and left, and waiting for a completed appointment is how that
 * person was lost.
 *
 * What it does is narrow on purpose. It never merges two records, and it only
 * ever fills a blank on one it finds — matching is a warning everywhere else
 * in this application, and a wrongly merged history is not something a
 * receptionist can unpick. Where the address is one person and the number is
 * another it attaches nothing at all and says so, because choosing between
 * them is a decision and this is not the thing that gets to make it.
 */
class WalkInClients
{
    /**
     * The client this walk-in is, creating the record if they are new.
     *
     * Nothing to go on is the common case and not a failure: a walk-in who
     * gives only a first name cannot be matched to anybody and cannot be
     * matched against later either, so a record for them is a row nobody can
     * ever use and one more "John" between the desk and the person it is
     * looking for.
     *
     * Neither is an invalid one. "973555" is a number somebody is halfway
     * through typing and "sarah@" is an address nobody can be reached at;
     * both would be stored as fact and neither could ever be corrected by the
     * person who mistyped it, because they have already left.
     *
     * @param  array{name?: string|null, phone?: string|null, email?: string|null, country?: string|null}  $guest
     */
    public static function resolve(
        Tenant $tenant,
        array $guest,
        ?int $locationId = null,
        ?int $staffId = null,
        ?string $visitedOn = null,
    ): WalkInClient {
        $name = trim((string) ($guest['name'] ?? ''));

        /* Validated before anything is looked up, let alone written. An
           address that is not an address matches nobody and would be stored
           as one, and a half-typed number would create a record on every
           keystroke that happened to land on a plausible length. */
        $email = EmailAddress::looksValid($guest['email'] ?? null)
            ? Str::lower(trim((string) $guest['email']))
            : null;

        $phone = trim((string) ($guest['phone'] ?? ''));
        $e164 = PhoneNumber::normalise($phone, $guest['country'] ?? null);

        if ($e164 === null) {
            $phone = '';
        }

        /* A way to reach them, or nothing doing. Name alone is not identity:
           it cannot be matched on — the duplicate rules are all built from a
           number or an address — so a record made from one could never be
           found again by the person who needed it. */
        if ($phone === '' && $email === null) {
            return WalkInClient::none('incomplete');
        }

        $settings = ClientSettings::forTenant($tenant);

        /* The business decides which desks may put someone on the book. A
           salon that has switched walk-ins off has said it does not want the
           client list filling from the front door. */
        if (! self::mayCreateFromWalkIn($settings)) {
            return WalkInClient::none('not_allowed');
        }

        $candidate = [
            'first_name' => self::firstName($name),
            'last_name' => self::lastName($name),
            'mobile' => $phone,
            'email' => $email ?? '',
            'phone_country' => $guest['country'] ?? null,
        ];

        /* Asked one field at a time rather than both together, because which
           field matched is the answer. Both matching the same person is a
           recognition; both matching different people is a conflict; and a
           single query returning two rows cannot tell those apart. */
        $rules = array_values(array_intersect($settings->duplicate_rules ?? [], ['email', 'mobile']));

        $byEmail = $email === null ? null : self::matchOn($tenant, ['email' => $email], $rules, 'email');
        $byPhone = $phone === '' ? null : self::matchOn(
            $tenant,
            ['mobile' => $phone, 'phone_country' => $guest['country'] ?? null],
            $rules,
            'mobile',
        );

        if ($byEmail !== null && $byPhone !== null && $byEmail->id !== $byPhone->id) {
            return WalkInClient::conflict([$byEmail, $byPhone]);
        }

        $existing = $byEmail ?? $byPhone;

        if ($existing !== null) {
            self::fillBlanks($existing, $candidate);

            return WalkInClient::matched($existing);
        }

        return WalkInClient::created(self::create($tenant, $candidate, $locationId, $staffId, $visitedOn));
    }

    /**
     * The one client this field points at, where exactly one does.
     *
     * The same engine the client form runs, against the rules this business
     * configured — one set of rules, in one place. Only the contact rules are
     * consulted: name_mobile would need a name the desk may not have taken,
     * and its answer is already covered by the number on its own.
     *
     * @param  array<string, mixed>  $field
     * @param  array<int, string>  $rules
     */
    private static function matchOn(Tenant $tenant, array $field, array $rules, string $rule): ?Client
    {
        if (! in_array($rule, $rules, true)) {
            return null;
        }

        return Client::possibleDuplicates($tenant->getTenantKey(), $field, [$rule])->first();
    }

    /**
     * Add what the record is missing, and change nothing it already has.
     *
     * A walk-in who gives an address the record does not hold is filling in a
     * blank, and that is worth having. A walk-in whose number differs from
     * the one on file is not a correction — it might be their new phone, or
     * it might be their partner's, or the receptionist's own typo — so the
     * stored one stands and the typed one stays on the booking, where the two
     * can be read against each other by somebody who can ask.
     *
     * @param  array{first_name: string, last_name: string|null, mobile: string, email: string, phone_country?: string|null}  $candidate
     */
    private static function fillBlanks(Client $client, array $candidate): void
    {
        if ($candidate['mobile'] !== '' && $client->phones()->count() === 0) {
            $client->syncPhones([[
                'number' => $candidate['mobile'],
                'country' => $candidate['phone_country'] ?? null,
                'type' => 'mobile',
                'is_primary' => true,
            ]]);
        }

        if ($candidate['email'] !== '' && $client->emails()->count() === 0) {
            $client->syncEmails([[
                'email' => $candidate['email'],
                'type' => 'personal',
                'is_primary' => true,
            ]]);
        }

        /* A surname where the record has none. The given name is left alone:
           it is never blank, and "Sarah" on file against "Sara" typed at the
           desk is not something to resolve by overwriting. */
        if (blank($client->last_name) && filled($candidate['last_name'])) {
            $client->forceFill(['last_name' => $candidate['last_name']])->save();
        }
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
     * @param  array{first_name: string, last_name: string|null, mobile: string, email: string, phone_country?: string|null}  $candidate
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
                'country' => $candidate['phone_country'] ?? null,
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
