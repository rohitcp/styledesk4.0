{{--
    Everything that has been done to this booking.

    In the words the reasons had on the day, not the words they have now: a
    business that renames "Client Did Not Arrive" next spring has renamed
    their list, not last March's no-show. Nothing here can be edited or
    removed — an audit trail somebody can rewrite is not one.
--}}
<section>
    <h2 class="styledesk_heading">{{ __('bookings.activity.title') }}</h2>

    @if ($history->isEmpty())
        <p class="text-[13px] text-sub mt-2">{{ __('bookings.activity.none') }}</p>
    @else
        <ol class="mt-3">
            @foreach ($history as $entry)
                <li class="flex items-start gap-3 py-3 border-b border-line">
                    <span class="styledesk_settingcard__icon shrink-0 !h-8 !w-8" aria-hidden="true">
                        <x-icon :name="$entry->movedTheAppointment() ? 'calendar-check' : 'clipboard-list'" size="14" />
                    </span>

                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-baseline gap-x-2">
                            <p class="text-[13px] font-semibold text-head">
                                {{ $entry->movedTheAppointment()
                                    ? __('bookings.activity.events.confirmed')
                                    : $entry->title() }}
                            </p>
                            <time datetime="{{ $entry->created_at?->toIso8601String() }}" class="text-[12px] text-sub">
                                {{ $entry->created_at?->isoFormat('D MMM Y · h:mm A') }}
                            </time>
                        </div>

                        {{-- Who did it — and StyleDesk itself where nobody
                             did, because an automated change and somebody
                             pressing a button are different facts and a blank
                             would look like missing data. --}}
                        <p class="text-[12px] mt-0.5 {{ $entry->changed_by === null ? 'text-faint italic' : 'text-sub' }}">
                            {{ __('bookings.activity.by', ['name' => $entry->actor()]) }}
                        </p>

                        {{-- Both slots, because "moved" without the previous
                             one answers half the question — and the half it
                             drops is the one somebody is usually looking for. --}}
                        @if ($entry->movedTheAppointment())
                            <dl class="mt-1.5 text-[12px] space-y-0.5">
                                <div class="flex gap-2">
                                    <dt class="text-faint shrink-0">{{ __('bookings.activity.previous') }}</dt>
                                    <dd class="text-sub line-through">{{ $entry->slotLabel('from') }}</dd>
                                </div>
                                <div class="flex gap-2">
                                    <dt class="text-faint shrink-0">{{ __('bookings.activity.new') }}</dt>
                                    <dd class="text-head font-medium">{{ $entry->slotLabel('to') }}</dd>
                                </div>
                            </dl>
                        @endif

                        @if (filled($entry->reason_label))
                            <p class="text-[12px] text-sub mt-1">
                                <span class="text-faint">{{ __('bookings.activity.reason') }}:</span>
                                {{ collect([$entry->reason_label, $entry->details])->filter()->join(' — ') }}
                            </p>
                        @endif

                        @if (filled($entry->note))
                            <p class="text-[12px] text-sub mt-1 whitespace-pre-line">
                                <span class="text-faint">{{ __('bookings.activity.note') }}:</span>
                                {{ $entry->note }}
                            </p>
                        @endif
                    </div>
                </li>
            @endforeach
        </ol>
    @endif
</section>
