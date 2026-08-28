{{--
    The client records form, as its own file.

    One partial per accordion card: the page composes them, and a card
    that grows a field does not grow the page it sits on.
--}}
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
        
