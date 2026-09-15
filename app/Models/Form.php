<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

/**
 * A form the business asks its clients to fill in.
 *
 * This row is the form's identity and its rules. What it asks lives on
 * FormVersion, because a completed form is evidence: a client signed a
 * consent that said something specific, and rewording a question afterwards
 * must not change what anybody agreed to.
 */
class Form extends Model
{
    use BelongsToTenant;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    public const STATUS_ARCHIVED = 'archived';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'service_ids' => 'array',
            'service_category_ids' => 'array',
            'location_ids' => 'array',
            'signature_required' => 'boolean',
            'auto_send' => 'boolean',
            'contains_sensitive' => 'boolean',
            'archived_at' => 'datetime',
        ];
    }

    // ------------------------------------------------------- relationships

    public function category(): BelongsTo
    {
        return $this->belongsTo(FormCategory::class, 'category_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(FormVersion::class);
    }

    /**
     * The version a new assignment is pinned to.
     *
     * A plain belongsTo on a column with no foreign key: `forms` and
     * `form_versions` point at each other, and a constraint in both
     * directions cannot be created in either order.
     */
    public function currentVersion(): BelongsTo
    {
        return $this->belongsTo(FormVersion::class, 'current_version_id');
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(FormSubmission::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // -------------------------------------------------------------- scopes

    /** Live, and the only status that is ever assigned to anybody. */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    /**
     * Everything except the archived.
     *
     * What the forms list shows by default: an archived form is kept for the
     * submissions hanging off it, not to be read past every day.
     */
    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('status', '!=', self::STATUS_ARCHIVED);
    }

    // -------------------------------------------------------- what it is

    public function isArchived(): bool
    {
        return $this->status === self::STATUS_ARCHIVED;
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function typeLabel(): string
    {
        return __('forms.types.'.$this->type);
    }

    public function statusLabel(): string
    {
        return __('forms.statuses.'.$this->status);
    }

    public function validityLabel(): string
    {
        return $this->validity === 'custom'
            ? __('forms.validity_custom', ['days' => (int) $this->validity_days])
            : __('forms.validity.'.$this->validity);
    }

    /**
     * How long a submission of this form stays good for, in days.
     *
     * Null where the answer is not a number of days — once, never, and
     * every-appointment, which is answered per booking rather than by a date.
     */
    public function validityDays(): ?int
    {
        return $this->validity === 'custom'
            ? (int) $this->validity_days
            : config('forms.validity.'.$this->validity.'.days');
    }

    /**
     * The address the business hands out.
     *
     * Built from the tenant's own subdomain, the way every other public
     * tenant URL in the app is — see Backoffice\ClientController. Null until
     * the form is published, because a draft has nothing at the other end of
     * its link.
     */
    public function publicUrl(): ?string
    {
        if ($this->public_token === null) {
            return null;
        }

        /* On the application's own domain, where that is how this
           deployment hands links out — see config('forms.public_link_host'). */
        if (config('forms.public_link_host') === 'central') {
            return rtrim((string) config('app.url'), '/').'/form/'.$this->public_token;
        }

        /*
         * The subdomain that actually resolves.
         *
         * Onboarding registers a `domains` row equal to the slug, and the
         * tenancy middleware identifies the business from THAT — so a link
         * built from the slug alone would be one nobody could open the day
         * the two disagreed. The slug is the fallback for a tenant created
         * without a domain row, which is a seeder or a test rather than a
         * real business.
         */
        $host = $this->tenant?->domains()->value('domain') ?? $this->tenant?->slug;

        if ($host === null) {
            return null;
        }

        return 'https://'.$host.'.'.config('tenancy.tenant_domain_suffix').'/form/'.$this->public_token;
    }

    /**
     * An address no amount of guessing finds.
     *
     * Anyone holding the URL can open the form, so the token is the whole of
     * the obscurity. Checked for collision because unique() on the column is
     * a constraint rather than a retry.
     */
    public static function newPublicToken(): string
    {
        do {
            $token = Str::lower(Str::random(12));
        } while (self::withoutGlobalScopes()->where('public_token', $token)->exists());

        return $token;
    }

    /**
     * Whether a form may be sent to anybody yet.
     *
     * A form with no published version has no questions, and a form with no
     * questions asked of a client is a link to an empty page.
     */
    public function isPublishable(): bool
    {
        return $this->versions()->whereNotNull('published_at')->exists();
    }
}
