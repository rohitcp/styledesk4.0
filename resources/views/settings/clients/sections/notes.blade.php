{{--
    The notes form, as its own file.

    One partial per accordion card: the page composes them, and a card
    that grows a field does not grow the page it sits on.
--}}
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
        
