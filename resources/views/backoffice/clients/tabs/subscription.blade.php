{{--
    Billing, when there is billing.

    The plan and trial dates this client already has are on the Overview,
    because they are columns on the row. Everything a subscription tab would
    add — a renewal date, a payment method, an invoice history, an upgrade —
    needs the billing module, which is its own phase.
--}}
<x-backoffice.coming-soon
    :title="__('backoffice.subscription.title')"
    :intro="__('backoffice.subscription.intro')"
    :items="[
        __('backoffice.subscription.items.plan'),
        __('backoffice.subscription.items.cycle'),
        __('backoffice.subscription.items.status'),
        __('backoffice.subscription.items.renewal'),
        __('backoffice.subscription.items.limits'),
        __('backoffice.subscription.items.method'),
        __('backoffice.subscription.items.history'),
        __('backoffice.subscription.items.change'),
    ]" />
