<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

/**
 * A business's own wording for one email.
 *
 * A row is an override. StyleDesk ships a default for every key, and a
 * business with no rows at all still sends properly formatted mail — which is
 * what stops a configuration gap from breaking client communication.
 *
 * Nothing here is HTML. The shell, the spacing and the button styling belong
 * to StyleDesk: an owner who cannot break the layout cannot ship a broken
 * email, and one fix reaches every business at once.
 */
class EmailTemplate extends Model
{
    use BelongsToTenant;

    public const TYPE_TRANSACTIONAL = 'transactional';

    public const TYPE_STANDARD = 'standard';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'blocks' => 'array',
            'detail_fields' => 'array',
            'detail_services' => 'array',
            'cta_enabled' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * The campaign this email offers, if it offers one.
     *
     * A reference rather than a stored code: a promotion that is disabled or
     * has expired must stop appearing, and a code copied into the template
     * would keep going out long after the campaign ended.
     */
    public function promotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class);
    }

    /**
     * The coupon to draw, or null.
     *
     * Null when the block is off, when no campaign is chosen, or when the
     * campaign is no longer usable — an expired code in a client's inbox is a
     * promise the business then has to break at the till.
     */
    public function couponToShow(): ?Promotion
    {
        if (! $this->shows('coupon') || $this->promotion === null) {
            return null;
        }

        return $this->promotion->status() === 'active' ? $this->promotion : null;
    }

    /**
     * Which blocks this email draws, defaults filled in.
     *
     * Merged rather than replaced, so a block added to the product later
     * appears on templates written before it existed instead of silently
     * staying off for every business that had already saved one.
     *
     * @return array<string, bool>
     */
    public function blocks(): array
    {
        $defaults = array_map(
            fn (array $block) => (bool) ($block['default'] ?? false),
            config('email_templates.blocks'),
        );

        $chosen = array_intersect_key($this->blocks ?? [], $defaults);

        return array_map('boolval', array_merge($defaults, $chosen));
    }

    /**
     * Whether a block appears.
     *
     * Some are not the owner's to switch off: an email with no heading and no
     * footer is not a template anybody meant to write.
     */
    public function shows(string $block): bool
    {
        if (config('email_templates.blocks.'.$block.'.always') === true) {
            return true;
        }

        return $this->blocks()[$block] ?? false;
    }

    /**
     * Which rows the details card shows, defaults filled in.
     *
     * @return array<string, bool>
     */
    public function detailFields(): array
    {
        $defaults = config('email_templates.detail_fields');
        $chosen = array_intersect_key($this->detail_fields ?? [], $defaults);

        return array_map('boolval', array_merge($defaults, $chosen));
    }

    public function showsDetail(string $field): bool
    {
        return $this->detailFields()[$field] ?? false;
    }

    /**
     * The services this template names, if it names any.
     *
     * Empty is the normal case and means "whatever this booking is for". A
     * confirmation describes the appointment it was sent about; aftercare for
     * a particular treatment names the treatment.
     */
    public function services(): Collection
    {
        return Service::query()
            ->whereIn('id', $this->detail_services ?: [])
            ->orderBy('name')
            ->get();
    }

    /** @return array<int, int> */
    public function serviceIds(): array
    {
        return array_map('intval', $this->detail_services ?: []);
    }

    public function isTransactional(): bool
    {
        return $this->type === self::TYPE_TRANSACTIONAL;
    }

    /**
     * Whether this is one of StyleDesk's own, rather than one somebody added.
     *
     * A system template can be reset and disabled but never deleted: something
     * in the product fires at its key, and deleting it would leave that event
     * with nothing to send.
     */
    public function isSystem(): bool
    {
        return $this->trigger !== null
            || in_array($this->key, config('email_templates.standard'), true);
    }

    public function typeLabel(): string
    {
        return __('email_templates.types.'.$this->type);
    }

    public function triggerLabel(): ?string
    {
        return $this->trigger === null
            ? null
            : __('email_templates.triggers.'.str_replace('.', '_', $this->trigger));
    }

    public function scopeOfType(Builder $query, ?string $type): Builder
    {
        return $type === null || $type === '' || $type === 'all'
            ? $query
            : $query->where('type', $type);
    }

    public function scopeMatching(Builder $query, string $term): Builder
    {
        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';

        return $query->where(fn (Builder $q) => $q
            ->where('name', 'like', $like)
            ->orWhere('subject', 'like', $like)
            ->orWhere('key', 'like', $like));
    }
}
