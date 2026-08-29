@extends('layouts.auth')

@section('title', 'Choose a new password')
@section('heading', 'Choose a new password')
@section('subheading', 'Pick something you have not used on StyleDesk before.')

@section('form')
    <form method="POST" action="{{ route('password.update') }}" class="mt-8 space-y-5">
        @csrf
        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        {{-- The account this link is for, shown rather than hidden.
             Somebody who has two StyleDesk logins, or who followed a link from
             an old email, has no other way of knowing which password they are
             about to change.

             Read-only, not merely hidden: the address is what the token is
             issued against, so letting it be edited would only ever produce a
             refusal. Still posted, because readonly fields are — which is why
             it replaces the hidden input rather than joining it. --}}
        <x-text-field name="email" label="Email address" type="email"
                      autocomplete="username" readonly
                      :value="$request->email"
                      hint="Your new password will be set for this account." />

        <x-text-field name="password" label="New password" type="password" autocomplete="new-password" required autofocus />
        <x-text-field name="password_confirmation" label="Confirm password" type="password" autocomplete="new-password" required />

        <button type="submit" class="w-full h-11 rounded-lg bg-brand hover:bg-brand-dark text-white text-[14px] font-semibold transition-colors">Reset password</button>
    </form>
@endsection

@section('value-panel')
    <h2 class="text-[26px] xl:text-[29px] font-bold text-head tracking-tight leading-[1.25]">
        One last step, <span class="text-[#9a93c4]">then you are back in.</span>
    </h2>
@endsection
