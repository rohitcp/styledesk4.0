<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

/**
 * A business's setting for one behavioural tag.
 *
 * The tag is defined in config/behavioral_tags.php — its label, its category
 * and the rule that earns it. This model holds the only part that belongs to
 * a business: whether the tag is applied.
 *
 * Deliberately not editable beyond that. The key is what reporting and the
 * rule engine join on, so renaming or deleting one would break every rule
 * that referred to it — which is why the settings screen offers activate and
 * deactivate and nothing else.
 */
class BehavioralTag extends Model
{
    use BelongsToTenant;

    protected $table = 'behavioral_tag_settings';

    protected $guarded = [];

    protected $attributes = ['is_active' => true];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return config('behavioral_tags.tags.'.$this->tag_key, [
            'label' => $this->tag_key,
            'category' => null,
            'rule' => null,
        ]);
    }

    public function label(): string
    {
        return $this->definition()['label'];
    }

    public function rule(): ?string
    {
        return $this->definition()['rule'] ?? null;
    }

    public function categoryLabel(): ?string
    {
        $category = $this->definition()['category'] ?? null;

        return $category ? config('behavioral_tags.categories.'.$category) : null;
    }

    /**
     * Give a business a row for every tag in the catalogue.
     *
     * firstOrCreate, so a tag added to the catalogue later reaches businesses
     * that already exist, and one a business has switched off stays off.
     */
    public static function seedDefaultsFor(Tenant $tenant): void
    {
        foreach (array_keys(config('behavioral_tags.tags')) as $key) {
            static::withoutGlobalScopes()->firstOrCreate(
                ['tenant_id' => $tenant->getTenantKey(), 'tag_key' => $key],
                ['is_active' => true],
            );
        }
    }

    /**
     * This business's tags, in catalogue order and grouped by category.
     *
     * Grouped here rather than in the view because the order is the
     * catalogue's, not the database's — a settings screen that listed forty
     * seven tags in insertion order would be a list nobody could scan.
     *
     * @return Collection<string, Collection<int, self>>
     */
    public static function groupedFor(Tenant $tenant): Collection
    {
        $order = array_keys(config('behavioral_tags.tags'));

        return static::withoutGlobalScopes()
            ->where('tenant_id', $tenant->getTenantKey())
            ->get()
            ->sortBy(fn (self $tag) => array_search($tag->tag_key, $order, true))
            ->groupBy(fn (self $tag) => $tag->definition()['category'] ?? 'other');
    }
}
