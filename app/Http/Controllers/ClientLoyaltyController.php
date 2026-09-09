<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\LoyaltySettings;
use App\Support\LoyaltyPoints;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * One client's rewards balance.
 *
 * The tab itself is rendered with the profile — it is a view of one record
 * rather than a place of its own, the same as every other tab there. What
 * lives here is the one thing that changes a balance by hand.
 *
 * Adjusting points is issuing the business money, which is why it is its own
 * permission and why every adjustment is signed. §7 asks for the points, the
 * type, the reason, the note, who and when; the ledger row carries all seven
 * and nothing can edit one afterwards.
 */
class ClientLoyaltyController extends Controller
{
    public function adjust(Request $request, Client $client): RedirectResponse
    {
        abort_unless($client->tenant_id === $request->user()->tenant?->getTenantKey(), 404);
        abort_unless($request->user()->hasPermission('loyalty.adjust_points', 'own'), 403);

        $settings = LoyaltySettings::forTenant($request->user()->tenant);

        /* A scheme that is switched off does not quietly accept points. The
           form is not rendered in that state either, but a POST that arrived
           anyway must be refused rather than banked. */
        abort_unless($settings->is_enabled, 403);

        $data = $request->validate([
            'direction' => ['required', Rule::in(['add', 'remove'])],
            /* Positive, always. The direction is a separate answer, so
               "-100" typed into a box labelled Points cannot mean the
               opposite of what the person choosing "Remove" intended. */
            'points' => ['required', 'integer', 'min:1', 'max:1000000'],
            'reason' => ['required', Rule::in(config('loyalty.adjustment_reasons'))],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        LoyaltyPoints::adjust(
            client: $client,
            points: (int) $data['points'],
            isAddition: $data['direction'] === 'add',
            reason: $data['reason'],
            note: $data['note'] ?? null,
            userId: $request->user()->id,
        );

        return redirect()
            ->route('clients.show', $client)
            ->withFragment('rewards')
            ->with('toast', [
                'type' => 'success',
                'message' => __('loyalty.client.adjusted'),
            ]);
    }
}
