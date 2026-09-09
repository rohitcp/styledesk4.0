{{--
    The communication form, as its own file.

    One partial per accordion card: the page composes them, and a card
    that grows a field does not grow the page it sits on.
--}}
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
        
