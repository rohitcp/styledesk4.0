@extends('layouts.auth')
@section('title', 'Confirm password')
@section('subtitle', 'Please confirm your password to continue')

@section('form')
    <form method="POST" action="{{ route('password.confirm.store') }}">
        @csrf
        <x-text-field name="password" label="Password" type="password" autocomplete="current-password" required autofocus />
        <button type="submit" class="styledesk_button">Confirm</button>
    </form>
@endsection
