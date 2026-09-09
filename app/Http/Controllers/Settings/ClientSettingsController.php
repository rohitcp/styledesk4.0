<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\BehavioralTag;
use App\Models\ClientPreference;
use App\Models\ClientSettings;
use App\Models\ClientTag;
use App\Models\Tenant;
use App\Support\InputCase;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * App Settings → Clients: global configuration for client records.
 *
 * Not the Clients module. This screen decides what a client record looks like
 * and how it behaves; the profiles, history, notes and day-to-day management
 * belong to the module that reads these settings. Keeping that line is what
 * stops a settings page from slowly becoming a second client screen.
 *
 * Access is Owner/Administrator through `can-manage-settings` on the route
 * group, per §18. Day-to-day permission over client records stays with Roles
 * & Permissions and is a different question from who may configure them.
 */
class ClientSettingsController extends Controller
{
    public function show(Request $request): View
    {
        $tenant = $request->user()->tenant;

        return view('settings.clients.edit', $this->state($tenant));
    }

    /**
     * What each card on the settings page owns.
     *
     * The page is a set of independent sections, each with its own Save, so
     * a save has to know which columns it is allowed to touch. Without this
     * map a section would post the fields it shows and silently blank every
     * switch it does not — an unchecked box and an absent one look identical
     * in a request.
     *
     * @return array<string, array<int, string>>
     */
    private function sections(): array
    {
        return [
            'records' => [
                'default_status', 'default_location_id', 'default_staff_id',
                'default_communication', 'default_marketing', 'name_format',
                'fields',
            ],
            'notes' => [
                'notes_enabled', 'notes_multiple', 'notes_in_booking',
                'notes_important_on_profile', 'notes_allow_important',
                'notes_staff_can_edit', 'notes_admin_can_delete',
            ],
            'booking' => ['booking_panels', 'history_panels', 'creation_sources'],
            /* "Find a client by" is drawn on this card, so this card owns
               it: a section may only save what it showed the reader. */
            'duplicates' => ['duplicate_warning', 'duplicate_show_matches', 'duplicate_rules', 'search_fields'],
            'communication' => [
                'comm_email', 'comm_sms', 'comm_phone',
                'comm_marketing_email', 'comm_marketing_sms',
            ],
            'status' => ['allow_booking_inactive', 'archived_in_search'],
            'privacy' => [
                'consent_record', 'consent_record_date', 'consent_record_captured_by',
                'consent_client_can_opt_out', 'consent_show_on_profile',
            ],
            'lists' => ['preferences_enabled', 'preferences_multiple', 'tags_enabled'],
        ];
    }

    /**
     * Save one card.
     *
     * Only the columns that card owns, so an open form elsewhere on the page
     * cannot be overwritten by a save the reader made here — and so a switch
     * this card never showed is not turned off by being absent from the
     * request.
     */
    public function update(Request $request): RedirectResponse
    {
        $tenant = $request->user()->tenant;
        $settings = ClientSettings::forTenant($tenant);

        $sections = $this->sections();
        $section = $request->input('section');

        // An unknown section is a request nothing on this page makes.
        abort_unless($section === null || isset($sections[$section]), 422);

        $owned = $section === null
            ? array_merge(...array_values($sections))
            : $sections[$section];

        $data = $this->validated($request, $tenant, $owned);

        $values = [];

        foreach ($owned as $key) {
            if ($key === 'fields') {
                $values['fields'] = $this->fields($settings, $data['fields'] ?? []);

                continue;
            }

            if (in_array($key, $this->switches(), true)) {
                // Stated explicitly: an unchecked box posts nothing, and
                // reading the request alone would leave it as it was.
                $values[$key] = (bool) ($data[$key] ?? false);

                continue;
            }

            if (in_array($key, $this->sets(), true)) {
                $values[$key] = $data[$key] ?? [];

                continue;
            }

            $values[$key] = $data[$key] ?? null;
        }

        $settings->forceFill($values)->save();

        return redirect()
            ->route('settings.clients.show')
            ->with('toast', ['type' => 'success', 'message' => __('clients.saved')]);
    }

    /** The columns stored as lists rather than single values. */
    private function sets(): array
    {
        return ['duplicate_rules', 'search_fields', 'booking_panels', 'history_panels', 'creation_sources'];
    }

