{{--
    The one status pill, in one place.

    The list and the detail page showed the same five states in two hand-rolled
    ternaries, which is two chances for "Past Due" to be amber on one screen and
    grey on the other. Tenant::displayStatus() decides what the state is; this
    decides what it looks like.
--}}
@props(['status'])

@php
    $tones = [
        'active' => 'bg-emerald-50 text-emerald-700',
        'trial' => 'bg-sky-50 text-sky-700',
        'past_due' => 'bg-amber-50 text-amber-700',
        'disabled' => 'bg-red-50 text-red-700',
        'cancelled' => 'bg-slate-100 text-slate-600',
    ];
@endphp

<span {{ $attributes->class([
    'inline-flex items-center rounded-full px-2 py-0.5 text-[11.5px] font-semibold whitespace-nowrap',
    $tones[$status] ?? 'bg-slate-100 text-slate-600',
]) }}>
    {{ __('backoffice.clients.statuses.'.$status) }}
</span>
