<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\EmailTemplate;
use App\Models\Tenant;
use Illuminate\Support\Collection;

/**
 * Which template answers a given email, and what it says.
 *
 * The rule the whole module rests on: **StyleDesk always has an answer.** A
 * business that has never opened the Email Templates screen has no rows at
 * all, and every transactional email it sends is still correctly worded and
 * correctly laid out. A row overrides the default; it does not replace the
 * concept of one.
 *
 * That is what makes "Reset to StyleDesk Default" a delete rather than a copy,
 * and why it cannot fail — there is no stored original to restore, because the
 * original is in the code.
 */
class EmailTemplates
{
    /**
     * The template for a trigger or a standard key, business wording applied.
     *
     * Returns an unsaved model when the business has no row, so callers never
     * have to branch on "customised or not" — it walks and talks like a
     * template either way.
     */
    public static function resolve(?Tenant $tenant, string $key): EmailTemplate
    {
        $stored = $tenant === null ? null : EmailTemplate::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenant->getTenantKey())
            ->where('key', $key)
            ->first();

        return $stored ?? self::default($tenant, $key);
    }

    /**
     * StyleDesk's own wording for a key, as an unsaved template.
     *
     * The prose lives in the language files rather than here, so a Spanish
     * salon is not handed English scaffolding to edit.
     */
    public static function default(?Tenant $tenant, string $key): EmailTemplate
    {
        $trigger = self::isTrigger($key) ? $key : null;
        $lang = 'email_templates.defaults.'.str_replace('.', '_', $key);

        return new EmailTemplate([
            'tenant_id' => $tenant?->getTenantKey(),
            'key' => $key,
            'type' => $trigger === null
                ? EmailTemplate::TYPE_STANDARD
                : EmailTemplate::TYPE_TRANSACTIONAL,
            'trigger' => $trigger,
            'name' => self::line($lang.'.name', $key),
            'subject' => self::line($lang.'.subject', ''),
            'heading' => self::line($lang.'.heading', ''),
            'intro' => self::line($lang.'.intro', ''),
            'supporting_message' => self::line($lang.'.supporting', null),
            'blocks' => null,
            'detail_fields' => null,
            'cta_enabled' => self::line($lang.'.cta', null) !== null,
            'cta_label' => self::line($lang.'.cta', null),
            'cta_action' => self::line($lang.'.cta_action', null),
            'is_active' => true,
        ]);
    }

    /**
     * Every template a business has, defaults standing in for the untouched.
     *
     * The list screen shows the full catalogue rather than only what has been
     * customised: an owner looking for "Booking Confirmation" wants to find
     * it, not to discover that it exists only once they have edited it.
     *
     * @return Collection<int, EmailTemplate>
     */
    public static function all(?Tenant $tenant): Collection
    {
        $stored = $tenant === null
            ? collect()
            : EmailTemplate::query()
                ->withoutGlobalScopes()
                ->where('tenant_id', $tenant->getTenantKey())
                ->with('updatedBy:id,first_name,last_name,display_name')
                ->get()
                ->keyBy('key');

        return self::keys()
            ->map(fn (string $key) => $stored->get($key) ?? self::default($tenant, $key))
            /* Anything the business added itself, which has no default. */
            ->concat($stored->reject(fn (EmailTemplate $t) => self::keys()->contains($t->key))->values())
            ->values();
    }

    /** Every key StyleDesk ships, triggers first. @return Collection<int, string> */
    public static function keys(): Collection
    {
        return collect(config('email_templates.triggers'))
            ->flatten()
            ->concat(config('email_templates.standard'));
    }

    public static function isTrigger(string $key): bool
    {
        return collect(config('email_templates.triggers'))->flatten()->contains($key);
    }

    /**
     * Whether an event may send at all.
     *
     * A disabled transactional template does not send — and the caller is told
     * so rather than handed an email it will quietly drop.
     */
    public static function activeFor(?Tenant $tenant, string $trigger): ?EmailTemplate
    {
        $template = self::resolve($tenant, $trigger);

        return $template->is_active ? $template : null;
    }

    /**
     * A translated line, or the fallback when the key is not written yet.
     *
     * `__()` hands back the key itself when nothing matches, which is how
     * "email_templates.defaults.booking_confirmed.heading" ends up printed at
     * the top of a client's email.
     */
    private static function line(string $key, ?string $fallback): ?string
    {
        $value = __($key);

        return is_string($value) && $value !== $key ? $value : $fallback;
    }
}