    // ---------------------------------------------------------- preferences

    public function storePreference(Request $request): RedirectResponse
    {
        $tenant = $request->user()->tenant;

        $data = $request->validate([
            'label' => [
                'required', 'string', 'max:80',
                // Two preferences with the same name are two ways to say one
                // thing, and a staff member picking between them is guessing.
                Rule::unique('client_preferences', 'label')
                    ->where(fn ($q) => $q->where('tenant_id', $tenant->getTenantKey())),
            ],
        ], ['label.unique' => __('clients.validation.preference_exists')]);

        $tenant->clientPreferences()->create([
            'label' => InputCase::sentence($data['label']),
            'position' => (int) $tenant->clientPreferences()->max('position') + 1,
        ]);

        return back()->with('toast', ['type' => 'success', 'message' => __('clients.preference_added')]);
    }

    public function togglePreference(Request $request, ClientPreference $preference): RedirectResponse
    {
        $this->assertOwnedBy($preference->tenant_id, $request->user()->tenant);

        /**
         * Deactivated, never deleted from here.
         *
         * A preference already assigned to two hundred clients should stop
         * being offered without removing what those records say about those
         * people.
         */
        $preference->forceFill(['is_active' => ! $preference->is_active])->save();

        return back()->with('toast', [
            'type' => 'success',
            'message' => $preference->is_active ? __('clients.preference_activated') : __('clients.preference_deactivated'),
        ]);
    }

    public function reorderPreferences(Request $request): RedirectResponse
    {
        $tenant = $request->user()->tenant;

        $data = $request->validate(['order' => ['required', 'array'], 'order.*' => ['integer']]);

        $this->applyOrder($tenant->clientPreferences(), $data['order']);

        return back()->with('toast', ['type' => 'success', 'message' => __('clients.order_saved')]);
    }

    // ----------------------------------------------------------------- tags

    public function storeTag(Request $request): RedirectResponse
    {
        $tenant = $request->user()->tenant;

        $data = $request->validate([
            'label' => [
                'required', 'string', 'max:60',
                Rule::unique('client_tags', 'label')
                    ->where(fn ($q) => $q->where('tenant_id', $tenant->getTenantKey())),
            ],
            'color' => ['required', Rule::in(array_keys(config('clients.tag_colors')))],
        ], ['label.unique' => __('clients.validation.tag_exists')]);

        $tenant->clientTags()->create([
            'label' => InputCase::sentence($data['label']),
            'color' => $data['color'],
            'position' => (int) $tenant->clientTags()->max('position') + 1,
        ]);

        return back()->with('toast', ['type' => 'success', 'message' => __('clients.tag_added')]);
    }

    public function updateTag(Request $request, ClientTag $tag): RedirectResponse
    {
        $tenant = $request->user()->tenant;
        $this->assertOwnedBy($tag->tenant_id, $tenant);

        $data = $request->validate([
            'label' => [
                'required', 'string', 'max:60',
                Rule::unique('client_tags', 'label')
                    ->where(fn ($q) => $q->where('tenant_id', $tenant->getTenantKey()))
                    ->ignore($tag->id),
            ],
            'color' => ['required', Rule::in(array_keys(config('clients.tag_colors')))],
        ], ['label.unique' => __('clients.validation.tag_exists')]);

        $tag->forceFill([
            'label' => InputCase::sentence($data['label']),
            'color' => $data['color'],
        ])->save();

        return back()->with('toast', ['type' => 'success', 'message' => __('clients.tag_saved')]);
    }

    public function toggleTag(Request $request, ClientTag $tag): RedirectResponse
    {
        $this->assertOwnedBy($tag->tenant_id, $request->user()->tenant);

        $tag->forceFill(['is_active' => ! $tag->is_active])->save();

        return back()->with('toast', [
            'type' => 'success',
            'message' => $tag->is_active ? __('clients.tag_activated') : __('clients.tag_deactivated'),
        ]);
    }

    public function reorderTags(Request $request): RedirectResponse
    {
        $tenant = $request->user()->tenant;

        $data = $request->validate(['order' => ['required', 'array'], 'order.*' => ['integer']]);

        $this->applyOrder($tenant->clientTags(), $data['order']);

        return back()->with('toast', ['type' => 'success', 'message' => __('clients.order_saved')]);
    }

