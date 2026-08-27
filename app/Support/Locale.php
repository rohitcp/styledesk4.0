<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Which language the interface is in, and who decided.
 *
 * Three answers, narrowest first: the person's own choice, then the business's
 * primary language, then English. That order is the whole feature — a business
 * sets the default, and any colleague who reads a different language changes
 * only their own screen.
 *
 * Scope is deliberately narrow and worth restating, because it is the thing
 * most easily got wrong: this decides the *interface*. Service names, client
 * records, email templates and anything else a business has typed are its own
 * words and are never translated by a language setting.
 */
class Locale
{
    /**
     * The languages a business may enable.
     *
     * Only the ones marked active. A language listed in the register but not
     * yet translated must not reach a selector: a business switching to French
     * and finding half its app in English reads that as a fault, not as a work
     * in progress.
     *
     * @return Collection<string, array{name: string, native: string, active: bool}>
     */
    public static function available(): Collection
    {
        return collect(config('languages.supported'))->filter(fn (array $language) => $language['active']);
    }

    public static function supports(?string $code): bool
    {
        return $code !== null && self::available()->has($code);
    }

    public static function fallback(): string
    {
        return config('languages.fallback', 'en');
    }

    /** What a language calls itself — "Español", not "Spanish". */
    public static function nativeName(string $code): string
    {
        return config('languages.supported.'.$code.'.native', $code);
    }

    /** What it is called in English, for an admin choosing between them. */
    public static function name(string $code): string
    {
        return config('languages.supported.'.$code.'.name', $code);
    }

    /**
     * Every language this business has switched on, primary first.
     *
     * The primary is always in the list whether or not it was also ticked as
     * a secondary — it is available by definition, and a list that omitted it
     * would leave the person who chose it unable to select it.
     *
     * @return Collection<int, string>
     */
    public static function enabledFor(?Tenant $tenant): Collection
    {
        if ($tenant === null) {
            return collect([self::fallback()]);
        }

        $primary = self::primaryFor($tenant);

        return $tenant->languages()
            ->orderBy('position')
            ->pluck('language_code')
            ->prepend($primary)
            ->filter(fn (string $code) => self::supports($code))
            ->unique()
            ->values();
    }

    /** The business's default, or English if it never chose one. */
    public static function primaryFor(?Tenant $tenant): string
    {
        $primary = $tenant?->default_language;

        return self::supports($primary) ? $primary : self::fallback();
    }

    /**
     * The language to render this request in.
     *
     * A personal choice only counts while the business still has that
     * language enabled: a colleague who chose Spanish should not keep a
     * Spanish app after the business turned Spanish off, and silently
     * honouring a disabled language would make the business setting a
     * suggestion rather than a setting.
     */
    public static function forUser(?User $user): string
    {
        $tenant = $user?->tenant;
        $enabled = self::enabledFor($tenant);

        if ($user?->locale !== null && $enabled->contains($user->locale)) {
            return $user->locale;
        }

        return self::primaryFor($tenant);
    }

    /**
     * A key's translation, falling back to English rather than showing the key.
     *
     * Laravel's own __() returns the key when nothing matches, which is how
     * "staff.status.active" ends up printed on a screen in front of a client.
     * This returns the English instead — the user sees a real word in the
     * wrong language, which is a far smaller failure — and records the gap so
     * it can be filled.
     *
     * @param  array<string, mixed>  $replace
     */
    public static function get(string $key, array $replace = [], ?string $locale = null): string
    {
        $locale ??= app()->getLocale();

        if (trans()->has($key, $locale)) {
            return trans($key, $replace, $locale);
        }

        $fallback = self::fallback();

        if ($locale !== $fallback && trans()->has($key, $fallback)) {
            self::reportMissing($key, $locale);

            return trans($key, $replace, $fallback);
        }

        /**
         * Missing from English too, which is a different fault: the key was
         * mistyped or never written.
         *
         * Reported at warning level, and the last segment is humanised rather
         * than returned. trans() hands back the key itself when nothing
         * matches, which is exactly how "staff.status.active" ends up printed
         * on a screen in front of a client — the one thing the spec says must
         * never happen. "Active" is wrong in the sense of being untranslated;
         * the dotted key is wrong in the sense of being gibberish.
         */
        self::reportMissing($key, $locale, true);

        return Str::of($key)->afterLast('.')->replace('_', ' ')->ucfirst()->toString();
    }

    /**
     * Log a gap once per key per request.
     *
     * A page can ask for the same key thirty times in a loop, and thirty
     * identical lines make the log worse at telling anyone what is missing.
     */
    private static function reportMissing(string $key, string $locale, bool $missingEverywhere = false): void
    {
        static $reported = [];

        $signature = $locale.'|'.$key;

        if (isset($reported[$signature])) {
            return;
        }

        $reported[$signature] = true;

        $context = ['key' => $key, 'locale' => $locale];

        $missingEverywhere
            ? Log::warning('Translation key is missing in every language.', $context)
            : Log::info('Translation missing; fell back to English.', $context);
    }
}
