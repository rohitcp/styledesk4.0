{{--
    The Membership header: title, the Create button, the four counts and the
    tabs. One partial because all four screens carry it, and four copies is
    how a tab gets added to three of them.

    The counts are links: a number somebody wants to act on should be one
    press from the list behind it.
--}}
@php
    $tabRoutes = [
        'overview' => route('membership.index'),
        'plans' => route('membership.plans'),
        'packages' => route('membership.packages'),
        'members' => route('membership.members'),
    ];

    $canCreate = auth()->user()?->hasPermission('clients.create', 'own') ?? false;
@endphp

<header class="flex flex-wrap items-start gap-4">
  <div class="min-w-0 flex-1">
    <h1 class="text-[22px] sm:text-[24px] font-bold text-head tracking-tight">{{ __('membership.title') }}</h1>
    <p class="text-[13px] text-sub mt-1.5">{{ __('membership.intro') }}</p>
  </div>

  @if ($canCreate)
    <a href="{{ route('membership.create') }}"
       class="shrink-0 inline-flex items-center gap-1.5 h-9 px-3.5 rounded-lg bg-brand hover:bg-brand-dark text-white text-[13px] font-semibold transition-colors">
      <x-icon name="plus" size="14" />
      {{ __('membership.new') }}
    </a>
  @endif
</header>

<nav class="mt-5 border-b border-line flex flex-wrap gap-1" aria-label="{{ __('membership.title') }}">
  @foreach ($tabs as $key)
    <a href="{{ $tabRoutes[$key] }}"
       @class([
           'h-9 px-3.5 inline-flex items-center text-[13px] font-semibold border-b-2 -mb-px transition-colors',
           'border-brand text-brand' => $tab === $key,
           'border-transparent text-sub hover:text-ink' => $tab !== $key,
       ])
       @if ($tab === $key) aria-current="page" @endif>
      {{ __('membership.tabs.'.$key) }}
    </a>
  @endforeach
</nav>

<div class="mt-5 grid grid-cols-2 lg:grid-cols-4 gap-3">
  @foreach (['plans' => $tabRoutes['plans'], 'packages' => $tabRoutes['packages'], 'drafts' => route('membership.plans', ['status' => 'draft']), 'members' => $tabRoutes['members']] as $key => $url)
    <a href="{{ $url }}" class="sd-card px-4 py-3 transition-colors hover:border-brand">
      <p class="text-[12px] font-semibold text-sub">{{ __('membership.summary.'.$key) }}</p>
      <p class="text-[22px] font-bold text-head leading-tight mt-0.5">{{ $counts[$key] }}</p>
    </a>
  @endforeach
</div>
