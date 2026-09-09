@extends('layouts.app')

@section('title', __('membership.choose.title'))

{{--
    Create Membership — step 1.

    Its own screen rather than the first field of the form, because it is the
    one answer that changes what every later question means. A billing
    frequency and a regular value are not two settings of one plan; they
    belong to different products, and a form that showed both would be a form
    where half the fields are noise.

    It is also why the kind cannot be changed afterwards: a plan that switched
    would have to change what every credit already granted under it means.
--}}

@section('content')
  <main class="w-full px-4 sm:px-5 lg:px-6 pt-5 sm:pt-6 pb-[200px]">
    <div class="styledesk_form">

      <nav class="text-[13px] text-sub" aria-label="Breadcrumb">
        <a href="{{ route('membership.index') }}" class="hover:text-ink transition-colors">{{ __('membership.title') }}</a>
        <span class="mx-1.5 text-faint">/</span>
        <span class="text-ink">{{ __('membership.choose.title') }}</span>
      </nav>

      <div class="mt-3 flex flex-wrap items-start gap-4">
        <div class="min-w-0 flex-1">
          <h1 class="text-[24px] sm:text-[28px] font-bold text-head tracking-tight">{{ __('membership.choose.question') }}</h1>
          <p class="text-[13px] text-sub mt-1.5 leading-relaxed max-w-[640px]">{{ __('membership.choose.intro') }}</p>
        </div>

        <a href="{{ route('membership.index') }}" class="styledesk_action shrink-0">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M14 6l-6 6 6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
          {{ __('common.back') }}
        </a>
      </div>

      <div class="mt-6 grid gap-4 sm:grid-cols-2">
        @foreach (array_keys($types) as $key)
          <a href="{{ route('membership.create', ['type' => $key]) }}"
             class="sd-card p-5 flex flex-col transition-colors hover:border-brand">
            <span class="styledesk_settingcard__icon !h-10 !w-10" aria-hidden="true">
              <x-icon :name="$key === 'recurring' ? 'arrow-right-arrow-left' : 'box'" size="16" />
            </span>

            <h2 class="text-[16px] font-semibold text-head mt-3">{{ __('membership.types.'.$key) }}</h2>
            <p class="text-[13px] text-sub mt-1.5 leading-relaxed flex-1">{{ __('membership.types.'.$key.'_hint') }}</p>

            <p class="text-[12.5px] text-sub mt-4">
              <span class="font-semibold text-head">{{ __('membership.choose.example') }}:</span>
              {{ __('membership.choose.'.$key.'_example') }}
            </p>

            <span class="styledesk_action mt-4 self-start">{{ __('membership.choose.select') }}</span>
          </a>
        @endforeach
      </div>
    </div>
  </main>
@endsection
