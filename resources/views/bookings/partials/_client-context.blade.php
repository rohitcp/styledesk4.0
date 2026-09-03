{{--
    The right column below the money: what is next, and what their booking
    history says about them. Reaching them is _contact-actions, which the
    column places above the payment summary.

    The tags and insights are the booking's own snapshot rather than the
    client's current profile — see `bookings.client_snapshot`. A client who
    booked every four weeks when this appointment was taken and every eight
    now should leave this record saying the first thing.
--}}

{{-- ---------------------------------------------------- next appointment --}}
<section class="styledesk_infocard styledesk_infocard--blue">
    <h2 class="styledesk_infocard__title">{{ __('clients.module.workspace.bookings.next_appointment') }}</h2>

    @if ($nextBooking)
        <p class="text-[15px] font-bold text-head mt-2">{{ $nextBooking->date->translatedFormat('j M Y') }}</p>
        <p class="text-[13px] text-sub">{{ $nextBooking->timeLabel() }}</p>
        <p class="text-[12px] text-sub mt-1.5">
            {{ collect([
                $nextBooking->services->pluck('name')->implode(', '),
                $nextBooking->staff?->displayName(),
            ])->filter()->join(' · ') }}
        </p>

        <a href="{{ route('bookings.show', $nextBooking) }}" class="text-[12px] font-semibold underline mt-2 inline-block">
            {{ __('leads.actions.view_booking') }}
        </a>
    @else
        <p class="text-[13px] text-sub mt-2">{{ __('clients.module.workspace.bookings.none_upcoming') }}</p>

        @if ($client)
            <a href="{{ route('bookings.create') }}" class="text-[12px] font-semibold underline mt-2 inline-block">
                {{ __('bookings.new') }}
            </a>
        @endif
    @endif
</section>

{{-- ---------------------------------------------------- behavioural tags --}}
<section class="styledesk_infocard mt-5">
    <h2 class="styledesk_infocard__title">{{ __('clients.behavioral.title') }}</h2>

    @if (filled($tags))
        <div class="flex flex-wrap gap-1.5 mt-2.5">
            @foreach ($tags as $tag)
                <span class="styledesk_metachip">{{ $tag }}</span>
            @endforeach
        </div>
    @else
        <p class="text-[13px] mt-1.5 leading-relaxed">{{ __('clients.behavioral.none_yet') }}</p>
    @endif
</section>

{{-- --------------------------------------------------- client insights --}}
<section class="styledesk_infocard mt-5">
    <h2 class="styledesk_infocard__title">{{ __('clients.module.workspace.insights.title') }}</h2>

    @if (filled($insights))
        <ul class="mt-2 space-y-1.5">
            @foreach ($insights as $insight)
                <li class="flex gap-2 text-[13px] leading-relaxed">
                    <span class="styledesk_bullet" aria-hidden="true"></span>
                    <span>{{ $insight }}</span>
                </li>
            @endforeach
        </ul>

        {{-- When this was true. The profile shows what is true now; this page
             shows what was true when the appointment was taken, and the two
             will disagree as the client changes. --}}
        @if ($snapshotTakenAt)
            <p class="text-[11px] opacity-75 mt-2.5">
                {{ __('bookings.detail.snapshot', ['when' => $snapshotTakenAt->translatedFormat('j M Y')]) }}
            </p>
        @endif
    @else
        <p class="text-[13px] mt-1.5 leading-relaxed">{{ __('clients.module.workspace.insights.coming') }}</p>
    @endif
</section>
