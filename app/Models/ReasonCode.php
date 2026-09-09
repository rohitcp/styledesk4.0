<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

/**
 * Why something happened, chosen from a list rather than typed.
 *
 * Every business starts with StyleDesk's own library and makes it theirs:
 * switch off the ones they never use, rename the ones they say differently,
 * reorder them, add their own. What they cannot do is delete one of ours —
 * because a reason deleted is a March cancellation that no longer says why.
 *
 * `key` is what survives a rename. A business calling "Client No Show"
 * "Didn't turn up" has renamed its own row, and the key is still how a later
 * default is matched to it rather than added twice.
 */
class ReasonCode extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
            'is_active' => 'boolean',
            'requires_details' => 'boolean',
        ];
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Give a business the library.
     *
     * Idempotent: run again after a tenth reason is added to the config and
     * only the tenth appears. `firstOrCreate` on the key is what makes that
     * true — a business that renamed one keeps its name, and a business that
     * switched one off keeps it off.
     */
    public static function seedDefaultsFor(Tenant $tenant): void
    {
        $detailsKey = (string) config('reasons.details_key');

        foreach (config('reasons.types') as $type => $definition) {
            foreach ($definition['reasons'] as $order => $name) {
                static::withoutGlobalScopes()->firstOrCreate(
                    [
                        'tenant_id' => $tenant->getTenantKey(),
                        'type' => $type,
                        'key' => Str::slug($name),
                    ],
                    [
                        'name' => $name,
                        'is_system' => true,
                        'is_active' => true,
                        'display_order' => $order,
                        /* "Other" is not an answer on its own, so choosing it
                           asks for one. Set here rather than in every list,
                           and matched on the key so renaming it to "Something
                           else" does not switch the requirement off. */
                        'requires_details' => Str::slug($name) === $detailsKey,
                    ]
                );
            }
        }
    }

    /** The reason types this application knows about, from the config. */
    public static function types(): array
    {
        return config('reasons.types', []);
    }

    public static function typeExists(string $type): bool
    {
        return array_key_exists($type, self::types());
    }

    /** What the type is called on screen, in the reader's own language. */
    public static function typeLabel(string $type): string
    {
        return __('reasons.types.'.$type.'.label');
    }

    public static function typeIntro(string $type): string
    {
        return __('reasons.types.'.$type.'.intro');
    }

    /**
     * The second question this type asks, where one answer is not enough.
     *
     * A no-show has a reason and somebody it is down to; a refund has a
     * reason and whether it was the whole bill. Folding either into the
     * reason list would make a menu that is half cause and half something
     * else.
     */
    public static function extraFor(string $type): ?array
    {
        return self::types()[$type]['extra'] ?? null;
    }

    /** Only the ones a business would be offered today. */
    public function scopeUsable(Builder $query): Builder
    {
        return $query->where('is_active', true)->inOrder();
    }

    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    /**
     * The order the business arranged, then the alphabet.
     *
     * The second is not decoration: two reasons added on the same day share a
     * display order, and without a tiebreak the list reshuffles itself
     * between page loads.
     */
    public function scopeInOrder(Builder $query): Builder
    {
        return $query->orderBy('display_order')->orderBy('name');
    }

    /** Ours, and so switchable but never deletable. */
    public function isSystem(): bool
    {
        return (bool) $this->is_system;
    }

    /** Renamed from what StyleDesk called it. */
    public function isRenamed(): bool
    {
        return $this->isSystem()
            && $this->key !== null
            && Str::slug((string) $this->name) !== $this->key;
    }
}
