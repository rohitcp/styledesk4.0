@extends('layouts.app')

@section('title', $staff->displayName().' — '.__('staff.tabs.services'))

@section('content')
  {{-- The full-width container the clients screens use: the same padding,
       the same breakpoints, no narrow column of its own. A workspace that
       sat in 1080px while every other screen filled the window would read
       as a different application. --}}
  <main class="w-full px-6 lg:px-8 pt-4 pb-[100px]">

      @include('settings.staff._header', ['tab' => 'services'])

      <section class="bg-white border border-line rounded-card p-5 mt-5">
        <div class="flex flex-wrap items-start gap-4">
          <div class="min-w-0 flex-1">
            <h2 class="text-[15px] font-semibold text-head">{{ __('staff.services_tab.title') }}</h2>
            <p class="text-[13px] text-sub mt-1 leading-relaxed max-w-[560px]">
              {{ __('staff.services_tab.intro') }}
            </p>
          </div>

          @can('update', $staff)
            @if ($available->isNotEmpty())
              {{-- The picker is on the page rather than behind a dialog: it is
                   a search and a list of checkboxes, and a dialog over a table
                   of the same rows is one more thing to open and dismiss. --}}
              <button type="button" class="shrink-0 inline-flex items-center gap-1.5 h-9 px-3.5 rounded-lg bg-brand hover:bg-brand-dark text-white text-[13px] font-semibold transition-colors"
                      data-add-services-toggle aria-expanded="false" aria-controls="addServices">
                <x-icon name="plus" size="14" />
                {{ __('staff.services_tab.add') }}
              </button>
            @endif
          @endcan
        </div>

        @can('update', $staff)
          @if ($available->isNotEmpty())
            <form id="addServices" method="POST" action="{{ \App\Support\StaffSection::route('services.attach', $staff) }}"
                  class="mt-4 rounded-lg border border-line p-4" hidden>
              @csrf

              <x-combo name="service_ids" multiple
                       :label="__('staff.services_tab.choose')"
                       :placeholder="__('staff.fields.services_placeholder')"
                       :options="$available->pluck('name', 'id')"
                       :selected="[]" />

              <div class="flex flex-wrap items-center gap-2 mt-3">
                <button type="submit" data-submit-once data-busy-label="{{ __('common.saving') }}"
                        class="h-9 px-4 rounded-lg bg-brand hover:bg-brand-dark text-white text-[13px] font-semibold transition-colors">
                  {{ __('staff.services_tab.add') }}
                </button>
                <button type="button" class="styledesk_action" data-add-services-cancel>{{ __('common.cancel') }}</button>
              </div>
            </form>
          @endif
        @endcan

        @if ($staff->services->isEmpty())
          {{-- Said in full, because it is the reason this person cannot be
               booked by name — not merely an empty table. --}}
          <div class="border-t border-line mt-4 py-12 text-center">
            <p class="text-[15px] font-semibold text-head">{{ __('staff.services_tab.none') }}</p>
            <p class="text-[13px] text-sub mt-1.5 max-w-[420px] mx-auto leading-relaxed">
              {{ __('staff.services_tab.none_hint', ['name' => $staff->displayName()]) }}
            </p>
          </div>
        @else
          <div class="mt-4 overflow-x-auto styledesk_scroll">
            <table class="w-full text-[13px]">
              <thead>
                <tr class="text-left text-sub border-b border-line">
                  <th class="font-medium py-2 pr-4">{{ __('services.name') }}</th>
                  <th class="font-medium py-2 pr-4">{{ __('services.category') }}</th>
                  <th class="font-medium py-2 pr-4">{{ __('services.duration') }}</th>
                  <th class="font-medium py-2 pr-4">{{ __('services.price') }}</th>
                  <th class="font-medium py-2 pr-4">{{ __('staff.columns.status') }}</th>
                  <th class="font-medium py-2 text-right">{{ __('staff.columns.actions') }}</th>
                </tr>
              </thead>
              <tbody>
                @foreach ($staff->services as $service)
                  <tr class="border-b border-line last:border-0">
                    <td class="py-2.5 pr-4 font-medium text-head">{{ $service->name }}</td>
                    <td class="py-2.5 pr-4 text-ink">{{ $service->category?->name ?? __('services.uncategorised') }}</td>
                    <td class="py-2.5 pr-4 text-ink">{{ $service->durationLabel() }}</td>
                    <td class="py-2.5 pr-4 text-ink">{{ $service->priceLabel($currency) ?: '—' }}</td>
                    <td class="py-2.5 pr-4">
                      <span class="styledesk_badge {{ $service->is_active ? 'styledesk_badge--active' : 'styledesk_badge--soon' }}">
                        {{ $service->is_active ? __('services.status.active') : __('services.status.inactive') }}
                      </span>
                    </td>
                    <td class="py-2.5 text-right">
                      @can('update', $staff)
                        {{-- Removing takes the service off this person; the
                             service itself is untouched, and the confirmation
                             says so. --}}
                        <form method="POST" action="{{ \App\Support\StaffSection::route('services.detach', [$staff, $service]) }}">
                          @csrf
                          @method('DELETE')
                          <button type="submit" class="styledesk_action styledesk_action--sm styledesk_action--remove"
                                  data-confirm="{{ __('staff.services_tab.remove_confirm', ['service' => $service->name, 'name' => $staff->displayName()]) }}"
                                  data-confirm-title="{{ __('staff.services_tab.remove') }}"
                                  data-confirm-label="{{ __('staff.services_tab.remove') }}"
                                  data-confirm-tone="danger">
                            {{ __('staff.services_tab.remove') }}
                          </button>
                        </form>
                      @endcan
                    </td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
        @endif
      </section>
  </main>
@endsection
