<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Support\Currencies;
use App\Support\ReturnTo;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * The Currency module: what the business prices in.
 *
 * Deliberately the same shape as Languages, because it is the same decision:
 * one primary that everything defaults to, and a set of additional ones the
 * business also supports. Where they differ is who it affects — a language is
 * a personal choice made from the header, while a currency is the business's
 * and applies to every price on every screen.
 *
 * No conversion happens anywhere here. §Pricing Behavior is explicit that a
 * business enters its own price per currency; guessing one from another with a
 * live rate would put a number on a client's receipt that nobody chose.
 */
class CurrencyController extends Controller
{
    public function show(Request $request): View
    {
        return view('settings.currency.show', $this->currencyState($request->user()->tenant));
    }

    public function edit(Request $request): View
    {
        return view('settings.currency.edit', [
            ...$this->currencyState($request->user()->tenant),
            ...$this->returnTo($request, route('settings.currency.show')),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $tenant = $request->user()->tenant;
        $codes = Currencies::available()->keys()->all();

        $data = $request->validate([
            'primary' => ['required', Rule::in($codes)],
            'secondary' => ['nullable', 'array'],
            'secondary.*' => [Rule::in($codes)],
        ], [
            'primary.required' => __('currency.validation.primary_required'),
            'primary.in' => __('currency.validation.unsupported'),
            'secondary.*.in' => __('currency.validation.unsupported'),
        ]);

        /**
         * The primary is dropped from the secondaries rather than refused.
         *
         * Ticking your own primary is a reasonable mistake and it means
         * nothing harmful — the currency is enabled either way. An error would
         * stop a save that already said what the user wanted.
         */
        $secondary = collect($data['secondary'] ?? [])
            ->reject(fn (string $code) => $code === $data['primary'])
            ->unique()
            ->values();

        DB::transaction(function () use ($tenant, $data, $secondary) {
            $tenant->forceFill(['currency_code' => $data['primary']])->save();

            /**
             * Rewritten rather than reconciled, and position is the order the
             * pricing rows are listed in. The primary is not stored here: it
             * is enabled by being the primary, and a second copy of that fact
             * is a second thing that can disagree.
             */
            $tenant->currencies()->delete();

            $secondary->each(fn (string $code, int $index) => $tenant->currencies()->create([
                'currency_code' => $code,
                'position' => $index + 1,
            ]));
        });

        return redirect()
            ->to(ReturnTo::resolve($request, route('settings.currency.show')))
            ->with('toast', ['type' => 'success', 'message' => __('currency.saved')]);
    }

    /**
     * @return array<string, mixed>
     */
    private function currencyState(Tenant $tenant): array
    {
        $enabled = Currencies::enabledFor($tenant);
        $primary = Currencies::primaryFor($tenant);

        return [
            'tenant' => $tenant,
            'options' => Currencies::options(),
            'primary' => $primary,
            'secondary' => $enabled->reject(fn (string $code) => $code === $primary)->values(),
            'enabled' => $enabled,
        ];
    }
}
