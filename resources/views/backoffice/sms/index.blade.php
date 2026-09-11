@extends('layouts.backoffice')

@section('title', __('backoffice.nav.sms'))

{{--
    Back Office → SMS.

    The platform's own carrier account: which company carries every business's
    messages, the keys to reach them, and two ways to prove it works before a
    salon finds out it does not.

    Not a salon's screen. A business decides what it sends (App Settings →
    SMS); this decides what carries it and whose account it is billed to.
--}}

@section('content')

    <h1 class="text-[22px] font-bold text-head tracking-tight">{{ __('backoffice.sms.title') }}</h1>
    <p class="text-[13px] text-sub mt-1.5 leading-relaxed max-w-[620px]">{{ __('backoffice.sms.intro') }}</p>

    @if (session('error'))
        <p class="sd-alert sd-alert--danger mt-4 text-[13px]" role="alert">{{ session('error') }}</p>
    @endif

    {{-- What the application will actually do right now.

         Not always what the form below says: a developer's machine never
         reaches a carrier whatever is configured here, and somebody reading
         "Provider: Telnyx" on a laptop deserves to know that nothing they
         send will leave it. --}}
    <div class="sd-card p-5 mt-5">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="min-w-0">
                <p class="styledesk_eyebrow">{{ __('backoffice.sms.in_effect') }}</p>
                <p class="text-[17px] font-semibold text-head mt-1">
                    {{ __('backoffice.sms.providers.'.$resolved) }}
                    @if ($sender)
                        <span class="text-sub font-normal tabular-nums">· {{ $sender }}</span>
                    @endif
                </p>
            </div>

            @unless ($live)
                <p class="shrink-0 max-w-[320px] text-[12px] text-sub bg-hover rounded-lg px-3 py-2">
                    {{ __('backoffice.sms.local_note') }}
                </p>
            @endunless
        </div>
    </div>

    <form method="POST" action="{{ route('backoffice.sms.update') }}" class="mt-5 space-y-5">
        @csrf
        @method('PATCH')

        <section class="sd-card p-5">
            <h2 class="text-[15px] font-semibold text-head">{{ __('backoffice.sms.service') }}</h2>

            <label class="flex items-start gap-3 mt-3">
                <input type="checkbox" name="is_enabled" value="1" class="mt-0.5"
                       @checked(old('is_enabled', $settings->is_enabled))>
                <span class="min-w-0">
                    <span class="block text-[13px] font-medium text-ink">{{ __('backoffice.sms.enable') }}</span>
                    <span class="block text-[12px] text-sub mt-0.5">{{ __('backoffice.sms.enable_hint') }}</span>
                </span>
            </label>

            <div class="mt-4 max-w-[280px]">
                <label for="provider" class="block text-[13px] font-medium text-ink mb-1.5">
                    {{ __('backoffice.sms.provider') }}
                </label>
                <select id="provider" name="provider" class="sd-input">
                    @foreach ($providers as $option)
                        <option value="{{ $option }}" @selected(old('provider', $settings->provider) === $option)>
                            {{ __('backoffice.sms.providers.'.$option) }}
                        </option>
                    @endforeach
                </select>
                @error('provider')<p class="mt-1.5 text-[12px] text-danger" role="alert">{{ $message }}</p>@enderror
            </div>
        </section>

        {{-- Each carrier's own account.

             A secret already stored is never sent back to the browser — that
             is the point of encrypting it — so an empty box means "leave it
             alone", not "clear it". Otherwise changing a sender number would
             wipe a working key. --}}
        @foreach (['telnyx' => ['key', 'public_key', 'from'], 'clicksend' => ['username', 'key', 'webhook_secret', 'from']] as $carrier => $fields)
            <section class="sd-card p-5">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h2 class="text-[15px] font-semibold text-head">{{ __('backoffice.sms.providers.'.$carrier) }}</h2>

                    <span class="styledesk_badge {{ $settings->isReady($carrier) ? 'styledesk_badge--active' : 'styledesk_badge--soon' }}">
                        {{ $settings->isReady($carrier) ? __('backoffice.sms.ready') : __('backoffice.sms.not_configured') }}
                    </span>
                </div>

                <div class="grid sm:grid-cols-2 gap-4 mt-4">
                    @foreach ($fields as $field)
                        @php
                            $name = $carrier.'_'.$field;
                            $secret = ! in_array($field, ['from'], true);
                        @endphp

                        <div>
                            <label for="{{ $name }}" class="block text-[13px] font-medium text-ink mb-1.5">
                                {{ __('backoffice.sms.fields.'.$field) }}
                            </label>

                            <input id="{{ $name }}" name="{{ $name }}"
                                   type="{{ $secret ? 'password' : 'tel' }}"
                                   autocomplete="off"
                                   class="sd-input @error($name) is-error @enderror"
                                   value="{{ $secret ? '' : old($name, $settings->{$name}) }}"
                                   placeholder="{{ $secret && filled($settings->{$name}) ? '••••••••••••' : '' }}">

                            @if ($secret)
                                <p class="mt-1.5 text-[12px] text-sub">
                                    {{ filled($settings->{$name}) ? __('backoffice.sms.stored') : __('backoffice.sms.not_set') }}
                                </p>
                            @endif

                            @error($name)<p class="mt-1.5 text-[12px] text-danger" role="alert">{{ $message }}</p>@enderror
                        </div>
                    @endforeach
                </div>
            </section>
        @endforeach

        <button type="submit" class="h-10 px-5 rounded-lg bg-brand hover:bg-brand-dark text-white text-[13px] font-semibold transition-colors">
            {{ __('common.save') }}
        </button>
    </form>

    {{-- Two ways to prove it, and they cost different amounts.

         The connection test is a read: no message, no charge, and it tells a
         wrong key from a wrong number. The message test is real money and a
         real handset — except on a developer's machine, where it goes to the
         SMS catcher like everything else. --}}
    <section class="sd-card p-5 mt-5">
        <h2 class="text-[15px] font-semibold text-head">{{ __('backoffice.sms.testing') }}</h2>
        <p class="text-[12.5px] text-sub mt-1 leading-relaxed max-w-[560px]">{{ __('backoffice.sms.testing_hint') }}</p>

        <div class="flex flex-wrap items-end gap-3 mt-4">
            <form method="POST" action="{{ route('backoffice.sms.test-connection') }}">
                @csrf
                <button type="submit" class="h-10 px-4 rounded-lg border border-line text-[13px] font-semibold text-ink hover:bg-hover transition-colors">
                    {{ __('backoffice.sms.test_connection') }}
                </button>
            </form>

            <form method="POST" action="{{ route('backoffice.sms.test-message') }}" class="flex flex-wrap items-end gap-2.5">
                @csrf

                <div>
                    <label for="to" class="block text-[13px] font-medium text-ink mb-1.5">{{ __('backoffice.sms.send_to') }}</label>
                    <input id="to" name="to" type="tel" required
                           class="sd-input w-[220px] @error('to') is-error @enderror"
                           value="{{ old('to') }}" placeholder="+1 201 555 0101">
                </div>

                <button type="submit" class="h-10 px-4 rounded-lg bg-brand hover:bg-brand-dark text-white text-[13px] font-semibold transition-colors">
                    {{ __('backoffice.sms.send_test') }}
                </button>
            </form>
        </div>

        @error('to')<p class="mt-2 text-[12px] text-danger" role="alert">{{ $message }}</p>@enderror

        <p class="mt-3 text-[12px] rounded-lg px-3 py-2 {{ $live ? 'text-danger bg-danger/5' : 'text-sub bg-hover' }}">
            {{ $live ? __('backoffice.sms.costs_money') : __('backoffice.sms.local_note') }}
        </p>
    </section>
@endsection
