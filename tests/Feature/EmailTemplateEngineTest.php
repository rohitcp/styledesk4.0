<?php

namespace Tests\Feature;

use App\Models\EmailTemplate;
use App\Models\Promotion;
use App\Models\Service;
use App\Models\Tenant;
use App\Support\EmailRenderer;
use App\Support\EmailTemplates;
use App\Support\EmailVariables;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The template engine: defaults, overrides, variables and the shell.
 *
 * The rule everything else rests on is that StyleDesk always has an answer. A
 * business that has never opened the Email Templates screen must still send a
 * correctly worded, correctly laid out email — otherwise a configuration gap
 * silently breaks client communication.
 */
class EmailTemplateEngineTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['name' => 'Serenity Spa', 'slug' => 'serenity']);
    }

    // ------------------------------------------------------------ defaults

    public function test_a_business_with_no_rows_still_has_every_template(): void
    {
        $this->assertSame(0, EmailTemplate::withoutGlobalScopes()->count());

        $templates = EmailTemplates::all($this->tenant);

        $this->assertCount(EmailTemplates::keys()->count(), $templates);
        $this->assertTrue($templates->every(fn (EmailTemplate $t) => filled($t->subject)));
        $this->assertTrue($templates->every(fn (EmailTemplate $t) => filled($t->name)));
    }

    /** Every shipped key has real wording behind it, not a printed lang key. */
    public function test_no_default_template_leaks_a_translation_key(): void
    {
        foreach (EmailTemplates::keys() as $key) {
            $template = EmailTemplates::default($this->tenant, $key);

            foreach (['name', 'subject', 'heading', 'intro'] as $field) {
                $this->assertStringNotContainsString(
                    'email_templates.',
                    (string) $template->{$field},
                    "[{$key}] {$field} printed a lang key"
                );
                $this->assertNotSame('', trim((string) $template->{$field}), "[{$key}] {$field} is empty");
            }
        }
    }

    public function test_a_trigger_key_is_transactional_and_a_standard_key_is_not(): void
    {
        $this->assertSame(
            EmailTemplate::TYPE_TRANSACTIONAL,
            EmailTemplates::default($this->tenant, 'booking.confirmed')->type
        );

        $standard = EmailTemplates::default($this->tenant, 'thank_you');

        $this->assertSame(EmailTemplate::TYPE_STANDARD, $standard->type);
        $this->assertNull($standard->trigger);
    }

    /** A stored row wins; resetting is a delete, and the default returns. */
    public function test_a_stored_row_overrides_the_default_and_deleting_restores_it(): void
    {
        $default = EmailTemplates::resolve($this->tenant, 'booking.confirmed')->subject;

        $row = EmailTemplate::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'key' => 'booking.confirmed',
            'type' => EmailTemplate::TYPE_TRANSACTIONAL,
            'trigger' => 'booking.confirmed',
            'name' => 'Ours',
            'subject' => 'See you soon',
        ]);

        $this->assertSame('See you soon', EmailTemplates::resolve($this->tenant, 'booking.confirmed')->subject);

        $row->delete();

        $this->assertSame($default, EmailTemplates::resolve($this->tenant, 'booking.confirmed')->subject);
    }

    public function test_a_disabled_transactional_template_does_not_send(): void
    {
        EmailTemplate::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'key' => 'booking.confirmed',
            'type' => EmailTemplate::TYPE_TRANSACTIONAL,
            'trigger' => 'booking.confirmed',
            'name' => 'Ours', 'subject' => 'Hi', 'is_active' => false,
        ]);

        $this->assertNull(EmailTemplates::activeFor($this->tenant, 'booking.confirmed'));
        $this->assertNotNull(EmailTemplates::activeFor($this->tenant, 'booking.cancelled'));
    }

    // ----------------------------------------------------------- variables

    public function test_every_offered_variable_resolves(): void
    {
        $samples = EmailVariables::samples($this->tenant);

        foreach (config('email_templates.variables') as $group => $names) {
            foreach ($names as $name) {
                $this->assertArrayHasKey("{$group}.{$name}", $samples);
                $this->assertNotSame('', $samples["{$group}.{$name}"], "{$group}.{$name} has no sample");
            }
        }
    }

    public function test_the_insert_menu_offers_only_variables_that_exist(): void
    {
        $samples = EmailVariables::samples($this->tenant);

        foreach (EmailVariables::catalogue() as $group) {
            foreach ($group['variables'] as $variable) {
                $token = trim($variable['token'], '{} ');

                $this->assertArrayHasKey($token, $samples);
                $this->assertStringNotContainsString('email_templates.', $variable['label']);
            }
        }
    }

    /**
     * A variable with no value is left standing rather than blanked.
     *
     * "{{payment.balance_due}}" in a client's inbox is reported within the
     * hour; "Your balance is ." is never noticed and goes out for months.
     */
    public function test_an_unresolvable_variable_is_left_in_the_text(): void
    {
        $this->assertSame(
            'You owe {{payment.balance_due}}',
            EmailVariables::render('You owe {{payment.balance_due}}', ['payment.balance_due' => ''])
        );
    }

    public function test_whitespace_inside_the_braces_is_tolerated(): void
    {
        $this->assertSame(
            'Hi Sarah',
            EmailVariables::render('Hi {{ client.first_name }}', ['client.first_name' => 'Sarah'])
        );
    }

    /** A lang key that is a group, not a line, must not kill the render. */
    public function test_a_non_scalar_value_is_skipped_rather_than_thrown(): void
    {
        $this->assertSame(
            'Hi {{client.first_name}}',
            EmailVariables::render('Hi {{client.first_name}}', ['client.first_name' => ['an', 'array']])
        );
    }

    // ------------------------------------------------------------ the shell

    public function test_a_preview_renders_with_believable_stand_ins(): void
    {
        $rendered = EmailRenderer::preview(
            EmailTemplates::resolve($this->tenant, 'booking.confirmed'),
            $this->tenant
        );

        $this->assertSame('Your appointment with Serenity Spa is confirmed', $rendered['subject']);
        $this->assertStringContainsString('Sarah', $rendered['html']);
        $this->assertStringContainsString('Swedish Massage', $rendered['html']);
        $this->assertStringContainsString('Serenity Spa', $rendered['html']);

        /* Nothing unresolved reaches a client. */
        $this->assertStringNotContainsString('{{', $rendered['html']);
    }

    /** Every shipped template renders without leaking a variable or throwing. */
    public function test_every_default_template_renders_cleanly(): void
    {
        foreach (EmailTemplates::keys() as $key) {
            $rendered = EmailRenderer::preview(
                EmailTemplates::resolve($this->tenant, $key),
                $this->tenant
            );

            $this->assertStringNotContainsString('{{', $rendered['html'], "[{$key}] leaked a variable");
            $this->assertStringNotContainsString('email_templates.', $rendered['html'], "[{$key}] leaked a lang key");
            $this->assertNotSame('', trim($rendered['subject']), "[{$key}] has no subject");
        }
    }

    /** A block switched off does not render; one that is always on ignores it. */
    public function test_blocks_can_be_switched_off_except_the_ones_that_cannot(): void
    {
        $template = EmailTemplates::resolve($this->tenant, 'booking.confirmed');
        $template->blocks = ['intro' => false, 'booking_details' => false, 'heading' => false];

        $this->assertFalse($template->shows('intro'));
        $this->assertFalse($template->shows('booking_details'));

        /* An email with no heading is not a template anybody meant to write. */
        $this->assertTrue($template->shows('heading'));

        $html = EmailRenderer::preview($template, $this->tenant)['html'];

        $this->assertStringNotContainsString('Swedish Massage', $html);
        $this->assertStringContainsString('Your appointment is confirmed', $html);
    }

    public function test_a_detail_row_switched_off_is_not_shown(): void
    {
        $template = EmailTemplates::resolve($this->tenant, 'booking.confirmed');
        $template->detail_fields = ['staff' => false];

        $html = EmailRenderer::preview($template, $this->tenant)['html'];

        $this->assertStringContainsString('Swedish Massage', $html);
        $this->assertStringNotContainsString('Jennifer Smith', $html);
    }

    /**
     * The details card is two columns that stack on a phone.
     *
     * Each cell is a label above its value rather than a label opposite it:
     * paired left-and-right works at full width and falls apart at 320px,
     * where a long value wraps under a label it no longer lines up with.
     */
    public function test_the_details_card_is_two_columns_that_stack_on_mobile(): void
    {
        $html = EmailRenderer::preview(
            EmailTemplates::resolve($this->tenant, 'booking.confirmed'),
            $this->tenant
        )['html'];

        /* Two half-width cells per row. */
        $this->assertStringContainsString('class="sd-cell" width="50%"', $html);

        /* And the rule that stacks them, which Outlook ignores on purpose —
           it renders through Word and keeps the table it can draw properly. */
        $this->assertStringContainsString('.sd-cell {', $html);
        $this->assertStringContainsString('display: block !important', $html);
    }

    // --------------------------------------------------- named in the text

    private function service(string $name, int $minutes = 60, ?int $priceMinor = null): Service
    {
        $service = Service::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'name' => $name,
            'duration_minutes' => $minutes,
            'is_active' => true,
        ]);

        if ($priceMinor !== null) {
            $service->prices()->create([
                'price_minor' => $priceMinor,
                'currency_code' => 'USD',
            ]);
        }

        return $service;
    }

    /**
     * `{{service.42.name}}` names one service; `{{service.name}}` follows the
     * booking. Both resolve through the same renderer.
     */
    public function test_a_token_naming_one_service_resolves_to_that_service(): void
    {
        $service = $this->service('Deep Tissue Massage', 90, 9500);

        $template = EmailTemplates::resolve($this->tenant, 'thank_you');
        $template->intro = "Book a {{service.{$service->id}.name}} — {{service.{$service->id}.duration}}, {{service.{$service->id}.price}}.";

        $html = EmailRenderer::preview($template, $this->tenant)['html'];

        $this->assertStringContainsString('Deep Tissue Massage', $html);
        $this->assertStringContainsString('90 minutes', $html);
        /* Major units: passing minor turns a $95 service into $9,500. */
        $this->assertStringContainsString('$95.00', $html);
    }

    /** A deleted service leaves its token standing, like any other unknown. */
    public function test_a_token_naming_a_service_that_is_gone_is_left_alone(): void
    {
        $template = EmailTemplates::resolve($this->tenant, 'thank_you');
        $template->intro = 'Book a {{service.999999.name}}.';

        $this->assertStringContainsString(
            '{{service.999999.name}}',
            EmailRenderer::preview($template, $this->tenant)['html'],
        );
    }

    /** The generic token still follows the booking. */
    public function test_the_generic_service_token_is_untouched(): void
    {
        $template = EmailTemplates::resolve($this->tenant, 'thank_you');
        $template->intro = 'Your {{service.name}}.';

        $this->assertStringContainsString(
            'Your Swedish Massage',
            EmailRenderer::preview($template, $this->tenant)['html'],
        );
    }

    // ------------------------------------------------------------- coupons

    private function coupon(array $attributes = []): Promotion
    {
        return Promotion::withoutGlobalScopes()->create($attributes + [
            'tenant_id' => $this->tenant->getTenantKey(),
            'name' => 'Welcome Offer',
            'code' => 'WELCOME20',
            'type' => 'coupon',
            'discount_type' => 'percent',
            'discount_value' => 20,
            'applies_to' => 'booking',
            'eligibility' => 'all',
            'starts_on' => now()->subWeek()->toDateString(),
            'location_mode' => 'all',
            'is_draft' => false,
            'is_disabled' => false,
        ]);
    }

    public function test_a_chosen_campaign_puts_its_code_in_the_email(): void
    {
        $coupon = $this->coupon();

        $template = EmailTemplates::resolve($this->tenant, 'thank_you');
        $template->blocks = ['coupon' => true];
        $template->promotion_id = $coupon->id;
        $template->setRelation('promotion', $coupon);

        $html = EmailRenderer::preview($template, $this->tenant)['html'];

        $this->assertStringContainsString('WELCOME20', $html);
        $this->assertStringContainsString('Welcome Offer', $html);
    }

    /**
     * A campaign that has ended stops appearing on its own.
     *
     * A code copied into the wording would keep going out for months after the
     * campaign closed, and the business would have to break the promise at the
     * till. The block reads the promotion at send time instead.
     */
    public function test_an_expired_campaign_is_not_shown(): void
    {
        $coupon = $this->coupon(['ends_on' => now()->subDay()->toDateString()]);

        $template = EmailTemplates::resolve($this->tenant, 'thank_you');
        $template->blocks = ['coupon' => true];
        $template->promotion_id = $coupon->id;
        $template->setRelation('promotion', $coupon);

        $this->assertNull($template->couponToShow());
        $this->assertStringNotContainsString('WELCOME20', EmailRenderer::preview($template, $this->tenant)['html']);
    }

    public function test_a_disabled_campaign_is_not_shown(): void
    {
        $coupon = $this->coupon(['is_disabled' => true]);

        $template = EmailTemplates::resolve($this->tenant, 'thank_you');
        $template->blocks = ['coupon' => true];
        $template->promotion_id = $coupon->id;
        $template->setRelation('promotion', $coupon);

        $this->assertNull($template->couponToShow());
    }

    /** The block off means no coupon, whatever campaign is attached. */
    public function test_the_block_switched_off_hides_the_coupon(): void
    {
        $coupon = $this->coupon();

        $template = EmailTemplates::resolve($this->tenant, 'thank_you');
        $template->blocks = ['coupon' => false];
        $template->promotion_id = $coupon->id;
        $template->setRelation('promotion', $coupon);

        $this->assertNull($template->couponToShow());
    }

    /** The code is available as a variable, for wording it into a sentence. */
    public function test_the_coupon_code_resolves_as_a_variable(): void
    {
        $coupon = $this->coupon();

        $template = EmailTemplates::resolve($this->tenant, 'thank_you');
        $template->blocks = ['coupon' => true];
        $template->intro = 'Use {{coupon.code}} for {{coupon.discount}}.';
        $template->promotion_id = $coupon->id;
        $template->setRelation('promotion', $coupon);

        $html = EmailRenderer::preview($template, $this->tenant)['html'];

        $this->assertStringContainsString('Use WELCOME20 for', $html);
        $this->assertStringNotContainsString('{{coupon', $html);
    }

    /** A block added to the product later must not stay off for everybody. */
    public function test_a_block_the_business_never_saw_takes_its_default(): void
    {
        $template = EmailTemplates::resolve($this->tenant, 'booking.confirmed');
        $template->blocks = ['intro' => false];

        $this->assertTrue($template->shows('booking_details'));
        $this->assertTrue($template->shows('contact'));
    }
}