    /**
     * Delete a tag, but only one nobody has used.
     *
     * A tag someone has put on a client is part of that client's record, and
     * removing it would quietly rewrite history on every client carrying it.
     * The refusal names the alternative rather than just saying no:
     * deactivating takes the tag out of every list it can be chosen from and
     * leaves the clients who already carry it alone.
     */
    public function destroyTag(Request $request, ClientTag $tag): RedirectResponse
    {
        $this->assertOwnedBy($tag->tenant_id, $request->user()->tenant);

        if (! $tag->isDeletable()) {
            return back()->with('toast', [
                'type' => 'danger',
                'message' => __('clients.tags.in_use', [
                    'label' => $tag->label,
                    'count' => $tag->clients()->count(),
                ]),
            ]);
        }

        $tag->delete();

        return back()->with('toast', ['type' => 'success', 'message' => __('clients.tags.deleted')]);
    }

    /**
     * Switch one behavioural tag on or off for this business.
     *
     * The only thing a business may change about them: the label, the
     * category and the rule are the same everywhere, because the key is what
     * reporting and the rule engine join on.
     */
    public function toggleBehavioralTag(Request $request, BehavioralTag $behavioralTag): RedirectResponse
    {
        $this->assertOwnedBy($behavioralTag->tenant_id, $request->user()->tenant);

        $behavioralTag->forceFill(['is_active' => ! $behavioralTag->is_active])->save();

        return back()->with('toast', [
            'type' => 'success',
            'message' => $behavioralTag->is_active
                ? __('clients.behavioral.activated', ['label' => $behavioralTag->label()])
                : __('clients.behavioral.deactivated', ['label' => $behavioralTag->label()]),
        ]);
    }

    // -------------------------------------------------------------- helpers

    /**
     * @return array<string, mixed>
     */
    /**
     * @param  array<int, string>|null  $only  The section's own fields.
     */
    private function validated(Request $request, Tenant $tenant, ?array $only = null): array
    {
        $sets = [
            'duplicate_rules' => array_keys(config('clients.duplicate_rules')),
            'search_fields' => array_keys(config('clients.search_fields')),
            'booking_panels' => array_keys(config('clients.booking_panels')),
            'history_panels' => array_keys(config('clients.history_panels')),
            'creation_sources' => collect(config('clients.creation_sources'))
                ->filter(fn (array $s) => $s['available'])->keys()->all(),
        ];

        $rules = [
            'default_status' => ['required', Rule::in(array_keys(config('clients.default_statuses')))],
            /**
             * Scoped to this business's own locations and staff.
             *
             * A bare exists rule would accept another salon's id and quietly
             * file every new client against a branch this business has never
             * heard of.
             */
            'default_location_id' => [
                'nullable',
                Rule::exists('locations', 'id')->where(fn ($q) => $q->where('tenant_id', $tenant->getTenantKey())),
            ],
            'default_staff_id' => [
                'nullable',
                Rule::exists('staff', 'id')->where(fn ($q) => $q->where('tenant_id', $tenant->getTenantKey())),
            ],
            'default_communication' => ['required', Rule::in(array_keys(config('clients.communication_methods')))],
            'default_marketing' => ['required', Rule::in(array_keys(config('clients.marketing_defaults')))],
            'name_format' => ['required', Rule::in(array_keys(config('clients.name_formats')))],

            'fields' => ['array'],
            'fields.*.enabled' => ['nullable', 'boolean'],
            'fields.*.required' => ['nullable', 'boolean'],
            'fields.*.order' => ['nullable', 'integer', 'min:0'],
        ];

        foreach ($sets as $set => $allowed) {
            $rules[$set] = ['nullable', 'array'];
            $rules[$set.'.*'] = [Rule::in($allowed)];
        }

        foreach ($this->switches() as $switch) {
            $rules[$switch] = ['nullable', 'boolean'];
        }

        if ($only !== null) {
            // Keep a field's own rule and its wildcard — fields.*.enabled
            // belongs to whoever owns fields.
            $rules = collect($rules)
                ->filter(fn ($rule, string $key) => in_array(Str::before($key, '.'), $only, true))
                ->all();
        }

        return $request->validate($rules, [
            'default_status.required' => __('clients.validation.status_required'),
            'default_location_id.exists' => __('clients.validation.location_invalid'),
            'default_staff_id.exists' => __('clients.validation.staff_invalid'),
            'name_format.required' => __('clients.validation.name_format_required'),
        ]);
    }

