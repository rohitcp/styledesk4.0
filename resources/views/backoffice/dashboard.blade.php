{{-- Phase 1: the shell, and the one thing that is already real.

     The platform figures the brief asks for — clients, revenue, renewals —
     arrive with the phase that builds the tables behind them. A KPI card
     invented from data that does not exist yet is a number somebody acts on. --}}
@extends('layouts.backoffice')

@section('title', __('backoffice.nav.dashboard'))

@section('content')


    <h1 class="text-[22px] font-bold text-head tracking-tight">
        {{ __('backoffice.dashboard.greeting', ['name' => $admin->name]) }}
    </h1>
    <p class="text-[13px] text-sub mt-1.5">{{ __('backoffice.dashboard.intro') }}</p>

    <section class="sd-card p-5 mt-5">
        <h2 class="text-[15px] font-semibold text-head">{{ __('backoffice.dashboard.recent_activity') }}</h2>

        @if ($recent->isEmpty())
            <p class="text-[13px] text-sub mt-3">{{ __('backoffice.dashboard.no_activity') }}</p>
        @else
            <ul class="mt-3 divide-y divide-line">
                @foreach ($recent as $entry)
                    <li class="py-2.5 flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
                        <span class="min-w-0">
                            <span class="text-[13px] font-medium text-head">{{ $entry->label() }}</span>
                            <span class="text-[12.5px] text-sub">
                                — {{ $entry->admin_name ?? $entry->admin_email ?? __('backoffice.audit.unknown_actor') }}
                            </span>
                        </span>
                        <span class="text-[12px] text-faint shrink-0">
                            {{ \App\Support\TimeFormat::dateTime($entry->created_at) }}
                        </span>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
@endsection
