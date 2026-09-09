<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Resource;
use App\Models\Tenant;
use Illuminate\Support\Collection;

/**
 * The number on a resource, and the next one that is free.
 *
 * Generated rather than typed. "RES-001" sitting after "RES-002" is the kind
 * of mistake nobody notices until two labels on two chairs say the same
 * thing — and a business with forty rooms is not going to keep a numbering
 * scheme in its head. So the form arrives with the next code already in it,
 * still editable for the business that has its own labels on the wall.
 *
 * The format is two settings, not one pattern: a prefix somebody types and a
 * width for the number. A single "RES-{000}" string would have to be parsed
 * to find the number again, and the number is the part that is read back and
 * incremented.
 */
class ResourceCode
{
    /** What this business puts in front of the number. */
    public static function prefix(?Tenant $tenant): string
    {
        $prefix = $tenant?->resource_code_prefix;

        return $prefix === null || $prefix === ''
            ? (string) config('resources.code.prefix')
            : $prefix;
    }

    /** How many digits the number is padded to. */
    public static function padding(?Tenant $tenant): int
    {
        $padding = (int) ($tenant?->resource_code_padding ?? config('resources.code.padding'));

        return max(
            (int) config('resources.code.min_padding'),
            min((int) config('resources.code.max_padding'), $padding)
        );
    }

    /** What the format looks like, for a preview beside the setting. */
    public static function sample(?Tenant $tenant, int $number = 1): string
    {
        return self::format($tenant, $number);
    }

    /**
     * The next code this business has not used.
     *
     * Counts from the highest number already issued rather than from how many
     * resources exist: deleting resource 7 of 7 must not hand the next one
     * number 7 again, because the old label may still be on the old chair —
     * and the unique index would refuse it anyway if the row was only
     * archived.
     *
     * Soft-deleted resources are included for the same reason: an archived
     * resource still holds its code, and restoring one whose number had been
     * reissued would be a collision nobody could resolve.
     */
    public static function next(?Tenant $tenant): string
    {
        if ($tenant === null) {
            return self::format(null, 1);
        }

        $used = self::usedCodes($tenant);
        $number = self::highestNumber($tenant, $used) + 1;
        $attempts = (int) config('resources.code.max_attempts');

        /* The highest number plus one is normally free. It is not when a code
           was typed by hand — a business with "RES-004" already on a shelf —
           so the next free one is searched for rather than assumed, and the
           search is bounded so one save can never become an endless loop. */
        for ($i = 0; $i < $attempts; $i++, $number++) {
            $candidate = self::format($tenant, $number);

            if (! $used->contains($candidate)) {
                return $candidate;
            }
        }

        /* Every number in the range is taken, which means the scheme itself
           has run out. An empty string is the honest answer: the field is
           optional, so the reader is asked to type one rather than handed a
           code that will be refused. */
        return '';
    }

    /** One number, dressed as a code. */
    public static function format(?Tenant $tenant, int $number): string
    {
        return self::prefix($tenant).str_pad((string) $number, self::padding($tenant), '0', STR_PAD_LEFT);
    }

    /**
     * The biggest number already issued under this prefix.
     *
     * Only codes that match the current prefix followed by digits count. A
     * business that used to number things "OLD-12" and now uses "RES-" is
     * starting a new scheme, and continuing from 13 would be reading the old
     * one as though it were this one.
     *
     * @param  Collection<int, string>  $used
     */
    private static function highestNumber(Tenant $tenant, $used): int
    {
        $pattern = '/^'.preg_quote(self::prefix($tenant), '/').'(\d+)$/';

        return (int) $used
            ->map(fn (string $code) => preg_match($pattern, $code, $matches) ? (int) $matches[1] : 0)
            ->max();
    }

    /**
     * Every code this business has spoken for, archived ones included.
     *
     * @return Collection<int, string>
     */
    private static function usedCodes(Tenant $tenant)
    {
        return Resource::withoutGlobalScopes()
            ->withTrashed()
            ->where('tenant_id', $tenant->getTenantKey())
            ->whereNotNull('code')
            ->pluck('code');
    }
}
