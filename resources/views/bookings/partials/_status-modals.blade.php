{{--
    The dialogues behind the header's actions.

    Three of the four are the same dialogue — a reason, an optional note, and
    the explanation a reason can ask for — so they are one partial rendered
    three times rather than three that drift apart. The fourth moves the
    appointment and needs a day and a time, so it has its own.

    Rendered only for the acts on offer: a reader without the permission is
    not sent the form for it, and the header has no button that would open it.
--}}

@foreach ($actions as $action)
    {{-- The two that ask something other than a reason and a note. --}}
    @continue(in_array($action, ['reschedule', 'check-in'], true))

    @php
        $danger = config('bookings.status_actions.'.$action.'.tone') === 'danger';
    @endphp

    <div id="statusModal-{{ $action }}" class="styledesk_modal" hidden data-status-modal="{{ $action }}">
        <div class="styledesk_modal__scrim" data-status-close></div>

        <div class="styledesk_modal__panel" role="dialog" aria-modal="true" aria-labelledby="statusModalTitle-{{ $action }}">
            <div class="styledesk_modal__head">
                <h2 id="statusModalTitle-{{ $action }}" class="text-[15px] font-semibold text-head">
                    {{ __('bookings.status.'.$action.'.title') }}
                </h2>

                <button type="button" class="styledesk_modal__close" data-status-close aria-label="{{ __('common.close') }}">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                </button>
            </div>

            <form method="POST" action="{{ route(config('bookings.status_actions.'.$action.'.route'), $booking) }}">
                @csrf

                <div class="styledesk_modal__body space-y-4">
                    <p class="text-[13px] text-sub leading-relaxed">{{ __('bookings.status.'.$action.'.intro') }}</p>

                    <div>
                        <label for="reason-{{ $action }}" class="block text-[13px] font-medium text-ink mb-1.5">
                            {{ __('bookings.status.'.$action.'.reason') }} <span class="text-danger" aria-hidden="true">*</span>
                        </label>

                        {{-- The business's own list, in the order they put it
                             in and without the ones they switched off. --}}
                        <select id="reason-{{ $action }}" name="reason_code_id" class="sd-input" required data-status-reason>
                            <option value="">{{ __('bookings.status.choose_reason') }}</option>
                            @foreach ($reasons[$action] as $reason)
                                <option value="{{ $reason->id }}" data-requires-details="{{ $reason->requires_details ? '1' : '0' }}">
                                    {{ $reason->name }}
                                </option>
                            @endforeach
                        </select>

                        <p data-error-for="reason_code_id" role="alert" class="mt-1.5 text-[12px] text-danger"
                           @unless ($errors->has('reason_code_id')) hidden @endunless>{{ $errors->first('reason_code_id') }}</p>
                    </div>

                    {{-- Revealed by the reason that asks for it, and required
                         only then. Which reason that is belongs to the
                         business, not to this template — "Other" is the
                         obvious one and they can ask the same of any. --}}
                    <div data-status-details-field hidden>
                        <label for="details-{{ $action }}" class="block text-[13px] font-medium text-ink mb-1.5">
                            {{ __('bookings.status.details') }} <span class="text-danger" aria-hidden="true">*</span>
                        </label>
                        <textarea id="details-{{ $action }}" name="details" rows="2" class="sd-input !h-auto py-2.5"
                                  maxlength="2000" data-status-details></textarea>
                        <p class="text-[12px] text-faint mt-1.5">{{ __('bookings.status.details_hint') }}</p>
                        <p data-error-for="details" role="alert" class="mt-1.5 text-[12px] text-danger"
                           @unless ($errors->has('details')) hidden @endunless>{{ $errors->first('details') }}</p>
                    </div>

                    <div>
                        <label for="note-{{ $action }}" class="block text-[13px] font-medium text-ink mb-1.5">
                            {{ __('bookings.status.note') }}
                            <span class="text-faint font-normal">{{ __('common.optional') }}</span>
                        </label>
                        <textarea id="note-{{ $action }}" name="note" rows="3" class="sd-input !h-auto py-2.5"
                                  maxlength="2000"></textarea>
                        <p class="text-[12px] text-faint mt-1.5">{{ __('bookings.status.note_hint') }}</p>
                    </div>
                </div>

                <div class="styledesk_modalfoot">
                    <button type="submit"
                            class="h-9 px-4 rounded-lg text-white text-[13px] font-semibold transition-colors
                                   {{ $danger ? 'bg-danger hover:brightness-95' : 'bg-brand hover:bg-brand-dark' }}">
                        {{ __('bookings.status.'.$action.'.confirm') }}
                    </button>

                    {{-- "Keep booking" where the dialogue is about cancelling:
                         a Cancel button in a Cancel booking dialog is the one
                         nobody can read twice the same way. --}}
                    <button type="button" class="styledesk_action" data-status-close>
                        {{ __(Lang::has('bookings.status.'.$action.'.dismiss') ? 'bookings.status.'.$action.'.dismiss' : 'bookings.status.dismiss') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
@endforeach

@if (in_array('reschedule', $actions, true))
    {{--
        Moving the appointment.

        The same booking rather than a new one: same reference, same client,
        same bill. A reschedule that cancelled one booking and wrote another
        would leave the desk chasing a deposit against a reference the client
        was never given.

        Everything already decided is carried in and left alone. The reader
        should have to answer only what is actually changing.
    --}}
    <div id="statusModal-reschedule" class="styledesk_modal" hidden data-status-modal="reschedule">
        <div class="styledesk_modal__scrim" data-status-close></div>

        <div class="styledesk_modal__panel" role="dialog" aria-modal="true" aria-labelledby="statusModalTitle-reschedule">
            <div class="styledesk_modal__head">
                <h2 id="statusModalTitle-reschedule" class="text-[15px] font-semibold text-head">
                    {{ __('bookings.status.reschedule.title') }}
                </h2>

                <button type="button" class="styledesk_modal__close" data-status-close aria-label="{{ __('common.close') }}">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                </button>
            </div>

            <form method="POST" action="{{ route('bookings.reschedule', $booking) }}"
                  data-reschedule-form
                  data-slots-url="{{ route('bookings.slots', $booking) }}"
                  data-loading-text="{{ __('bookings.status.reschedule.loading') }}"
                  data-empty-text="{{ __('bookings.status.reschedule.no_slots') }}"
                  data-prompt-text="{{ __('bookings.status.reschedule.pick_date') }}"
                  data-current-time="{{ $booking->startsAt() }}">
                @csrf

                <div class="styledesk_modal__body space-y-4">
                    <p class="text-[13px] text-sub leading-relaxed">{{ __('bookings.status.reschedule.intro') }}</p>

                    {{-- Where the appointment stands now, so the reader can
                         see what they are moving without closing the dialog. --}}
                    <div class="sd-card p-3">
                        <p class="text-[12px] font-semibold uppercase tracking-wide text-faint">{{ __('bookings.status.reschedule.current') }}</p>
                        <p class="text-[13px] text-head mt-1">
                            {{ $booking->date->isoFormat('D MMM Y') }} · {{ $booking->timeLabel() }}
                            @if ($booking->staff) · {{ $booking->staff->displayName() }} @endif
                        </p>
                    </div>

                    <div>
                        <label for="reason-reschedule" class="block text-[13px] font-medium text-ink mb-1.5">
                            {{ __('bookings.status.reschedule.reason') }} <span class="text-danger" aria-hidden="true">*</span>
                        </label>
                        <select id="reason-reschedule" name="reason_code_id" class="sd-input" required data-status-reason>
                            <option value="">{{ __('bookings.status.choose_reason') }}</option>
                            @foreach ($reasons['reschedule'] as $reason)
                                <option value="{{ $reason->id }}" data-requires-details="{{ $reason->requires_details ? '1' : '0' }}">
                                    {{ $reason->name }}
                                </option>
                            @endforeach
                        </select>
                        <p data-error-for="reason_code_id" role="alert" class="mt-1.5 text-[12px] text-danger"
                           @unless ($errors->has('reason_code_id')) hidden @endunless>{{ $errors->first('reason_code_id') }}</p>
                    </div>

                    <div data-status-details-field hidden>
                        <label for="details-reschedule" class="block text-[13px] font-medium text-ink mb-1.5">
                            {{ __('bookings.status.details') }} <span class="text-danger" aria-hidden="true">*</span>
                        </label>
                        <textarea id="details-reschedule" name="details" rows="2" class="sd-input !h-auto py-2.5"
                                  maxlength="2000" data-status-details></textarea>
                        <p class="text-[12px] text-faint mt-1.5">{{ __('bookings.status.details_hint') }}</p>
                    </div>

                    @if ($staffOptions->isNotEmpty())
                        <div class="grid sm:grid-cols-2 gap-4">
                            <div>
                                <label for="reschedule-staff" class="block text-[13px] font-medium text-ink mb-1.5">
                                    {{ __('bookings.status.reschedule.staff') }}
                                </label>
                                <select id="reschedule-staff" name="staff_id" class="sd-input" data-reschedule-staff>
                                    <option value="">{{ __('bookings.any_staff') }}</option>
                                    @foreach ($staffOptions as $member)
                                        <option value="{{ $member->id }}" @selected($booking->staff_id === $member->id)>{{ $member->displayName() }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <label for="reschedule-location" class="block text-[13px] font-medium text-ink mb-1.5">
                                    {{ __('bookings.status.reschedule.location') }}
                                </label>
                                <select id="reschedule-location" name="location_id" class="sd-input" data-reschedule-location>
                                    @foreach ($locationOptions as $location)
                                        <option value="{{ $location->id }}" @selected($booking->location_id === $location->id)>{{ $location->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    @endif

                    <div class="grid sm:grid-cols-2 gap-4">
                        <div>
                            <label for="reschedule-date" class="block text-[13px] font-medium text-ink mb-1.5">
                                {{ __('bookings.status.reschedule.new_date') }} <span class="text-danger" aria-hidden="true">*</span>
                            </label>
                            <input id="reschedule-date" name="date" type="date" class="sd-input" required
                                   min="{{ now()->toDateString() }}" value="{{ $booking->date->toDateString() }}"
                                   data-reschedule-date>
                            <p data-error-for="date" role="alert" class="mt-1.5 text-[12px] text-danger"
                               @unless ($errors->has('date')) hidden @endunless>{{ $errors->first('date') }}</p>
                        </div>

                        <div>
                            <label for="reschedule-time" class="block text-[13px] font-medium text-ink mb-1.5">
                                {{ __('bookings.status.reschedule.new_time') }} <span class="text-danger" aria-hidden="true">*</span>
                            </label>
                            {{-- Filled from what is actually free on the day
                                 chosen, which is why it is a list rather than
                                 a clock: a time nobody can work is not an
                                 option, it is a rejection waiting to happen. --}}
                            <select id="reschedule-time" name="starts_at" class="sd-input" required data-reschedule-time>
                                <option value="">{{ __('bookings.status.reschedule.pick_date') }}</option>
                            </select>
                            <p class="mt-1.5 text-[12px] text-faint" data-reschedule-hint hidden></p>
                            <p data-error-for="starts_at" role="alert" class="mt-1.5 text-[12px] text-danger"
                               @unless ($errors->has('starts_at')) hidden @endunless>{{ $errors->first('starts_at') }}</p>
                        </div>
                    </div>

                    <div>
                        <label for="note-reschedule" class="block text-[13px] font-medium text-ink mb-1.5">
                            {{ __('bookings.status.note') }}
                            <span class="text-faint font-normal">{{ __('common.optional') }}</span>
                        </label>
                        <textarea id="note-reschedule" name="note" rows="3" class="sd-input !h-auto py-2.5" maxlength="2000"></textarea>
                        <p class="text-[12px] text-faint mt-1.5">{{ __('bookings.status.note_hint') }}</p>
                    </div>
                </div>

                <div class="styledesk_modalfoot">
                    <button type="submit"
                            class="h-9 px-4 rounded-lg bg-brand hover:bg-brand-dark text-white text-[13px] font-semibold transition-colors">
                        {{ __('bookings.status.reschedule.confirm') }}
                    </button>

                    <button type="button" class="styledesk_action" data-status-close>{{ __('bookings.status.dismiss') }}</button>
                </div>
            </form>
        </div>
    </div>
@endif

@if (in_array('check-in', $actions, true))
    {{--
        The client is here.

        No reason asked for: arriving for an appointment does not need to be
        explained, and a required dropdown at the front desk while somebody
        stands at it waiting is the wrong shape entirely.

        What it does show is the appointment itself, because the question the
        receptionist is actually answering is "is this the right booking" —
        and they are usually looking at a queue, not at one name.
    --}}
    <div id="statusModal-check-in" class="styledesk_modal" hidden data-status-modal="check-in">
        <div class="styledesk_modal__scrim" data-status-close></div>

        <div class="styledesk_modal__panel" role="dialog" aria-modal="true" aria-labelledby="statusModalTitle-check-in">
            <div class="styledesk_modal__head">
                <h2 id="statusModalTitle-check-in" class="text-[15px] font-semibold text-head">
                    {{ __('bookings.status.check-in.title') }}
                </h2>

                <button type="button" class="styledesk_modal__close" data-status-close aria-label="{{ __('common.close') }}">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                </button>
            </div>

            <form method="POST" action="{{ route('bookings.check-in', $booking) }}">
                @csrf

                <div class="styledesk_modal__body space-y-4">
                    <p class="text-[13px] text-sub leading-relaxed">{{ __('bookings.status.check-in.intro') }}</p>

                    <dl class="sd-card p-3 grid sm:grid-cols-2 gap-x-6 gap-y-2.5">
                        <div>
                            <dt class="text-[12px] text-faint">{{ __('bookings.summary.client') }}</dt>
                            <dd class="text-[13px] text-head font-medium">{{ $booking->clientName() }}</dd>
                        </div>
                        <div>
                            <dt class="text-[12px] text-faint">{{ __('bookings.summary.reference') }}</dt>
                            <dd class="text-[13px] text-head font-mono">{{ $booking->reference }}</dd>
                        </div>
                        <div>
                            <dt class="text-[12px] text-faint">{{ __('bookings.summary.services') }}</dt>
                            <dd class="text-[13px] text-head">{{ $booking->services->pluck('name')->join(', ') }}</dd>
                        </div>
                        <div>
                            <dt class="text-[12px] text-faint">{{ __('bookings.status.reschedule.staff') }}</dt>
                            <dd class="text-[13px] text-head">{{ $booking->staff?->displayName() ?? __('bookings.any_staff') }}</dd>
                        </div>
                        <div>
                            <dt class="text-[12px] text-faint">{{ __('bookings.summary.date') }}</dt>
                            <dd class="text-[13px] text-head">{{ $booking->date->isoFormat('D MMM Y') }}</dd>
                        </div>
                        <div>
                            <dt class="text-[12px] text-faint">{{ __('bookings.summary.starts') }}</dt>
                            <dd class="text-[13px] text-head">{{ $booking->timeLabel() }}</dd>
                        </div>
                        @if ($booking->location)
                            <div class="sm:col-span-2">
                                <dt class="text-[12px] text-faint">{{ __('bookings.status.reschedule.location') }}</dt>
                                <dd class="text-[13px] text-head">{{ $booking->location->name }}</dd>
                            </div>
                        @endif
                    </dl>

                    <div>
                        <label for="note-check-in" class="block text-[13px] font-medium text-ink mb-1.5">
                            {{ __('bookings.status.check-in.note') }}
                            <span class="text-faint font-normal">{{ __('common.optional') }}</span>
                        </label>
                        <textarea id="note-check-in" name="note" rows="3" class="sd-input !h-auto py-2.5" maxlength="2000"></textarea>
                        <p class="text-[12px] text-faint mt-1.5">{{ __('bookings.status.check-in.note_hint') }}</p>
                    </div>
                </div>

                <div class="styledesk_modalfoot">
                    <button type="submit"
                            class="h-9 px-4 rounded-lg bg-brand hover:bg-brand-dark text-white text-[13px] font-semibold transition-colors">
                        {{ __('bookings.status.check-in.confirm') }}
                    </button>

                    <button type="button" class="styledesk_action" data-status-close>{{ __('bookings.status.dismiss') }}</button>
                </div>
            </form>
        </div>
    </div>
@endif
