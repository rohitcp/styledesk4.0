@extends('layouts.app')

@section('title', __('leads.title'))

@section('content')
  {{-- Leads: bookings that were started and not finished.
       Read as a list of calls to return rather than as a view of the diary,
       which is why it is ordered by when the conversation happened and not
       by the date somebody was hoping for. --}}
  <main class="w-full px-6 lg:px-8 pt-4 pb-[100px]">

    <header class="flex flex-wrap items-start gap-4">
      <div class="min-w-0 flex-1">
        <h1 class="text-[22px] sm:text-[24px] font-bold text-head tracking-tight">{{ __('leads.title') }}</h1>
        <p class="text-[13px] text-sub mt-1.5">{{ __('leads.intro') }}</p>
      </div>

      <a href="{{ route('bookings.create') }}"
         class="shrink-0 inline-flex items-center gap-1.5 h-9 px-3.5 rounded-lg bg-brand hover:bg-brand-dark text-white text-[13px] font-semibold transition-colors">
        <x-icon name="plus" size="14" />
        {{ __('bookings.new') }}
      </a>
    </header>

    @if (! $hasLeads)
      {{-- Nothing at all, which is a different fact from nothing matching. A
           business with no leads has not lost any calls, so the empty state
           says what a lead is rather than apologising for the emptiness. --}}
      <div class="mt-6 border-t border-line py-16 text-center">
        <p class="text-[15px] font-semibold text-head">{{ __('leads.none_yet') }}</p>
        <p class="text-[13px] text-sub mt-1.5 max-w-[420px] mx-auto">{{ __('leads.none_yet_hint') }}</p>
      </div>
    @else

    <form method="GET" action="{{ route('bookings.leads') }}" class="mt-4">
      <div class="flex flex-wrap items-start gap-2">
        <div class="relative w-full lg:w-auto lg:flex-1 lg:min-w-[220px]">
          <span class="styledesk_input__prefix pointer-events-none" aria-hidden="true">
            <x-icon name="magnifying-glass" size="15" />
          </span>
          <input name="search" type="search" class="sd-input styledesk_input--prefixed"
                 value="{{ $filters['search'] }}"
                 aria-label="{{ __('leads.search_label') }}"
                 placeholder="{{ __('leads.search') }}">
        </div>

        <div class="flex flex-col lg:flex-row lg:flex-wrap items-stretch lg:items-start gap-2 w-full lg:w-auto">
          <x-combo name="status"
                   {{-- Only what a person chooses. Payment pending and
                        expired are set by the system, and a filter offering
                        them invites a search that finds nothing. --}}
                   :options="collect(config('bookings.lead_statuses'))
                       ->filter(fn (array $status) => $status['mvp'])
                       ->keys()
                       ->mapWithKeys(fn (string $key) => [$key => __('leads.statuses.'.$key.'.label')])"
                   :selected="$filters['status']"
                   :placeholder="__('leads.all_statuses')"
                   class="w-full lg:w-[190px] shrink-0" />

          <button type="submit" class="styledesk_search w-full lg:w-auto shrink-0">
            {{ __('common.search') }}
          </button>

          @if (collect($filters)->filter()->isNotEmpty())
            <a href="{{ route('bookings.leads') }}" class="styledesk_action w-full lg:w-auto shrink-0">
              {{ __('bookings.filters.reset') }}
            </a>
          @endif
        </div>
      </div>
    </form>

    <p class="mt-3 text-[12px] font-semibold text-sub" data-result-count></p>

    @php
        $gridConfig = [
            'labels' => [
                'actions_for' => __('bookings.actions_for', ['name' => ':name']),
                'showing' => __('leads.showing'),
                'results' => [
                    'zero' => __('leads.results.zero'),
                    'one' => __('leads.results.one'),
                    'many' => __('leads.results.many'),
                ],
                'clear_filters' => __('bookings.results.clear'),
                'empty' => __('leads.empty'),
            ],
            'name_field' => 'name',
            'page_size' => 25,
            'columns' => [
                ['field' => 'name', 'title' => __('leads.columns.who'), 'type' => 'primary', 'grow' => 2.4, 'min' => 200, 'responsive' => 0],
                ['field' => 'services', 'title' => __('leads.columns.services'), 'grow' => 2.2, 'min' => 170, 'responsive' => 3],
                ['field' => 'expected', 'title' => __('leads.columns.expected'), 'grow' => 1.2, 'min' => 120, 'responsive' => 2],
                ['field' => 'total', 'title' => __('leads.columns.total'), 'width' => 110, 'responsive' => 4],
                ['field' => 'started', 'title' => __('leads.columns.started'), 'grow' => 1.4, 'min' => 150, 'responsive' => 3],
                ['field' => 'step', 'title' => __('leads.columns.step'), 'grow' => 1.4, 'min' => 140, 'responsive' => 1],
                ['field' => 'status', 'title' => __('leads.columns.status'), 'type' => 'badge', 'width' => 150, 'responsive' => 0],
            ],
        ];
    @endphp

    <div class="mt-2 styledesk_gridframe">
      <div data-grid
           data-url="{{ route('bookings.leads.data', array_filter($filters)) }}"
           data-config='@json($gridConfig)'></div>
    </div>

    {{-- The lead, over the listing rather than instead of it.
         A front desk reads three or four of these in a row while somebody is
         on hold, and a detail page would throw away the filters, the page and
         the scroll position every time. Rendered here and filled in by
         script: the shell is the same for every lead, and only the contents
         are worth a round trip. --}}
    @php
        $sheetLabels = \Illuminate\Support\Arr::only(__('leads.drawer'), [
            'not_selected', 'summary', 'client', 'booking', 'payment', 'journey',
            'activity', 'no_activity', 'stopped_at', 'view_client',
        ]) + [
            'complete' => __('leads.actions.complete'),
            'view_booking' => __('leads.actions.view_booking'),
            'choose_reason' => __('leads.cancel.choose_reason'),
            'cancelled' => __('leads.cancel.cancelled'),
            'cancel_failed' => __('leads.cancel.failed'),
            'notes_empty' => __('leads.notes.empty'),
            'notes_saved' => __('leads.notes.saved'),
            'notes_needs_client' => __('leads.notes.needs_client'),
        ];
    @endphp

    <div class="styledesk_sheet" data-lead-sheet data-labels='@json($sheetLabels)' hidden>
      <aside class="styledesk_sheet__panel" role="dialog" aria-modal="true"
             aria-labelledby="leadSheetName">
        <header class="styledesk_sheet__head">
          <div class="min-w-0">
            <p id="leadSheetName" class="text-[15px] font-bold text-head truncate" data-sheet-name></p>
            <p class="text-[12px] font-medium text-sub" data-sheet-reference></p>
          </div>

          {{-- Where it stands, on the name's own row and in the part of the
               panel that does not scroll: what happened and where they
               stopped are the two facts a reader needs at every point in the
               body below. --}}
          <div class="ml-auto shrink-0 text-right">
            <span class="styledesk_badge" data-sheet-status></span>
            <span class="block text-[11.5px] text-sub mt-1" data-sheet-step></span>
          </div>

          <button type="button" class="styledesk_sheet__close" data-sheet-close
                  aria-label="{{ __('leads.drawer.close') }}">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true">
              <path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"/>
            </svg>
          </button>
        </header>

        {{-- The only part that scrolls. Head and footer stay put, because
             the reference at the top and the way on at the bottom are what a
             reader needs at every point in between. --}}
        <div class="styledesk_sheet__body" data-sheet-body></div>

        {{-- Notes, over the card rather than beside it. Written while the
             call is happening, so it covers what it is about — and the card
             underneath is still where it came from when it closes. --}}
        <div class="styledesk_sheet__notes" data-notes-panel hidden>
          {{-- Back rather than a second close: this panel is over the lead,
               not instead of it, and the way out of it is the way back to
               what it is about. --}}
          <div class="styledesk_sheet__head items-center">
            <button type="button" class="styledesk_sheet__back" data-notes-close>
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <path d="M14 6l-6 6 6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
              </svg>
              {{ __('common.back') }}
            </button>

            <p class="text-[14px] font-semibold text-head">{{ __('leads.notes.title') }}</p>
          </div>

          <div class="styledesk_sheet__body">
            <label for="leadNote" class="block text-[13px] font-medium text-ink mb-1.5">
              {{ __('leads.notes.write') }}
            </label>
            <textarea id="leadNote" rows="4" class="sd-input h-auto py-2.5" data-notes-input
                      placeholder="{{ __('leads.notes.placeholder') }}"></textarea>
            <p class="mt-1.5 text-[12px] text-sub">{{ __('leads.notes.hint') }}</p>
            <p class="mt-1.5 text-[12px] text-danger" data-notes-error hidden></p>

            <div class="mt-4 space-y-2.5" data-notes-list></div>
          </div>

          <div class="styledesk_sheet__foot">
            <button type="button" class="styledesk_sheet__cta" data-notes-save>{{ __('leads.notes.save') }}</button>
          </div>
        </div>

        <footer class="styledesk_sheet__foot">
          <a class="styledesk_sheet__cta" data-sheet-complete>{{ __('leads.actions.complete') }}</a>

          <div class="flex items-center gap-2">
            <button type="button" class="styledesk_action flex-1 justify-center" data-notes-open>
              {{ __('leads.notes.button') }}
            </button>

            {{-- Shown disabled rather than hidden: an action that quietly is
                 not there reads as a permission the reader lacks. --}}
            <button type="button" class="styledesk_action flex-1 justify-center" disabled>
              {{ __('leads.drawer.send_email') }}
              <span class="styledesk_badge styledesk_badge--soon">{{ __('leads.drawer.soon') }}</span>
            </button>
            <button type="button" class="styledesk_action flex-1 justify-center" disabled>
              {{ __('leads.drawer.send_sms') }}
              <span class="styledesk_badge styledesk_badge--soon">{{ __('leads.drawer.soon') }}</span>
            </button>
          </div>

          <button type="button" class="styledesk_sheet__cancel" data-sheet-cancel>
            {{ __('bookings.cancel') }}
          </button>
        </footer>
      </aside>
    </div>

    {{-- Cancelling asks why. The answer is a code, because "why do we lose
         bookings" is a question of counting. --}}
    <div class="styledesk_modal" data-cancel-modal hidden>
      <div class="styledesk_modal__scrim" data-cancel-close></div>

      <div class="styledesk_modal__panel" role="dialog" aria-modal="true" aria-labelledby="cancelLeadTitle">
        <div class="styledesk_modal__head">
          <h2 id="cancelLeadTitle" class="text-[15px] font-semibold text-head">{{ __('leads.cancel.title') }}</h2>
        </div>

        <form class="styledesk_modal__body space-y-4" data-cancel-form>
          <p class="text-[13px] text-sub">{{ __('leads.cancel.body') }}</p>

          <div>
            <label for="cancelReason" class="block text-[13px] font-medium text-ink mb-1.5">
              {{ __('leads.cancel.reason') }}
            </label>
            <select id="cancelReason" name="reason" class="sd-input" required>
              <option value="">{{ __('leads.cancel.choose_reason') }}</option>
              @foreach (config('bookings.lead_cancel_reasons') as $reason)
                <option value="{{ $reason }}">{{ __('leads.reasons.'.$reason) }}</option>
              @endforeach
            </select>
            <p class="mt-1.5 text-[12px] text-danger" data-cancel-error hidden></p>
          </div>

          <div>
            <label for="cancelNote" class="block text-[13px] font-medium text-ink mb-1.5">
              {{ __('leads.cancel.note') }} <span class="text-faint font-normal">{{ __('common.optional') }}</span>
            </label>
            <textarea id="cancelNote" name="note" rows="2" class="sd-input h-auto py-2.5"
                      placeholder="{{ __('leads.cancel.note_placeholder') }}"></textarea>
          </div>
        </form>

        <div class="styledesk_modalfoot">
          <button type="button" class="styledesk_action styledesk_action--danger" data-cancel-confirm>
            {{ __('leads.cancel.confirm') }}
          </button>
          <button type="button" class="styledesk_action" data-cancel-close>{{ __('leads.cancel.keep') }}</button>
        </div>
      </div>
    </div>
    @endif
  </main>
@endsection
