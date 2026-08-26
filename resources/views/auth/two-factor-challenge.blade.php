@extends('layouts.auth')
@section('title', 'Two-factor authentication')
@section('subtitle', 'Enter your authentication code')

@section('form')
    <form method="POST" action="{{ route('two-factor.login.store') }}">
        @csrf
        <x-text-field name="code" label="Authentication code" autocomplete="one-time-code" />
        <p class="text-sub mb-4">Lost your device? Enter one of your recovery codes instead.</p>
        <x-text-field name="recovery_code" label="Recovery code" autocomplete="one-time-code" />

        <button type="submit" class="styledesk_button">Continue</button>
    </form>
@endsection
