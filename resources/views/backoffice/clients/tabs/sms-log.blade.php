{{--
    SMS, when there is SMS.

    Nothing in the product sends one yet, so there is nothing to list. An
    honest "not yet" rather than an empty table, which would read as "this
    business has never texted anybody" — a different and untrue answer.
--}}
<x-backoffice.coming-soon
    :title="__('backoffice.sms.title')"
    :intro="__('backoffice.sms.intro')"
    :items="[
        __('backoffice.sms.items.history'),
        __('backoffice.sms.items.delivery'),
        __('backoffice.sms.items.activity'),
        __('backoffice.sms.items.spend'),
    ]" />
