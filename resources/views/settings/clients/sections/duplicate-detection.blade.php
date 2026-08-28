{{--
    The duplicate detection form, as its own file.

    One partial per accordion card: the page composes them, and a card
    that grows a field does not grow the page it sits on.
--}}
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
        
