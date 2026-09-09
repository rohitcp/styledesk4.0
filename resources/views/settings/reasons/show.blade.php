@extends('layouts.app')

@section('title', $label)

@section('content')
  <main class="w-full px-4 sm:px-5 lg:px-6 pt-5 sm:pt-6 pb-[200px]">
    <div class="styledesk_form">

      <nav class="text-[13px] text-sub" aria-label="Breadcrumb">
        <a href="{{ route('settings.index') }}" class="hover:text-ink transition-colors">{{ __('navigation.app_settings') }}</a>
        <span class="mx-1.5 text-faint">/</span>
        <a href="{{ route('settings.reasons.index') }}" class="hover:text-ink transition-colors">{{ __('reasons.title') }}</a>
        <span class="mx-1.5 text-faint">/</span>
        <span class="text-ink">{{ $label }}</span>
      </nav>

      <div class="mt-3 flex flex-wrap items-start gap-4">
        <div class="min-w-0 flex-1">
          <h1 class="text-[24px] sm:text-[28px] font-bold text-head tracking-tight">{{ $label }}</h1>
          <p class="text-[13px] text-sub mt-1.5 leading-relaxed max-w-[640px]">{{ $intro }}</p>
        </div>

        <div class="shrink-0 flex items-center gap-2">
          <a href="{{ route('settings.reasons.index') }}" class="styledesk_action">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M14 6l-6 6 6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
            {{ __('reasons.back') }}
          </a>

          <button type="button" data-reason-add
                  class="inline-flex items-center gap-1.5 h-9 px-3.5 rounded-lg bg-brand hover:bg-brand-dark text-white text-[13px] font-semibold transition-colors">
            <x-icon name="plus" size="14" />
            {{ __('reasons.add') }}
          </button>
        </div>
      </div>

      {{-- The second question this list asks, where it asks one. Shown
           because a business arranging the reasons should know what else the
           screen will want — a no-show has a reason and somebody it is down
           to, and only one of those is theirs to configure. --}}
      @if ($extra)
        <div class="mt-5 bg-white border border-line rounded-card p-4">
          <p class="text-[11px] font-semibold uppercase tracking-wide text-faint">{{ __('reasons.extra_title') }}</p>
          <p class="text-[13.5px] font-semibold text-head mt-1">{{ $extra['label'] }}</p>

          <div class="mt-2 flex flex-wrap gap-1.5">
            @foreach ($extra['options'] as $value => $optionLabel)
              <span class="styledesk_metachip">{{ $optionLabel }}</span>
            @endforeach
          </div>

          <p class="text-[12px] text-faint mt-2 leading-relaxed">
            {{ __('reasons.extra_hint', ['label' => $label]) }}
          </p>
        </div>
      @endif

      @if ($reasons->isEmpty())
        <p class="text-[13px] text-sub mt-6">{{ __('reasons.none') }}</p>
      @else
        {{-- One form holding the whole order. The order is the record, so it
             is stated in full rather than as a pair of swapped ids — two
             people dragging at once would otherwise produce a list neither of
             them arranged. --}}
        <form method="POST" action="{{ route('settings.reasons.reorder', $type) }}" data-reorder-form class="mt-5">
          @csrf

          <div class="sd-tablewrap bg-white border border-line rounded-card">
            <table class="sd-table sd-table--cards" style="min-width: 720px">
              <thead>
                <tr>
                  <th scope="col" style="width: 2.25rem"><span class="sr-only">{{ __('reasons.reorder_hint') }}</span></th>
                  <th scope="col">{{ __('reasons.columns.reason') }}</th>
                  <th scope="col">{{ __('reasons.columns.source') }}</th>
                  <th scope="col">{{ __('reasons.columns.details') }}</th>
                  <th scope="col">{{ __('reasons.columns.status') }}</th>
                  <th scope="col"><span class="sr-only">{{ __('reasons.columns.action') }}</span></th>
                </tr>
              </thead>

              <tbody data-reason-list>
                @foreach ($reasons as $reason)
                  @php
                      /* Assembled here rather than inline in the attribute:
                         Blade's json directive counts brackets instead of
                         reading PHP, so an array literal written inside the
                         tag ends at its first closing bracket. */
                      $editable = [
                          'id' => $reason->id,
                          'name' => $reason->name,
                          'description' => $reason->description,
                          'requires_details' => (bool) $reason->requires_details,
                          'system' => $reason->isSystem(),
                          'url' => route('settings.reasons.update', $reason),
                      ];
                  @endphp

                  <tr draggable="true" data-reason-row data-id="{{ $reason->id }}"
                      class="{{ $reason->is_active ? '' : 'opacity-60' }}">
                    <td>
                      <span class="styledesk_catrow__grip" data-drag-handle aria-hidden="true"
                            title="{{ __('reasons.reorder_hint') }}">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><circle cx="9" cy="6" r="1.6"/><circle cx="15" cy="6" r="1.6"/><circle cx="9" cy="12" r="1.6"/><circle cx="15" cy="12" r="1.6"/><circle cx="9" cy="18" r="1.6"/><circle cx="15" cy="18" r="1.6"/></svg>
                      </span>
                    </td>

                    <td>
                      <span class="sd-cell__label">{{ __('reasons.columns.reason') }}</span>
                      <span class="min-w-0">
                        <span class="block text-[14px] font-medium text-head">{{ $reason->name }}</span>
                        @if ($reason->description)
                          <span class="block text-[12px] text-sub">{{ $reason->description }}</span>
                        @endif
                      </span>
                    </td>

                    <td>
                      <span class="sd-cell__label">{{ __('reasons.columns.source') }}</span>
                      <span class="styledesk_metachip">
                        {{ $reason->isSystem() ? __('reasons.system') : __('reasons.custom') }}
                      </span>
                      {{-- Renamed from what StyleDesk called it, which is
                           worth saying: it is still our reason underneath,
                           and that is how a later default finds it. --}}
                      @if ($reason->isRenamed())
                        <span class="styledesk_metachip">{{ __('reasons.renamed') }}</span>
                      @endif
                    </td>

                    <td>
                      <span class="sd-cell__label">{{ __('reasons.columns.details') }}</span>
                      @if ($reason->requires_details)
                        <span class="styledesk_badge styledesk_badge--setup">{{ __('reasons.details_label') }}</span>
                      @else
                        <span class="text-faint">—</span>
                      @endif
                    </td>

                    <td>
                      <span class="sd-cell__label">{{ __('reasons.columns.status') }}</span>
                      <span class="styledesk_badge {{ $reason->is_active ? 'styledesk_badge--active' : 'styledesk_badge--soon' }}">
                        {{ $reason->is_active ? __('reasons.active') : __('reasons.inactive') }}
                      </span>
                    </td>

                    <td class="text-right">
                      <span class="styledesk_rowmenu" data-rowmenu>
                        <button type="button" class="styledesk_rowmenu__button" data-rowmenu-button
                                aria-haspopup="true" aria-expanded="false"
                                aria-label="{{ $reason->name }}">
                          <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                            <circle cx="5" cy="12" r="1.7"/><circle cx="12" cy="12" r="1.7"/><circle cx="19" cy="12" r="1.7"/>
                          </svg>
                        </button>

                        <span class="styledesk_rowmenu__pop" data-rowmenu-pop hidden role="menu">
                          <button type="button" class="styledesk_rowmenu__item w-full" role="menuitem"
                                  data-reason-edit data-reason='@json($editable)'>
                            {{ __('reasons.edit') }}
                          </button>

                          <button type="submit" form="reasonToggle{{ $reason->id }}" class="styledesk_rowmenu__item w-full" role="menuitem">
                            {{ $reason->is_active ? __('reasons.deactivate') : __('reasons.activate') }}
                          </button>

                          {{-- Absent for a StyleDesk reason rather than shown
                               disabled: it is not a thing that will become
                               possible, and a greyed row invites the reader
                               to work out why. --}}
                          @unless ($reason->isSystem())
                            <span class="styledesk_rowmenu__rule" role="separator"></span>

                            <button type="submit" form="reasonDelete{{ $reason->id }}" role="menuitem"
                                    class="styledesk_rowmenu__item styledesk_rowmenu__item--danger w-full"
                                    data-confirm-title="{{ __('reasons.delete') }}"
                                    data-confirm="{{ __('reasons.delete_confirm', ['name' => $reason->name]) }}"
                                    data-confirm-label="{{ __('reasons.delete') }}"
                                    data-confirm-tone="danger">
                              {{ __('reasons.delete') }}
                            </button>
                          @endunless
                        </span>
                      </span>
                    </td>

                    <input type="hidden" name="order[]" value="{{ $reason->id }}">
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>

          {{-- Only offered once something has moved: a Save that does nothing
               is a Save the reader learns to ignore. --}}
          <div class="mt-3 flex items-center gap-3" data-reorder-bar hidden>
            <button type="submit" class="h-9 px-4 rounded-lg bg-brand hover:bg-brand-dark text-white text-[13px] font-semibold transition-colors">
              {{ __('reasons.save_order') }}
            </button>
            <p class="text-[12px] text-sub">{{ __('reasons.order_changed') }}</p>
          </div>
        </form>

        {{-- The per-row forms, kept out of the table: a form is not something
             a <tr> may contain, and the browser silently drops one that is. --}}
        @foreach ($reasons as $reason)
          <form id="reasonToggle{{ $reason->id }}" method="POST" action="{{ route('settings.reasons.toggle', $reason) }}" class="hidden">
            @csrf @method('PATCH')
          </form>

          @unless ($reason->isSystem())
            <form id="reasonDelete{{ $reason->id }}" method="POST" action="{{ route('settings.reasons.destroy', $reason) }}" class="hidden">
              @csrf @method('DELETE')
            </form>
          @endunless
        @endforeach
      @endif
    </div>

    @include('settings.reasons._modal')
  </main>
@endsection

@push('scripts')
  @include('settings.reasons._scripts')
@endpush
