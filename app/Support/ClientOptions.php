<?php

declare(strict_types=1);

namespace App\Support;

/**
 * The Clients module's option lists, in the reader's language.
 *
 * Same division as StaffOptions, LocationOptions and BusinessProfile:
 * config/clients.php decides which options exist, because validation reads
 * that same list and a rule built from one source with a control built from
 * another will eventually offer something the rule refuses. This decides only
 * what each option is called.
 */
class ClientOptions
{
    /** @return array<string, string> */
    public static function fields(): array
    {
        return self::translate('fields', collect(config('clients.fields'))->map(fn (array $f) => $f['label'])->all());
    }

    /** @return array<string, string> */
    public static function nameFormats(): array
    {
        return self::translate('name_formats', config('clients.name_formats'));
    }

    /** @return array<string, string> */
    public static function phoneTypes(): array
    {
        return self::translate('phone_types', config('clients.phone_types'));
    }

    /** @return array<string, string> */
    public static function emailTypes(): array
    {
        return self::translate('email_types', config('clients.email_types'));
    }

    /** @return array<string, string> */
    public static function statuses(): array
    {
        return self::translate('statuses', config('clients.statuses'));
    }

    /** @return array<string, string> */
    public static function defaultStatuses(): array
    {
        return self::translate('default_statuses', config('clients.default_statuses'));
    }

    /** @return array<string, string> */
    public static function communicationMethods(): array
    {
        return self::translate('communication_methods', config('clients.communication_methods'));
    }

    /** @return array<string, string> */
    public static function marketingDefaults(): array
    {
        return self::translate('marketing_defaults', config('clients.marketing_defaults'));
    }

    /** @return array<string, string> */
    public static function duplicateRules(): array
    {
        return self::translate('duplicate_rules', config('clients.duplicate_rules'));
    }

    /** @return array<string, string> */
    public static function searchFields(): array
    {
        return self::translate('search_fields', config('clients.search_fields'));
    }

    /** @return array<string, string> */
    public static function bookingPanels(): array
    {
        return self::translate('booking_panels', config('clients.booking_panels'));
    }

    /** @return array<string, string> */
    public static function historyPanels(): array
    {
        return self::translate('history_panels', config('clients.history_panels'));
    }

    /** @return array<string, string> */
    public static function tagColors(): array
    {
        return self::translate(
            'tag_colors',
            collect(config('clients.tag_colors'))->mapWithKeys(fn (string $hex, string $key) => [$key => ucfirst($key)])->all()
        );
    }

    /**
     * Where a client can be created from, and which of those are real yet.
     *
     * The availability flag travels with the label because the screen shows
     * an unavailable source disabled rather than hiding it — a business that
     * expects point of sale should see that we know about it.
     *
     * @return array<string, string>
     */
    public static function creationSources(): array
    {
        return self::translate(
            'creation_sources',
            collect(config('clients.creation_sources'))->map(fn (array $s) => $s['label'])->all()
        );
    }

    /** @return array<int, string> */
    public static function unavailableSources(): array
    {
        return collect(config('clients.creation_sources'))
            ->reject(fn (array $source) => $source['available'])
            ->keys()
            ->all();
    }

    /**
     * A config list with its labels replaced by translations.
     *
     * The config's own label is the fallback, so an option added without a
     * translation reads as itself rather than as a key.
     *
     * @param  array<string, string>  $fallbacks
     * @return array<string, string>
     */
    private static function translate(string $set, array $fallbacks): array
    {
        return collect($fallbacks)
            ->mapWithKeys(function (string $label, string $value) use ($set) {
                $key = 'client_options.'.$set.'.'.$value;

                return [$value => trans()->has($key) ? __($key) : $label];
            })
            ->all();
    }
}
