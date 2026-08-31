{{--
    The right column: reach them, see what is next, and read what their
    booking history says about them.

    The tags and insights are the booking's own snapshot rather than the
    client's current profile — see `bookings.client_snapshot`. A client who
    booked every four weeks when this appointment was taken and every eight
    now should leave this record saying the first thing.
--}}

{{-- ---------------------------------------------------- contact actions --}}
<section>
    <h2 class="styledesk_heading">{{ __('clients.module.workspace.contact.title') }}</h2>

    <div class="grid grid-cols-3 gap-2 mt-2.5">
        {{-- The same three the client profile offers, in the same order and
             the same shape. Disabled rather than hidden where the detail is
             missing: "no email on file" is worth knowing, and a row that
             changes shape per client is a row nobody learns. --}}
        @foreach ([
            ['scheme' => 'mailto:', 'value' => $email, 'icon' => 'envelope', 'label' => __('clients.module.workspace.contact.email_short'), 'tip' => __('clients.module.workspace.contact.send_email'), 'none' => __('clients.module.workspace.contact.no_email')],
            ['scheme' => 'sms:', 'value' => $phone, 'icon' => 'comment-sms', 'label' => __('clients.module.workspace.contact.sms_short'), 'tip' => __('clients.module.workspace.contact.send_sms'), 'none' => __('clients.module.workspace.contact.no_phone')],
            ['scheme' => 'tel:', 'value' => $phone, 'icon' => null, 'label' => __('clients.module.workspace.contact.call'), 'tip' => __('clients.module.workspace.contact.call'), 'none' => __('clients.module.workspace.contact.no_phone')],
        ] as $action)
            @if ($action['value'])
                <a href="{{ $action['scheme'].$action['value'] }}" class="styledesk_action styledesk_action--sm justify-center"
                   data-tip="{{ $action['tip'] }}">
                    @if ($action['icon'])<x-icon :name="$action['icon']" size="13" />@endif
                    <span class="truncate">{{ $action['label'] }}</span>
                </a>
            @else
                <span class="styledesk_action styledesk_action--sm justify-center opacity-50 cursor-not-allowed"
                      aria-disabled="true" data-tip="{{ $action['none'] }}">
                    @if ($action['icon'])<x-icon :name="$action['icon']" size="13" />@endif
                    <span class="truncate">{{ $action['label'] }}</span>
                </span>
            @endif
        @endforeach
    </div>
</section>

{{-- ---------------------------------------------------- next appointment --}}
<section class="styledesk_infocard styledesk_infocard--blue mt-5">
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
