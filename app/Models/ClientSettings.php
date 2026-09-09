<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\ClientOptions;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

/**
 * What a business has configured about client records.
 *
 * One row per tenant, created on first read rather than at signup: a business
 * that never opens this screen still needs an answer to every question on it,
 * and the answers are the column defaults.
 *
 * This is configuration, not client data. Nothing here belongs to a person —
 * the Clients module reads these to decide what a client record looks like.
 */
class ClientSettings extends Model
{
    use BelongsToTenant;

    protected $table = 'client_settings';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'fields' => 'array',
            'duplicate_rules' => 'array',
            'search_fields' => 'array',
            'booking_panels' => 'array',
            'history_panels' => 'array',
            'creation_sources' => 'array',

            'preferences_enabled' => 'boolean',
            'preferences_multiple' => 'boolean',
            'tags_enabled' => 'boolean',

            'notes_enabled' => 'boolean',
            'notes_multiple' => 'boolean',
            'notes_in_booking' => 'boolean',
            'notes_important_on_profile' => 'boolean',
            'notes_allow_important' => 'boolean',
            'notes_staff_can_edit' => 'boolean',
            'notes_admin_can_delete' => 'boolean',

            'duplicate_warning' => 'boolean',
            'duplicate_show_matches' => 'boolean',

            'allow_booking_inactive' => 'boolean',
            'archived_in_search' => 'boolean',

            'comm_email' => 'boolean',
            'comm_sms' => 'boolean',
            'comm_phone' => 'boolean',
            'comm_marketing_email' => 'boolean',
            'comm_marketing_sms' => 'boolean',

            'consent_record' => 'boolean',
            'consent_record_date' => 'boolean',
            'consent_record_captured_by' => 'boolean',
            'consent_client_can_opt_out' => 'boolean',
            'consent_show_on_profile' => 'boolean',
        ];
    }

    /**
     * Defaults the instance carries, not only the table.
     *
     * A column default is applied by the database and is not reflected on the
     * model that was just created, so a freshly made row read back its
     * booleans as null and every toggle on the screen rendered off.
     */
    protected $attributes = [
        'default_status' => 'active',
        'default_communication' => 'email',
        'default_marketing' => 'ask',
        'name_format' => 'first_last',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * This business's configuration, made if it does not exist yet.
     *
     * The JSON sets start as the catalogue's own defaults rather than empty,
     * because an empty search-field list means "search by nothing" and an
     * empty panel list means a booking screen that shows the client's name
     * and no more.
     */
    public static function forTenant(Tenant $tenant): self
    {
        $settings = self::withoutGlobalScopes()
            ->where('tenant_id', $tenant->getTenantKey())
            ->first();

        if ($settings !== null) {
            return $settings;
        }

        $settings = new self([
            'tenant_id' => $tenant->getTenantKey(),
            'fields' => self::defaultFields(),
            'duplicate_rules' => ['email', 'mobile'],
            'search_fields' => array_keys(config('clients.search_fields')),
            'booking_panels' => array_keys(config('clients.booking_panels')),
            'history_panels' => array_keys(config('clients.history_panels')),
            'creation_sources' => collect(config('clients.creation_sources'))
                ->filter(fn (array $source) => $source['available'])
                ->keys()->all(),
        ]);

        $settings->save();

        /**
         * Read back, so the row's own defaults are on the model.
         *
         * A freshly inserted model knows only what was assigned: every
         * boolean the schema defaults would read as null, and a caller
         * passing one to a typed argument gets a TypeError rather than the
         * false the column actually holds.
         */
        return $settings->refresh();
    }

    /**
     * The field configuration a business starts with.
     *
     * @return array<string, array{enabled: bool, required: bool, order: int}>
     */
    public static function defaultFields(): array
    {
        return collect(config('clients.fields'))
            ->values()
            ->mapWithKeys(fn (array $field, int $index) => [
                array_keys(config('clients.fields'))[$index] => [
                    'enabled' => $field['default_enabled'],
                    'required' => $field['default_required'],
                    'order' => $index,
                ],
            ])
            ->all();
    }

    /**
     * One field's configuration, with the lock applied.
     *
     * Read through this rather than off the array, so a locked field is
     * enabled and required wherever it is asked about — including on a row
     * saved before the lock existed, or edited by someone posting the form
     * by hand.
     *
     * @return array{enabled: bool, required: bool, order: int, locked: bool}
     */
    public function field(string $key): array
    {
        $catalogue = config('clients.fields.'.$key);
        $stored = ($this->fields ?? [])[$key] ?? null;
        $locked = (bool) ($catalogue['locked'] ?? false);

        return [
            'enabled' => $locked ? true : (bool) ($stored['enabled'] ?? $catalogue['default_enabled'] ?? false),
            'required' => $locked ? true : (bool) ($stored['required'] ?? $catalogue['default_required'] ?? false),
            'order' => (int) ($stored['order'] ?? 0),
            'locked' => $locked,
        ];
    }

    /**
     * Every field in the order the business put them in.
     *
     * @return array<int, array<string, mixed>>
     */
    public function orderedFields(): array
    {
        $labels = ClientOptions::fields();

        return collect(config('clients.fields'))
            ->map(fn (array $catalogue, string $key) => [
                'key' => $key,
                // Translated, so the field list reads in the same language as
                // the page it sits on.
                'label' => $labels[$key] ?? $catalogue['label'],
                ...$this->field($key),
            ])
            ->sortBy('order')
            ->values()
            ->all();
    }

    /** Whether a set contains a value, for the checkbox groups. */
    public function has(string $set, string $value): bool
    {
        return in_array($value, $this->{$set} ?? [], true);
    }
}
