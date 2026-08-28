{{--
    The booking form, as its own file.

    One partial per accordion card: the page composes them, and a card
    that grows a field does not grow the page it sits on.
--}}
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
        
