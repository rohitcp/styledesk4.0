{{--
    Paying for an appointment, from a link.

    Its own page rather than the app layout: whoever is reading this is a
    client, not a StyleDesk user, and a page wrapped in a nav bar for an app
    they cannot sign into is a page that invites them to try.

    It takes no money. StyleDesk charges nothing anywhere, so what this shows
    is where to send it — the business's own accounts, the same ones the desk
    reads out over the phone.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    {{-- Never indexed and never followed: a page that names an appointment
         and an amount has no business in a search result. --}}
    <meta name="robots" content="noindex, nofollow">
    <title>{{ __('bookings.pay_link.title') }} — {{ $businessName }}</title>
    @vite(['resources/css/app.css'])
</head>
<body class="bg-hover text-ink text-[13px]">

<main class="min-h-screen flex items-start justify-center px-4 py-10">
    <div class="w-full max-w-[520px]">
        <p class="text-[13px] text-sub">{{ $businessName }}</p>
        <h1 class="text-[22px] font-bold text-head tracking-tight mt-1">{{ __('bookings.pay_link.title') }}</h1>

        @if ($expired && ! $settled)
            {{-- Said first and said plainly. Somebody who reads the amount
                 before the expiry is somebody who pays into a closed link. --}}
            <div class="sd-alert sd-alert--warn mt-4" role="alert">
                <p class="font-semibold">{{ __('bookings.pay_link.expired') }}</p>
                <p class="text-[12.5px] mt-1">{{ __('bookings.pay_link.expired_hint') }}</p>
            </div>
        @elseif ($settled)
            <div class="sd-alert sd-alert--success mt-4" role="status">
                <p class="font-semibold">{{ __('bookings.pay_link.settled') }}</p>
                <p class="text-[12.5px] mt-1">{{ __('bookings.pay_link.settled_hint') }}</p>
            </div>
        @endif

        <section class="mt-4 bg-white border border-line rounded-card p-5">
            <p class="text-[12px] text-sub">{{ __('bookings.pay_link.amount') }}</p>
            <p class="text-[30px] font-bold text-head leading-tight">{{ $amount }}</p>

            @if (! $settled && $due !== $amount)
                <p class="text-[12px] text-sub mt-1">
                    {{ __('bookings.pay_link.balance', ['amount' => $due]) }}
                </p>
            @endif

            <dl class="mt-4 pt-4 border-t border-line space-y-2 text-[13px]">
                <div class="flex items-baseline justify-between gap-4">
                    <dt class="text-sub">{{ __('bookings.summary.when') }}</dt>
                    <dd class="font-medium text-head text-right">
                        {{ $booking->date->translatedFormat('l j F Y') }} · {{ $booking->timeLabel() }}
                    </dd>
                </div>
                <div class="flex items-baseline justify-between gap-4">
                    <dt class="text-sub">{{ __('bookings.summary.services') }}</dt>
                    <dd class="font-medium text-head text-right">{{ $booking->services->pluck('name')->implode(', ') }}</dd>
                </div>
                @if ($booking->location)
                    <div class="flex items-baseline justify-between gap-4">
                        <dt class="text-sub">{{ __('bookings.when.location') }}</dt>
                        <dd class="font-medium text-head text-right">{{ $booking->location->name }}</dd>
                    </div>
                @endif
                <div class="flex items-baseline justify-between gap-4">
                    <dt class="text-sub">{{ __('bookings.summary.reference') }}</dt>
                    <dd class="font-mono font-medium text-head">{{ $booking->reference }}</dd>
                </div>
            </dl>
        </section>

        @if (! $settled && ! $expired)
            <section class="mt-4 bg-white border border-line rounded-card p-5">
                <h2 class="text-[14px] font-semibold text-head">{{ __('bookings.pay_link.how') }}</h2>

                @if (count($handles))
                    <ul class="mt-3 space-y-2">
                        @foreach ($handles as $row)
                            <li class="border border-line rounded-lg px-3.5 py-2.5">
                                <p class="text-[11px] uppercase tracking-wide text-faint">{{ $row['name'] }}</p>
                                <p class="text-[15px] font-semibold text-head mt-0.5 break-all">{{ $row['handle'] }}</p>
                            </li>
                        @endforeach
                    </ul>

                    <p class="text-[12px] text-sub mt-3">
                        {{ __('bookings.pay_link.reference_hint', ['reference' => $booking->reference]) }}
                    </p>
                @else
                    {{-- No account set up to send it to. Honest rather than
                         blank: the client is told to ring, which is the only
                         thing that actually works from here. --}}
                    <p class="text-[13px] text-sub mt-2">{{ __('bookings.pay_link.no_handles') }}</p>
                @endif
            </section>
        @endif

        <p class="text-[12px] text-faint mt-4 text-center">{{ __('bookings.pay_link.footer') }}</p>
    </div>
</main>

</body>
</html>
