<?php

declare(strict_types=1);

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Models\EmailCampaign;
use App\Support\CampaignAudience;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Email campaigns — what the business writes to its whole client list.
 *
 * Governed by its own permissions rather than by the email ones: sending one
 * client a receipt and sending twelve hundred people a promotion are different
 * acts, and a business can want its receptionist to do the first and not the
 * second. Sending is separate again from creating, because a campaign is
 * drafted, read over, and then sent — two decisions, two authorities.
 */
class EmailCampaignController extends Controller
{
    public function index(Request $request): View
    {
        $this->allow($request, 'marketing.view');

        return view('marketing.email.index', [
            'summary' => CampaignAudience::summary(),
            'statuses' => collect(EmailCampaign::STATUSES)
                ->mapWithKeys(fn (string $status) => [$status => __('marketing.statuses.'.$status)])
                ->all(),
            'canCreate' => $request->user()->hasPermission('marketing.create'),
        ]);
    }

    /** The rows the listing grid asks for, as JSON. */
    public function data(Request $request): JsonResponse
    {
        $this->allow($request, 'marketing.view');

        $page = max(1, $request->integer('page', 1));
        $size = min(100, max(1, $request->integer('size', 25)));

        $query = EmailCampaign::query()
            ->with('author')
            ->matching($request->string('search')->value())
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')->value()))
            ->when($request->filled('created_by'), fn ($q) => $q->where('created_by', $request->integer('created_by')))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('created_at', '>=', $request->string('from')->value()))
            ->when($request->filled('until'), fn ($q) => $q->whereDate('created_at', '<=', $request->string('until')->value()))
            ->latest('id');

        $total = (clone $query)->count();

