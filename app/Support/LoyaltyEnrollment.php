<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Client;
use App\Models\ClientLoyaltyPoint;
use App\Models\LoyaltySettings;
use Illuminate\Support\Facades\DB;

/**
 * Joining the rewards scheme.
 *
 * One entry point, because joining is four things that have to happen
 * together or not at all: the client is stamped as a member, given a number
 * to quote, credited with whatever the business gives for joining, and the
 * whole of it written to the timeline. Done separately they leave a member
 * with no number, or welcome points credited to somebody whose record failed
 * to save.
 *
 * It runs only after the client exists. That is the rule the brief is most
 * insistent about and it is the right one: a loyalty account created while a
 * form is still being filled in is an account attached to nobody, and points
 * issued before the save is a balance that survives a cancelled creation.
 */
class LoyaltyEnrollment
{
    /** How the create-client form finds its checkbox already answered. */
    public const MODES = ['auto', 'default_on', 'opt_in'];

    /** What can become of a membership once it exists. */
    public const STATUSES = ['active', 'paused', 'suspended', 'unenrolled'];

    /**
     * Enrol this client, once.
     *
     * Idempotent on purpose: `store()` and any later "join the scheme" action
     * both reach this, and a client enrolled twice would be a second welcome
     * bonus for the same person. An already-enrolled client is left exactly
     * as they were, member number and joining date included.
     *
     * Returns whether this call is what enrolled them, so the caller can say
     * "created and enrolled" rather than guessing.
     */
    public static function enroll(
        Client $client,
        ?LoyaltySettings $settings = null,
        ?int $userId = null,
        ?int $locationId = null,
        string $source = 'client_creation',
    ): bool {
        $settings ??= LoyaltySettings::forTenant($client->tenant);

        /* A scheme that is switched off enrols nobody. The business has
           paused rewards; signing people up to a paused scheme would promise
           them something nothing is currently doing. */
        if (! $settings->is_enabled || $client->isEnrolledInLoyalty()) {
            return false;
        }

        DB::transaction(function () use ($client, $settings, $userId, $locationId, $source) {
            $client->forceFill([
                'loyalty_member_id' => self::nextMemberId((string) $client->tenant_id),
                'loyalty_enrolled_at' => now(),
                'loyalty_status' => 'active',
                'loyalty_enrolled_by' => $userId,
                'loyalty_enrollment_location_id' => $locationId,
                'loyalty_enrollment_source' => $source,
            ])->save();

            self::creditWelcomePoints($client, $settings, $userId);
        });

        ClientActivityLog::loyaltyEnrolled($client->fresh(), $userId);

        return true;
    }

    /**
     * The joining bonus, where the business gives one.
     *
     * A ledger line like every other movement — there is no such thing here
     * as a balance that was not written down — and typed `welcome` so the
     * history can say what it was for rather than calling it an adjustment
     * somebody made.
     */
    private static function creditWelcomePoints(Client $client, LoyaltySettings $settings, ?int $userId): void
    {
        $points = (int) $settings->welcome_points;

        if ($points <= 0) {
            return;
        }

        LoyaltyPoints::credit($client, $points, 'welcome', $userId);

        ClientActivityLog::loyaltyWelcomePoints($client, $points, $userId);
    }

    /**
     * Whether the create-client form should arrive with the box ticked.
     *
     * `auto` and `default_on` both tick it; they differ in whether the
     * receptionist may untick it, which is a question for the form rather
     * than for this.
     */
    public static function defaultsToEnrolled(LoyaltySettings $settings): bool
    {
        return in_array($settings->enrollment_mode, ['auto', 'default_on'], true);
    }

    /** Whether the receptionist gets a say at all. */
    public static function isOptional(LoyaltySettings $settings): bool
    {
        return $settings->enrollment_mode !== 'auto';
    }

    /**
     * The next member number for this business, as RW-000123.
     *
     * Taken from the highest already issued rather than from a count, for the
     * same reason `Client::nextRef()` is: a count reuses the number of a
     * client that was removed, and a member number that once meant one person
     * and later means another is worse than a gap.
     */
    public static function nextMemberId(string $tenantId): string
    {
        $prefix = (string) config('loyalty.member_id.prefix', 'RW-');
        $padding = (int) config('loyalty.member_id.padding', 6);

        $highest = DB::table('clients')
            ->where('tenant_id', $tenantId)
            ->where('loyalty_member_id', 'like', $prefix.'%')
            ->lockForUpdate()
            ->selectRaw('max(cast(substring(loyalty_member_id, ?) as unsigned)) as top', [mb_strlen($prefix) + 1])
            ->value('top');

        return $prefix.str_pad((string) ((int) $highest + 1), $padding, '0', STR_PAD_LEFT);
    }

    /**
     * What this client has been given for joining, if anything.
     *
     * Read from the ledger rather than from the settings: the bonus a client
     * actually got is the line that was written on the day they joined, and a
     * business that changed the figure since has not changed what they were
     * given.
     */
    public static function welcomePointsFor(Client $client): int
    {
        return (int) ClientLoyaltyPoint::query()
            ->where('client_id', $client->id)
            ->where('type', 'welcome')
            ->sum('points');
    }
}
