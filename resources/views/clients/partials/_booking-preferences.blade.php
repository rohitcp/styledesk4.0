{{-- How this client likes to be booked.

     Two kinds of line, told apart on purpose: what somebody said, which can
     be removed, and what the diary noticed, which cannot — it is worked out
     from the bookings every time this page is drawn, so it can never go
     stale and is never presented as something the client asked for. --}}
<h2 class="styledesk_heading">{{ __('clients.module.booking_preferences.title') }}</h2>

@php $derived = collect($bookingContext['preferences'] ?? [])->where('source', 'system'); @endphp

@if ($client->bookingPreferences->isEmpty() && $derived->isEmpty())
    <p class="text-[12px] text-faint mt-2">{{ __('clients.module.booking_preferences.none') }}</p>
@else
    <ul class="mt-2.5 space-y-1.5">
        @foreach ($client->bookingPreferences as $preference)
            <li class="group flex items-start gap-2 text-[13px] text-ink">
                <span class="styledesk_bullet mt-2" aria-hidden="true"></span>
                <span class="min-w-0 flex-1">{{ $preference->label }}</span>

                @can('update', $client)
                    <form method="POST"
                          action="{{ route('clients.booking-preferences.destroy', [$client, $preference]) }}">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="sd-iconbtn grid place-items-center styledesk_iconbtn--danger opacity-0 group-hover:opacity-100 focus:opacity-100 transition-opacity"
                                aria-label="{{ __('clients.module.booking_preferences.remove', ['label' => $preference->label]) }}">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"/></svg>
                        </button>
                    </form>
                @endcan
            </li>
        @endforeach

        @foreach ($derived as $preference)
            <li class="flex items-start gap-2 text-[13px] text-ink">
                <span class="styledesk_bullet mt-2" aria-hidden="true"></span>
                <span class="min-w-0">
                    {{ $preference['label'] }}
                    <span class="styledesk_fromdiary">{{ __('bookings.context.from_diary') }}</span>
                </span>
            </li>
        @endforeach
    </ul>
@endif

@can('update', $client)
    {{-- One field, because these arrive one at a time: somebody mentions on
         the phone that they cannot do stairs, and it is written down while
         they are still talking. --}}
    <form method="POST" action="{{ route('clients.booking-preferences.store', $client) }}" class="mt-3 flex gap-2">
        @csrf
        <label for="bookingPreference" class="sr-only">{{ __('clients.module.booking_preferences.add') }}</label>
        <input id="bookingPreference" name="label" type="text" class="sd-input h-9 text-[13px]" required
               maxlength="120" placeholder="{{ __('clients.module.booking_preferences.placeholder') }}">

        <button type="submit" class="styledesk_action shrink-0">{{ __('common.add') }}</button>
    </form>
@endcan
