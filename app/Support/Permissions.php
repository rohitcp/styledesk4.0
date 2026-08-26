<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Read-only access to the permission catalogue.
 *
 * Everything that needs to know whether a permission exists, what scopes it
 * accepts or how it is labelled comes through here, so config/permissions.php
 * stays the single authority and nothing has to re-derive its shape.
 */
class Permissions
{
    /** Scopes, most restrictive first. Order is what makes comparison work. */
    public const SCOPE_ORDER = ['own', 'assigned', 'location', 'all'];

    /** @var array<string, array<string, mixed>>|null */
    private static ?array $flat = null;

    /**
     * Every permission, keyed by its catalogue key.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function all(): array
    {
        if (self::$flat !== null) {
            return self::$flat;
        }

        $flat = [];

        foreach (config('permissions.groups') as $groupKey => $group) {
            foreach ($group['permissions'] as $key => $permission) {
                $flat[$key] = $permission + ['group' => $groupKey, 'key' => $key];
            }
        }

        return self::$flat = $flat;
    }

    public static function exists(string $key): bool
    {
        return isset(self::all()[$key]);
    }

    /**
     * The scopes a permission accepts.
     *
     * A plain toggle reports ['all'] rather than an empty list, so a caller
     * never has to branch on "is this scoped" before asking what is allowed.
     *
     * @return array<int, string>
     */
    public static function scopesFor(string $key): array
    {
        return self::all()[$key]['scopes'] ?? ['all'];
    }

    public static function isOwnerOnly(string $key): bool
    {
        return (bool) (self::all()[$key]['owner_only'] ?? false);
    }

    public static function label(string $key): string
    {
        return self::all()[$key]['label'] ?? $key;
    }

    /**
     * Whether `$held` satisfies a requirement for `$needed`.
     *
     * Scopes widen: someone granted `all` satisfies a requirement for `own`,
     * but not the reverse. Comparing positions in SCOPE_ORDER is what encodes
     * that, and it is why the order of that constant is load-bearing rather
     * than cosmetic.
     */
    public static function scopeSatisfies(string $held, string $needed): bool
    {
        $heldAt = array_search($held, self::SCOPE_ORDER, true);
        $neededAt = array_search($needed, self::SCOPE_ORDER, true);

        if ($heldAt === false || $neededAt === false) {
            return false;
        }

        return $heldAt >= $neededAt;
    }
}
