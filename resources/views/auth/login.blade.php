@extends('layouts.auth')
@section('title', 'Sign in')
@section('subtitle', 'Sign in to your account')

@section('form')
    <form method="POST" action="{{ route('login.store') }}">
        @csrf
        <x-text-field name="email" label="Email" type="email" autocomplete="username" required autofocus />
        <x-text-field name="password" label="Password" type="password" autocomplete="current-password" required />

        <label class="flex items-center gap-2 mb-4 text-sub">
            <input type="checkbox" name="remember" value="1" class="rounded border-stroke">
            Remember me
        </label>

        <button type="submit" class="styledesk_button">Sign in</button>
    </form>
@endsection

@section('footer')
    <a class="text-link" href="{{ route('password.request') }}">Forgot your password?</a>
    &middot;
    <a class="text-link" href="{{ route('register') }}">Create an account</a>
@endsection
