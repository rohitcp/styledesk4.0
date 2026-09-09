{{--
    App Settings → Payments.

    Three questions in the order a business answers them: are we taking
    payments, who processes them, and what will we accept.

    The screen never mentions an API, a key or a webhook. A salon owner is
    connecting their card processor, not integrating one.
--}}
@extends('layouts.app')

@section('title', __('payments.title'))

@section('content')
  <main class="w-full px-4 sm:px-5 lg:px-6 pt-5 sm:pt-6 pb-[200px]">
    <div class="styledesk_form">

      <nav class="text-[13px] text-sub" aria-label="Breadcrumb">
        <a href="{{ route('settings.index') }}" class="hover:text-ink transition-colors">{{ __('navigation.app_settings') }}</a>
        <span class="mx-1.5 text-faint">/</span>
        <span class="text-ink">{{ __('payments.title') }}</span>
      </nav>

      <div class="mt-3 flex flex-wrap items-start gap-4">
        <div class="min-w-0 flex-1">
          <h1 class="text-[24px] sm:text-[28px] font-bold text-head tracking-tight">{{ __('payments.title') }}</h1>
          <p class="text-[14px] text-sub mt-2 max-w-[640px] leading-relaxed">{{ __('payments.intro') }}</p>
        </div>

        <a href="{{ route('settings.index') }}" class="styledesk_action shrink-0">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M14 6l-6 6 6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
          {{ __('common.back') }}
        </a>
      </div>

      @if (session('status'))
        <div class="sd-alert sd-alert--info mt-5" role="status"><p class="min-w-0">{{ session('status') }}</p></div>
      @endif

      @if ($errors->any())
        <div class="sd-alert sd-alert--danger mt-5" role="alert"><p class="min-w-0">{{ $errors->first() }}</p></div>
      @endif

      <form method="POST" action="{{ route('settings.payments.update') }}" class="mt-5 space-y-4">
        @csrf
        @method('PATCH')

        {{-- ------------------------------------------------- the switch --}}
        <section class="bg-white border border-line rounded-card p-5">
          <input type="hidden" name="payments_enabled" value="0">

          <label class="styledesk_toggle">
            <input type="checkbox" name="payments_enabled" value="1" class="styledesk_toggle__input"
                   data-payments-enabled @checked(old('payments_enabled', $tenant->payments_enabled))>
            <span class="styledesk_toggle__track" aria-hidden="true"><span class="styledesk_toggle__knob"></span></span>
            <span class="min-w-0 flex-1">
              <span class="styledesk_toggle__label">{{ __('payments.enable') }}</span>
              <span class="styledesk_toggle__hint">{{ __('payments.enable_hint') }}</span>
            </span>
          </label>
        </section>

        <div data-payment-options @class(['space-y-4', 'hidden' => ! old('payments_enabled', $tenant->payments_enabled)])>

          {{-- ------------------------------------------- the processor --}}
          <section class="bg-white border border-line rounded-card p-5">
            <h2 class="text-[15px] font-semibold text-head">{{ __('payments.processor') }}</h2>
            <p class="text-[13px] text-sub mt-1">{{ __('payments.processor_hint') }}</p>

            <div class="mt-4 space-y-3">
              @foreach ($gateways as $gateway)
                @php $isActive = $active->key() === $gateway['key']; @endphp

                <div @class([
                    'rounded-card border p-4',
                    'border-brand bg-brand/[0.04]' => $isActive,
                    'border-line' => ! $isActive,
                    'opacity-60' => ! $gateway['supported'],
                ])>
                  <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                      <p class="text-[14px] font-semibold text-head">{{ __('payments.gateways.'.$gateway['key'].'.name') }}</p>
                      <p class="text-[12.5px] text-sub mt-1 leading-relaxed">
                        {{ __('payments.gateways.'.$gateway['key'].'.description') }}
                      </p>
                    </div>

                    {{-- Exactly one says ACTIVE, because exactly one takes the
                         money. One that is not built yet says so rather than
                         looking broken. --}}
                    @if ($isActive)
                      <span class="shrink-0 styledesk_badge styledesk_badge--active">{{ __('payments.active') }}</span>
                    @elseif (! $gateway['supported'])
                      <span class="shrink-0 styledesk_badge styledesk_badge--soon">{{ __('payments.coming_soon') }}</span>
                    @endif
                  </div>

                  @if ($gateway['ready'])
                    <label class="mt-3 flex items-center gap-2 cursor-pointer w-fit">
                      <input type="radio" name="payment_gateway" value="{{ $gateway['key'] }}" class="sd-check"
                             @checked($isActive)>
                      <span class="text-[12.5px] font-medium text-ink">{{ __('payments.use_this') }}</span>
                    </label>
                  @elseif (! $gateway['supported'] && filled(__('payments.gateways.'.$gateway['key'].'.unavailable')))
                    {{-- Said plainly, and only where it is true. An owner who
                         reads "coming soon" and an owner who reads "connect
                         your account" are being asked to do different things. --}}
                    <p class="mt-3 text-[12px] text-faint">{{ __('payments.gateways.'.$gateway['key'].'.unavailable') }}</p>
                  @endif

                  {{-- Which account, and whether Stripe will let it take money
                       yet. Connected is not the same as ready: verification
                       takes hours or days, and an account that has submitted
                       its details but not passed cannot be charged against. --}}
                  @if ($gateway['key'] === 'stripe' && $gateway['supported'])
                    <div class="mt-3 pt-3 border-t border-line">
                      @if ($stripe)
                        <div class="flex items-center justify-between gap-3">
                          <span class="min-w-0">
                            <span class="block text-[13px] font-semibold text-head truncate">
                              {{ $stripe->business_name ?: $tenant->name }}
                            </span>
                            <span class="block text-[12px] text-sub">
                              @if ($stripe->payout_last4)
                                {{ __('payments.stripe.payout_account', ['last4' => $stripe->payout_last4]) }}
                              @else
                                {{ __('payments.stripe.no_payout_account') }}
                              @endif
                            </span>
                          </span>

                          <span @class([
                              'shrink-0 styledesk_badge',
                              'styledesk_badge--active' => $stripe->statusKey() === 'connected',
                              'styledesk_badge--setup' => $stripe->statusKey() !== 'connected',
                          ])>{{ __('payments.stripe.statuses.'.$stripe->statusKey()) }}</span>
                        </div>

                        {{-- Stripe's own words for what is missing. "We need
                             more information" helps nobody; the field names
                             are what an owner has to go and supply. --}}
                        @if ($stripe->outstanding())
                          <p class="mt-2 text-[12px] text-danger">
                            {{ __('payments.stripe.outstanding', ['fields' => implode(', ', $stripe->outstanding())]) }}
                          </p>
                        @endif
                      @else
                        <p class="text-[12.5px] text-sub">{{ __('payments.stripe.not_connected') }}</p>
                      @endif

                      {{-- Where StyleDesk holds no platform keys, the only way
                           in is the business's own. Said, rather than leaving
                           an owner to wonder why there is no Connect button. --}}
                      @unless ($platformStripe)
                        <p class="mt-2 text-[12px] text-faint">{{ __('payments.stripe.platform_unavailable') }}</p>
                      @endunless

                      {{-- Which arrangement this business is on. Said,
                           because the two differ in who holds the keys and
                           where the money settles, and an owner should know
                           which they chose. --}}
                      @if ($stripe)
                        <p class="mt-2 text-[12px] text-sub">
                          {{ __('payments.stripe.modes.'.$stripe->mode) }}
                          @if ($stripe->usesOwnKeys() && $stripe->keyHint())
                            · {{ $stripe->keyHint() }}
                          @endif
                        </p>
                      @endif

                      <div class="mt-3 flex flex-wrap items-center gap-2">
                        @if ($platformStripe && (! $stripe || ! $stripe->usesOwnKeys()))
                          <a href="{{ route('settings.payments.stripe.connect') }}" class="styledesk_action styledesk_action--sm">
                            {{ $stripe ? __('payments.stripe.continue') : __('payments.stripe.connect') }}
                          </a>
                        @endif

                        @if ($stripe?->details_submitted)
                          <a href="{{ route('settings.payments.stripe.dashboard') }}"
                             class="styledesk_action styledesk_action--sm" target="_blank" rel="noopener noreferrer">
                            {{ __('payments.stripe.manage') }}
                          </a>
                        @endif

                        @if ($stripe)
                          <button type="submit" form="stripe-disconnect"
                                  class="styledesk_action styledesk_action--sm styledesk_action--danger">
                            {{ __('payments.stripe.disconnect') }}
                          </button>
                        @endif
                      </div>

                      <p class="mt-3 text-[12px] text-faint">{{ __('payments.stripe.money_note') }}</p>

                      {{-- The other way in, for a salon that already has
                           Stripe and would rather keep StyleDesk out of the
                           arrangement: their key, their account, their
                           payouts. Behind a disclosure because most businesses
                           want the first route, and a secret-key box on an
                           otherwise simple card invites pasting one in
                           without needing to. --}}
                      <details class="mt-3 group">
                        <summary class="cursor-pointer list-none text-[12.5px] font-semibold text-link hover:underline">
                          {{ $stripe?->usesOwnKeys() ? __('payments.stripe.replace_keys') : __('payments.stripe.use_own') }}
                        </summary>

                        <p class="mt-2 text-[12px] text-sub">{{ __('payments.stripe.use_own_hint') }}</p>

                        <div class="mt-3 space-y-3">
                          <div>
                            <label for="api_key" class="block text-[12.5px] font-medium text-ink mb-1.5">
                              {{ __('payments.stripe.secret_key') }}
                            </label>
                            {{-- Never pre-filled. The key is write-only from
                                 this screen: nobody needs to read it back out
                                 of StyleDesk, and a field that showed it would
                                 be a field worth attacking. --}}
                            <input id="api_key" name="api_key" type="password" class="sd-input"
                                   form="stripe-keys" autocomplete="off" placeholder="sk_live_…">
                            @error('api_key')
                              <p class="mt-1.5 text-[12px] text-danger" role="alert">{{ $message }}</p>
                            @enderror
                          </div>

                          <div>
                            <label for="publishable_key" class="block text-[12.5px] font-medium text-ink mb-1.5">
                              {{ __('payments.stripe.publishable_key') }}
                            </label>
                            <input id="publishable_key" name="publishable_key" type="text" class="sd-input"
                                   form="stripe-keys" autocomplete="off" placeholder="pk_live_…"
                                   value="{{ old('publishable_key', $stripe?->publishable_key) }}">
                          </div>

                          <button type="submit" form="stripe-keys" class="styledesk_action styledesk_action--sm">
                            {{ __('payments.stripe.save_keys') }}
                          </button>

                          <p class="text-[12px] text-faint">{{ __('payments.stripe.key_warning') }}</p>
                        </div>
                      </details>
                    </div>
                  @endif
                </div>
              @endforeach
            </div>
          </section>

          {{-- --------------------------------------------- the methods --}}
          <section class="bg-white border border-line rounded-card p-5">
            <h2 class="text-[15px] font-semibold text-head">{{ __('payments.methods') }}</h2>
            <p class="text-[13px] text-sub mt-1">{{ __('payments.methods_hint') }}</p>

            <div class="mt-3 grid sm:grid-cols-2 gap-2">
              @foreach ($methods as $method => $config)
                {{-- Its own name, not $takeable: reassigning the list to a
                     boolean inside the loop leaves the next iteration testing
                     against a bool. --}}
                @php $canTake = in_array($method, $takeable, true); @endphp

                <label @class(['flex items-start gap-2.5', 'opacity-50' => ! $canTake])>
                  <input type="checkbox" name="accepted_methods[]" value="{{ $method }}" class="sd-check mt-0.5"
                         @checked(in_array($method, $accepted, true)) @disabled(! $canTake)>
                  <span class="min-w-0">
                    <span class="block text-[13px] text-ink">{{ __('payments.method_names.'.$method) }}</span>

                    {{-- Why a method cannot be ticked, rather than a box that
                         silently refuses: nobody walks in holding an Apple
                         Pay, and an owner deserves to know that is the
                         reason. --}}
                    @unless ($canTake)
                      <span class="block text-[11.5px] text-faint">{{ __('payments.needs_processor') }}</span>
                    @endunless
                  </span>
                </label>
              @endforeach
            </div>
          </section>

          {{-- --------------------------------------------- the deposit --}}
          <section class="bg-white border border-line rounded-card p-5">
            <h2 class="text-[15px] font-semibold text-head">{{ __('payments.deposit') }}</h2>
            <p class="text-[13px] text-sub mt-1">{{ __('payments.deposit_hint') }}</p>

            <div class="mt-4 flex flex-wrap items-end gap-3">
              <div>
                <label for="default_deposit_type" class="block text-[13px] font-medium text-ink mb-1.5">
                  {{ __('payments.deposit_type') }}
                </label>
                <select id="default_deposit_type" name="default_deposit_type" class="sd-input" data-deposit-type>
                  @foreach (['none', 'fixed', 'percent'] as $type)
                    <option value="{{ $type }}" @selected(old('default_deposit_type', $depositType) === $type)>
                      {{ __('payments.deposit_types.'.$type) }}
                    </option>
                  @endforeach
                </select>
              </div>

              <div data-deposit-value @class(['hidden' => ($depositType ?? 'none') === 'none'])>
                <label for="default_deposit_value" class="block text-[13px] font-medium text-ink mb-1.5">
                  {{ __('payments.deposit_value') }}
                </label>
                <input id="default_deposit_value" name="default_deposit_value" type="number" step="0.01" min="0"
                       class="sd-input" value="{{ old('default_deposit_value', $depositValue) }}">
              </div>
            </div>

            <p class="mt-3 text-[12px] text-sub">{{ __('payments.deposit_levels') }}</p>
          </section>
        </div>

        <button type="submit"
                class="h-10 px-4 rounded-lg bg-brand hover:bg-brand-dark text-white text-[13px] font-semibold transition-colors">
          {{ __('payments.save') }}
        </button>
      </form>

      {{-- The target for the Disconnect button above. Outside the settings
           form, because a form inside a form is invalid HTML and the browser
           drops the inner one without saying so. --}}
      @if ($stripe)
        <form id="stripe-disconnect" method="POST" action="{{ route('settings.payments.stripe.disconnect') }}" class="hidden">
          @csrf
          @method('DELETE')
        </form>
      @endif

      {{-- The keys form, outside the settings form for the same reason: a form
           inside a form is invalid HTML and the browser drops the inner one. --}}
      <form id="stripe-keys" method="POST" action="{{ route('settings.payments.stripe.keys') }}" class="hidden">
        @csrf
      </form>
    </div>
  </main>
@endsection

@push('scripts')
  <script>
    (function () {
      const toggle = document.querySelector('[data-payments-enabled]');
      const options = document.querySelector('[data-payment-options]');
      const type = document.querySelector('[data-deposit-type]');
      const value = document.querySelector('[data-deposit-value]');

      /* Applied from script rather than left to the markup, so a page with no
         JavaScript shows every field: a settings screen that hides its own
         contents and cannot un-hide them is worse than one showing too much. */
      function sync() {
        options?.classList.toggle('hidden', !toggle?.checked);
        value?.classList.toggle('hidden', type?.value === 'none');
      }

      toggle?.addEventListener('change', sync);
      type?.addEventListener('change', sync);
      sync();
    }());
  </script>
@endpush
