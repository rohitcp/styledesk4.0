{{--
    The privacy form, as its own file.

    One partial per accordion card: the page composes them, and a card
    that grows a field does not grow the page it sits on.
--}}
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
        
