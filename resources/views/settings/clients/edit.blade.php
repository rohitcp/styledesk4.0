@extends('layouts.app')

@section('title', __('clients.title'))

@section('content')
  <main class="w-full px-4 sm:px-5 lg:px-6 pt-5 sm:pt-6 pb-[200px]">
    {{-- Single column, per the recommended structure: eight cards read top to
         bottom, and a settings page that asks which column to start in is
         asking a question it should answer itself. --}}
    <div class="max-w-[760px]">

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
           class="shrink-0 inline-flex items-center gap-1.5 h-9 px-3 rounded-lg border border-stroke bg-white hover:bg-hover text-ink text-[13px] font-semibold transition-colors">
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
      @endphp

      <form id="clientSettingsForm" method="POST" action="{{ route('settings.clients.update') }}" class="mt-6 space-y-5">
        @csrf
        @method('PATCH')

        {{-- ═══════════════════════════ 1 — client records ═══════════════ --}}
        <section class="bg-white border border-line rounded-card p-5 space-y-5">
          <div>
            <h2 class="text-[15px] font-semibold text-head">{{ __('clients.cards.records') }}</h2>
            <p class="text-[13px] text-sub mt-0.5">{{ __('clients.cards.records_hint') }}</p>
          </div>

          <div class="pt-4 border-t border-line">
            <h3 class="text-[13px] font-medium text-ink mb-3">{{ __('clients.defaults.title') }}</h3>

            <div class="grid sm:grid-cols-2 gap-x-4 gap-y-4">
              <x-combo name="default_status" :label="__('clients.defaults.status')" required
                       :options="$opt::defaultStatuses()"
                       :selected="old('default_status', $settings->default_status)" />

              <x-combo name="default_communication" :label="__('clients.defaults.communication')" required
                       :options="$opt::communicationMethods()"
                       :selected="old('default_communication', $settings->default_communication)" />

              <x-combo name="default_location_id" :label="__('clients.defaults.location')"
                       :options="$locationOptions"
                       :selected="old('default_location_id', $settings->default_location_id)"
                       :placeholder="__('clients.defaults.none')" />

              <x-combo name="default_staff_id" :label="__('clients.defaults.staff')"
                       :options="$staffOptions"
                       :selected="old('default_staff_id', $settings->default_staff_id)"
                       :placeholder="__('clients.defaults.none')" />

              <div class="sm:col-span-2">
                <x-combo name="default_marketing" :label="__('clients.defaults.marketing')" required
                         :options="$opt::marketingDefaults()"
                         :selected="old('default_marketing', $settings->default_marketing)" />
              </div>
            </div>
          </div>

          {{-- ---------------------------------------- profile fields --}}
          <div class="pt-4 border-t border-line">
            <h3 class="text-[13px] font-medium text-ink">{{ __('clients.fields.title') }}</h3>
            <p class="text-[12px] text-sub mt-0.5">{{ __('clients.fields.hint') }}</p>

            <div class="mt-3 rounded-lg border border-line overflow-hidden" data-field-list>
              <div class="grid grid-cols-[1fr_auto_auto] gap-3 px-3 py-2 border-b border-line bg-hover/50 text-[12px] text-sub">
                <span>{{ __('clients.fields.field') }}</span>
                <span class="w-[44px] text-center">{{ __('clients.fields.enabled') }}</span>
                <span class="w-[74px] text-center">{{ __('clients.fields.required') }}</span>
              </div>

              @foreach ($settings->orderedFields() as $index => $field)
                <div class="grid grid-cols-[1fr_auto_auto] gap-3 items-center px-3 py-2.5 border-b border-line last:border-0"
                     data-field-row draggable="true">
                  <span class="flex items-center gap-2 min-w-0">
                    <span class="text-faint cursor-grab select-none" aria-hidden="true" data-field-handle>⠿</span>

                    <span class="min-w-0">
                      <span class="block text-[13px] text-ink truncate">{{ $field['label'] }}</span>
                      @if ($field['locked'])
                        <span class="block text-[11px] text-faint">{{ __('clients.fields.locked') }}</span>
                      @endif
                    </span>
                  </span>

                  <span class="w-[44px] grid place-items-center">
                    {{-- A locked field posts nothing and shows a tick it
                         cannot change. The server forces it on regardless, so
                         this is honesty about a decision already made rather
                         than the control that enforces it. --}}
                    <input type="checkbox" class="sd-check"
                           @if (! $field['locked']) name="fields[{{ $field['key'] }}][enabled]" @endif
                           value="1" data-field-enabled
                           @checked($field['enabled']) @disabled($field['locked'])>
                  </span>

                  <span class="w-[74px] grid place-items-center">
                    <input type="checkbox" class="sd-check"
                           @if (! $field['locked']) name="fields[{{ $field['key'] }}][required]" @endif
                           value="1" data-field-required
                           @checked($field['required'])
                           @disabled($field['locked'] || ! $field['enabled'])>
                  </span>

                  <input type="hidden" name="fields[{{ $field['key'] }}][order]" value="{{ $index }}" data-field-order>
                </div>
              @endforeach
            </div>

            <p class="mt-2 text-[12px] text-sub">{{ __('clients.fields.contact_advice') }}</p>
          </div>

          {{-- ------------------------------------------ name format --}}
          <div class="pt-4 border-t border-line">
            <x-combo name="name_format" :label="__('clients.name_format')" required
                     :options="$opt::nameFormats()"
                     :selected="old('name_format', $settings->name_format)"
                     :hint="__('clients.name_format_hint')" />

            @php
                /**
                 * A worked example of each format, so the choice is read
                 * rather than decoded. "First name and last initial" means
                 * far less than seeing "Amara O.".
                 */
                $namePreviews = [
                    'first_last' => 'Amara Osei',
                    'last_first' => 'Osei, Amara',
                    'first_initial' => 'Amara O.',
                    'preferred_last' => 'Ami Osei',
                ];
            @endphp

            <p class="mt-2 text-[12px] text-sub">
              {{ __('clients.name_preview') }}:
              <span class="text-ink font-medium" data-name-preview>{{ $namePreviews[$settings->name_format] ?? '' }}</span>
            </p>

            <script type="application/json" data-name-previews>@json($namePreviews)</script>
          </div>

          {{-- -------------------------------------------- client ID --}}
          <div class="pt-4 border-t border-line">
            <p class="text-[13px] font-medium text-ink">{{ __('clients.client_id') }}</p>
            <p class="text-[12px] text-sub mt-0.5 leading-relaxed">{{ __('clients.client_id_hint') }}</p>
            <p class="text-[12px] text-sub mt-1">
              {{ __('clients.client_id_example', [
                  'example' => $cfg['client_id']['prefix'].str_pad('123', $cfg['client_id']['padding'], '0', STR_PAD_LEFT),
              ]) }}
            </p>
          </div>
        </section>

        {{-- ═══════════════════════════ 3 — notes ════════════════════════ --}}
        <section class="bg-white border border-line rounded-card p-5 space-y-4">
          <div>
            <h2 class="text-[15px] font-semibold text-head">{{ __('clients.cards.notes') }}</h2>
            <p class="text-[13px] text-sub mt-0.5">{{ __('clients.cards.notes_hint') }}</p>
          </div>

          <div class="space-y-3">
            @foreach ([
                'notes_enabled' => 'clients.notes.enabled',
                'notes_multiple' => 'clients.notes.multiple',
                'notes_in_booking' => 'clients.notes.in_booking',
                'notes_important_on_profile' => 'clients.notes.important_on_profile',
                'notes_allow_important' => 'clients.notes.allow_important',
                'notes_staff_can_edit' => 'clients.notes.staff_can_edit',
                'notes_admin_can_delete' => 'clients.notes.admin_can_delete',
            ] as $switch => $key)
              @include('settings.clients._toggle', [
                  'name' => $switch,
                  'label' => __($key),
                  'checked' => (bool) old($switch, $settings->{$switch}),
              ])
            @endforeach
          </div>
        </section>

        {{-- ═══════════════════════ 4 — booking behaviour ════════════════ --}}
        <section class="bg-white border border-line rounded-card p-5 space-y-4">
          <div>
            <h2 class="text-[15px] font-semibold text-head">{{ __('clients.cards.booking') }}</h2>
            <p class="text-[13px] text-sub mt-0.5">{{ __('clients.cards.booking_hint') }}</p>
          </div>

          @include('settings.clients._checkset', [
              'name' => 'booking_panels',
              'legend' => __('clients.booking_panels'),
              'options' => $opt::bookingPanels(),
              'selected' => old('booking_panels', $settings->booking_panels ?? []),
          ])

          <div class="pt-4 border-t border-line">
            @include('settings.clients._checkset', [
                'name' => 'history_panels',
                'legend' => __('clients.history_panels'),
                'options' => $opt::historyPanels(),
                'selected' => old('history_panels', $settings->history_panels ?? []),
            ])
          </div>

          <div class="pt-4 border-t border-line">
            @include('settings.clients._checkset', [
                'name' => 'creation_sources',
                'legend' => __('clients.creation'),
                'options' => $opt::creationSources(),
                'selected' => old('creation_sources', $settings->creation_sources ?? []),
                'unavailable' => $opt::unavailableSources(),
            ])
          </div>
        </section>

        {{-- ═══════════════════ 5 — duplicate detection ══════════════════ --}}
        <section class="bg-white border border-line rounded-card p-5 space-y-4">
          <div>
            <h2 class="text-[15px] font-semibold text-head">{{ __('clients.cards.duplicates') }}</h2>
            <p class="text-[13px] text-sub mt-0.5">{{ __('clients.cards.duplicates_hint') }}</p>
          </div>

          @include('settings.clients._checkset', [
              'name' => 'duplicate_rules',
              'legend' => __('clients.duplicates.rules'),
              'options' => $opt::duplicateRules(),
              'selected' => old('duplicate_rules', $settings->duplicate_rules ?? []),
              'columns' => 1,
          ])

          <div class="space-y-3 pt-4 border-t border-line">
            @include('settings.clients._toggle', [
                'name' => 'duplicate_warning',
                'label' => __('clients.duplicates.warning'),
                'checked' => (bool) old('duplicate_warning', $settings->duplicate_warning),
            ])
            @include('settings.clients._toggle', [
                'name' => 'duplicate_show_matches',
                'label' => __('clients.duplicates.show_matches'),
                'checked' => (bool) old('duplicate_show_matches', $settings->duplicate_show_matches),
            ])
          </div>

          <p class="text-[12px] text-sub leading-relaxed pt-4 border-t border-line">
            {{ __('clients.duplicates.no_merge') }}
          </p>

          <div class="pt-4 border-t border-line">
            @include('settings.clients._checkset', [
                'name' => 'search_fields',
                'legend' => __('clients.search'),
                'options' => $opt::searchFields(),
                'selected' => old('search_fields', $settings->search_fields ?? []),
                'columns' => 3,
            ])
          </div>
        </section>

        {{-- ═════════════════════ 6 — communication ══════════════════════ --}}
        <section class="bg-white border border-line rounded-card p-5 space-y-4">
          <div>
            <h2 class="text-[15px] font-semibold text-head">{{ __('clients.cards.communication') }}</h2>
            <p class="text-[13px] text-sub mt-0.5">{{ __('clients.cards.communication_hint') }}</p>
          </div>

          <fieldset>
            <legend class="text-[13px] font-medium text-ink mb-2">{{ __('clients.communication.methods') }}</legend>
            <div class="grid sm:grid-cols-3 gap-x-4 gap-y-2">
              @foreach (['comm_email' => 'Email', 'comm_sms' => 'SMS', 'comm_phone' => 'Phone'] as $switch => $label)
                @include('settings.clients._toggle', [
                    'name' => $switch,
                    'label' => $opt::communicationMethods()[str_replace('comm_', '', $switch)] ?? $label,
                    'checked' => (bool) old($switch, $settings->{$switch}),
                ])
              @endforeach
            </div>
          </fieldset>

          <fieldset class="pt-4 border-t border-line">
            <legend class="text-[13px] font-medium text-ink mb-2">{{ __('clients.communication.marketing') }}</legend>
            <div class="grid sm:grid-cols-2 gap-x-4 gap-y-2">
              @include('settings.clients._toggle', [
                  'name' => 'comm_marketing_email',
                  'label' => $opt::communicationMethods()['email'],
                  'checked' => (bool) old('comm_marketing_email', $settings->comm_marketing_email),
              ])
              @include('settings.clients._toggle', [
                  'name' => 'comm_marketing_sms',
                  'label' => $opt::communicationMethods()['sms'],
                  'checked' => (bool) old('comm_marketing_sms', $settings->comm_marketing_sms),
              ])
            </div>
          </fieldset>

          <p class="text-[12px] text-sub leading-relaxed">{{ __('clients.communication.stored_separately') }}</p>
        </section>

        {{-- ═══════════════════ 7 — status & archiving ═══════════════════ --}}
        <section class="bg-white border border-line rounded-card p-5 space-y-4">
          <div>
            <h2 class="text-[15px] font-semibold text-head">{{ __('clients.cards.status') }}</h2>
            <p class="text-[13px] text-sub mt-0.5">{{ __('clients.cards.status_hint') }}</p>
          </div>

          <div class="space-y-3">
            @include('settings.clients._toggle', [
                'name' => 'allow_booking_inactive',
                'label' => __('clients.status.allow_booking_inactive'),
                'checked' => (bool) old('allow_booking_inactive', $settings->allow_booking_inactive),
            ])
            @include('settings.clients._toggle', [
                'name' => 'archived_in_search',
                'label' => __('clients.status.archived_in_search'),
                'checked' => (bool) old('archived_in_search', $settings->archived_in_search),
            ])
          </div>

          <p class="text-[12px] text-sub leading-relaxed pt-4 border-t border-line">
            {{ __('clients.status.explainer') }}
          </p>
        </section>

        {{-- ═══════════════════ 8 — privacy & consent ════════════════════ --}}
        <section class="bg-white border border-line rounded-card p-5 space-y-4">
          <div>
            <h2 class="text-[15px] font-semibold text-head">{{ __('clients.cards.privacy') }}</h2>
            <p class="text-[13px] text-sub mt-0.5">{{ __('clients.cards.privacy_hint') }}</p>
          </div>

          <div class="space-y-3">
            @foreach ([
                'consent_record' => 'clients.consent.record',
                'consent_record_date' => 'clients.consent.record_date',
                'consent_record_captured_by' => 'clients.consent.record_captured_by',
                'consent_client_can_opt_out' => 'clients.consent.client_can_opt_out',
                'consent_show_on_profile' => 'clients.consent.show_on_profile',
            ] as $switch => $key)
              @include('settings.clients._toggle', [
                  'name' => $switch,
                  'label' => __($key),
                  'checked' => (bool) old($switch, $settings->{$switch}),
              ])
            @endforeach
          </div>

          <p class="text-[12px] text-faint leading-relaxed pt-4 border-t border-line">
            {{ __('clients.consent.later') }}
          </p>
        </section>

        <div class="flex flex-wrap items-center gap-3">
          <button type="submit" id="clientSettingsSave"
                  class="h-9 px-4 rounded-lg bg-brand hover:bg-brand-dark text-white text-[13px] font-semibold transition-colors disabled:opacity-60 disabled:pointer-events-none">
            {{ __('common.save_changes') }}
          </button>
          <a href="{{ route('settings.index') }}"
             class="h-9 px-3.5 inline-flex items-center rounded-lg border border-stroke bg-white hover:bg-hover text-ink text-[13px] font-semibold transition-colors">
            {{ __('common.cancel') }}
          </a>
        </div>
      </form>

      {{-- ═══════════════════ 2 — preferences & tags ═══════════════════
           Outside the settings form on purpose: these are records with their
           own lifecycle, and adding a tag should not require saving forty
           unrelated switches — nor should a failed save of those switches
           lose a tag somebody just added. --}}
      @include('settings.clients._lists')

      <p class="mt-6 text-[12px] text-faint">{{ __('clients.scope_note') }}</p>
    </div>
  </main>
@endsection

@push('scripts')
  @include('settings.clients._scripts')
@endpush
