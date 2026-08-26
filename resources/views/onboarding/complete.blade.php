@extends('layouts.onboarding')

@section('title', 'All set')
@section('heading', 'You are all set')
@section('subheading', 'Your workspace is ready. Everything you entered is editable in Settings.')

@section('form')
    <div class="sd-alert sd-alert--success mt-6" role="status">
        <div class="flex items-start gap-2.5">
            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" class="shrink-0 mt-px" aria-hidden="true"><path d="M5 12.5l4.5 4.5L19 7.5" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
            <div class="min-w-0">
                <p class="font-semibold">{{ $tenant->name }} is live</p>
                <p class="mt-1">Clients can book at
                    <b class="break-all">https://{{ $tenant->slug }}.{{ config('tenancy.tenant_domain_suffix') }}</b>
                </p>
            </div>
        </div>
    </div>

    <div class="mt-8">
        <a href="{{ route('dashboard') }}"
           class="inline-flex items-center justify-center h-11 px-6 rounded-lg bg-brand hover:bg-brand-dark text-white text-[14px] font-semibold transition-colors">
            Go to dashboard
        </a>
    </div>
@endsection

@section('rail')
    <h2 class="text-[15px] font-semibold text-head">What next</h2>
    <ul class="mt-3 space-y-2.5">
        @foreach (['Share your booking link with clients.', 'Invite the rest of your team.', 'Add add-ons and packages in Settings.'] as $point)
            <li class="flex items-start gap-2.5">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" class="text-brand mt-0.5 shrink-0" aria-hidden="true"><path d="M5 12.5l4.5 4.5L19 7.5" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                <span class="text-[13px] text-sub leading-relaxed">{{ $point }}</span>
            </li>
        @endforeach
    </ul>
@endsection
