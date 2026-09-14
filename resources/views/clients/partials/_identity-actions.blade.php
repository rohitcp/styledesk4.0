{{--
    The client profile's own header actions: back to the list, take a booking,
    and everything else behind the menu.

    Lifted out of the page so the header itself can be shared. What sits here
    is the one part of that header that differs between the screens showing
    it — the Loyalty module's client page has a different list to go back to
    and a different primary thing to do.
--}}
        {{-- Labelled where there is room, a bare arrow where there is not.
             The accessible name says where it goes either way — "Back" alone
             is what the eye needs beside an arrow, not what a screen reader
             needs read out on its own. --}}
        <a href="{{ route('clients.index') }}" data-tip="{{ __('clients.module.workspace.back') }}"
           aria-label="{{ __('clients.module.workspace.back') }}"
           class="styledesk_action styledesk_action--shrinklabel shrink-0">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M14 6l-6 6 6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
          <span class="styledesk_action__label">{{ __('common.back') }}</span>
        </a>

        {{-- The primary action. Only the id goes in the URL: the booking
             screen reads the client back from it, so a profile left open in
             a tab since Tuesday cannot carry Tuesday's phone number into
             today's booking. --}}
        @if ($canBook)
          <a href="{{ route('bookings.create', ['client' => $client->id]) }}"
             class="h-9 px-4 rounded-lg bg-brand hover:bg-brand-dark text-white text-[13px] font-semibold
                    inline-flex items-center min-w-0 truncate transition-colors">
            {{ __('clients.module.workspace.quick.create_booking') }}
          </a>
        @else
          {{-- A reader who may not take an appointment is told so, rather
               than given a button that refuses them afterwards. --}}
          <span class="h-9 px-4 rounded-lg bg-brand text-white text-[13px] font-semibold opacity-50
                       cursor-not-allowed inline-flex items-center min-w-0 truncate"
                aria-disabled="true">
            {{ __('clients.module.workspace.quick.create_booking') }}
          </span>
        @endif

