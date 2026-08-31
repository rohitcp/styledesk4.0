<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Collection;

/**
 * What StyleDesk can notify somebody about, and what they have said about it.
 *
 * The catalogue itself is config/notifications.php. This turns it into the
 * three things the rest of the app asks for: the screen's grid, one person's
 * effective answer for a single message, and the list of keys a request is
 * allowed to mention.
 *
 * The rule that shapes everything here: a stored row is an exception, not the
 * state. Somebody with no rows has the catalogue's defaults, so a new
 * notification type added next year arrives switched on for the people it was
 * designed for rather than silently off for everybody who ever saved this
 * screen.
 */
class NotificationCatalog
{
    /**
     * The channels, in column order.
     *
     * @return array<string, array{available: bool, label: string}>
     */
    public static function channels(): array
    {
        return collect(config('notifications.channels'))
            ->map(fn (array $channel, string $key) => $channel + [
                'label' => __('account.notifications.channels.'.$key),
            ])
            ->all();
    }

    /** The channels a preference may actually be saved for. */
    public static function availableChannels(): array
    {
        return collect(config('notifications.channels'))
            ->filter(fn (array $channel) => $channel['available'])
            ->keys()
            ->all();
    }

    /**
     * Every type, flattened, keyed by its stored key.
     *
     * @return Collection<string, array{group: string, channels: array<int, string>, default: array<int, string>, critical: bool}>
     */
    public static function types(): Collection
    {
        return collect(config('notifications.groups'))
            ->flatMap(fn (array $types, string $group) => collect($types)
                ->map(fn (array $type) => [
                    'group' => $group,
                    'channels' => $type['channels'],
                    'default' => $type['default'],
                    'critical' => $type['critical'] ?? false,
                ])
                ->all());
    }

    public static function isCritical(string $typeKey): bool
    {
        return (bool) (self::types()->get($typeKey)['critical'] ?? false);
    }

    public static function has(string $typeKey): bool
    {
        return self::types()->has($typeKey);
    }

    /**
     * The screen: groups, their types, and whether each switch is on.
     *
     * Labels come from the account language files rather than from the
     * config, for
     * the same reason BusinessProfile translates its own options — a config
     * that carried English would put English on a Spanish screen.
     *
     * @return array<int, array{key: string, label: string, description: string, types: array<int, array{key: string, label: string, description: ?string, critical: bool, channels: array<string, array{supported: bool, enabled: bool, locked: bool}>}>}>
     */
    public static function grid(User $user): array
    {
        $saved = self::savedFor($user);
        $channels = array_keys(config('notifications.channels'));

        return collect(config('notifications.groups'))
            ->map(fn (array $types, string $group) => [
                'key' => $group,
                'label' => __('account.notifications.groups.'.$group.'.label'),
                'description' => __('account.notifications.groups.'.$group.'.description'),
                'types' => collect($types)->map(function (array $type, string $key) use ($saved, $channels) {
                    $critical = $type['critical'] ?? false;

                    return [
                        'key' => $key,
                        'label' => __('account.notifications.types.'.$key),
                        'critical' => $critical,
                        'channels' => collect($channels)->mapWithKeys(fn (string $channel) => [
                            $channel => [
                                'supported' => in_array($channel, $type['channels'], true),
                                /* Critical types read as on whatever is stored:
                                   a row written before a type became critical
                                   must not keep it silenced. */
                                'enabled' => $critical || self::resolve($saved, $key, $channel, $type['default']),
                                'locked' => $critical,
                            ],
                        ])->all(),
                    ];
                })->values()->all(),
            ])
            ->values()
            ->all();
    }

    /**
     * Whether this person wants this message on this channel.
     *
     * The one method the sending side should call. Unknown keys answer false
     * rather than throwing: a notification that has been retired should stop
     * being sent, not break the job that sends it.
     */
    public static function wants(User $user, string $typeKey, string $channel): bool
    {
        $type = self::types()->get($typeKey);

        if ($type === null || ! in_array($channel, $type['channels'], true)) {
            return false;
        }

        if ($type['critical']) {
            return true;
        }

        return self::resolve(self::savedFor($user), $typeKey, $channel, $type['default']);
    }

    /**
     * The defaults, as the grid's shape — what "Reset to default" restores.
     *
     * @return array<string, array<string, bool>>
     */
    public static function defaults(): array
    {
        return self::types()
            ->map(fn (array $type) => collect($type['channels'])
                ->mapWithKeys(fn (string $channel) => [
                    $channel => $type['critical'] || in_array($channel, $type['default'], true),
                ])
                ->all())
            ->all();
    }

    /**
     * One person's stored exceptions, as [type_key][channel] => bool.
     *
     * @return array<string, array<string, bool>>
     */
    private static function savedFor(User $user): array
    {
        return $user->notificationPreferences
            ->groupBy('type_key')
            ->map(fn (Collection $rows) => $rows->pluck('is_enabled', 'channel')
                ->map(fn ($enabled) => (bool) $enabled)
                ->all())
            ->all();
    }

    /**
     * @param  array<string, array<string, bool>>  $saved
     * @param  array<int, string>  $default
     */
    private static function resolve(array $saved, string $typeKey, string $channel, array $default): bool
    {
        return $saved[$typeKey][$channel] ?? in_array($channel, $default, true);
    }
}
