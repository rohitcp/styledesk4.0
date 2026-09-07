<?php

declare(strict_types=1);

namespace App\Models;

use App\Payments\PaymentGatewayManager;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Stancl\Tenancy\Database\Concerns\HasDomains;
use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;

/**
 * A StyleDesk business (salon, clinic, studio).
 *
 * StyleDesk runs single-database tenancy: every tenant's rows live in the one
 * schema and are separated by a `tenant_id` column, not by a separate
 * database. See config/tenancy.php for why DatabaseTenancyBootstrapper is off.
 *
 * The base model keeps any attribute not listed in getCustomColumns() inside a
 * `data` JSON column. Anything worth querying, indexing or joining on must
 * therefore be declared below and given a real column in the migration.
 */
class Tenant extends BaseTenant
{
    use HasDomains;

    /**
     * `status` has a database-level default, but that value is not reflected on
     * the in-memory model until it is refreshed. Declaring it here means
     * Tenant::create([...])->status reads 'active' straight away rather than ''.
     *
     * @var array<string, string>
     */
    protected $attributes = [
        'status' => self::STATUS_ACTIVE,
    ];

    public const STATUS_ACTIVE = 'active';

    /**
     * Signed up, not yet finished setting up.
     *
     * Written the moment the Business step brings the workspace into
     * existence and cleared to STATUS_ACTIVE when the wizard completes. It is
     * a stage of setup, never a refusal — a trial business may sign in and
     * work, which is what the rest of onboarding needs.
     */
    public const STATUS_TRIAL = 'trial';

    /**
     * Switched off by the platform.
     *
     * Nobody belonging to this business can sign in and no existing session
     * survives the next request. The rows stay exactly where they are — this
     * is a door being locked, not data being deleted.
     */
    public const STATUS_DISABLED = 'disabled';

    /**
     * Neither the base model nor HasDataColumn declares casts, so this does
     * not need to merge anything from the parent.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'trial_started_at' => 'datetime',
            'trial_ends_at' => 'datetime',
            'disabled_at' => 'datetime',
            'enabled_at' => 'datetime',
            'shift_rules_enabled' => 'boolean',
            'client_email_enabled' => 'boolean',
            'payments_enabled' => 'boolean',
            'accepted_methods' => 'array',
        ];
    }

    /**
     * Attributes stored as real columns rather than inside the `data` blob.
     *
     * @return array<int, string>
     */
    public static function getCustomColumns(): array
    {
        return [
            'id',
            'name',
            'slug',
            'status',
            'business_phone',
            'business_phone_country',
            'business_email',
            'website',
            'logo_path',
            'country_code',
            'currency_code',
            'owner_user_id',
            'timezone',
            'default_language',
            'trial_started_at',
            'trial_ends_at',
            'subscription_status',
            'plan_id',
            'disabled_at',
            'disabled_by',
            'disabled_reason',
            'disabled_note',
            'previous_status',
            'enabled_at',
            'enabled_by',
            'enable_note',

            // App Settings → Email.
            'client_email_enabled',
            'email_provider',
            'email_sender_name',
            'email_reply_to',

            // App Settings → Payments.
            'payment_gateway',
            'payment_account_id',
            'payments_enabled',
            'accepted_methods',
            'default_deposit_type',
            'default_deposit_value',

            // Business settings screen. Every one of these needs to be here:
            // a column missing from this list is silently written into the
            // `data` JSON instead, and the real column stays null.
            'legal_name',
            'business_category',
            'description',
            'support_email',
            'booking_email',
            'date_format',
            'time_format',
            'first_day_of_week',
            'default_booking_duration',
            'default_appointment_interval',
            'default_tax_behavior',
            'default_tax_rate',
            'default_staff_assignment',
            'instagram_url',
            'facebook_url',
            'tiktok_url',
            'google_business_url',

            // Where money is asked for when it is not handed over at the
            // desk. Same rule as above: a column left off this list is
            // written into the `data` blob and the field reads back empty.
            'paypal_handle',
            'zelle_handle',
            'cash_app_handle',
            'venmo_handle',

            // Whether this business uses shift rules. Listed for the reason
            // above: left out, the switch would be written into the `data`
            // blob while the real column stayed at its default, and turning
            // the feature off would appear to do nothing.
            'shift_rules_enabled',

            // Branding. Same rule as above — the palette is read on every
            // render, so it must be columns rather than JSON keys, and it is
            // only columns if it is listed here.
            'favicon_path',
            'brand_primary',
            'brand_secondary',
            'brand_accent',
        ];
    }

    /** The people this business sees. */
    public function clients(): HasMany
    {
        return $this->hasMany(Client::class);
    }

