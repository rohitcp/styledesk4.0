@extends('layouts.app')

@section('title', __('currency.title'))

@section('content')
  <main class="w-full px-4 sm:px-5 lg:px-6 pt-5 sm:pt-6 pb-[200px]">
    <div class="styledesk_form">

      <nav class="text-[13px] text-sub" aria-label="Breadcrumb">
        <a href="{{ route('settings.index') }}" class="hover:text-ink transition-colors">{{ __('navigation.app_settings') }}</a>
        <span class="mx-1.5 text-faint">/</span>
        <span class="text-ink">{{ __('currency.title') }}</span>
      </nav>

      <div class="mt-3 flex flex-wrap items-start gap-4">
        <div class="min-w-0 flex-1">
          <h1 class="text-[24px] sm:text-[28px] font-bold text-head tracking-tight">{{ __('currency.title') }}</h1>
          <p class="text-[14px] text-sub mt-2 max-w-[640px] leading-relaxed">{{ __('currency.intro') }}</p>
        </div>

        <div class="shrink-0 flex items-center gap-2">
          <a href="{{ route('settings.index') }}"
             class="styledesk_action">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M14 6l-6 6 6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
            {{ __('common.back') }}
          </a>

          <a href="{{ route('settings.currency.edit') }}"
             class="inline-flex items-center gap-2 h-9 px-3.5 rounded-lg bg-brand hover:bg-brand-dark text-white text-[13px] font-semibold transition-colors">
            {{ __('currency.edit') }}
          </a>
        </div>
      </div>

      {{-- Said before the numbers, because it is the thing a business is most
           likely to assume the wrong way round: enabling a second currency
           does not convert anything. --}}
      <div class="sd-alert sd-alert--info mt-5" role="status">
        <div class="flex items-start gap-2.5">
          <svg width="17" height="17" viewBox="0 0 24 24" fill="none" class="shrink-0 mt-px" aria-hidden="true"><circle cx="12" cy="12" r="8.5" stroke="currentColor" stroke-width="1.7"/><path d="M12 11v5.5M12 8v.4" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"/></svg>
          <p class="min-w-0">{{ __('currency.no_conversion') }}</p>
        </div>
      </div>

      <div class="mt-6 space-y-5">
        <x-settings.card :title="__('currency.title')">
          <x-settings.field :label="__('currency.primary')" :value="App\Support\Currencies::label($primary)" />

          <x-settings.field :label="__('currency.secondary')">
            @if ($secondary->isNotEmpty())
              <span class="flex flex-col gap-1">
                @foreach ($secondary as $code)
                  <span>{{ App\Support\Currencies::label($code) }}</span>
                @endforeach
              </span>
            @else
              <span class="text-faint">{{ __('common.none') }}</span>
            @endif
          </x-settings.field>

          <x-settings.field :label="__('currency.enabled')">
            <span class="flex flex-wrap gap-1.5">
              @foreach ($enabled as $code)
                <span class="styledesk_badge styledesk_badge--active">{{ $code }}</span>
              @endforeach
            </span>
          </x-settings.field>
        </x-settings.card>

        {{-- What the formatting rules actually produce, per currency.
             Shown rather than described: "symbol position" and "decimal
             separator" mean far less than seeing 1.234,56 € beside $1,234.56,
             and this is also the honest way to say the business does not set
             these — the currency does. --}}
        <x-settings.card :title="__('currency.format')" :description="__('currency.format_hint')">
          <div class="mt-1 overflow-x-auto">
            <table class="w-full text-[13px]" style="min-width: 420px">
              <thead>
                <tr class="text-left text-[12px] text-sub border-b border-line">
                  <th class="font-medium py-2">{{ __('currency.title') }}</th>
                  <th class="font-medium py-2 text-right">1234.5</th>
                  <th class="font-medium py-2 text-right">-75</th>
                  <th class="font-medium py-2 text-right">0</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-line">
                @foreach ($enabled as $code)
                  <tr>
                    <td class="py-2.5">
                      <span class="font-mono text-ink">{{ $code }}</span>
                      <span class="text-sub">— {{ App\Support\Currencies::name($code) }}</span>
                    </td>
                    <td class="py-2.5 text-right text-ink whitespace-nowrap">{{ App\Support\Money::format(1234.5, $code) }}</td>
                    <td class="py-2.5 text-right text-ink whitespace-nowrap">{{ App\Support\Money::format(-75, $code) }}</td>
                    <td class="py-2.5 text-right text-sub whitespace-nowrap">{{ App\Support\Money::zero($code) }}</td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
        </x-settings.card>

        @if ($enabled->count() === 1)
          <p class="text-[13px] text-sub">{{ __('currency.single_currency') }}</p>
        @endif

        <p class="text-[12px] text-faint">{{ __('currency.scope_note') }}</p>
      </div>
    </div>
  </main>
@endsection
