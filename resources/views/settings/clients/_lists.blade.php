{{--
    Preferences and tags — card 2.

    Their own forms rather than fields inside the settings form. A tag is a
    record with its own lifecycle: adding one should not require saving forty
    unrelated switches, and a validation failure on those switches should not
    lose the tag somebody just typed.
--}}
<div class="space-y-5">
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
      <label class="styledesk_choice">
        <input type="checkbox" name="preferences_enabled" value="1" class="sd-check mt-0.5"
               form="clientSettingsForm" @checked((bool) old('preferences_enabled', $settings->preferences_enabled))>
        <span class="styledesk_choice__label">{{ __('clients.preferences.enabled') }}</span>
      </label>

      <label class="styledesk_choice">
        <input type="checkbox" name="preferences_multiple" value="1" class="sd-check mt-0.5"
               form="clientSettingsForm" @checked((bool) old('preferences_multiple', $settings->preferences_multiple))>
        <span class="styledesk_choice__label">{{ __('clients.preferences.multiple') }}</span>
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
              <button type="submit" class="sd-iconbtn grid sd-tip"
                      data-tip="{{ $preference->is_active ? __('clients.deactivate') : __('clients.activate') }}"
                      aria-label="{{ $preference->is_active ? __('clients.deactivate') : __('clients.activate') }}">
                @if ($preference->is_active)
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="8.5" stroke="currentColor" stroke-width="1.7"/><path d="M8.5 12h7" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"/></svg>
                @else
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="8.5" stroke="currentColor" stroke-width="1.7"/><path d="M8.5 12.2l2.4 2.4 4.6-5.2" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"/></svg>
                @endif
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
              class="styledesk_action styledesk_action--tall">
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

    {{-- Above the list rather than under it: it says how the list behaves,
         which is worth knowing before reading twenty rows rather than after. --}}
    <p class="mt-1 text-[12px] text-sub">{{ __('clients.tags.multiple_note') }}</p>

    <label class="mt-3 flex items-start gap-2.5 cursor-pointer">
      <input type="checkbox" name="tags_enabled" value="1" class="sd-check mt-0.5"
             form="clientSettingsForm" @checked((bool) old('tags_enabled', $settings->tags_enabled))>
      <span class="text-[13px] text-ink">{{ __('clients.tags.enabled') }}</span>
    </label>

    @if ($tags->isEmpty())
      <p class="mt-3 text-[13px] text-sub">{{ __('clients.tags.empty') }}</p>
    @else
      <ul class="mt-3 rounded-lg border border-line divide-y divide-line" data-tag-list>
        @foreach ($tags as $tag)
          <li class="px-3 py-2.5" data-tag-row data-id="{{ $tag->id }}">
            {{-- What the row normally shows. --}}
            <div class="flex flex-wrap items-center gap-3" data-tag-view>
              <span class="text-faint cursor-grab select-none" aria-hidden="true" data-tag-handle>⠿</span>

              <span class="h-3 w-3 shrink-0 rounded-full" style="background: {{ $tag->hex() }}" aria-hidden="true"></span>

              <span class="min-w-0 flex-1 text-[13px] {{ $tag->is_active ? 'text-ink' : 'text-faint line-through' }}">
                {{ $tag->label }}
              </span>

              {{-- Icons rather than three words per row: with twenty tags the
                   text repeated sixty times, and the row's own label was the
                   quietest thing in it. Each carries a tooltip and the same
                   words as its accessible name, so nothing is lost to anyone
                   who cannot see the icon. --}}
              <button type="button" class="sd-iconbtn grid sd-tip" data-tag-edit
                      data-tip="{{ __('common.edit') }}" aria-label="{{ __('common.edit') }}">
                <x-icon name="pen-to-square" size="14" />
              </button>

              <form method="POST" action="{{ route('settings.clients.tags.toggle', $tag) }}">
                @csrf
                @method('PATCH')
                <button type="submit" class="sd-iconbtn grid sd-tip"
                        data-tip="{{ $tag->is_active ? __('clients.deactivate') : __('clients.activate') }}"
                        aria-label="{{ $tag->is_active ? __('clients.deactivate') : __('clients.activate') }}">
                  @if ($tag->is_active)
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="8.5" stroke="currentColor" stroke-width="1.7"/><path d="M8.5 12h7" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"/></svg>
                  @else
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="8.5" stroke="currentColor" stroke-width="1.7"/><path d="M8.5 12.2l2.4 2.4 4.6-5.2" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"/></svg>
                  @endif
                </button>
              </form>

              {{-- Only offered when it can actually be done. A tag a client
                   carries is part of that client's record; the button that
                   remains is Deactivate, which is what "remove" means once a
                   label has been used. --}}
              @if ($tag->isDeletable())
                <form method="POST" action="{{ route('settings.clients.tags.destroy', $tag) }}">
                  @csrf
                  @method('DELETE')
                  <button type="submit" class="sd-iconbtn grid sd-tip styledesk_iconbtn--danger"
                          data-tip="{{ __('common.delete') }}" aria-label="{{ __('common.delete') }}"
                          data-confirm="{{ __('clients.tags.delete_confirm', ['label' => $tag->label]) }}"
                          data-confirm-title="{{ __('common.delete') }}"
                          data-confirm-label="{{ __('common.delete') }}">
                    <x-icon name="trash-can" size="14" />
                  </button>
                </form>
              @endif
            </div>

            {{-- The same row as a form. Shown in place rather than on its own
                 screen: renaming a tag is a change to one word. --}}
            <form method="POST" action="{{ route('settings.clients.tags.update', $tag) }}"
                  class="flex flex-wrap items-center gap-2" data-tag-form hidden>
              @csrf
              @method('PATCH')

              <input name="label" type="text" class="sd-input flex-1 min-w-[160px]" data-capitalize
                     value="{{ $tag->label }}" aria-label="{{ __('clients.tags.name') }}">

              <select name="color" class="sd-input w-[150px]" data-combo data-combo-options='{"search":false,"width":"200px"}'
                      aria-label="{{ __('clients.tags.colour') }}">
                @foreach (App\Support\ClientOptions::tagColors() as $key => $colorLabel)
                  <option value="{{ $key }}" @selected($tag->color === $key)>{{ $colorLabel }}</option>
                @endforeach
              </select>

              <button type="submit" class="styledesk_action styledesk_action--tall">{{ __('common.save') }}</button>
              <button type="button" class="styledesk_action styledesk_action--tall" data-tag-cancel>{{ __('common.cancel') }}</button>
            </form>
          </li>
        @endforeach
      </ul>

      <form method="POST" action="{{ route('settings.clients.tags.reorder') }}" class="hidden" data-tag-order-form>
        @csrf
        @method('PATCH')
      </form>

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
        <select name="color" class="sd-input" data-combo data-combo-options='{"search":false,"width":"200px"}'
                aria-label="{{ __('clients.tags.colour') }}">
          @foreach (App\Support\ClientOptions::tagColors() as $key => $colorLabel)
            <option value="{{ $key }}">{{ $colorLabel }}</option>
          @endforeach
        </select>
      </div>

      <button type="submit"
              class="styledesk_action styledesk_action--tall">
        {{ __('clients.tags.add') }}
      </button>
    </form>
  </div>
</div>
