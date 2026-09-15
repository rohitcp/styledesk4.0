<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

/**
 * What a form asked, at one point in its life.
 *
 * Every submission pins the version it was assigned against, so editing a
 * form that has submissions produces a new row here rather than overwriting
 * one. A form nobody has filled in yet is edited in place — a business still
 * building its first intake form should not accumulate nine versions before
 * anybody has seen it.
 */
class FormVersion extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'schema' => 'array',
            'published_at' => 'datetime',
        ];
    }

    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }

    public function previous(): BelongsTo
    {
        return $this->belongsTo(self::class, 'previous_version_id');
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(FormSubmission::class, 'form_version_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isPublished(): bool
    {
        return $this->published_at !== null;
    }

    /**
     * The rows, in the order the client meets them.
     *
     * A row holds one question or two side by side, which is what an intake
     * form actually looks like: first name beside last name, medical history
     * on its own. A single global column count could not express that, so the
     * arrangement is per row.
     *
     * A version saved before rows existed is a flat list of questions, and is
     * read back as one question per row — the same form, described the new
     * way. Nothing is migrated: the next save writes the new shape, and until
     * then this reads the old one correctly.
     *
     * @return array<int, array<string, mixed>>
     */
    public function rows(): array
    {
        $rows = $this->schema['rows'] ?? null;

        if (is_array($rows)) {
            return $rows;
        }

        $legacy = $this->schema['fields'] ?? [];

        return array_values(array_map(
            fn (array $field, int $index) => [
                'key' => 'row_'.($index + 1),
                'layout' => 'single',
                'split' => '50_50',
                'spacing' => null,
                'spacing_px' => null,
                'fields' => [$field],
            ],
            $legacy,
            array_keys($legacy),
        ));
    }

    /**
     * Every question on the form, whichever row it sits in.
     *
     * What a submission is answered against and what "has this form got any
     * questions yet" means, neither of which cares about the arrangement.
     *
     * @return array<int, array<string, mixed>>
     */
    public function fields(): array
    {
        $rows = $this->rows();

        return $rows === []
            ? []
            : array_merge(...array_map(fn (array $row) => $row['fields'] ?? [], $rows));
    }

    /**
     * How the form looks, with the house answers filled in underneath.
     *
     * Merged over the config defaults rather than stored complete, so a
     * setting added later reaches every form already built instead of only
     * the ones saved afterwards.
     *
     * @return array<string, mixed>
     */
    public function theme(): array
    {
        return array_merge(
            config('forms.theme.defaults'),
            array_filter($this->schema['theme'] ?? [], fn ($value) => $value !== null),
        );
    }

    /**
     * Whether anybody has been asked this version.
     *
     * What decides between editing this row and opening a new one: the moment
     * a submission points here, these questions are what somebody answered.
     */
    public function hasSubmissions(): bool
    {
        return $this->submissions()->exists();
    }
}
