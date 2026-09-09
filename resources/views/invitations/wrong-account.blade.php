@extends('layouts.auth')

@section('title', 'Wrong account')
@section('heading-class', 'text-[26px] sm:text-[28px]')
@section('heading', 'This invitation is for a different account')
@section('subheading', 'Sign out and sign back in with the invited address to accept it.')

@section('form')
    <div class="mt-8 rounded-card border border-line bg-white p-5 space-y-3">
        <dl class="grid grid-cols-[auto_1fr] gap-x-6 gap-y-2">
            <dt class="text-[13px] text-sub">Invitation sent to</dt>
            <dd class="text-[13px] font-medium text-ink truncate">{{ $invitation->email }}</dd>

            <dt class="text-[13px] text-sub">You are signed in as</dt>
            <dd class="text-[13px] font-medium text-ink truncate">{{ $signedInAs }}</dd>

            <dt class="text-[13px] text-sub">Invited as</dt>
            <dd class="text-[13px] font-medium text-ink">{{ $roleLabel }}</dd>
        </dl>

        {{-- The invitation is bound to one address, so switching account is the
             only way through. Offered as a button rather than described, since
             signing out is otherwise buried inside the app. --}}
        <form method="POST" action="{{ route('logout') }}" class="pt-3 border-t border-line">
            @csrf
            <button type="submit"
                    class="styledesk_action">
                Sign out
            </button>
        </form>
    </div>
@endsection

@section('value-panel')
    <h2 class="text-[24px] font-bold text-head tracking-tight leading-[1.2]">
        Invitations are tied to one address
    </h2>
    <p class="text-[14px] text-sub mt-3 leading-relaxed">
        That is what stops a forwarded invitation from being used by someone it was never meant for.
    </p>
@endsection
