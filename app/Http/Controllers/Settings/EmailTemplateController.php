<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\EmailTemplate;
use App\Models\Promotion;
use App\Models\Service;
use App\Support\ClientEmailSender;
use App\Support\EmailRenderer;
use App\Support\EmailTemplates;
use App\Support\EmailVariables;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * App Settings → Email Templates.
 *
 * The wording StyleDesk uses when it writes to a business's clients, and the
 * templates their team sends by hand. One list for both, because the only
 * difference between them is who starts the email.
 *
 * The list shows the whole catalogue, not only what has been customised: an
 * owner looking for "Booking Confirmation" wants to find it, not to discover
 * it exists once they have already edited it.
 */
class EmailTemplateController extends Controller
{
    public function index(Request $request): View
    {
        $filters = [
            'search' => trim((string) $request->query('search', '')),
            'type' => in_array($request->query('type'), config('email_templates.types'), true)
                ? (string) $request->query('type')
                : '',
            'status' => in_array($request->query('status'), ['active', 'disabled'], true)
                ? (string) $request->query('status')
                : '',
        ];

        return view('settings.email-templates.index', [
            'templates' => $this->filtered($request->user()->tenant, $filters),
            'filters' => $filters,
            'counts' => $this->counts($request->user()->tenant),
        ]);
    }

    /** The editor, for a stored template or for one of StyleDesk's defaults. */
    public function edit(Request $request, string $key): View
    {
        $tenant = $request->user()->tenant;

        return view('settings.email-templates.edit', $this->editorState(
            $tenant,
            EmailTemplates::resolve($tenant, $key),
        ));
    }

    /**
     * A new template of the business's own.
     *
     * Standard only. A transactional template answers an event, and StyleDesk
     * owns the list of events — inventing a trigger would produce a template
     * nothing will ever fire.
     */
    public function create(Request $request): View
    {
        $tenant = $request->user()->tenant;

        $blank = new EmailTemplate([
            'tenant_id' => $tenant->getTenantKey(),
            'type' => EmailTemplate::TYPE_STANDARD,
            'name' => '',
            'subject' => '',
            'heading' => '',
            'intro' => '',
            'cta_enabled' => false,
            'is_active' => true,
        ]);

        return view('settings.email-templates.edit', $this->editorState($tenant, $blank, creating: true));
    }

    public function store(Request $request): RedirectResponse
    {
        $tenant = $request->user()->tenant;
        $data = $this->validated($request);

        /* Slugged from the name, and made unique. The key is what the sending
           drawer refers to, so it has to be stable and readable — and it must
           not collide with one of StyleDesk's own. */
        $key = $this->uniqueKey($tenant, Str::slug($data['name'], '_'));

        EmailTemplate::withoutGlobalScopes()->create($data + [
            'tenant_id' => $tenant->getTenantKey(),
            'key' => $key,
            'type' => EmailTemplate::TYPE_STANDARD,
            'trigger' => null,
            'updated_by' => $request->user()->id,
        ]);

        return redirect()->route('settings.email-templates.edit', $key)
            ->with('status', __('email_templates.editor.created', ['name' => $data['name']]));
    }

    /**
     * Save the business's wording.
     *
     * `updateOrCreate`, because most templates have no row until the first
     * time somebody edits one — the catalogue is code and the database holds
     * only what has been changed.
     */
    public function update(Request $request, string $key): RedirectResponse
    {
        $tenant = $request->user()->tenant;
        $existing = EmailTemplates::resolve($tenant, $key);

        EmailTemplate::withoutGlobalScopes()->updateOrCreate(
            ['tenant_id' => $tenant->getTenantKey(), 'key' => $key],
            $this->validated($request) + [
                'type' => $existing->type,
                'trigger' => $existing->trigger,
                'updated_by' => $request->user()->id,
            ],
        );

        return back()->with('status', __('email_templates.editor.saved'));
    }