        return response()->json([
            'total' => $total,
            'last_page' => (int) max(1, ceil($total / $size)),
            'data' => $query->forPage($page, $size)->get()->map(fn (EmailCampaign $campaign) => [
                'id' => $campaign->id,
                'name' => $campaign->name,
                'primary_badge' => $campaign->subject,
                'audience' => CampaignAudience::describe($campaign->audience),
                'recipients' => (string) ($campaign->total_recipients ?: ($campaign->estimated_recipients ?? 0)),
                'scheduled' => $campaign->scheduled_for?->translatedFormat('j M Y, g:i A') ?? '—',
                'sent' => $campaign->sent_at?->translatedFormat('j M Y') ?? '—',
                'delivered' => (string) $campaign->delivered_count,
                'opened' => $campaign->delivered_count > 0 ? $campaign->rate('opened').'%' : '—',
                'clicked' => $campaign->delivered_count > 0 ? $campaign->rate('clicked').'%' : '—',
                'author' => $campaign->author?->name ?? '—',
                'status' => $campaign->statusLabel(),
                'status_class' => $campaign->statusClass(),
                'url' => route('marketing.email.edit', $campaign),
            ])->all(),
        ]);
    }

    public function create(Request $request): View
    {
        $this->allow($request, 'marketing.create');

        return view('marketing.email.form', [
            'campaign' => new EmailCampaign,
            'options' => CampaignAudience::options(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->allow($request, 'marketing.create');

        $campaign = EmailCampaign::create($this->validated($request) + [
            'status' => 'draft',
            'created_by' => $request->user()->id,
        ]);

        $this->reestimate($campaign);

        return redirect()
            ->route('marketing.email.edit', $campaign)
            ->with('status', __('marketing.saved'));
    }

    public function edit(Request $request, EmailCampaign $campaign): View
    {
        $this->allow($request, 'marketing.view');

        return view('marketing.email.form', [
            'campaign' => $campaign,
            'options' => CampaignAudience::options(),
        ]);
    }

    public function update(Request $request, EmailCampaign $campaign): RedirectResponse
    {
        $this->allow($request, 'marketing.edit');

        /* A campaign that is going out cannot be edited, and neither can one
           that has gone: the first would change what half the list receives
           midway, and the second would rewrite what people were sent. */
        abort_unless($campaign->isEditable(), 422);

        $campaign->update($this->validated($request));
        $this->reestimate($campaign);

        return back()->with('status', __('marketing.saved'));
    }

    public function destroy(Request $request, EmailCampaign $campaign): RedirectResponse
    {
        $this->allow($request, 'marketing.delete');

        /* Only a draft. Something that was sent is a record of what people
           received, and deleting it would not unsend it. */
        abort_unless($campaign->status === 'draft', 422);

        $campaign->delete();

        return redirect()
            ->route('marketing.email.index')
            ->with('status', __('marketing.deleted'));
    }

    /**
     * What the rules come to right now.
     *
     * Asked as the audience is built rather than on save: the whole point of
     * the step is watching the number move as the rules change.
     */
    public function estimate(Request $request): JsonResponse
    {
        $this->allow($request, 'marketing.view');

        return response()->json(
            CampaignAudience::estimate($this->rules($request->input('audience', []))),
        );
    }

    // ------------------------------------------------------------- the parts

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'subject' => ['nullable', 'string', 'max:200'],
            'preview_text' => ['nullable', 'string', 'max:200'],
            'from_name' => ['nullable', 'string', 'max:120'],
            'reply_to' => ['nullable', 'email', 'max:200'],
            /* Not `array`: the form posts the rules as one JSON field, and
               the estimate endpoint posts them as an object. Both are
               normalised by rules() below, which is also what decides what
               may appear in them — so validating the shape here would only
               reject one of the two callers. */
            'audience' => ['nullable'],
            'template' => ['nullable', 'string', Rule::in(array_keys(config('marketing.templates')))],
        ]);

        $data['audience'] = $this->rules($request->input('audience', []));

        return $data;
    }

    /**
     * The audience rules, cleaned.
     *
     * Whitelisted rather than stored as posted: this array is JSON in a column
     * and is later read straight into a query builder, so what may appear in
     * it is decided here and not by whoever wrote the form.
     *
     * @return array<string, mixed>
     */
    private function rules(mixed $input): array
    {
        /* The form sends one hidden field holding JSON; the estimate endpoint
           sends an object. Decoded here so the two arrive at the same place. */
        if (is_string($input)) {
            $input = json_decode($input, true);
        }

        $input = is_array($input) ? $input : [];

        $ids = fn (string $key) => collect($input[$key] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->values()
            ->all();

        $days = function (string $key) use ($input) {
            $value = (int) ($input[$key] ?? 0);

            return in_array($value, CampaignAudience::LAPSED_DAYS, true) ? $value : null;
        };

        return array_filter([
            'scope' => in_array($input['scope'] ?? 'all', CampaignAudience::SCOPES, true)
                ? $input['scope']
                : 'all',
            'locations' => $ids('locations'),
            'tags' => $ids('tags'),
            'staff' => $ids('staff'),
            'services' => $ids('services'),
            'not_visited_days' => $days('not_visited_days'),
            'visited_within_days' => $days('visited_within_days'),
            'has_upcoming' => match ($input['has_upcoming'] ?? null) {
                true, 'true', '1', 1 => true,
                false, 'false', '0', 0 => false,
                default => null,
            },
        ], fn ($value) => $value !== null && $value !== []);
    }

    /**
     * Remember what the audience came to.
     *
     * A cache for the listing, stamped with when it was counted — the rules
     * are the truth and they answer differently tomorrow, so a figure without
     * a date on it would be a number nobody could trust.
     */
    private function reestimate(EmailCampaign $campaign): void
    {
        $campaign->forceFill([
            'estimated_recipients' => CampaignAudience::estimate($campaign->audience ?? [])['eligible'],
            'estimated_at' => now(),
        ])->save();
    }

    private function allow(Request $request, string $permission): void
    {
        abort_unless($request->user()?->hasPermission($permission, 'location'), 403);
    }
}
