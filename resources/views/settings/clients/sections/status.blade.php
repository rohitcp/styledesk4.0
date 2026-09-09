{{--
    The status form, as its own file.

    One partial per accordion card: the page composes them, and a card
    that grows a field does not grow the page it sits on.
--}}
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
        
