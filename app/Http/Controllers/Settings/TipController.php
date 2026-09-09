<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Models\TipSettings;
use App\Support\Currencies;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * App Settings → Tips.
 *
 * Two questions, and they are different ones. Whether this business takes
 * tips at all is the switch at the top; which of its services are tipped is
 * the table underneath, because a salon that tips its stylists does not tip
 * the shelf a bottle of shampoo came off.
 *
 * Switching tips off hides the question at the till and never erases how the
 * business had answered it. A salon that turns tipping off for the winter
 * should find their services exactly as they left them in the spring.
 */
class TipController extends Controller
{
    public function index(Request $request): View
    {
        $settings = TipSettings::forTenant($request->user()->tenant);

        /* Two tabs once tipping is on: what to suggest, and which services
           it applies to. Its own address rather than a script that swaps
           panels, so a business working through forty services can be sent
           a link that lands on the table. */
        $tab = in_array($request->query('tab'), ['suggest', 'services'], true)
            ? (string) $request->query('tab')
            : 'suggest';

        return view('settings.tips.index', [
            'tab' => $tab,
            'settings' => $settings,
            /* Every active service, because the table is where a business
               decides which of them are tipped — including the ones that are
               not, which is the point of the column. */
            'services' => Service::query()
                ->with(['category', 'prices'])
                ->where('is_active', true)
                ->orderBy('name')
                ->get(),
            'types' => TipSettings::TYPES,
            /* Named rather than read off each service's tenant relation:
               these rows are loaded without it. */
            'currency' => Currencies::resolve(),
        ]);
    }

    /** The switch at the top, and what the business suggests by default. */
    public function update(Request $request): RedirectResponse
    {
        $settings = TipSettings::forTenant($request->user()->tenant);

        $data = $request->validate([
            'is_enabled' => ['nullable', 'boolean'],
            'default_tip_type' => ['required', Rule::in(TipSettings::TYPES)],
            /* A hundred per cent is a tip the size of the bill, which is the
               most anybody could mean by it. */
            'default_tip_value' => ['required', 'integer', 'min:0', 'max:100'],
            'require_selection' => ['nullable', 'boolean'],
            'allow_no_tip' => ['nullable', 'boolean'],
            'percentages' => ['nullable', 'array', 'max:6'],
            /* Nullable, because the form is six boxes and a business that
               wants four leaves two of them empty. Without this the whole
               save bounced back with an error nobody could see, on a field
               they had deliberately left blank. */
            'percentages.*' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $wasEnabled = $settings->is_enabled;

        $settings->update([
            'is_enabled' => (bool) ($data['is_enabled'] ?? false),
            'default_tip_type' => $data['default_tip_type'],
            'default_tip_value' => (int) $data['default_tip_value'],
            'require_selection' => (bool) ($data['require_selection'] ?? false),
            'allow_no_tip' => (bool) ($data['allow_no_tip'] ?? false),
            /* The blanks dropped rather than stored as nought: an empty box
               is a percentage the business did not want, not a nought per
               cent tip. */
            'percentages' => array_values(array_unique(array_map(
                'intval',
                array_filter($data['percentages'] ?? [], fn ($value) => $value !== null && $value !== '')
            ))) ?: null,
        ]);

        return back()->with('toast', [
            'type' => 'success',
            'message' => $wasEnabled === $settings->is_enabled
                ? __('tips.saved')
                : ($settings->is_enabled ? __('tips.enabled') : __('tips.disabled')),
        ]);
    }

    /**
     * One service's answer.
     *
     * Every field may be left blank, and blank means "whatever the business
     * says" rather than a value of its own — so a salon that changes its
     * default has changed it for every service that never disagreed.
     */
    public function service(Request $request, Service $service): RedirectResponse
    {
        $data = $request->validate([
            'accepts_tips' => ['nullable', 'boolean'],
            'tip_type' => ['nullable', Rule::in(TipSettings::TYPES)],
            'tip_value' => ['nullable', 'integer', 'min:0', 'max:100'],
            'tip_required' => ['nullable', 'boolean'],
            'allow_no_tip' => ['nullable', 'boolean'],
        ]);

        $service->update([
            'accepts_tips' => (bool) ($data['accepts_tips'] ?? false),
            'tip_type' => $data['tip_type'] ?? null,
            /* Blank rather than nought: a default of nothing and no default
               at all are different, and only one of them follows the
               business when it changes its mind. */
            'tip_value' => ($data['tip_value'] ?? null) === null ? null : (int) $data['tip_value'],
            'tip_required' => (bool) ($data['tip_required'] ?? false),
            'allow_no_tip' => (bool) ($data['allow_no_tip'] ?? false),
        ]);

        return back()->with('toast', ['type' => 'success', 'message' => __('tips.service_saved')]);
    }
}