    /** The structured preferences staff can assign to this business's clients. */
    public function clientPreferences(): HasMany
    {
        return $this->hasMany(ClientPreference::class);
    }

    /** The labels this business classifies its clients with. */
    /**
     * This business's behavioural tag settings — which of the catalogue it
     * applies. The tags themselves live in config; see BehavioralTag.
     */
    public function behavioralTags(): HasMany
    {
        return $this->hasMany(BehavioralTag::class);
    }

    public function clientTags(): HasMany
    {
        return $this->hasMany(ClientTag::class);
    }

    /**
     * Users belonging to this tenant.
     *
     * StyleDesk is one-tenant-per-user: membership is the `users.tenant_id`
     * foreign key, so this is a plain HasMany rather than a pivot.
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function countries(): HasMany
    {
        return $this->hasMany(TenantCountry::class)->orderBy('position');
    }

    /**
     * Replace the set of operating countries.
     *
     * The first code given becomes the primary and is mirrored into
     * country_code, which is what every later screen filters on. Writing both
     * in one place is what stops the mirror drifting from the table.
     *
     * @param  array<int, string>  $codes
     */
    public function syncCountries(array $codes): void
    {
        $codes = array_values(array_unique(array_filter($codes)));

        if ($codes === []) {
            return;
        }

        $this->countries()->whereNotIn('country_code', $codes)->delete();

        foreach ($codes as $position => $code) {
            TenantCountry::withoutGlobalScopes()->updateOrCreate(
                ['tenant_id' => $this->getTenantKey(), 'country_code' => $code],
                ['position' => $position]
            );
        }

        $this->forceFill(['country_code' => $codes[0]])->save();
    }

    public function currencies(): HasMany
    {
        return $this->hasMany(TenantCurrency::class)->orderBy('position');
    }

    /**
     * Replace the set of trading currencies.
     *
     * Mirrors the first into currency_code, the same arrangement as countries:
     * the list is the record, the mirrored column is what the rest of the app
     * reads.
     *
     * @param  array<int, string>  $codes
     */
    public function syncCurrencies(array $codes): void
    {
        $codes = array_values(array_unique(array_filter($codes)));

        if ($codes === []) {
            return;
        }

        $this->currencies()->whereNotIn('currency_code', $codes)->delete();

        foreach ($codes as $position => $code) {
            TenantCurrency::withoutGlobalScopes()->updateOrCreate(
                ['tenant_id' => $this->getTenantKey(), 'currency_code' => $code],
                ['position' => $position]
            );
        }

        $this->forceFill(['currency_code' => $codes[0]])->save();
    }

    public function languages(): HasMany
    {
        return $this->hasMany(TenantLanguage::class)->orderBy('position');
    }

    /**
     * Replace the supported languages, first entry primary.
     *
     * @param  array<int, string>  $codes
     */
    public function syncLanguages(array $codes): void
    {
        $codes = array_values(array_unique(array_filter($codes)));

        if ($codes === []) {
            return;
        }

        $this->languages()->whereNotIn('language_code', $codes)->delete();

        foreach ($codes as $position => $code) {
            TenantLanguage::withoutGlobalScopes()->updateOrCreate(
                ['tenant_id' => $this->getTenantKey(), 'language_code' => $code],
                ['position' => $position]
            );
        }

        $this->forceFill(['default_language' => $codes[0]])->save();
    }

    /**
     * The operating country, with a fallback.
     *
     * Onboarding always sets this at the business step, but a tenant created
     * another way — a seeder, a fixture, a row migrated before the column
     * existed — may not have it. One accessor means the controller and the
     * view cannot disagree about what the fallback is.
     */
    public function countryCode(): string
    {
        return $this->country_code ?: 'US';
    }

    /** The currency symbol for display, falling back to the code itself. */
    public function currencySymbol(): string
    {
        return config('currencies.currencies.'.$this->currency_code.'.symbol', (string) $this->currency_code);
    }

