@extends('layouts.auth')
@section('title', 'Choose a new password')
@section('subtitle', 'Choose a new password')

@section('form')
    <form method="POST" action="{{ route('password.update') }}">
        @csrf
        <input type="hidden" name="token" value="{{ $request->route('token') }}">
        <input type="hidden" name="email" value="{{ $request->email }}">

        <x-text-field name="password" label="New password" type="password" autocomplete="new-password" required autofocus />
        <x-text-field name="password_confirmation" label="Confirm password" type="password" autocomplete="new-password" required />

        <button type="submit" class="styledesk_button">Reset password</button>
    </form>
@endsection
