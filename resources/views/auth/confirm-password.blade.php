@extends('layouts.auth')

@section('title', 'Confirm password')
@section('heading', 'Confirm your password')
@section('subheading', 'This area is sensitive, so please confirm your password to continue.')

@section('form')
    <form method="POST" action="{{ route('password.confirm.store') }}" class="mt-8 space-y-5">
        @csrf
        <x-text-field name="password" label="Password" type="password" autocomplete="current-password" required autofocus />
        <button type="submit" class="w-full h-11 rounded-lg bg-brand hover:bg-brand-dark text-white text-[14px] font-semibold transition-colors">Confirm</button>
    </form>
@endsection

@section('value-panel')
    <h2 class="text-[26px] xl:text-[29px] font-bold text-head tracking-tight leading-[1.25]">
        A quick check <span class="text-[#9a93c4]">before you continue.</span>
    </h2>
@endsection
