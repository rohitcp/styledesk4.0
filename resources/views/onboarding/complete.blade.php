@extends('layouts.onboarding')

@section('title', 'All set')
@section('heading', 'Your StyleDesk is ready')
@section('subheading', "Your business has been created successfully. You're ready to start managing clients, services, appointments, and your team.")

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

    <ul class="mt-8 space-y-2.5">
        @foreach ($checklist as $item)
            <li class="flex items-start gap-2.5">
                @if ($item['done'])
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" class="text-success mt-0.5 shrink-0" aria-hidden="true"><path d="M5 12.5l4.5 4.5L19 7.5" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    <span class="text-[13px] text-ink">{{ $item['label'] }}</span>
                @else
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" class="text-faint mt-0.5 shrink-0" aria-hidden="true"><circle cx="12" cy="12" r="8.5" stroke="currentColor" stroke-width="1.6"/></svg>
                    <span class="text-[13px] text-sub">
                        {{ \Illuminate\Support\Str::before($item['label'], ' ') }} —
                        <span class="text-faint">Setup later</span>
                    </span>
                @endif
            </li>
        @endforeach
    </ul>

    <div class="mt-8">
        <a href="{{ route('dashboard') }}"
           class="inline-flex items-center justify-center h-11 px-6 rounded-lg bg-brand hover:bg-brand-dark text-white text-[14px] font-semibold transition-colors">
            Go to StyleDesk
        </a>
    </div>
@endsection

@section('rail')
    <h2 class="text-[15px] font-semibold text-head">Your workspace</h2>
    <p class="text-[13px] text-sub mt-1.5 leading-relaxed">Everything below is editable in Settings.</p>

    <div class="mt-5 rounded-card border border-line bg-white shadow-sm overflow-hidden" aria-hidden="true">
        <div class="flex items-center gap-3 px-4 py-3.5 border-b border-line">
            <span class="h-10 w-10 rounded-lg bg-brand text-white grid place-items-center shrink-0 overflow-hidden">
                @if ($tenant->logo_path)
                    <img src="{{ Storage::disk('public')->url($tenant->logo_path) }}" alt="" class="h-full w-full object-cover">
                @else
                    <svg width="20" height="20" viewBox="0 0 32 32" fill="currentColor"><path d="M6.5 21.5 L14 6 L18.5 6 L11 21.5 Z"/><path d="M14.5 21.5 L22 6 L26.5 6 L19 21.5 Z"/><rect x="4" y="24.6" width="24" height="3.6" rx="1.8"/></svg>
                @endif
            </span>
            <div class="min-w-0">
                <p class="text-[14px] font-semibold text-head truncate">{{ $tenant->name }}</p>
                <p class="text-[12px] text-faint truncate">{{ $tenant->slug }}.{{ config('tenancy.tenant_domain_suffix') }}</p>
            </div>
        </div>

        {{-- Counts come from the database, not from the wizard's own flags: a
             step marked complete with nothing entered would otherwise report
             work that does not exist. --}}
        <ul class="divide-y divide-line">
            @foreach ([
                'Locations' => $tenant->locations()->count(),
                'Services' => $tenant->services()->count(),
                'Team' => $tenant->staff()->count(),
                'Business types' => $tenant->businessTypes()->count(),
            ] as $label => $count)
                <li class="flex items-center justify-between gap-3 px-4 py-2.5">
                    <span class="text-[12px] text-sub">{{ $label }}</span>
                    <span class="text-[12px] text-ink font-medium">{{ $count }}</span>
                </li>
            @endforeach
        </ul>
    </div>

    <ul class="mt-8 space-y-4">
        @foreach ([
            'Your trial runs for '.$tenant->trialDaysRemaining().' more days — no card needed until you choose a plan.',
            'Anything you skipped is waiting for you — the summary on the left links straight to it.',
            'Your dashboard opens with a getting-started checklist for the rest.',
        ] as $point)
            <li class="flex items-start gap-3">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" class="text-brand mt-0.5 shrink-0" aria-hidden="true"><path d="M5 12.5l4.5 4.5L19 7.5" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                <span class="text-[13px] text-ink leading-relaxed">{{ $point }}</span>
            </li>
        @endforeach
    </ul>
@endsection