    /**
     * Field configuration, with the locked ones forced on.
     *
     * §2 says First Name always stays enabled and required. The screen does
     * not offer the toggle, and this is what makes that true of a form posted
     * by hand as well.
     *
     * @param  array<string, array<string, mixed>>  $submitted
     * @return array<string, array{enabled: bool, required: bool, order: int}>
     */
    private function fields(ClientSettings $settings, array $submitted): array
    {
        return collect(config('clients.fields'))
            ->map(function (array $catalogue, string $key) use ($settings, $submitted) {
                $row = $submitted[$key] ?? [];
                $locked = (bool) ($catalogue['locked'] ?? false);

                $enabled = $locked || (bool) ($row['enabled'] ?? false);

                return [
                    'enabled' => $enabled,
                    // A disabled field cannot be required: a client the
                    // business is never asked for cannot be missing.
                    'required' => $locked || ($enabled && (bool) ($row['required'] ?? false)),
                    /**
                     * A field the form did not mention keeps the position it
                     * had.
                     *
                     * Defaulting to zero collapsed the whole order onto one
                     * value the moment anything posted a partial set — the
                     * screen always sends every field, but a field added to
                     * the catalogue after a business last saved would
                     * otherwise drag every existing one to the top with it.
                     */
                    'order' => (int) ($row['order'] ?? $settings->field($key)['order']),
                ];
            })
            ->all();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function scalars(array $data): array
    {
        $values = collect($data)->only([
            'default_status', 'default_location_id', 'default_staff_id',
            'default_communication', 'default_marketing', 'name_format',
        ])->all();

        // An unchecked checkbox posts nothing, so every switch is stated
        // explicitly — reading them from the request alone would turn a
        // toggle off by leaving it out and on by leaving it out too.
        foreach ($this->switches() as $switch) {
            $values[$switch] = (bool) ($data[$switch] ?? false);
        }

        return $values;
    }

    /** @return array<int, string> */
    private function switches(): array
    {
        return [
            'preferences_enabled', 'preferences_multiple', 'tags_enabled',
            'notes_enabled', 'notes_multiple', 'notes_in_booking',
            'notes_important_on_profile', 'notes_allow_important',
            'notes_staff_can_edit', 'notes_admin_can_delete',
            'duplicate_warning', 'duplicate_show_matches',
            'allow_booking_inactive', 'archived_in_search',
            'comm_email', 'comm_sms', 'comm_phone',
            'comm_marketing_email', 'comm_marketing_sms',
            'consent_record', 'consent_record_date', 'consent_record_captured_by',
            'consent_client_can_opt_out', 'consent_show_on_profile',
        ];
    }

    /**
     * @param  array<int, int>  $order
     */
    private function applyOrder($relation, array $order): void
    {
        DB::transaction(function () use ($relation, $order) {
            foreach (array_values($order) as $position => $id) {
                $relation->clone()->whereKey($id)->update(['position' => $position]);
            }
        });
    }

    /**
     * A record from another business must not be reachable by swapping the id
     * in the URL — these are lists a salon curates about its own clients.
     */
    private function assertOwnedBy(?string $tenantId, Tenant $tenant): void
    {
        abort_unless($tenantId === $tenant->getTenantKey(), 404);
    }

    /**
     * @return array<string, mixed>
     */
    private function state(Tenant $tenant): array
    {
        return [
            'tenant' => $tenant,
            'settings' => ClientSettings::forTenant($tenant),
            'preferences' => $tenant->clientPreferences()->inOrder()->get(),
            'tags' => $tenant->clientTags()->inOrder()->get(),
            'behavioralTags' => BehavioralTag::groupedFor($tenant),
            'locations' => $tenant->locations()->active()->inDisplayOrder()->get(),
            'staff' => $tenant->staff()->where('is_active', true)->orderBy('first_name')->get(),
        ];
    }
}
