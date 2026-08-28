{{--
    The right column, in the order the work happens: reach them, see what is
    booked, read what matters, then what their behaviour says about them.

    Sections are divided by rules rather than boxed, and each is only shown
    when it has something true to say — except the appointment, which says
    "none" because that is itself the answer a receptionist is looking for.
--}}

{{-- ---------------------------------------------------- contact actions --}}
<section>
    <h2 class="styledesk_heading">{{ __('clients.module.workspace.contact.title') }}</h2>

    {{-- Three buttons on one row, each disabled when the detail it needs is
         missing. Disabled rather than hidden: "no email on file" is worth
         knowing, and a row that changes shape per client is a row nobody
         learns. --}}
    <div class="grid grid-cols-3 gap-2 mt-2.5">
        @php
            $email = $canViewContact ? $primaryEmail?->email : null;
            $phone = $canViewContact ? $primaryPhone?->number : null;
        @endphp

        @if ($email)
            <a href="mailto:{{ $email }}" class="styledesk_action styledesk_action--sm justify-center"
               data-tip="{{ __('clients.module.workspace.contact.send_email') }}">
                <x-icon name="envelope" size="13" />
                <span class="truncate">{{ __('clients.module.workspace.contact.email_short') }}</span>
            </a>
        @else
            <span class="styledesk_action styledesk_action--sm justify-center opacity-50 cursor-not-allowed"
                  aria-disabled="true" data-tip="{{ __('clients.module.workspace.contact.no_email') }}">
                <x-icon name="envelope" size="13" />
                <span class="truncate">{{ __('clients.module.workspace.contact.email_short') }}</span>
            </span>
        @endif

        @if ($phone)
            <a href="sms:{{ $phone }}" class="styledesk_action styledesk_action--sm justify-center"
               data-tip="{{ __('clients.module.workspace.contact.send_sms') }}">
                <x-icon name="comment-sms" size="13" />
                <span class="truncate">{{ __('clients.module.workspace.contact.sms_short') }}</span>
            </a>

            <a href="tel:{{ $phone }}" class="styledesk_action styledesk_action--sm justify-center"
               data-tip="{{ __('clients.module.workspace.contact.call') }}">
                <span class="truncate">{{ __('clients.module.workspace.contact.call') }}</span>
            </a>
        @else
            <span class="styledesk_action styledesk_action--sm justify-center opacity-50 cursor-not-allowed"
                  aria-disabled="true" data-tip="{{ __('clients.module.workspace.contact.no_phone') }}">
                <x-icon name="comment-sms" size="13" />
                <span class="truncate">{{ __('clients.module.workspace.contact.sms_short') }}</span>
            </span>

            <span class="styledesk_action styledesk_action--sm justify-center opacity-50 cursor-not-allowed"
                  aria-disabled="true" data-tip="{{ __('clients.module.workspace.contact.no_phone') }}">
                <span class="truncate">{{ __('clients.module.workspace.contact.call') }}</span>
            </span>
        @endif
    </div>
</section>

{{-- ---------------------------------------------------- next appointment --}}
<section class="styledesk_infocard styledesk_infocard--blue mt-5">
    <h2 class="styledesk_infocard__title">{{ __('clients.module.workspace.bookings.next_appointment') }}</h2>

    @if ($client->next_booking_at)
        <p class="text-[15px] font-bold text-head mt-2">{{ $client->next_booking_at->isoFormat('D MMM Y') }}</p>
        <p class="text-[13px] text-sub">{{ $client->next_booking_at->isoFormat('h:mm A') }}</p>

        @if ($client->preferredStaff || $client->preferredLocation)
            <p class="text-[12px] text-sub mt-1.5">
                {{ collect([$client->preferredStaff?->displayName(), $client->preferredLocation?->name])->filter()->join(' · ') }}
            </p>
        @endif

        {{-- Show more belongs here once a client can hold several upcoming
             appointments. One booking cannot be a list, so the control is not
             drawn for it. --}}
    @else
        {{-- The sentence and nothing else. A disabled Create booking here
             offered a way to fix the emptiness that does not exist yet, and
             the header already carries the same button. --}}
        <p class="text-[13px] text-sub mt-2">{{ __('clients.module.workspace.bookings.none_upcoming') }}</p>
    @endif
</section>

{{-- ------------------------------------------------------- private note --}}
@if ($canViewNotes && ($importantNotes->isNotEmpty() || filled($client->notes)))
    @php
        /**
         * Read as text, not as markup.
         *
         * A note written in the editor is HTML, and this card shows a
         * hundred characters of it beside a Show more — cutting markup at a
         * hundred characters produces an unclosed tag, and printing it
         * escaped shows the reader their own <p> tags. The plain reading is
         * what a preview wants; the Notes tab is where the formatting lives.
         */
        $note = $importantNotes->first();
        $noteBody = $note ? \App\Support\NoteHtml::toText($note->format === 'html' ? $note->body : e($note->body)) : $client->notes;
        $long = mb_strlen($noteBody) > 100;
    @endphp

    <section class="styledesk_infocard styledesk_infocard--amber mt-5" data-disclosure>
        <div class="flex items-center gap-2">
            <h2 class="styledesk_infocard__title flex-1 min-w-0">{{ __('clients.module.workspace.important.title') }}</h2>

            @if ($canAddNotes)
                <button type="button" class="styledesk_cardbtn sd-tip shrink-0"
                        data-open-tab="notes" data-focus="#noteBody"
                        data-tip="{{ __('clients.module.workspace.notes.add') }}"
                        aria-label="{{ __('clients.module.workspace.notes.add') }}">
                    <x-icon name="plus" size="14" />
                </button>
            @endif
        </div>

        {{-- The text sits flush against its tags on purpose. These carry
             `white-space: pre-line` so a note's own line breaks survive — and
             that keeps the newline after the opening tag too, which drew a
             blank first line above every note. --}}
        <p class="text-[13px] mt-1 leading-relaxed whitespace-pre-line break-words" data-disclosure-short @if (! $long) hidden @endif>{{ Str::limit($noteBody, 100) }}</p>

        <p class="text-[13px] mt-1 leading-relaxed whitespace-pre-line break-words" data-disclosure-full @if ($long) hidden @endif>{{ $noteBody }}</p>

        @if ($long)
            <button type="button" class="text-[12px] font-semibold underline mt-1.5" data-disclosure-toggle
                    data-more="{{ __('common.show_more') }}" data-less="{{ __('common.show_less') }}">
                {{ __('common.show_more') }}
            </button>
        @endif

        @if ($importantNotes->isNotEmpty())
            <p class="text-[11px] opacity-75 mt-1.5">
                {{ $importantNotes->first()->authorName() }} · {{ $importantNotes->first()->created_at->isoFormat('D MMM Y') }}
            </p>
        @endif
    </section>
@endif

@include('clients.partials._behavioral')

{{-- --------------------------------------------------- client insights --}}
<section class="styledesk_infocard styledesk_infocard--teal mt-5">
    <h2 class="styledesk_infocard__title">{{ __('clients.module.workspace.insights.title') }}</h2>
    <p class="text-[13px] mt-1.5 leading-relaxed">{{ __('clients.module.workspace.insights.coming') }}</p>
</section>
