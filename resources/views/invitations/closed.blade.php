@extends('layouts.auth')

@section('title', 'Invitation unavailable')
@section('heading-class', 'text-[26px] sm:text-[28px]')
@section('heading', $isExpired ? 'This invitation has expired.' : 'This invitation is no longer available.')
@section('subheading', $isExpired
    ? 'Please ask your administrator to send you a new invitation.'
    : 'It may have already been used, or been cancelled by the business that sent it.')

@section('form')
    {{-- No error code, no token, no business name.
         The spec asks for a plain explanation rather than a raw error, and
         naming the business to someone holding a dead link would leak which
         tenant that link belonged to. --}}
    <div class="mt-8 rounded-card border border-line bg-white p-5">
        <p class="text-[13px] text-ink leading-relaxed">
            @if ($isAccepted)
                This invitation has already been accepted. If that was you, sign in with the email address it was sent to.
            @else
                Invitations are valid for {{ \App\Models\TeamInvitation::EXPIRES_AFTER_DAYS }} days. Whoever invited you can send a new one from their team settings, and it will arrive at the same address.
            @endif
        </p>

        <a href="{{ route('login') }}"
           class="inline-flex items-center justify-center h-10 px-4 mt-4 rounded-lg border border-stroke bg-white hover:bg-hover text-ink text-[13px] font-semibold transition-colors">
            Go to sign in
        </a>
    </div>
@endsection

@section('value-panel')
    <h2 class="text-[24px] font-bold text-head tracking-tight leading-[1.2]">
        Nothing has been lost
    </h2>
    <p class="text-[14px] text-sub mt-3 leading-relaxed">
        Invitations expire so that a forwarded or forgotten email cannot be used to join a business
        months later. A new one takes seconds to send.
    </p>
@endsection
