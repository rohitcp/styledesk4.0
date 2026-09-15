@extends('layouts.app')

@section('title', __('forms.title'))

{{--
    App Settings → Forms & Waivers.

    The administrative half of the module: what forms exist and whether they
    are live. Assigning one to somebody and chasing it up happens on the
    client's profile, because that is where the person doing it is already
    standing.

    Every action offered here is one the controller implements. A row menu
    that lists Edit before a builder exists is a menu that 404s, which is
    worse than a short menu.
--}}

@section('content')
  <main class="w-full px-4 sm:px-5 lg:px-6 pt-5 sm:pt-6 pb-[200px]">
    <div class="styledesk_form">

      <nav class="text-[13px] text-sub" aria-label="Breadcrumb">
        <a href="{{ route('settings.index') }}" class="hover:text-ink transition-colors">{{ __('navigation.app_settings') }}</a>
        <span class="mx-1.5 text-faint">/</span>
        <span class="text-ink">{{ __('forms.title') }}</span>
      </nav>

      <div class="mt-3 flex flex-wrap items-start gap-4">
        <div class="min-w-0 flex-1">
          <h1 class="text-[24px] sm:text-[28px] font-bold text-head tracking-tight">{{ __('forms.title') }}</h1>
          <p class="text-[13px] text-sub mt-1.5 leading-relaxed max-w-[640px]">{{ __('forms.intro') }}</p>
        </div>

        {{-- Back beside Create as one group on the right, secondary first:
             the same pair, in the same order and the same place, as every
             other settings page. --}}
        <div class="shrink-0 flex items-center gap-2">
          <a href="{{ route('settings.index') }}" class="styledesk_action">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M14 6l-6 6 6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
            {{ __('common.back') }}
          </a>

          @if ($canCreate)
            <button type="button" data-form-add
                    class="inline-flex items-center gap-1.5 h-9 px-3.5 rounded-lg bg-brand hover:bg-brand-dark text-white text-[13px] font-semibold transition-colors">
              <x-icon name="plus" size="14" />
              {{ __('forms.create') }}
            </button>
          @endif
        </div>
      </div>

      @error('form')
        <p class="mt-4 text-[13px] text-danger bg-danger-soft border border-danger rounded-lg px-3 py-2">{{ $message }}</p>
      @enderror

      @php
          /* Combo options are assembled here rather than inside the tags.

             The encoding directive's argument is parsed by counting brackets
             instead of by reading PHP, so an array literal written inside an
             attribute ends at its first closing bracket — the rule recorded
             on .ai/rules/views.md. */
          $combo = fn (string $label) => [
              'searchLabel' => $label,
              'searchPlaceholder' => __('common.search'),
              'width' => '240px',
          ];

          $categoryCombo = $combo(__('forms.new.search_category'));
          $typeCombo = $combo(__('forms.new.search_type'));
          $statusCombo = $combo(__('forms.columns.status'));
          $signatureCombo = $combo(__('forms.columns.signature'));
      @endphp

      {{-- One row of controls, all the same kind of control.

           Four native selects and a text box read as five different widgets
           on five platforms; dressed as combos they are one. Compact widths
           so the whole filter sits on a single line on a desktop, and still
           wrapping below that — a row forced to stay one line on a phone is
           a row half of which is off the screen. --}}
      <form method="GET" class="mt-5 flex flex-wrap items-center gap-2">
        <div class="relative flex-1 min-w-[180px] max-w-[280px]">
          <span class="styledesk_input__prefix pointer-events-none" aria-hidden="true">
            <x-icon name="magnifying-glass" size="15" />
          </span>
          <input name="search" type="search" class="sd-input styledesk_input--prefixed !h-9 text-[12.5px]"
                 value="{{ $filters['search'] ?? '' }}"
                 aria-label="{{ __('forms.search') }}" placeholder="{{ __('forms.search') }}">
        </div>

        <select name="category" class="sd-input !h-9 !w-[150px] shrink-0 text-[12.5px]"
                aria-label="{{ __('forms.columns.category') }}"
                data-combo data-combo-options='@json($categoryCombo)'>
          <option value="">{{ __('forms.filters.all_categories') }}</option>
          @foreach ($categories as $category)
            <option value="{{ $category->id }}" @selected((string) ($filters['category'] ?? '') === (string) $category->id)>{{ $category->label() }}</option>
          @endforeach
        </select>

        <select name="type" class="sd-input !h-9 !w-[150px] shrink-0 text-[12.5px]"
                aria-label="{{ __('forms.columns.type') }}"
                data-combo data-combo-options='@json($typeCombo)'>
          <option value="">{{ __('forms.filters.all_types') }}</option>
          @foreach ($types as $type)
            <option value="{{ $type }}" @selected(($filters['type'] ?? '') === $type)>{{ __('forms.types.'.$type) }}</option>
          @endforeach
        </select>

        <select name="status" class="sd-input !h-9 !w-[150px] shrink-0 text-[12.5px]"
                aria-label="{{ __('forms.columns.status') }}"
                data-combo data-combo-options='@json($statusCombo)'>
          <option value="">{{ __('forms.filters.all_statuses') }}</option>
          @foreach ($statuses as $status)
            <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ __('forms.statuses.'.$status) }}</option>
          @endforeach
        </select>

        <select name="signature" class="sd-input !h-9 !w-[160px] shrink-0 text-[12.5px]"
                aria-label="{{ __('forms.columns.signature') }}"
                data-combo data-combo-options='@json($signatureCombo)'>
          <option value="">{{ __('forms.filters.signature_any') }}</option>
          <option value="1" @selected(($filters['signature'] ?? '') === '1')>{{ __('forms.filters.signature_yes') }}</option>
          <option value="0" @selected(($filters['signature'] ?? '') === '0')>{{ __('forms.filters.signature_no') }}</option>
        </select>

        <button type="submit" class="styledesk_search shrink-0">{{ __('common.search') }}</button>
      </form>

      @if ($forms->isEmpty())
        <p class="text-[13px] text-sub mt-6">
          {{ ($filters['search'] ?? '') !== '' || count($filters) > 0 ? __('forms.no_matches') : __('forms.empty') }}
        </p>
      @else
        <div class="sd-tablewrap bg-white border border-line rounded-card mt-5">
          <table class="sd-table sd-table--cards" style="min-width: 940px">
            <thead>
              <tr>
                <th scope="col">{{ __('forms.columns.name') }}</th>
                <th scope="col">{{ __('forms.columns.category') }}</th>
                <th scope="col">{{ __('forms.columns.type') }}</th>
                <th scope="col">{{ __('forms.columns.services') }}</th>
                <th scope="col">{{ __('forms.columns.locations') }}</th>
                <th scope="col">{{ __('forms.columns.auto_send') }}</th>
                <th scope="col">{{ __('forms.columns.signature') }}</th>
                <th scope="col" class="sd-table__num">{{ __('forms.columns.submissions') }}</th>
                <th scope="col">{{ __('forms.columns.status') }}</th>
                <th scope="col">{{ __('forms.columns.updated') }}</th>
                <th scope="col"><span class="sr-only">{{ __('forms.columns.action') }}</span></th>
              </tr>
            </thead>

            <tbody>
              @foreach ($forms as $form)
                @php
                    $badge = match ($form->status) {
                        \App\Models\Form::STATUS_ACTIVE => 'styledesk_badge--active',
                        \App\Models\Form::STATUS_DRAFT => 'styledesk_badge--setup',
                        default => 'styledesk_badge--soon',
                    };

                    /* Counts rather than names. A form limited to nine
                       services would otherwise wrap its row to four lines,
                       and the names are on the form's own screen. */
                    $services = empty($form->service_ids)
                        ? __('forms.all_services')
                        : __('forms.counted_services', ['count' => count($form->service_ids)]);

                    $locations = empty($form->location_ids)
                        ? __('forms.all_locations')
                        : __('forms.counted_locations', ['count' => count($form->location_ids)]);
                @endphp

                <tr>
                  <td>
                    <span class="sd-cell__label">{{ __('forms.columns.name') }}</span>
                    <span class="min-w-0">
                      <span class="block text-[14px] font-medium text-head">{{ $form->name }}</span>
                      @if ($form->internal_description)
                        <span class="block text-[12px] text-sub">{{ $form->internal_description }}</span>
                      @endif
                    </span>
                  </td>

                  <td>
                    <span class="sd-cell__label">{{ __('forms.columns.category') }}</span>
                    <span class="text-sub">{{ $form->category?->label() ?? __('forms.uncategorised') }}</span>
                  </td>

                  <td>
                    <span class="sd-cell__label">{{ __('forms.columns.type') }}</span>
                    <span class="styledesk_metachip">{{ $form->typeLabel() }}</span>
                  </td>

                  <td>
                    <span class="sd-cell__label">{{ __('forms.columns.services') }}</span>
                    <span class="text-sub">{{ $services }}</span>
                  </td>

                  <td>
                    <span class="sd-cell__label">{{ __('forms.columns.locations') }}</span>
                    <span class="text-sub">{{ $locations }}</span>
                  </td>

                  <td>
                    <span class="sd-cell__label">{{ __('forms.columns.auto_send') }}</span>
                    <span class="text-sub">{{ $form->auto_send ? __('common.on') : __('common.off') }}</span>
                  </td>

                  <td>
                    <span class="sd-cell__label">{{ __('forms.columns.signature') }}</span>
                    <span class="text-sub">{{ $form->signature_required ? __('common.yes') : __('common.no') }}</span>
                  </td>

                  {{-- What a business wants before it deactivates or archives
                       anything: how many people have already answered it. --}}
                  <td class="sd-table__num">
                    <span class="sd-cell__label">{{ __('forms.columns.submissions') }}</span>
                    <span class="text-head">{{ $form->submissions_count }}</span>
                  </td>

                  <td>
                    <span class="sd-cell__label">{{ __('forms.columns.status') }}</span>
                    <span class="styledesk_badge {{ $badge }}">{{ $form->statusLabel() }}</span>
                  </td>

                  <td>
                    <span class="sd-cell__label">{{ __('forms.columns.updated') }}</span>
                    <span class="text-sub">{{ $form->updated_at->translatedFormat('j M Y') }}</span>
                  </td>

                  <td class="text-right">
                    <span class="styledesk_rowmenu" data-rowmenu>
                      <button type="button" class="styledesk_rowmenu__button" data-rowmenu-button
                              aria-haspopup="true" aria-expanded="false"
                              aria-label="{{ __('forms.actions.for', ['name' => $form->name]) }}">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                          <circle cx="5" cy="12" r="1.7"/><circle cx="12" cy="12" r="1.7"/><circle cx="19" cy="12" r="1.7"/>
                        </svg>
                      </button>

                      <span class="styledesk_rowmenu__pop" data-rowmenu-pop hidden role="menu">
                        {{-- Build and Edit are different jobs: the questions
                             a client answers, and what the form is called.
                             Build opens in its own window so this list is
                             still here afterwards — and is absent on an
                             archived form, which is being kept rather than
                             worked on. --}}
                        @unless ($form->isArchived())
                          <a href="{{ route('settings.forms.build', $form) }}" target="_blank" rel="noopener"
                             class="styledesk_rowmenu__item w-full" role="menuitem">
                            {{ __('forms.actions.build') }}
                          </a>
                        @endunless

                        <a href="{{ route('settings.forms.edit', $form) }}" class="styledesk_rowmenu__item w-full" role="menuitem">
                          {{ $canEdit ? __('common.edit') : __('common.view') }}
                        </a>

                        @if ($canCreate)
                          <button type="submit" form="formDuplicate{{ $form->id }}" class="styledesk_rowmenu__item w-full" role="menuitem">
                            {{ __('forms.actions.duplicate') }}
                          </button>
                        @endif

                        {{-- Absent on an archived form rather than shown
                             disabled: restoring is the only thing that
                             happens to one, and a greyed row invites the
                             reader to work out why. --}}
                        @if ($canEdit && ! $form->isArchived())
                          <button type="submit" form="formToggle{{ $form->id }}" class="styledesk_rowmenu__item w-full" role="menuitem">
                            {{ $form->isActive() ? __('forms.actions.deactivate') : __('forms.actions.activate') }}
                          </button>
                        @endif

                        @if ($canArchive)
                          <span class="styledesk_rowmenu__rule" role="separator"></span>

                          @if ($form->isArchived())
                            <button type="submit" form="formRestore{{ $form->id }}" class="styledesk_rowmenu__item w-full" role="menuitem">
                              {{ __('forms.actions.restore') }}
                            </button>
                          @else
                            <button type="submit" form="formArchive{{ $form->id }}" role="menuitem"
                                    class="styledesk_rowmenu__item styledesk_rowmenu__item--danger w-full"
                                    data-confirm-title="{{ __('forms.actions.archive') }}"
                                    data-confirm="{{ __('forms.actions.archive_confirm', ['name' => $form->name]) }}"
                                    data-confirm-label="{{ __('forms.actions.archive') }}"
                                    data-confirm-tone="danger">
                              {{ __('forms.actions.archive') }}
                            </button>
                          @endif

                          {{-- Delete, only where there is nothing to keep.
                               A form anybody has completed is evidence, so
                               the entry is absent rather than shown and
                               refused — a menu item that exists to say no is
                               one the reader has to try before finding out.
                               The server refuses it too; this is the
                               courtesy, not the rule. --}}
                          @if ($form->submissions_count === 0)
                            <button type="submit" form="formDelete{{ $form->id }}" role="menuitem"
                                    class="styledesk_rowmenu__item styledesk_rowmenu__item--danger w-full"
                                    data-confirm-title="{{ __('forms.actions.delete_title') }}"
                                    data-confirm="{{ __('forms.actions.delete_confirm') }}"
                                    data-confirm-label="{{ __('forms.actions.delete') }}"
                                    data-confirm-tone="danger">
                              {{ __('forms.actions.delete') }}
                            </button>
                          @endif
                        @endif
                      </span>
                    </span>
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>

        {{-- The per-row forms, kept out of the table: a form is not something
             a table row may contain, and the browser silently drops one that
             is. Each row's menu reaches its form by id. --}}
        @foreach ($forms as $form)
          @if ($canCreate)
            <form id="formDuplicate{{ $form->id }}" method="POST" action="{{ route('settings.forms.duplicate', $form) }}" class="hidden">
              @csrf
            </form>
          @endif

          @if ($canEdit && ! $form->isArchived())
            <form id="formToggle{{ $form->id }}" method="POST" action="{{ route('settings.forms.toggle', $form) }}" class="hidden">
              @csrf @method('PATCH')
            </form>
          @endif

          @if ($canArchive)
            @if ($form->isArchived())
              <form id="formRestore{{ $form->id }}" method="POST" action="{{ route('settings.forms.restore', $form) }}" class="hidden">
                @csrf @method('PATCH')
              </form>
            @else
              <form id="formArchive{{ $form->id }}" method="POST" action="{{ route('settings.forms.archive', $form) }}" class="hidden">
                @csrf @method('PATCH')
              </form>
            @endif

            @if ($form->submissions_count === 0)
              <form id="formDelete{{ $form->id }}" method="POST" action="{{ route('settings.forms.destroy', $form) }}" class="hidden">
                @csrf @method('DELETE')
              </form>
            @endif
          @endif
        @endforeach
      @endif
    </div>

    @if ($canCreate)
      @include('settings.forms._modal')
    @endif
  </main>
@endsection

@if ($canCreate)
  @push('scripts')
    @include('settings.forms._scripts')
  @endpush
@endif
