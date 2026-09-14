{{--
    The Loyalty page's header actions.

    The same row the profile fills, answering the two questions differently:
    "back" means back to the member list somebody came from, and the thing
    they most likely want next is the rest of this client's record rather than
    a booking — the booking screen is a click away from the profile, and this
    page is open because the rewards are the job.
--}}
<a href="{{ route('clients.loyalty') }}" data-tip="{{ __('loyalty.members.back') }}"
   aria-label="{{ __('loyalty.members.back') }}"
   class="styledesk_action styledesk_action--shrinklabel shrink-0">
  <svg width="15" height="15" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M14 6l-6 6 6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
  <span class="styledesk_action__label">{{ __('common.back') }}</span>
</a>

{{-- Between the two, because that is the order the row reads in: where you
     came from, the thing this page is for, and where you would go next.

     It opens the same dialog the rewards body does — the button is only ever
     in one of the two places, so there is one control rather than two that
     could disagree about whether the reader may use it. --}}
@if ($canAdjustPoints && $loyalty->is_enabled)
  <button type="button" class="styledesk_action shrink-0" data-rewards-open>
    <x-icon name="sliders" size="14" />
    {{ __('loyalty.client.adjust') }}
  </button>
@endif

<a href="{{ route('clients.show', $client) }}"
   class="h-9 px-4 rounded-lg bg-brand hover:bg-brand-dark text-white text-[13px] font-semibold
          inline-flex items-center min-w-0 truncate transition-colors">
  {{ __('loyalty.members.open_profile') }}
</a>
