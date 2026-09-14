@extends('layouts.app')

@section('title', $client->displayName())

{{--
    Clients → Loyalty → one member.

    The Loyalty module's own page for somebody, rather than a jump into the
    client profile's Rewards tab. It shows the same balance because it renders
    the same partial — one copy, so the two cannot drift — but it is opened for
    a different reason: the profile tab is one of nine things you look at while
    reading a client, and this is the screen you are on when the job in hand is
    the rewards themselves.

    The header is the profile's own, from the same partial: one introduction
    to a person rather than two that can drift apart. Only the action row
    differs — where "back" goes, and that the primary action here is the rest
    of their record rather than a booking.
--}}

@section('content')
  <main class="w-full px-6 lg:px-8 pt-4 pb-[100px]">

    {{-- The same header the client profile draws, from the same partial:
         one introduction to a person, so the two screens cannot describe
         them differently. Only the action row differs. --}}
    @include('clients.partials._identity-header', [
        'clientSettings' => $clientSettings,
        'identityActions' => 'clients.partials._identity-actions-loyalty',
    ])

    {{-- The same body the profile's Rewards tab renders. --}}
    <div class="mt-4">
      @include('clients.partials._rewards-body', ['rewardsAdjustInHeader' => true])
    </div>

  </main>

  @include('clients.partials._rewards-modal')
@endsection
