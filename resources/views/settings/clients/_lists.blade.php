{{--
    Preferences and tags — card 2.

    Their own forms rather than fields inside the settings form. A tag is a
    record with its own lifecycle: adding one should not require saving forty
    unrelated switches, and a validation failure on those switches should not
    lose the tag somebody just typed.
--}}
<section class="mt-5 bg-white border border-line rounded-card p-5 space-y-5">
  <div>
    <h2 class="text-[15px] font-semibold text-head">{{ __('clients.cards.lists') }}</h2>
    <p class="text-[13px] text-sub mt-0.5">{{ __('clients.cards.lists_hint') }}</p>
  </div>

  {{-- ------------------------------------------------ preferences --}}
  <div class="pt-4 border-t border-line">
    <h3 class="text-[13px] font-medium text-ink">{{ __('clients.preferences.title') }}</h3>

    <div class="mt-3 space-y-3">
      {{-- The two switches belong to the settings form above, so they are
           posted from there by name rather than duplicated here. --}}
      <label class="flex items-start gap-2.5 cursor-pointer">
        <input type="checkbox" name="preferences_enabled" value="1" class="sd-check mt-0.5"
               form="clientSettingsForm" @checked((bool) old('preferences_enabled', $settings->preferences_enabled))>
        <span class="text-[13px] text-ink">{{ __('clients.preferences.enabled') }}</span>
      </label>

      <label class="flex items-start gap-2.5 cursor-pointer">
        <input type="checkbox" name="preferences_multiple" value="1" class="sd-check mt-0.5"
               form="clientSettingsForm" @checked((bool) old('preferences_multiple', $settings->preferences_multiple))>
        <span class="text-[13px] text-ink">{{ __('clients.preferences.multiple') }}</span>
      </label>
    </div>

    @if ($preferences->isEmpty())
      <p class="mt-3 text-[13px] text-sub">{{ __('clients.preferences.empty') }}</p>
    @else
      <ul class="mt-3 rounded-lg border border-line divide-y divide-line" data-preference-list>
        @foreach ($preferences as $preference)
          <li class="flex flex-wrap items-center gap-3 px-3 py-2.5" data-preference-row data-id="{{ $preference->id }}">
            <span class="text-faint cursor-grab select-none" aria-hidden="true" data-preference-handle>⠿</span>

            <span class="min-w-0 flex-1 text-[13px] {{ $preference->is_active ? 'text-ink' : 'text-faint line-through' }}">
              {{ $preference->label }}
            </span>

            <form method="POST" action="{{ route('settings.clients.preferences.toggle', $preference) }}">
              @csrf
              @method('PATCH')
              <button type="submit" class="text-[13px] font-medium {{ $preference->is_active ? 'text-sub hover:text-danger' : 'text-link' }} hover:underline">
                {{ $preference->is_active ? __('clients.deactivate') : __('clients.activate') }}
              </button>
            </form>
          </li>
        @endforeach
      </ul>

      <p class="mt-2 text-[12px] text-sub">{{ __('clients.preferences.deactivate_note') }}</p>
    @endif

    <form method="POST" action="{{ route('settings.clients.preferences.store') }}"
          class="mt-3 flex flex-wrap items-start gap-2">
      @csrf
      <div class="min-w-[220px] flex-1">
        <input name="label" type="text" class="sd-input" data-capitalize
               placeholder="{{ __('clients.preferences.placeholder') }}"
               aria-label="{{ __('clients.preferences.add') }}">
        @error('label')<p class="mt-1.5 text-[12px] text-danger">{{ $message }}</p>@enderror
      </div>

      <button type="submit"
              class="h-11 px-4 rounded-lg border border-stroke bg-white hover:bg-hover text-ink text-[13px] font-semibold transition-colors">
        {{ __('clients.preferences.add') }}
      </button>
    </form>

    <form method="POST" action="{{ route('settings.clients.preferences.reorder') }}" class="hidden" data-preference-order-form>
      @csrf
      @method('PATCH')
    </form>
  </div>

  {{-- ------------------------------------------------------- tags --}}
  <div class="pt-4 border-t border-line">
    <h3 class="text-[13px] font-medium text-ink">{{ __('clients.tags.title') }}</h3>

    <label class="mt-3 flex items-start gap-2.5 cursor-pointer">
      <input type="checkbox" name="tags_enabled" value="1" class="sd-check mt-0.5"
             form="clientSettingsForm" @checked((bool) old('tags_enabled', $settings->tags_enabled))>
      <span class="text-[13px] text-ink">{{ __('clients.tags.enabled') }}</span>
    </label>

    @if ($tags->isEmpty())
      <p class="mt-3 text-[13px] text-sub">{{ __('clients.tags.empty') }}</p>
    @else
      <ul class="mt-3 rounded-lg border border-line divide-y divide-line">
        @foreach ($tags as $tag)
          <li class="flex flex-wrap items-center gap-3 px-3 py-2.5">
            <span class="h-3 w-3 shrink-0 rounded-full" style="background: {{ $tag->hex() }}" aria-hidden="true"></span>

            <span class="min-w-0 flex-1 text-[13px] {{ $tag->is_active ? 'text-ink' : 'text-faint line-through' }}">
              {{ $tag->label }}
            </span>

            <form method="POST" action="{{ route('settings.clients.tags.toggle', $tag) }}">
              @csrf
              @method('PATCH')
              <button type="submit" class="text-[13px] font-medium {{ $tag->is_active ? 'text-sub hover:text-danger' : 'text-link' }} hover:underline">
                {{ $tag->is_active ? __('clients.deactivate') : __('clients.activate') }}
              </button>
            </form>
          </li>
        @endforeach
      </ul>

      <p class="mt-2 text-[12px] text-sub">{{ __('clients.tags.multiple_note') }}</p>
    @endif

    <form method="POST" action="{{ route('settings.clients.tags.store') }}"
          class="mt-3 flex flex-wrap items-start gap-2">
      @csrf
      <div class="min-w-[200px] flex-1">
        <input name="label" type="text" class="sd-input" data-capitalize
               placeholder="{{ __('clients.tags.placeholder') }}"
               aria-label="{{ __('clients.tags.add') }}">
      </div>

      <div class="w-[150px]">
        <select name="color" class="sd-input" aria-label="{{ __('clients.tags.colour') }}">
          @foreach (config('clients.tag_colors') as $key => $hex)
            <option value="{{ $key }}">{{ ucfirst($key) }}</option>
          @endforeach
        </select>
      </div>

      <button type="submit"
              class="h-11 px-4 rounded-lg border border-stroke bg-white hover:bg-hover text-ink text-[13px] font-semibold transition-colors">
        {{ __('clients.tags.add') }}
      </button>
    </form>
  </div>
</section>