    public function businessTypes(): BelongsToMany
    {
        return $this->belongsToMany(BusinessType::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    /**
     * Whole days left in the trial, floored at zero.
     *
     * Shown in the app chrome, so it must never read as negative once the
     * trial has lapsed — that is what subscription_status is for.
     */
    public function trialDaysRemaining(): int
    {
        if ($this->trial_ends_at === null) {
            return 0;
        }

        return max(0, (int) now()->startOfDay()->diffInDays($this->trial_ends_at->startOfDay(), false));
    }

    /**
     * Whether anybody belonging to this business may sign in.
     *
     * Asked on every authenticated request and again at the login form, so it
     * reads the column and nothing else — no relation, no query.
     *
     * Trial counts as active. `status` answers "may they in?" and only
     * STATUS_DISABLED says no; whether a trial has run out is a billing
     * question, and billing lives in `subscription_status`. Treating trial as
     * inactive logged every new owner out on the redirect that followed the
     * Business step, which is the one screen that creates a trial workspace.
     */
    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isDisabled(): bool
    {
        return ! $this->isActive();
    }

    /**
     * Lock the door.
     *
     * Who, why and when are recorded on the row as well as in the audit log:
     * the first question after "we cannot sign in" is "who turned us off",
     * and it should be answerable from the screen that turned them off.
     *
     * Nothing is deleted. Staff, customers, bookings, payments, files and
     * configuration are untouched — this sets a column, and the account is
     * restorable by clearing it.
     *
     * @param  string  $reason  A key from config('backoffice.disable_reasons')
     */
    public function disable(BackofficeAdmin $by, string $reason, ?string $note = null): void
    {
        $this->forceFill([
            /* Kept so enabling restores what was there rather than assuming
               'active'. Guarded on DISABLED specifically, not on "not active":
               a second press must not record "disabled" as the state to return
               to, but any other status is a real one worth keeping. */
            'previous_status' => $this->status === self::STATUS_DISABLED
                ? $this->previous_status
                : $this->status,
            'status' => self::STATUS_DISABLED,
            'disabled_at' => now(),
            'disabled_by' => $by->getKey(),
            'disabled_reason' => $reason,
            'disabled_note' => $note,
        ])->save();
    }

    /**
     * Open it again.
     *
     * The disable columns are cleared rather than left standing, or a business
     * disabled once would read as disabled forever to anything that checks
     * `disabled_at` instead of `status`. The enable columns replace them, so
     * the row still says who was last responsible for it being reachable.
     */
    public function enable(BackofficeAdmin $by, ?string $note = null): void
    {
        $this->forceFill([
            'status' => $this->previous_status ?: self::STATUS_ACTIVE,
            'disabled_at' => null,
            'disabled_by' => null,
            'disabled_reason' => null,
            'disabled_note' => null,
            'previous_status' => null,
            'enabled_at' => now(),
            'enabled_by' => $by->getKey(),
            'enable_note' => $note,
        ])->save();
    }

    /** The administrator who last switched this business back on, if any. */
    public function enabledBy(): BelongsTo
    {
        return $this->belongsTo(BackofficeAdmin::class, 'enabled_by');
    }

    /**
     * The one status a reader is shown, from the two the row keeps.
     *
     * `status` is about access — whether anybody may sign in — and
     * `subscription_status` is about billing. They are deliberately separate:
     * the business rule is Active → Past Due → Disabled, so a client with an
     * unpaid invoice can be told apart from one whose access has actually been
     * suspended. Disabled wins when both have something to say, because it is
     * the one that stops people working.
     *
     * @return string One of: disabled, trial, past_due, cancelled, active
     */
    public function displayStatus(): string
    {
        if ($this->isDisabled()) {
            return 'disabled';
        }

        return match ($this->subscription_status) {
            'trialing', 'trial' => 'trial',
            'past_due' => 'past_due',
            'canceled', 'cancelled' => 'cancelled',
            default => 'active',
        };
    }

    /** The administrator who switched this business off, if one did. */
    public function disabledBy(): BelongsTo
    {
        return $this->belongsTo(BackofficeAdmin::class, 'disabled_by');
    }

    /**
     * The payment methods this business takes.
     *
     * Null means it has never said, which is not the same as none: a salon
     * that has not opened the payments screen still takes cash. The gateway's
     * own list is the answer until somebody narrows it.
     *
     * @return array<int, string>
     */
    public function acceptedMethods(): array
    {
        $available = app(PaymentGatewayManager::class)->for($this)->methods();

        if ($this->accepted_methods === null) {
            return $available;
        }

        /* Intersected rather than trusted: a method that was accepted before
           the business changed processor may no longer be takeable, and
           offering it would be offering something the till cannot complete. */
        return array_values(array_intersect($this->accepted_methods, $available));
    }

    public function onboarding(): HasOne
    {
        return $this->hasOne(TenantOnboarding::class);
    }

    public function locations(): HasMany
    {
        return $this->hasMany(Location::class);
    }

    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }

    public function teamInvitations(): HasMany
    {
        return $this->hasMany(TeamInvitation::class);
    }

    public function staff(): HasMany
    {
        return $this->hasMany(Staff::class);
    }

    public function bookingSettings(): HasOne
    {
        return $this->hasOne(BookingSettings::class);
    }

    /**
     * Route key so tenant URLs read /t/acme rather than /t/<uuid>.
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
