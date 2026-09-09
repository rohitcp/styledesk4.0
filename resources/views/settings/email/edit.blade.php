{{--
    App Settings → Email.

    One switch and two provider cards. The switch is the point: a salon that
    has not set a sender up should not be able to put mail in a client's inbox
    by accident, so the feature is opted into rather than out of.

    The language throughout is the owner's, not the plumbing's — "Connect
    Gmail", never "Gmail SMTP". They are connecting their email account, not
    configuring a mail server.
--}}
@extends('layouts.app')

@section('title', __('client_email.title'))

@section('content')
  <main class="w-full px-4 sm:px-5 lg:px-6 pt-5 sm:pt-6 pb-[200px]">
    <div class="styledesk_form">

      <nav class="text-[13px] text-sub" aria-label="Breadcrumb">
        <a href="{{ route('settings.index') }}" class="hover:text-ink transition-colors">{{ __('navigation.app_settings') }}</a>
        <span class="mx-1.5 text-faint">/</span>
        <span class="text-ink">{{ __('client_email.title') }}</span>
      </nav>

      <div class="mt-3 flex flex-wrap items-start gap-4">
        <div class="min-w-0 flex-1">
          <h1 class="text-[24px] sm:text-[28px] font-bold text-head tracking-tight">{{ __('client_email.title') }}</h1>
          <p class="text-[14px] text-sub mt-2 max-w-[640px] leading-relaxed">{{ __('client_email.intro') }}</p>
        </div>

        <a href="{{ route('settings.index') }}" class="styledesk_action shrink-0">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M14 6l-6 6 6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
          {{ __('common.back') }}
        </a>
      </div>

      @if (session('status'))
        <div class="sd-alert sd-alert--info mt-5" role="status">
          <p class="min-w-0">{{ session('status') }}</p>
        </div>
      @endif

      @if ($errors->any())
        <div class="sd-alert sd-alert--danger mt-5" role="alert">
          <p class="min-w-0">{{ $errors->first() }}</p>
        </div>
      @endif

      <form method="POST" action="{{ route('settings.email.update') }}" class="mt-5 space-y-4">
        @csrf
        @method('PATCH')

        {{-- ------------------------------------------------- the switch --}}
        {{-- The house toggle, and the only control on the page until it is on.
             Everything below configures sending, and a business that has not
             decided to send should not be reading eight fields about how. --}}
        <section class="bg-white border border-line rounded-card p-5">
          {{-- The hidden zero is what makes "off" a value rather than an
               absence: an unchecked box sends nothing, and the request would
               look identical to one that never touched the switch. --}}
          <input type="hidden" name="client_email_enabled" value="0">

          <label class="styledesk_toggle">
            <input type="checkbox" name="client_email_enabled" value="1" class="styledesk_toggle__input"
                   data-email-enabled
                   @checked(old('client_email_enabled', $tenant->client_email_enabled))>
            <span class="styledesk_toggle__track" aria-hidden="true">
              <span class="styledesk_toggle__knob"></span>
            </span>
            <span class="min-w-0 flex-1">
              <span class="styledesk_toggle__label">{{ __('client_email.settings.enable') }}</span>
              <span class="styledesk_toggle__hint">{{ __('client_email.settings.enable_hint') }}</span>
            </span>
          </label>
        </section>

        {{-- Revealed by the switch above. `hidden` rather than removed: the
             fields keep their values, so switching off and on again does not
             quietly blank a reply-to address somebody typed last month.

             Without JavaScript nothing is hidden at all — see the script at
             the foot of the page — because a settings screen that shows
             nothing is worse than one that shows everything. --}}
        <div data-email-options @class(['space-y-4', 'hidden' => ! old('client_email_enabled', $tenant->client_email_enabled)])>

        {{-- ---------------------------------------------- the two cards --}}
        <section class="bg-white border border-line rounded-card p-5">
          <h2 class="text-[15px] font-semibold text-head">{{ __('client_email.settings.default_method') }}</h2>
          <p class="text-[13px] text-sub mt-1">{{ __('client_email.settings.default_hint') }}</p>

          <div class="mt-4 grid sm:grid-cols-2 gap-3">
            @foreach ($providers as $key => $provider)
              @php
                  $available = $provider['available'] ?? false;
                  $isActive = old('email_provider', $activeProvider) === $key;
              @endphp

              {{-- A div, not a label. The card carries a Connect link and a
                   Disconnect button, and a label around them would swallow
                   every click into the radio. The radio keeps its own small
                   label below. --}}
              <div @class([
                  'block rounded-card border p-4 transition-colors',
                  'border-brand bg-brand/[0.04]' => $isActive,
                  'border-line' => ! $isActive,
                  'opacity-60' => ! $available,
              ])>
                <span class="flex items-start justify-between gap-3">
                  <span class="min-w-0">
                    <span class="block text-[14px] font-semibold text-head">
                      {{ __('client_email.providers.'.$key.'.name') }}
                    </span>
                    <span class="block text-[12.5px] text-sub mt-1 leading-relaxed">
                      {{ __('client_email.providers.'.$key.'.description') }}
                    </span>
                  </span>

                  {{-- Exactly one card says ACTIVE, because exactly one
                       provider sends. A card that is not yet buildable says
                       so rather than looking broken. --}}
                  @if ($isActive)
                    <span class="shrink-0 styledesk_badge styledesk_badge--active">{{ __('client_email.settings.active') }}</span>
                  @elseif (! $available)
                    <span class="shrink-0 styledesk_badge styledesk_badge--soon">{{ __('client_email.settings.coming_soon') }}</span>
                  @endif
                </span>

                @if ($available)
                  <label class="mt-3 flex items-center gap-2 cursor-pointer w-fit">
                    <input type="radio" name="email_provider" value="{{ $key }}" class="sd-check"
                           @checked($isActive)>
                    <span class="text-[12.5px] font-medium text-ink">{{ __('client_email.settings.use_this') }}</span>
                  </label>
                @endif

                {{-- Which mailbox, and whether it still works.
                     Three states told apart on purpose — never connected,
                     connected, connected and broken — because what the owner
                     has to do differs in each, and "Disconnected" shown to
                     somebody whose token merely lapsed sends them round the
                     whole handshake for nothing. --}}
                @if ($key === 'gmail' && $available)
                  <div class="mt-3 pt-3 border-t border-line">
                    @if ($gmail)
                      <div class="flex items-center justify-between gap-3">
                        <span class="min-w-0">
                          <span class="block text-[13px] font-semibold text-head truncate">{{ $gmail->email }}</span>
                          <span class="block text-[12px] text-sub">
                            {{ __('client_email.gmail.connected_on', [
                                'date' => \App\Support\TimeFormat::dateTime($gmail->connected_at),
                            ]) }}
                          </span>
                        </span>

                        <span @class([
                            'shrink-0 styledesk_badge',
                            'styledesk_badge--active' => $gmail->isUsable(),
                            'styledesk_badge--setup' => ! $gmail->isUsable(),
                        ])>{{ $gmail->statusLabel() }}</span>
                      </div>

                      @if (! $gmail->isUsable())
                        <p class="text-[12px] text-danger mt-1.5">{{ __('client_email.errors.reconnect_gmail') }}</p>
                      @endif
                    @else
                      <p class="text-[12.5px] text-sub">{{ __('client_email.connection.disconnected') }}</p>
                    @endif

                    {{-- The act, on the card it is about.
                         It used to sit at the foot of the page, below Save,
                         which meant choosing Gmail offered no visible way to
                         authorise it — the button existed and nobody could
                         find it. --}}
                    @if ($canConnectGmail)
                      <div class="mt-3 flex flex-wrap items-center gap-2">
                        {{-- A link, not a submit: this leaves for Google, and
                             it must not carry the unsaved settings form with
                             it. --}}
                        <a href="{{ route('settings.email.gmail.connect') }}"
                           class="styledesk_action styledesk_action--sm">
                          <x-icon name="envelope" size="13" />
                          {{ $gmail ? __('client_email.gmail.reconnect') : __('client_email.gmail.connect') }}
                        </a>

                        @if ($gmail && $canDisconnectGmail)
                          {{-- Bound to a form rendered outside this one: a
                               form inside a form is invalid HTML, and the
                               browser drops the inner one silently. --}}
                          <button type="submit" form="gmail-disconnect"
                                  class="styledesk_action styledesk_action--sm styledesk_action--danger">
                            {{ __('client_email.gmail.disconnect') }}
                          </button>
                        @endif
                      </div>
                    @endif

                    {{-- Said plainly, because it is the one thing about this
                         feature an owner will otherwise discover by waiting
                         for a reply that never appears in StyleDesk. --}}
                    <p class="text-[12px] text-sub mt-3 pt-2 border-t border-line">
                      {{ __('client_email.gmail.replies_note') }}
                    </p>
                  </div>
                @endif
              </div>
            @endforeach
          </div>
        </section>

        {{-- ------------------------------------------------- the sender --}}
        <section class="bg-white border border-line rounded-card p-5">
          <h2 class="text-[15px] font-semibold text-head">{{ __('client_email.settings.sender') }}</h2>
          <p class="text-[13px] text-sub mt-1">{{ __('client_email.settings.sender_hint') }}</p>

          <div class="mt-4 space-y-4">
            <div>
              <label for="email_sender_name" class="block text-[13px] font-medium text-ink mb-1.5">
                {{ __('client_email.settings.sender_name') }}
              </label>
              <input id="email_sender_name" name="email_sender_name" type="text" class="sd-input" maxlength="120"
                     placeholder="{{ $tenant->name }}"
                     value="{{ old('email_sender_name', $tenant->email_sender_name) }}">
              <p class="mt-1.5 text-[12px] text-sub">{{ __('client_email.settings.sender_name_hint') }}</p>
            </div>

            <div>
              <label for="email_reply_to" class="block text-[13px] font-medium text-ink mb-1.5">
                {{ __('client_email.settings.reply_to') }}
              </label>
              <input id="email_reply_to" name="email_reply_to" type="email" class="sd-input" maxlength="255"
                     value="{{ old('email_reply_to', $tenant->email_reply_to) }}">
              <p class="mt-1.5 text-[12px] text-sub">{{ __('client_email.settings.reply_to_hint') }}</p>
              {{-- On Gmail the message comes FROM the connected mailbox, so a
                   reply lands there on its own and this field does not apply.
                   Said rather than hidden: a field that silently stops mattering
                   is one somebody fills in and then wonders about. --}}
              @if ($activeProvider === 'gmail')
                <p class="mt-1 text-[12px] text-sub">{{ __('client_email.settings.reply_to_gmail') }}</p>
              @endif
            </div>

            {{-- What a client actually sees. Said here because "Smile Spa via
                 StyleDesk" surprises an owner who expected their own address,
                 and this is the screen where they can do something about it. --}}
            <div class="rounded-card border border-line bg-[#fbfbfc] p-4">
              <p class="text-[12px] text-sub">{{ __('client_email.settings.preview') }}</p>
              <p class="text-[13.5px] font-semibold text-head mt-1">
                @if ($activeProvider === 'gmail' && $gmail?->isUsable())
                  {{ old('email_sender_name', $senderName) }} &lt;{{ $gmail->email }}&gt;
                @else
                  {{ __('client_email.send.from_via', ['name' => old('email_sender_name', $senderName)]) }}
                @endif
              </p>
              @if ($tenant->email_reply_to)
                <p class="text-[12.5px] text-sub mt-0.5">
                  {{ __('client_email.settings.reply_to') }}: {{ $tenant->email_reply_to }}
                </p>
              @endif
            </div>
          </div>
        </section>

        </div>

        <div class="flex flex-wrap items-center gap-3">
          <button type="submit"
                  class="h-10 px-4 rounded-lg bg-brand hover:bg-brand-dark text-white text-[13px] font-semibold transition-colors">
            {{ __('client_email.settings.save') }}
          </button>
        </div>
      </form>

      {{-- The target for the Disconnect button on the card above.
           Rendered here, outside the settings form, because a form inside a
           form is invalid HTML and the browser drops the inner one without
           saying so. The button reaches it by id. --}}
      @if (($providers['gmail']['available'] ?? false) && $gmail && $canDisconnectGmail)
        <form id="gmail-disconnect" method="POST" action="{{ route('settings.email.gmail.disconnect') }}" class="hidden">
          @csrf
          @method('DELETE')
        </form>
      @endif

      {{-- Its own form: a test is an action, not a setting, and posting it
           through the save would make "check this works" indistinguishable
           from "change this". --}}
      <form method="POST" action="{{ route('settings.email.test') }}" data-email-test
            @class(['mt-4', 'hidden' => ! old('client_email_enabled', $tenant->client_email_enabled)])>
        @csrf
        <section class="bg-white border border-line rounded-card p-5 flex flex-wrap items-center justify-between gap-4">
          <div class="min-w-0">
            <h2 class="text-[15px] font-semibold text-head">{{ __('client_email.settings.send_test') }}</h2>
            <p class="text-[13px] text-sub mt-1">{{ __('client_email.settings.test_hint') }}</p>
          </div>

          <button type="submit" class="styledesk_action shrink-0">
            {{ __('client_email.settings.send_test') }}
          </button>
        </section>
      </form>
    </div>
  </main>
@endsection

@push('scripts')
  <script>
    /*
     * Show the sending options only once the feature is on.
     *
     * Applied from script rather than left to the markup so that a page with
     * no JavaScript shows every field: a settings screen that hides its own
     * contents and cannot un-hide them is worse than one that shows too much.
     */
    (function () {
      const toggle = document.querySelector('[data-email-enabled]');
      const options = document.querySelectorAll('[data-email-options], [data-email-test]');

      if (!toggle) {
        return;
      }

      function sync() {
        options.forEach(function (section) {
          section.classList.toggle('hidden', !toggle.checked);
        });
      }

      toggle.addEventListener('change', sync);
      sync();
    }());
  </script>
@endpush
