{{-- A module the navigation names and the next phase builds. An honest "not
     yet" rather than a 404, on a route whose permission gate is already
     live. --}}
@extends('layouts.backoffice')

@section('title', $title)

@section('content')


    <h1 class="text-[22px] font-bold text-head tracking-tight">{{ $title }}</h1>

    <section class="sd-card p-8 mt-5 text-center">
        <p class="text-[14px] font-semibold text-head">{{ __('backoffice.soon.title') }}</p>
        <p class="text-[13px] text-sub mt-1.5 max-w-[420px] mx-auto leading-relaxed">
            {{ __('backoffice.soon.'.$module) }}
        </p>
    </section>
@endsection
