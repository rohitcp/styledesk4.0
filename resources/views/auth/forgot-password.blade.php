@extends('layouts.auth')
@section('title', 'Reset password')
@section('subtitle', 'We will email you a reset link')

@section('form')
    <form method="POST" action="{{ route('password.email') }}">
        @csrf
        <x-text-field name="email" label="Email" type="email" autocomplete="username" required autofocus />
        <button type="submit" class="styledesk_button">Email reset link</button>
    </form>
@endsection

@section('footer')
    <a class="text-link" href="{{ route('login') }}">Back to sign in</a>
@endsection
