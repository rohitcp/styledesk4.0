@extends('layouts.auth')
@section('title', 'Create account')
@section('subtitle', 'Create your StyleDesk account')

@section('form')
    <form method="POST" action="{{ route('register.store') }}">
        @csrf
        <x-text-field name="name" label="Name" autocomplete="name" required autofocus />
        <x-text-field name="email" label="Email" type="email" autocomplete="username" required />
        <x-text-field name="password" label="Password" type="password" autocomplete="new-password" required />
        <x-text-field name="password_confirmation" label="Confirm password" type="password" autocomplete="new-password" required />

        <button type="submit" class="styledesk_button">Create account</button>
    </form>
@endsection

@section('footer')
    Already have an account? <a class="text-link" href="{{ route('login') }}">Sign in</a>
@endsection
