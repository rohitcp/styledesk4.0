{{--
    Any StyleDesk email, assembled from a template and a set of values.

    One view for every kind — booking, payment, form, gift card, and a message
    a receptionist typed. The template decides which blocks appear and what
    they say; this decides nothing except where they go, which is what makes
    the look consistent across all of them.
--}}
@php
    $blocks = $template->blocks();
@endphp

<x-mail.standard :brand="$brand"
                 :heading="$heading"
                 :preheader="$preheader"
                 :cta-label="$ctaLabel"
                 :cta-url="$ctaUrl"
                 :contact-lines="$template->shows('contact') ? $contactLines : []">

    @if ($template->shows('intro') && filled($intro))
        {{-- Line breaks preserved, markup not. The editor is prose, and a
             pasted fragment of HTML must not be able to rewrite the email. --}}
        <div style="margin:0; white-space:pre-wrap;">{{ $intro }}</div>
    @endif

    @if ($template->shows('booking_details'))
        <x-mail.detail-card :title="__('email_templates.cards.booking')" :rows="$bookingRows" />
    @endif

    @if ($template->shows('payment_summary'))
        <x-mail.detail-card :title="__('email_templates.cards.payment')"
                            :rows="$paymentRows"
                            :strong-rows="$paymentTotals" />
    @endif

    {{-- Between the details and the button: the offer is a reason to press
         the button, so it belongs above it. --}}
    @if ($coupon)
        <x-mail.coupon :coupon="$coupon" :currency="$currency" />
    @endif

    @if ($template->shows('supporting_message') && filled($supporting))
        <x-slot:supporting>
            <div style="margin:0; white-space:pre-wrap;">{{ $supporting }}</div>
        </x-slot:supporting>
    @endif
</x-mail.standard>
