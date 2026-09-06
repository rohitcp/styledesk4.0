@extends('layouts.app')

@section('title', $campaign->exists ? __('marketing.edit') : __('marketing.create'))

@section('content')
  <main class="w-full px-6 lg:px-8 pt-5 pb-[100px]">

    <header class="flex flex-wrap items-start gap-4">
      <div class="min-w-0 flex-1">
        <h1 class="text-[22px] sm:text-[24px] font-bold text-head tracking-tight">
          {{ $campaign->exists ? $campaign->name : __('marketing.create') }}
        </h1>
        <p class="text-[13px] text-sub mt-1.5">{{ __('marketing.details.intro') }}</p>
      </div>

      @if ($campaign->exists)
        <span class="styledesk_badge {{ $campaign->statusClass() }}">{{ $campaign->statusLabel() }}</span>
      @endif
    </header>

    @php
      $campaignProps = [
          'urls' => [
              'estimate' => route('marketing.email.estimate'),
          ],
          'audience' => $campaign->audience ?? ['scope' => 'all'],
          'options' => $options,
          'labels' => __('marketing'),
      ];
    @endphp

    <form method="POST"
          action="{{ $campaign->exists ? route('marketing.email.update', $campaign) : route('marketing.email.store') }}"
          class="mt-5 grid lg:grid-cols-3 gap-5 items-start">
      @csrf
      @if ($campaign->exists) @method('PATCH') @endif

      <div class="lg:col-span-2 space-y-5">

        {{-- Step one: what the email is, and what a client sees before they
             open it. --}}
        <section class="bg-white border border-line rounded-card p-5 sm:p-6">
          <h2 class="text-[15px] font-semibold text-head">{{ __('marketing.details.title') }}</h2>

          <div class="mt-5 grid sm:grid-cols-2 gap-x-5 gap-y-5">
            <div class="sm:col-span-2">
              <label for="name" class="block text-[13px] font-medium text-ink mb-1.5">
                {{ __('marketing.details.name') }} <span class="text-danger">*</span>
              </label>
              <input id="name" name="name" type="text" required
                     @class(['sd-input', 'is-error' => $errors->has('name')])
                     value="{{ old('name', $campaign->name) }}"
                     placeholder="{{ __('marketing.details.name_placeholder') }}">
              <p class="mt-1.5 text-[12px] text-sub">{{ __('marketing.details.name_hint') }}</p>
              @error('name') <p class="mt-1.5 text-[12px] text-danger">{{ $message }}</p> @enderror
            </div>

            <div class="sm:col-span-2">
              <label for="subject" class="block text-[13px] font-medium text-ink mb-1.5">{{ __('marketing.details.subject') }}</label>
              <input id="subject" name="subject" type="text" class="sd-input"
                     value="{{ old('subject', $campaign->subject) }}"
                     placeholder="{{ __('marketing.details.subject_placeholder') }}">
            </div>

            <div class="sm:col-span-2">
              <label for="preview_text" class="block text-[13px] font-medium text-ink mb-1.5">{{ __('marketing.details.preview_text') }}</label>
              <input id="preview_text" name="preview_text" type="text" class="sd-input"
                     value="{{ old('preview_text', $campaign->preview_text) }}"
                     placeholder="{{ __('marketing.details.preview_placeholder') }}">
              <p class="mt-1.5 text-[12px] text-sub">{{ __('marketing.details.preview_hint') }}</p>
            </div>

            <div>
              <label for="from_name" class="block text-[13px] font-medium text-ink mb-1.5">{{ __('marketing.details.from_name') }}</label>
              <input id="from_name" name="from_name" type="text" class="sd-input"
                     value="{{ old('from_name', $campaign->from_name) }}">
            </div>

            <div>
              <label for="reply_to" class="block text-[13px] font-medium text-ink mb-1.5">{{ __('marketing.details.reply_to') }}</label>
              <input id="reply_to" name="reply_to" type="email"
                     @class(['sd-input', 'is-error' => $errors->has('reply_to')])
                     value="{{ old('reply_to', $campaign->reply_to) }}">
              <p class="mt-1.5 text-[12px] text-sub">{{ __('marketing.details.reply_hint') }}</p>
              @error('reply_to') <p class="mt-1.5 text-[12px] text-danger">{{ $message }}</p> @enderror
            </div>
          </div>
        </section>

        {{-- Step two: who it goes to, and how many that is. One island,
             because the rules and the count are the same question — the point
             of the step is watching the number move as the rules change. --}}
        <div data-vue-component="CampaignAudience" data-props='@json($campaignProps)'></div>
      </div>

      <aside class="space-y-5">
        <section class="bg-white border border-line rounded-card p-5">
          <div class="flex flex-wrap items-center gap-3">
            <button type="submit" data-submit-once
                    class="inline-flex items-center h-10 px-5 rounded-lg bg-brand hover:bg-brand-dark text-white text-[13px] font-semibold transition-colors">
              {{ __('marketing.save_draft') }}
            </button>

            <a href="{{ route('marketing.email.index') }}" class="text-[13px] font-semibold text-sub hover:text-ink">
              {{ __('marketing.cancel') }}
            </a>
          </div>
        </section>

        {{-- What this screen does not do yet, said plainly. A workflow that
             shows four steps and answers two is one somebody gets halfway
             through before finding out. --}}
        <section class="bg-white border border-line rounded-card p-5">
          <h2 class="text-[14px] font-semibold text-head">{{ __('marketing.next.title') }}</h2>
          <p class="text-[12.5px] text-sub mt-1 leading-relaxed">{{ __('marketing.next.intro') }}</p>

          <ul class="mt-3 space-y-2">
            @foreach (['design', 'preview', 'send'] as $step)
              <li class="flex items-center gap-2 text-[13px] text-sub">
                <span class="h-1.5 w-1.5 rounded-full bg-faint" aria-hidden="true"></span>
                {{ __('marketing.next.'.$step) }}
                <span class="styledesk_badge styledesk_badge--soon ms-auto">{{ __('marketing.next.soon') }}</span>
              </li>
            @endforeach
          </ul>
        </section>
      </aside>
    </form>

    @if ($campaign->exists && $campaign->status === 'draft')
      <form method="POST" action="{{ route('marketing.email.destroy', $campaign) }}" class="mt-5"
            data-confirm="{{ __('marketing.delete_confirm') }}">
        @csrf
        @method('DELETE')
        <button type="submit" class="styledesk_action styledesk_action--danger">{{ __('marketing.delete') }}</button>
      </form>
    @endif
  </main>
@endsection
