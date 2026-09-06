@extends('layouts.app')

@section('title', __('account.notifications.title'))

@section('content')
<x-account.shell current="notifications" :title="__('account.notifications.title')" :intro="__('account.notifications.intro')">

  <form method="POST" action="{{ route('account.notifications.update') }}" class="space-y-5 max-w-[900px]" id="notificationsForm">
    @csrf
    @method('PATCH')

    {{-- The two bulk controls. Buttons rather than links, and they act on the
         checkboxes in the page rather than posting on their own: the reader
         still sees what they did and still has to save it, so "Enable all"
         cannot silently commit a hundred changes. --}}
    <div class="flex flex-wrap items-center gap-2.5">
      <button type="button" class="styledesk_action" data-notify-all="1">{{ __('account.notifications.enable_all') }}</button>
      <button type="button" class="styledesk_action" data-notify-all="0">{{ __('account.notifications.disable_all') }}</button>
      <p class="text-[12px] text-sub">{{ __('account.notifications.bulk_hint') }}</p>
    </div>

    @foreach ($groups as $group)
      <section class="bg-white border border-line rounded-card p-5 sm:p-6">
        <h3 class="text-[15px] font-semibold text-head">{{ $group['label'] }}</h3>
        <p class="text-[13px] text-sub mt-1 max-w-[620px]">{{ $group['description'] }}</p>

        {{-- A table because it is one: notifications down, channels across.
             Below sm the CSS turns each row into a card with its channels
             listed underneath — four columns of checkboxes do not survive a
             phone. --}}
        <table class="sd-matrix mt-5">
          <thead>
            <tr>
              <th scope="col">{{ $group['label'] }}</th>
              @foreach ($channels as $key => $channel)
                <th scope="col">
                  {{ $channel['label'] }}
                  @unless ($channel['available'])
                    <span class="block sd-matrix__later font-normal normal-case tracking-normal">{{ __('account.notifications.coming_soon') }}</span>
                  @endunless
                </th>
              @endforeach
            </tr>
          </thead>

          <tbody>
            @foreach ($group['types'] as $type)
              <tr>
                <td>
                  <span class="inline-flex items-center gap-1.5">
                    {{ $type['label'] }}

                    {{-- What actually sets it off. A button rather than a
                         bare icon so the keyboard reaches it — the tooltip
                         script answers focus as well as hover — and the same
                         sentence is in the DOM for a screen reader, which
                         cannot hover anything. --}}
                    @if ($type['description'])
                      <button type="button" class="sd-matrix__why" data-tip="{{ $type['description'] }}"
                              aria-label="{{ $type['label'] }}">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                          <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.8"/>
                          <path d="M12 11v5" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"/>
                          <circle cx="12" cy="7.75" r="1.1" fill="currentColor"/>
                        </svg>
                        <span class="sr-only">{{ $type['description'] }}</span>
                      </button>
                    @endif
                  </span>

                  @if ($type['critical'])
                    <span class="block sd-matrix__later">{{ __('account.notifications.always_on') }}</span>
                  @endif
                </td>

                @foreach ($channels as $key => $channel)
                  @php $cell = $type['channels'][$key]; @endphp
                  <td>
                    <span class="sd-matrix__label">{{ $channel['label'] }}</span>

                    @if (! $cell['supported'] || ! $channel['available'])
                      {{-- Drawn, disabled, and given a name a screen reader can
                           read: an empty cell says nothing about why. --}}
                      <input type="checkbox" class="sd-check" disabled
                             aria-label="{{ $type['label'].' — '.$channel['label'] }}"
                             title="{{ $channel['available'] ? __('account.notifications.not_supported') : __('account.notifications.coming_soon') }}">
                    @elseif ($cell['locked'])
                      {{-- Checked and disabled, so the screen says the message
                           will be sent. A disabled box posts nothing, which is
                           right: the server never stores a critical type. --}}
                      <input type="checkbox" class="sd-check" checked disabled
                             aria-label="{{ $type['label'].' — '.$channel['label'] }}"
                             title="{{ __('account.notifications.always_on') }}">
                    @else
                      {{-- The unchecked value first, so a switched-off box
                           posts "off" rather than saying nothing and leaving
                           the server to guess. --}}
                      <input type="hidden" name="notifications[{{ $type['key'] }}][{{ $key }}]" value="0">
                      <input type="checkbox" class="sd-check" data-notify-box
                             name="notifications[{{ $type['key'] }}][{{ $key }}]" value="1"
                             aria-label="{{ $type['label'].' — '.$channel['label'] }}"
                             @checked($cell['enabled'])>
                    @endif
                  </td>
                @endforeach
              </tr>
            @endforeach
          </tbody>
        </table>
      </section>
    @endforeach

    <div class="flex flex-wrap items-center gap-3">
      <button type="submit" data-submit-once
              class="inline-flex items-center h-11 px-6 rounded-lg bg-brand hover:bg-brand-dark text-white text-[14px] font-semibold transition-colors">
        {{ __('account.notifications.save') }}
      </button>
    </div>
  </form>

  <form method="POST" action="{{ route('account.notifications.reset') }}" class="mt-5 max-w-[900px]">
    @csrf
    <button type="submit"
            data-confirm="{{ __('account.reset_confirm') }}"
            data-confirm-label="{{ __('account.reset') }}"
            data-confirm-tone="brand"
            class="h-11 px-4 inline-flex items-center rounded-lg text-[13px] font-semibold text-sub hover:text-ink hover:bg-hover transition-colors">
      {{ __('account.reset') }}
    </button>
  </form>
</x-account.shell>
@endsection

@push('scripts')
<script>
    /* Enable all / disable optional.
       Only the boxes that are actually switchable are touched — a disabled
       one is either a channel that does not exist yet or a security alert,
       and neither is the reader's to change. */
    document.addEventListener('click', function (event) {
        var trigger = event.target.closest('[data-notify-all]');
        if (!trigger) return;

        var on = trigger.dataset.notifyAll === '1';

        document.querySelectorAll('[data-notify-box]').forEach(function (box) {
            box.checked = on;
        });
    });
</script>
@endpush
