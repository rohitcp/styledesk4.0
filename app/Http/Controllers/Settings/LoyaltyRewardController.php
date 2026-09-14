<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\LoyaltyReward;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * The reward catalogue, on App Settings → Loyalty & Rewards.
 *
 * Its own controller rather than more fields on LoyaltySettingsController:
 * that screen saves one row of rules and this one keeps a list, and a single
 * update() that had to tell "the business changed its earning rate" from "the
 * business added a reward" would be two forms wearing one action.
 *
 * Rewards are deactivated, never deleted, once anything has been redeemed
 * against them — a client's history says what they got, and a deleted row
 * turns that line into a number with no name.
 */
class LoyaltyRewardController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $this->permit($request);

        $tenant = $request->user()->tenant;
        $data = $this->validated($request);

        LoyaltyReward::create($data + [
            'tenant_id' => $tenant->getTenantKey(),
            /* On the end of the list. A reward added today is not one the
               business has decided should lead the catalogue. */
            'position' => (int) LoyaltyReward::query()->max('position') + 1,
        ]);

        return back()->with('toast', [
            'type' => 'success',
            'message' => __('loyalty.rewards.added'),
        ]);
    }

    public function update(Request $request, LoyaltyReward $reward): RedirectResponse
    {
        $this->permit($request);

        $reward->update($this->validated($request));

        return back()->with('toast', [
            'type' => 'success',
            'message' => __('loyalty.rewards.saved'),
        ]);
    }

    /**
     * Take a reward off the list.
     *
     * Deactivated rather than removed wherever somebody has spent points on
     * it: the row is what a redemption line reads its name from, and deleting
     * it would leave a client's history saying "-500" with nothing beside it.
     * A reward nobody ever took is genuinely deleted — it was a mistake being
     * tidied away, not a thing that happened.
     */
    public function destroy(Request $request, LoyaltyReward $reward): RedirectResponse
    {
        $this->permit($request);

        $redeemed = $reward->loadCount('redemptions')->redemptions_count > 0;

        if ($redeemed) {
            $reward->update(['is_active' => false]);
        } else {
            $reward->delete();
        }

        return back()->with('toast', [
            'type' => 'success',
            'message' => __($redeemed ? 'loyalty.rewards.retired' : 'loyalty.rewards.removed'),
        ]);
    }

    /**
     * What a reward has to say about itself.
     *
     * The type decides which of the value fields is required, because each
     * type is worth something in a different way: an amount off, a percentage
     * off, or a named service being given. Asking for all three and ignoring
     * two would let a business save "20% off" with £10 typed beside it and
     * never find out which one the till would use.
     *
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $types = collect(config('loyalty.reward_types'))
            ->filter(fn (array $type) => $type['available'])
            ->keys()
            ->all();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:255'],
            /* Only a type something can actually hand over. A free product
               would be a reward the till could never settle. */
            'type' => ['required', Rule::in($types)],
            'points_required' => ['required', 'integer', 'min:1', 'max:1000000'],
            'value' => ['nullable', 'numeric', 'min:0.01', 'max:100000'],
            'percent' => ['nullable', 'integer', 'min:1', 'max:100'],
            'service_id' => ['nullable', 'integer', Rule::exists('services', 'id')],
            'scope' => ['required', Rule::in(config('loyalty.reward_scopes'))],
            'scope_ids' => ['nullable', 'array'],
            'scope_ids.*' => ['integer'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $needs = (string) config('loyalty.reward_types.'.$data['type'].'.needs', 'none');

        $this->requireValueFor($needs, $data);
        $this->requireScopeFor($data);

        return [
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'type' => $data['type'],
            'points_required' => (int) $data['points_required'],
            /* Only the field this type is worth something by. Clearing the
               other two is what stops a reward edited from "20% off" to "£10
               off" keeping a stale percentage the till might still read. */
            'value_minor' => $needs === 'amount' ? (int) round(((float) $data['value']) * 100) : null,
            'percent' => $needs === 'percent' ? (int) $data['percent'] : null,
            'service_id' => $needs === 'service' ? (int) $data['service_id'] : null,
            'scope' => $data['scope'],
            'scope_ids' => $data['scope'] === 'all_services'
                ? null
                : array_values(array_unique(array_map('intval', $data['scope_ids'] ?? []))),
            'is_active' => (bool) ($data['is_active'] ?? false),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function requireValueFor(string $needs, array $data): void
    {
        $missing = match ($needs) {
            'amount' => ! isset($data['value']),
            'percent' => ! isset($data['percent']),
            'service' => ! isset($data['service_id']),
            default => false,
        };

        if (! $missing) {
            return;
        }

        throw ValidationException::withMessages([
            match ($needs) {
                'amount' => 'value',
                'percent' => 'percent',
                default => 'service_id',
            } => __('loyalty.rewards.validation.'.$needs.'_required'),
        ]);
    }

    /**
     * A narrowed reward has to name what it is narrowed to.
     *
     * "These services" with nothing chosen is a reward that covers nothing,
     * which reads on the catalogue exactly like one that covers everything.
     *
     * @param  array<string, mixed>  $data
     */
    private function requireScopeFor(array $data): void
    {
        if ($data['scope'] === 'all_services' || ! empty($data['scope_ids'])) {
            return;
        }

        throw ValidationException::withMessages([
            'scope_ids' => __('loyalty.rewards.validation.scope_required'),
        ]);
    }

    /**
     * Changing the catalogue is the same authority as changing the rate.
     *
     * Deciding what a point is worth and deciding what a thousand of them buy
     * are the same decision said two ways, so they share a permission rather
     * than inventing a third.
     */
    private function permit(Request $request): void
    {
        abort_unless($request->user()?->hasPermission('loyalty.manage_settings', 'own'), 403);
    }
}