    /**
     * Copy a template so the business can vary it.
     *
     * The copy is always standard, whatever it was copied from: two templates
     * answering one trigger is two emails for one event, and the product
     * cannot choose between them.
     */
    public function duplicate(Request $request, string $key): RedirectResponse
    {
        $tenant = $request->user()->tenant;
        $source = EmailTemplates::resolve($tenant, $key);

        $name = __('email_templates.editor.copy_of', ['name' => $source->name]);
        $newKey = $this->uniqueKey($tenant, Str::slug($name, '_'));

        EmailTemplate::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->getTenantKey(),
            'key' => $newKey,
            'type' => EmailTemplate::TYPE_STANDARD,
            'trigger' => null,
            'name' => $name,
            'subject' => $source->subject,
            'heading' => $source->heading,
            'intro' => $source->intro,
            'supporting_message' => $source->supporting_message,
            'blocks' => $source->blocks,
            'detail_fields' => $source->detail_fields,
            'cta_enabled' => $source->cta_enabled,
            'cta_label' => $source->cta_label,
            'cta_action' => $source->cta_action,
            'promotion_id' => $source->promotion_id,
            'is_active' => true,
            'updated_by' => $request->user()->id,
        ]);

        return redirect()->route('settings.email-templates.edit', $newKey)
            ->with('status', __('email_templates.editor.duplicated'));
    }

    /**
     * The same preview, for a template that does not exist yet.
     *
     * The create screen needs one as much as the editor does — arguably more,
     * since somebody writing from scratch has nothing else to judge the
     * wording against. It cannot use the keyed route because a new template
     * has no key until it is saved.
     */
    public function previewDraft(Request $request): Response
    {
        $tenant = $request->user()->tenant;

        $draft = new EmailTemplate($this->validated($request) + [
            'tenant_id' => $tenant->getTenantKey(),
            'type' => EmailTemplate::TYPE_STANDARD,
            'is_active' => true,
        ]);

        if ($draft->promotion_id) {
            $draft->setRelation('promotion', Promotion::find($draft->promotion_id));
        }

        return $this->asHtml(EmailRenderer::preview($draft, $tenant)['html']);
    }

    /**
     * The email as it would look, from what is in the form right now.
     *
     * Rendered on the server from unsaved values rather than approximated in
     * the browser: the preview has to be the same HTML the client will get,
     * and a second implementation in JavaScript would drift from it the first
     * time either changed.
     */
    public function preview(Request $request, string $key): Response
    {
        $tenant = $request->user()->tenant;

        $template = EmailTemplates::resolve($tenant, $key)->fill($this->validated($request));

        /* The chosen campaign has to be on the instance, not just its id, or
           the coupon block has nothing to draw and the preview quietly loses
           it the moment somebody picks one. */
        if ($template->promotion_id) {
            $template->setRelation('promotion', Promotion::find($template->promotion_id));
        }

        return $this->asHtml(EmailRenderer::preview($template, $tenant)['html']);
    }

    private function asHtml(string $html): Response
    {
        return response($html)->header('Content-Type', 'text/html');
    }

    /**
     * Send it to somebody, with sample data.
     *
     * The point is to see it in a real inbox — layout, branding, subject line,
     * how it looks on a phone — before a client ever does.
     */
    public function test(Request $request, ?string $key = null): JsonResponse
    {
        $tenant = $request->user()->tenant;

        $data = $request->validate(['email' => ['required', 'email', 'max:255']]);

        if (! ClientEmailSender::readyFor($tenant)) {
            throw ValidationException::withMessages([
                'email' => ClientEmailSender::blockingReason($tenant),
            ]);
        }

        /*
         * Rendered from what is on the screen, not from what is stored.
         *
         * The point of a test send is to judge wording before committing to
         * it. Sending the saved version would mail the reader the email they
         * are in the middle of changing, which is the one thing it must not
         * do — and on the create screen there is nothing saved to send.
         */
        $template = $key === null
            ? new EmailTemplate($this->validated($request) + [
                'tenant_id' => $tenant->getTenantKey(),
                'type' => EmailTemplate::TYPE_STANDARD,
                'is_active' => true,
            ])
            : EmailTemplates::resolve($tenant, $key)->fill($this->validated($request));

        if ($template->promotion_id) {
            $template->setRelation('promotion', Promotion::find($template->promotion_id));
        }

        $rendered = EmailRenderer::preview($template, $tenant);

        try {
            Mail::html($rendered['html'], function ($message) use ($data, $rendered, $tenant) {
                $message->to($data['email'])
                    ->subject($rendered['subject'])
                    ->from(
                        (string) config('mail.from.address'),
                        __('client_email.send.from_via', ['name' => $tenant->email_sender_name ?: $tenant->name]),
                    );
            });
        } catch (\Throwable $e) {
            throw ValidationException::withMessages([
                'email' => __('email_templates.editor.test_failed', ['reason' => $e->getMessage()]),
            ]);
        }

        return response()->json([
            'message' => __('email_templates.editor.test_sent', ['email' => $data['email']]),
        ]);
    }

    /**
     * Switch a template off, or back on.
     *
     * Never a delete. Something in the product fires at a transactional
     * template's key, and removing the row would leave that event with nothing
     * to send — so "disabled" is the strongest thing an owner can do to one.
     */
    public function toggle(Request $request, string $key): RedirectResponse
    {
        $tenant = $request->user()->tenant;
        $template = EmailTemplates::resolve($tenant, $key);

        /* A default has no row yet. Switching one off is the first time the
           business has expressed an opinion about it, so this is where the
           row gets written. */
        $template->fill([
            'tenant_id' => $tenant->getTenantKey(),
            'is_active' => ! $template->is_active,
            'updated_by' => $request->user()->id,
        ])->save();

        return back()->with('status', __(
            $template->is_active ? 'email_templates.list.enabled' : 'email_templates.list.disabled',
            ['name' => $template->name],
        ));
    }

    /**
     * Throw away the business's wording and go back to StyleDesk's.
     *
     * A delete, because the default is in the code rather than in a stored
     * copy. That is what makes this the one action on the screen that cannot
     * fail or leave the business without an email.
     */
    public function reset(Request $request, string $key): RedirectResponse
    {
        EmailTemplate::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $request->user()->tenant->getTenantKey())
            ->where('key', $key)
            ->delete();

        return back()->with('status', __('email_templates.list.reset', [
            'name' => EmailTemplates::default($request->user()->tenant, $key)->name,
        ]));
    }

    /**
     * What the editor needs: the template, and every choice it can make.
     *
     * @return array<string, mixed>
     */
    private function editorState($tenant, EmailTemplate $template, bool $creating = false): array
    {
        return [
            'template' => $template,
            /* An argument rather than something the caller merges over: array
               union keeps the LEFT value on a duplicate key, so an override
               written as `state() + ['creating' => true]` is silently thrown
               away — which shipped a create screen that tried to build a
               Duplicate link for a template with no key. */
            'creating' => $creating,
            'blocks' => config('email_templates.blocks'),
            /* Labelled for the combo, which shows names rather than keys. */
            'detailFields' => collect(array_keys(config('email_templates.detail_fields')))
                ->mapWithKeys(fn (string $field) => [$field => __('email_templates.rows.'.$field)])
                ->all(),

            /* What each row will actually contain, so the reader is choosing
               between "Date · 8 September 2026" and "Reference · BK-2026…"
               rather than between two words they have to imagine the value
               behind. Sample data, the same the preview uses. */
            'detailSamples' => $this->detailSamples($tenant),

            /* The variable behind each row, so a row can be dropped into the
               wording as well as shown in the card. "Your {{service.name}} on
               {{booking.date}}" is a sentence somebody wants to write, and
               making them find the token in a menu they have already seen the
               value for is a step too many. */
            'detailTokens' => [
                'service' => '{{service.name}}',
                'date' => '{{booking.date}}',
                'time' => '{{booking.start_time}}',
                'staff' => '{{staff.full_name}}',
                'location' => '{{location.name}}',
                'reference' => '{{booking.reference}}',
                'price' => '{{service.price}}',
                'amount_paid' => '{{payment.amount_paid}}',
                'balance_due' => '{{payment.balance_due}}',
            ],

            /* What this business actually sells, for the second combo. */
            'services' => Service::query()
                ->orderBy('name')
                ->pluck('name', 'id')
                ->all(),
            'ctaActions' => config('email_templates.cta_actions'),
            'variables' => EmailVariables::catalogue(),
            /* Coupons only, and only ones a client could actually use. An
               offer with no code is not something to put in an email, and a
               draft or expired campaign would send a code the till refuses. */
            'coupons' => Promotion::query()
                ->where('type', 'coupon')
                ->whereNotNull('code')
                ->orderBy('name')
                ->get()
                ->filter(fn (Promotion $promotion) => $promotion->status() === 'active')
                ->values(),
            'limits' => config('email_templates.limits'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $limits = config('email_templates.limits');

        /* The preview must render from a half-written form — the whole point
           is watching it take shape — so it asks for the same fields without
           insisting they are filled in yet. Saving still requires them. */
        $required = $request->routeIs('*.preview')
            || $request->routeIs('*.preview-draft')
            || $request->routeIs('*.test')
            || $request->routeIs('*.test-draft')
                ? 'nullable'
                : 'required';

        $data = $request->validate([
            'name' => [$required, 'string', 'max:'.$limits['name']],
            'subject' => [$required, 'string', 'max:'.$limits['subject']],
            'heading' => ['nullable', 'string', 'max:'.$limits['heading']],
            'intro' => ['nullable', 'string', 'max:'.$limits['body']],
            'supporting_message' => ['nullable', 'string', 'max:'.$limits['body']],
            'cta_enabled' => ['nullable', 'boolean'],
            'cta_label' => ['nullable', 'string', 'max:60'],
            /* From the list StyleDesk can actually perform. A free-text URL is
               how a transactional email ends up pointing at a dead page. */
            'cta_action' => ['nullable', Rule::in(config('email_templates.cta_actions'))],
            /* Scoped to this business's own coupons: an id from another tenant
               would put somebody else's campaign in this salon's email. */
            'promotion_id' => [
                'nullable',
                Rule::exists('promotions', 'id')
                    ->where('tenant_id', $request->user()->tenant->getTenantKey())
                    ->where('type', 'coupon'),
            ],
            'blocks' => ['nullable', 'array'],
            'detail_fields' => ['nullable', 'array'],
            /* This business's own services. An id from another tenant would
               name somebody else's treatment in this salon's email. */
            'detail_services' => ['nullable', 'array'],
            'detail_services.*' => [
                Rule::exists('services', 'id')
                    ->where('tenant_id', $request->user()->tenant->getTenantKey()),
            ],
        ]);

        /* Checkboxes send nothing when unticked, so an absent block is off
           rather than missing — otherwise switching one off would look
           identical to never having touched the form. */
        $data['blocks'] = collect(array_keys(config('email_templates.blocks')))
            ->mapWithKeys(fn (string $block) => [$block => (bool) ($request->input('blocks.'.$block))])
            ->all();

        /*
         * The combo posts a list of the fields that are on; the row stores a
         * map of every field to true or false.
         *
         * Kept as a map because that is what "shown or hidden" means, and a
         * list cannot tell a field somebody switched off from one that did not
         * exist when they saved. The control's shape and the column's are
         * allowed to differ; this is where they meet.
         */
        $chosen = collect((array) $request->input('detail_fields', []))
            ->map(fn ($value, $key) => is_int($key) ? $value : ($value ? $key : null))
            ->filter()
            ->values();

        $data['detail_fields'] = collect(array_keys(config('email_templates.detail_fields')))
            ->mapWithKeys(fn (string $field) => [$field => $chosen->contains($field)])
            ->all();

        /* Only meaningful while the service row is shown at all. */
        $data['detail_services'] = $data['detail_fields']['service']
            ? array_values(array_map('intval', (array) ($data['detail_services'] ?? [])))
            : [];

        $data['cta_enabled'] = (bool) ($data['cta_enabled'] ?? false);

        return $data;
    }

    /**
     * A believable value for each row of the details card.
     *
     * Drawn from the same sample set as the preview, so what the editor
     * promises and what the preview shows cannot disagree.
     *
     * @return array<string, string>
     */
    private function detailSamples($tenant): array
    {
        $values = EmailVariables::samples($tenant);

        return [
            'service' => $values['service.name'],
            'date' => $values['booking.date'],
            'time' => $values['booking.start_time'].' – '.$values['booking.end_time'],
            'staff' => $values['staff.full_name'],
            'location' => $values['location.name'].' · '.$values['location.address'],
            'reference' => $values['booking.reference'],
            'price' => $values['service.price'],
            'amount_paid' => $values['payment.amount_paid'],
            'balance_due' => $values['payment.balance_due'],
        ];
    }

    /** A key nothing else is using, StyleDesk's own catalogue included. */
    private function uniqueKey($tenant, string $base): string
    {
        $base = mb_substr($base ?: 'template', 0, 60);
        $key = $base;
        $suffix = 2;

        while (EmailTemplates::keys()->contains($key) || EmailTemplate::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenant->getTenantKey())
            ->where('key', $key)
            ->exists()
        ) {
            $key = $base.'_'.$suffix++;
        }

        return $key;
    }

    /**
     * @param  array{search: string, type: string, status: string}  $filters
     * @return Collection<int, EmailTemplate>
     */
    private function filtered($tenant, array $filters): Collection
    {
        return EmailTemplates::all($tenant)
            ->when($filters['type'] !== '', fn (Collection $all) => $all
                ->where('type', $filters['type']))
            ->when($filters['status'] !== '', fn (Collection $all) => $all
                ->where('is_active', $filters['status'] === 'active'))
            /* Filtered in PHP rather than SQL because most of these rows do
               not exist — the catalogue is code, and only what a business has
               customised is in the database. Thirty-five rows is a list, not a
               query. */
            ->when($filters['search'] !== '', fn (Collection $all) => $all
                ->filter(fn (EmailTemplate $t) => str_contains(
                    mb_strtolower($t->name.' '.$t->subject.' '.$t->key.' '.($t->triggerLabel() ?? '')),
                    mb_strtolower($filters['search']),
                )))
            ->values();
    }

    /** @return array{all: int, transactional: int, standard: int, disabled: int} */
    private function counts($tenant): array
    {
        $all = EmailTemplates::all($tenant);

        return [
            'all' => $all->count(),
            'transactional' => $all->where('type', EmailTemplate::TYPE_TRANSACTIONAL)->count(),
            'standard' => $all->where('type', EmailTemplate::TYPE_STANDARD)->count(),
            'disabled' => $all->where('is_active', false)->count(),
        ];
    }
}
