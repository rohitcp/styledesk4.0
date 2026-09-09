{{-- Step one. The address, and nothing else: the password is the second
     question, so somebody who has found this page has no form to attack. --}}
@extends('layouts.backoffice-auth')

@section('title', __('backoffice.auth.email_title'))

@section('intro', __('backoffice.auth.email_intro'))

@section('content')


    <form method="POST" action="{{ route('backoffice.verify.send') }}" class="space-y-4">
        @csrf

        <div>
            <label for="email" class="block text-[13px] font-medium text-ink mb-1.5">
                {{ __('backoffice.auth.email_label') }}
            </label>
            <input id="email" name="email" type="email" class="sd-input" required autofocus
                   autocomplete="username" value="{{ $email }}">
            @error('email')
                <p class="mt-1.5 text-[12px] text-danger" role="alert">{{ $message }}</p>
            @enderror
        </div>

        <button type="submit" class="w-full h-11 rounded-lg bg-brand hover:bg-brand-dark text-white text-[13px] font-semibold transition-colors">
            {{ __('backoffice.auth.send_code') }}
        </button>
    </form>
@endsection
