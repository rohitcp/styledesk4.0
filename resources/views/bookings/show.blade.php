@extends('layouts.app')

@section('title', $booking->reference ?: __('bookings.title'))

@php
    use App\Support\ClientOptions;

    /* The variables the client profile's own partials read. Reused rather
       than reimplemented: contact details and preferences look the same here
       because they *are* the same thing, and a second version of the contact
       card would drift from the first within a month. */
    $name = $client?->displayName($settings->name_format);
    $methods = ClientOptions::communicationMethods();
    $primaryPhone = $client?->primaryPhone();
    $primaryEmail = $client?->primaryEmail();
    $address = $client ? collect([$client->address, $client->city, $client->state, $client->postal_code])->filter()->join(', ') : '';
    $country = $client?->country ? config('locations.countries.'.$client->country) : null;
    /* What the diary has noticed about how they like to be booked. Read
       live from the client, like the rest of this column: it belongs to the
       person, not to this appointment. */
    $bookingContext = $client ? App\Support\ClientBookingContext::for($client)->toArray() : [];

    $email = $canViewContact ? ($primaryEmail?->email ?? $client?->email) : null;
    $phone = $canViewContact ? ($primaryPhone?->number ?? $client?->mobile) : null;
@endphp

@section('content')
  {{-- The same workspace shape as the client profile: the person on the
       left, the work in the middle, what to do about them on the right. A
       booking is read the same way, and a second layout for it would be a
       second thing to learn. --}}
  <main class="w-full px-6 lg:px-8 pt-4 pb-24 xl:pb-0 xl:h-full xl:flex xl:flex-col xl:overflow-hidden">

    <header class="styledesk_identity mt-3 xl:shrink-0">
      <div class="styledesk_actionrow styledesk_identity__actions">
        <a href="{{ route('bookings.index') }}" data-tip="{{ __('bookings.title') }}"
           aria-label="{{ __('bookings.title') }}"
           class="styledesk_action styledesk_action--shrinklabel shrink-0">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M14 6l-6 6 6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
          <span class="styledesk_action__label">{{ __('common.back') }}</span>
        </a>

        {{-- What can be done to this booking now, and only that.

             An action the reader may not take is absent rather than shown
             disabled: a button they can never enable is furniture. An action
             that would make no sense from here — marking a completed
             appointment as a no-show — is absent for the same reason. --}}
        @foreach ($actions as $action)
          @php $tone = config('bookings.status_actions.'.$action.'.tone'); @endphp
          <button type="button" data-status-action="{{ $action }}"
                  class="styledesk_action shrink-0 {{ $tone === 'danger' ? 'styledesk_action--danger' : '' }}">
            {{ __('bookings.status.'.$action.'.action') }}
          </button>
        @endforeach

        {{-- Write to the client about this appointment.

             The same composer the client profile opens, fixed to this
             booking: everything written from here is about it, so the
             templates render against it and the Related booking field has
             nothing left to ask.

             Only where there is a client record to write to and a history to
             file it against — a walk-in nobody put on the book has neither,
             and their address lives on the booking rather than on a record
             this can reach. --}}
        @if ($client?->email && auth()->user()?->hasPermission('email.send', 'own'))
          <button type="button" data-send-email
                  data-compose-url="{{ route('clients.emails.compose', $client) }}"
                  data-send-url="{{ route('clients.emails.store', $client) }}"
                  class="styledesk_action shrink-0">
            <x-icon name="envelope" size="14" />
            {{ __('client_email.send.action') }}
          </button>
        @endif

        <a href="{{ route('bookings.receipt', $booking) }}" target="_blank" rel="noopener" class="styledesk_action shrink-0">
          {{ __('bookings.confirmation.print') }}
        </a>
      </div>

      {{-- The client profile's own header, element for element: same avatar,
           same 22/24px name, same reference chip, same chip row underneath.
           A booking is opened from a client and a client from a booking, and
           two headers that are nearly the same read as a fault in one. --}}
      <span class="sd-avatar styledesk_avatar--identity styledesk_identity__avatar" aria-hidden="true">{{ $client?->initials() ?? mb_strtoupper(mb_substr($booking->clientName(), 0, 2)) }}</span>

      <div class="styledesk_identity__body">
        <div class="flex flex-wrap items-center gap-2.5">
          <h1 class="text-[22px] sm:text-[24px] font-bold text-head tracking-tight leading-tight min-w-0 truncate">{{ $booking->clientName() }}</h1>
          <span class="styledesk_metachip styledesk_metachip--ref font-mono">{{ $booking->reference }}</span>
        </div>

        {{-- The chips carry the appointment's own facts, in the same shape
             the profile uses for the client's: state first, then the values
             a person would otherwise have to open something to find. --}}
        <div class="flex flex-wrap justify-start items-center gap-2 mt-2">
          <span class="styledesk_metachip {{ $booking->statusClass() }}">
            <span class="styledesk_statusdot" aria-hidden="true"></span>
            {{ $booking->statusLabel() }}
          </span>

          <span class="styledesk_metachip {{ $booking->paymentStatusClass() }}">
            <span class="styledesk_statusdot" aria-hidden="true"></span>
            {{ $booking->paymentStatusLabel() }}
          </span>

          <span class="styledesk_metachip">{{ $booking->date->translatedFormat('D, j M Y') }}</span>
          <span class="styledesk_metachip">{{ $booking->timeLabel() }}</span>

          @if ($booking->staff)
            <span class="styledesk_metachip">{{ $booking->staff->displayName() }}</span>
          @endif

          @if ($booking->location)
            <a href="{{ route('settings.locations.show', $booking->location) }}"
               class="styledesk_metachip styledesk_metachip--place">{{ $booking->location->name }}</a>
          @endif

          @if ($client)
            <a href="{{ route('clients.show', $client) }}" class="styledesk_metachip">
              {{ __('leads.actions.view_client') }}
            </a>
          @endif
        </div>
      </div>
    </header>

    <hr class="border-line mt-4 xl:shrink-0">

    {{-- The client profile's own column proportions, to the fraction: 22 /
         53 / 25. A booking is read beside a client and often in the same
         sitting, and two pages whose side columns are nearly the same width
         read as a mistake in one of them. --}}
    <div class="grid items-stretch lg:grid-cols-[minmax(0,22fr)_minmax(0,78fr)] xl:grid-cols-[minmax(0,22fr)_minmax(0,53fr)_minmax(0,25fr)] xl:flex-1 xl:min-h-0">

      {{-- --------------------------------------------- column 1 — client --}}
      <aside class="contents lg:block lg:h-full lg:pr-6 lg:border-r lg:border-line min-w-0 overflow-x-hidden
                    xl:overflow-y-auto styledesk_scroll">
        <div class="contents lg:block lg:sticky lg:top-4 xl:static xl:pb-6">
          @if ($client)
            {{-- Read live from the client record. These belong to the person
                 rather than to the appointment, and a copy of them here
                 would be out of date the first time somebody edited one. --}}
            <div class="order-1 lg:order-none py-5">@include('clients.partials._booking-preferences')</div>
            <div class="order-2 lg:order-none py-5 border-t border-line">@include('clients.partials._client-preferences')</div>
            <div class="order-3 lg:order-none py-5 border-t border-line">@include('clients.partials._contact')</div>
          @else
            {{-- A walk-in has a name and a number and no record behind it.
                 Saying so is better than three empty cards. --}}
            <div class="py-5">
              <h2 class="styledesk_heading">{{ __('bookings.summary.client') }}</h2>
              <p class="text-[13px] text-sub mt-2">{{ __('bookings.detail.walk_in') }}</p>
              @if ($booking->guest_phone)
                <p class="text-[13px] text-head mt-2">{{ $booking->guest_phone }}</p>
              @endif
              @if ($booking->guest_email)
                <p class="text-[13px] text-head">{{ $booking->guest_email }}</p>
              @endif
            </div>
          @endif
        </div>
      </aside>

      {{-- -------------------------------------------- column 2 — booking --}}
      <div class="order-3 lg:order-none min-w-0 lg:h-full lg:pl-6 xl:pr-6 xl:border-r xl:border-line py-5 border-t border-line lg:border-t-0
                  xl:overflow-y-auto styledesk_scroll">
        <div class="xl:pb-[200px] space-y-6">
          {{-- Already here, and so not a button any more.

               The line replaces the Check In action rather than sitting beside
               it: a Check In button on a client who is standing in the salon is
               an invitation to record them arriving twice.

               A panel across the column rather than a chip in the toolbar. It
               is the first thing the desk needs to know about the booking on
               the day — the client is in the building — and a chip among the
               action buttons reads as one more control rather than as a fact. --}}
          @if ($checkIn)
            {{-- Bled to the column's own edges: the negative margins cancel the
                 column's `py-5` above and its `lg:pl-6` / `xl:pr-6` at the
                 sides, so the band runs the full width and sits against the
                 top. Square and unbordered for the same reason — a banner that
                 touches three edges and still draws its own outline reads as a
                 card that has been pushed out of place. --}}
            <div class="-mt-5 lg:-ml-6 xl:-mr-6 bg-sky-50 px-6 py-3" role="status">
              {{-- The vendored set has no plain tick, and calendar-check is the
                   truer icon anyway: the appointment has been kept, which is
                   what checking in records. --}}
              <p class="flex items-center gap-2 text-[13px] font-semibold text-sky-900">
                <x-icon name="calendar-check" size="15" class="shrink-0" aria-hidden="true" />
                <span>
                  {{ __('bookings.status.check-in.done_at', [
                      'time' => $checkIn->created_at?->format(\App\Support\TimeFormat::clock()),
                      'name' => $checkIn->actor(),
                  ]) }}
                </span>
              </p>
            </div>
          @endif

          @include('bookings.partials._summary')
          @include('bookings.partials._services')
          @include('bookings.partials._notes')
          @include('bookings.partials._activity')
        </div>
      </div>

      {{-- ------------------------------------------- column 3 — activity --}}
      <aside class="order-7 lg:order-none min-w-0 overflow-x-hidden xl:h-full xl:pl-6 xl:pr-2.5 py-5 border-t border-line xl:border-t-0 lg:col-span-2 xl:col-span-1
                    xl:overflow-y-auto styledesk_scroll">
        <div class="xl:pb-6">
          {{-- Reach them, then settle up, then everything else.
               Contact is three buttons deep and belongs at the top where the
               eye lands; the money is what this column is opened for on the
               day, so it sits directly under it rather than three sections
               down the middle column where it used to be. Each block carries
               its own bottom margin instead of a space-y on the wrapper,
               because the client-context sections space themselves. --}}
          <div class="mb-5">
            @include('bookings.partials._contact-actions')
          </div>

          <div class="mb-6 space-y-6">
            @include('bookings.partials._payments')
          </div>

          @include('bookings.partials._client-context')
        </div>
      </aside>
    </div>
  </main>

  {{-- Rendered only where the act is on offer, so a reader without the
       permission is not sent the dialogue for it. --}}
  @include('bookings.partials._status-modals')

  {{-- The Send Email composer, the same one the client profile opens. --}}
  @if ($client?->email && auth()->user()?->hasPermission('email.send', 'own'))
    @include('clients.partials._email-drawer')
  @endif
@endsection

@push('scripts')
  @include('bookings.partials._status-scripts')

  {{-- The composer's behaviour, shared with the client profile and fixed to
       this appointment. --}}
  @if ($client?->email && auth()->user()?->hasPermission('email.send', 'own'))
    @include('clients.partials._email-drawer-scripts', ['emailBookingId' => $booking->id])
  @endif
@endpush
