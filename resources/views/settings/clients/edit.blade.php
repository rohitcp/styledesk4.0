@extends('layouts.app')

@section('title', __('clients.title'))

@section('content')
  <main class="w-full px-4 sm:px-5 lg:px-6 pt-5 sm:pt-6 pb-[200px]">
    {{-- Single column, per the recommended structure: eight cards read top to
         bottom, and a settings page that asks which column to start in is
         asking a question it should answer itself. --}}
    <div class="styledesk_form">

      <nav class="text-[13px] text-sub" aria-label="Breadcrumb">
        <a href="{{ route('settings.index') }}" class="hover:text-ink transition-colors">{{ __('navigation.app_settings') }}</a>
        <span class="mx-1.5 text-faint">/</span>
        <span class="text-ink">{{ __('clients.title') }}</span>
      </nav>

      <div class="mt-3 flex flex-wrap items-start gap-4">
        <div class="min-w-0 flex-1">
          <h1 class="text-[24px] sm:text-[28px] font-bold text-head tracking-tight">{{ __('clients.title') }}</h1>
          <p class="text-[14px] text-sub mt-2 max-w-[640px] leading-relaxed">{{ __('clients.intro') }}</p>
        </div>

        <a href="{{ route('settings.index') }}"
           class="styledesk_action shrink-0">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M14 6l-6 6 6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
          {{ __('common.back') }}
        </a>
      </div>

      {{-- Said once at the top. Most of what this page configures describes
           screens that do not exist yet, and a business is entitled to know
           that before it spends ten minutes setting them. --}}
      <div class="sd-alert sd-alert--info mt-5" role="status">
        <div class="flex items-start gap-2.5">
          <svg width="17" height="17" viewBox="0 0 24 24" fill="none" class="shrink-0 mt-px" aria-hidden="true"><circle cx="12" cy="12" r="8.5" stroke="currentColor" stroke-width="1.7"/><path d="M12 11v5.5M12 8v.4" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"/></svg>
          <p class="min-w-0">{{ __('clients.pending_note') }}</p>
        </div>
      </div>

      @if ($errors->any())
        <div class="sd-alert sd-alert--danger mt-5" role="alert">
          <p class="min-w-0">{{ $errors->first() }}</p>
        </div>
      @endif


      @php
          $cfg = config('clients');
          $opt = App\Support\ClientOptions::class;
          $locationOptions = ['' => __('clients.defaults.none')] + $locations->pluck('name', 'id')->all();
          $staffOptions = ['' => __('clients.defaults.none')]
              + $staff->mapWithKeys(fn ($m) => [$m->id => $m->displayName()])->all();

          /**
           * The one-line summary each card shows when it is closed.
           *
           * Written from the settings themselves rather than restated by
           * hand, so a card cannot describe a configuration the page is not
           * actually holding — which is the only way a preview earns being
           * trusted instead of opened.
           */
          $on = fn (bool $value) => $value ? __('common.on') : __('common.off');
          $list = fn (array $keys, array $labels) => collect($keys)
              ->map(fn ($key) => $labels[$key] ?? $key)->join(' · ');

          $enabledFields = collect($settings->orderedFields())->where('enabled', true);

          $summaries = [
              'client-records' => trans_choice('clients.summary.fields', $enabledFields->count(), ['count' => $enabledFields->count()])
                  .' · '.($opt::nameFormats()[$settings->name_format] ?? ''),

              'lists' => trans_choice('clients.summary.preferences', $preferences->where('is_active', true)->count(), ['count' => $preferences->where('is_active', true)->count()])
                  .' · '.trans_choice('clients.summary.tags', $tags->where('is_active', true)->count(), ['count' => $tags->where('is_active', true)->count()]),

              'behavioral-tags' => trans_choice('clients.summary.behavioral', $behavioralTags->flatten()->where('is_active', true)->count(), [
                  'count' => $behavioralTags->flatten()->where('is_active', true)->count(),
                  'total' => $behavioralTags->flatten()->count(),
              ]),

              'notes' => $settings->notes_enabled
                  ? trans_choice('clients.summary.notes_on', (int) $settings->notes_multiple)
                  : __('clients.summary.notes_off'),

              'booking' => trans_choice('clients.summary.panels', count($settings->booking_panels ?? []), ['count' => count($settings->booking_panels ?? [])]),

              'duplicate-detection' => $settings->duplicate_warning
                  ? $list($settings->duplicate_rules ?? [], $opt::duplicateRules())
                  : __('clients.summary.duplicates_off'),

              'communication' => $list(
                  collect(['email' => $settings->comm_email, 'sms' => $settings->comm_sms, 'phone' => $settings->comm_phone])
                      ->filter()->keys()->all(),
                  $opt::communicationMethods(),
              ) ?: __('common.none'),

              'status' => __('clients.summary.status', [
                  'inactive' => $on((bool) $settings->allow_booking_inactive),
                  'archived' => $on((bool) $settings->archived_in_search),
              ]),

              'privacy' => $settings->consent_record
                  ? __('clients.summary.consent_on')
                  : __('clients.summary.consent_off'),
          ];

          /**
           * The fuller preview each card shows when it is open: the saved
           * configuration as a list of facts, in the same shape for every
           * card so it can be scanned rather than learned.
           */
          $fieldSummary = fn () => collect($settings->orderedFields())
              ->filter(fn (array $f) => $f['enabled'])
              ->map(fn (array $f) => ($opt::fields()[$f['key']] ?? $f['key'])
                  .' '.($f['required'] ? __('clients.preview.required') : __('clients.preview.optional')))
              ->join(' · ');

          $previews = [
              'client-records' => [
                  __('clients.preview.fields') => $fieldSummary(),
                  __('clients.name_format') => $opt::nameFormats()[$settings->name_format] ?? '',
                  __('clients.defaults.status') => $opt::defaultStatuses()[$settings->default_status] ?? '',
                  __('clients.defaults.communication') => $opt::communicationMethods()[$settings->default_communication] ?? '',
                  /* Drawn with the shared consent indicator rather than
                     written out, so the default a business picked reads the
                     same as the consent it produces on a client record. */
                  __('clients.defaults.marketing') => new \Illuminate\Support\HtmlString(
                      \Illuminate\Support\Facades\Blade::render(
                          '<x-consent-status :granted="$granted" :label="$label" />',
                          [
                              'granted' => $settings->default_marketing === 'in',
                              'label' => $opt::marketingDefaults()[$settings->default_marketing] ?? null,
                          ],
                      )
                  ),
              ],
              'notes' => [
                  __('clients.notes.enabled') => $on((bool) $settings->notes_enabled),
                  __('clients.notes.multiple') => $on((bool) $settings->notes_multiple),
                  __('clients.notes.in_booking') => $on((bool) $settings->notes_in_booking),
                  __('clients.notes.important_on_profile') => $on((bool) $settings->notes_important_on_profile),
                  __('clients.notes.admin_can_delete') => $on((bool) $settings->notes_admin_can_delete),
              ],
              'booking' => [
                  __('clients.booking_panels') => $list($settings->booking_panels ?? [], $opt::bookingPanels()),
                  __('clients.history_panels') => $list($settings->history_panels ?? [], $opt::historyPanels()),
                  __('clients.creation') => $list($settings->creation_sources ?? [], $opt::creationSources()),
              ],
              'duplicate-detection' => [
                  __('clients.duplicates.warning') => $on((bool) $settings->duplicate_warning),
                  __('clients.duplicates.rules') => $list($settings->duplicate_rules ?? [], $opt::duplicateRules()),
                  __('clients.duplicates.show_matches') => $on((bool) $settings->duplicate_show_matches),
                  __('clients.search') => $list($settings->search_fields ?? [], $opt::searchFields()),
              ],
              'communication' => [
                  __('clients.communication.methods') => $list(
                      collect(['email' => $settings->comm_email, 'sms' => $settings->comm_sms, 'phone' => $settings->comm_phone])
                          ->filter()->keys()->all(),
                      $opt::communicationMethods(),
                  ),
                  __('clients.communication.marketing') => $list(
                      collect(['email' => $settings->comm_marketing_email, 'sms' => $settings->comm_marketing_sms])
                          ->filter()->keys()->all(),
                      $opt::communicationMethods(),
                  ),
              ],
              'status' => [
                  __('clients.status.allow_booking_inactive') => $on((bool) $settings->allow_booking_inactive),
                  __('clients.status.archived_in_search') => $on((bool) $settings->archived_in_search),
              ],
              'privacy' => [
                  __('clients.consent.record') => $on((bool) $settings->consent_record),
                  __('clients.consent.record_date') => $on((bool) $settings->consent_record_date),
                  __('clients.consent.record_captured_by') => $on((bool) $settings->consent_record_captured_by),
                  __('clients.consent.show_on_profile') => $on((bool) $settings->consent_show_on_profile),
              ],
          ];

          /** Title, description and which partial holds each card's form. */
          $cards = [
              ['id' => 'client-records', 'title' => __('clients.cards.records'), 'hint' => __('clients.cards.records_hint'), 'section' => 'records'],
              ['id' => 'notes', 'title' => __('clients.cards.notes'), 'hint' => __('clients.cards.notes_hint'), 'section' => 'notes'],
              ['id' => 'booking', 'title' => __('clients.cards.booking'), 'hint' => __('clients.cards.booking_hint'), 'section' => 'booking'],
              ['id' => 'duplicate-detection', 'title' => __('clients.cards.duplicates'), 'hint' => __('clients.cards.duplicates_hint'), 'section' => 'duplicates'],
              ['id' => 'communication', 'title' => __('clients.cards.communication'), 'hint' => __('clients.cards.communication_hint'), 'section' => 'communication'],
              ['id' => 'status', 'title' => __('clients.cards.status'), 'hint' => __('clients.cards.status_hint'), 'section' => 'status'],
              ['id' => 'privacy', 'title' => __('clients.cards.privacy'), 'hint' => __('clients.cards.privacy_hint'), 'section' => 'privacy'],
          ];
      @endphp

      <div class="mt-6 flex flex-wrap items-center justify-end gap-2">
        <button type="button" class="styledesk_action styledesk_action--sm" data-accordion-expand-all>
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M8 10l4 4 4-4M8 4l4 4 4-4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
          {{ __('clients.expand_all') }}
        </button>

        <button type="button" class="styledesk_action styledesk_action--sm" data-accordion-collapse-all>
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M8 14l4-4 4 4M8 20l4-4 4 4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
          {{ __('clients.collapse_all') }}
        </button>
      </div>

      <div class="mt-3">
        {{-- Each card is its own form, posting only what it owns. There is no
             page-level Save: these settings have nothing to do with each
             other, and one button that wrote all of them would make every
             visit to this page a chance to change something the reader never
             looked at. --}}
        @foreach ($cards as $card)
          <x-settings.accordion :id="$card['id']" :title="$card['title']"
                                :description="$card['hint']" :summary="$summaries[$card['id']] ?? null">
            <x-slot:preview>
              @include('settings.clients._preview', ['rows' => $previews[$card['id']] ?? []])
            </x-slot:preview>

            <form method="POST" action="{{ route('settings.clients.update') }}" data-accordion-form class="space-y-5">
              @csrf
              @method('PATCH')
              <input type="hidden" name="section" value="{{ $card['section'] }}">

              @include('settings.clients.sections.'.$card['id'])

              <div class="flex flex-wrap items-center gap-3 pt-4 border-t border-line">
                <button type="submit"
                        class="h-9 px-4 rounded-lg bg-brand hover:bg-brand-dark text-white text-[13px] font-semibold transition-colors">
                  {{ __('common.save_changes') }}
                </button>
              </div>
            </form>
          </x-settings.accordion>
        @endforeach

        {{-- The three list cards manage records rather than fields: adding a
             tag or switching one off takes effect on its own, so they have no
             Save of their own to press. --}}
        <x-settings.accordion id="lists" :title="__('clients.cards.lists')"
                              :description="__('clients.cards.lists_hint')"
                              :summary="$summaries['lists']" :saves="false">
          <x-slot:preview>
            @include('settings.clients._preview', ['rows' => [
                __('clients.preferences.title') => $preferences->where('is_active', true)->pluck('label')->take(6)->join(' · ')
                    .($preferences->where('is_active', true)->count() > 6 ? ' '.__('clients.preview.more', ['count' => $preferences->where('is_active', true)->count() - 6]) : ''),
                __('clients.preferences.multiple') => $on((bool) $settings->preferences_multiple),
                __('clients.tags.title') => $tags->where('is_active', true)->pluck('label')->take(6)->join(' · ')
                    .($tags->where('is_active', true)->count() > 6 ? ' '.__('clients.preview.more', ['count' => $tags->where('is_active', true)->count() - 6]) : ''),
                __('clients.tags.enabled') => $on((bool) $settings->tags_enabled),
            ]])
          </x-slot:preview>

          @include('settings.clients._lists')
        </x-settings.accordion>

        <x-settings.accordion id="behavioral-tags" :title="__('clients.behavioral.title')"
                              :description="__('clients.behavioral.intro')"
                              :summary="$summaries['behavioral-tags']" :saves="false">
          <x-slot:preview>
            @include('settings.clients._preview', [
                'rows' => collect(config('behavioral_tags.categories'))
                    ->mapWithKeys(fn ($label, $key) => [$label => $behavioralTags->get($key, collect())
                        ->where('is_active', true)->count().' / '.$behavioralTags->get($key, collect())->count()])
                    ->all(),
                'note' => __('clients.behavioral.pending'),
            ])
          </x-slot:preview>

          @include('settings.clients._behavioral')
        </x-settings.accordion>
      </div>
    </div>
  </main>
@endsection

{{-- Pushed, not included loose: anything a child view emits outside a
     section is discarded, so the page's behaviour has to be handed to the
     layout's stack rather than left at the end of the file. --}}
@push('scripts')
  @include('settings.clients._scripts')
@endpush
