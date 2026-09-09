{{--
    The header language selector.

    Shown only when the business has enabled more than one language: a control
    offering one choice is furniture, and it would sit in the header of every
    salon that will never use it.

    Each option is written in its own language, because a Spanish speaker
    should not have to read English to escape English.
--}}
@php
    $languageUser = auth()->user();
    $languageOptions = App\Support\Locale::enabledFor($languageUser?->tenant);
    $currentLanguage = app()->getLocale();
@endphp

@if ($languageOptions->count() > 1)
  <div class="sd-menu sd-menu--right hidden sm:block" data-language-menu>
    <button type="button" data-language-button
            class="sd-navicon sd-tip grid" data-tip="{{ __('languages.selector_label') }}"
            aria-haspopup="true" aria-expanded="false" aria-label="{{ __('languages.selector_label') }}">
      <x-icon name="language" size="18" />
    </button>

    <div class="sd-menu__pop" data-menu-pop hidden role="menu" aria-label="{{ __('languages.selector_label') }}">
      <p class="px-3 pt-2 pb-1 text-[11px] font-semibold uppercase tracking-wide text-faint">
        {{ __('languages.your_language') }}
      </p>

      @foreach ($languageOptions as $code)
        {{-- A form per option rather than links: changing a language changes
             stored state, and state changes do not belong behind a GET that a
             prefetch or a crawler could trigger. --}}
        <form method="POST" action="{{ route('language.preference') }}">
          @csrf
          @method('PATCH')
          <input type="hidden" name="locale" value="{{ $code }}">

          <button type="submit" class="sd-menu__item w-full text-left" role="menuitem"
                  @if ($code === $currentLanguage) aria-current="true" @endif>
            <span class="flex items-center gap-2">
              <span class="min-w-0 flex-1">{{ App\Support\Locale::nativeName($code) }}</span>

              @if ($code === $currentLanguage)
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" class="shrink-0 text-brand" aria-hidden="true">
                  <path d="M5 12.5l4.5 4.5L19 7.5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
              @endif
            </span>
          </button>
        </form>
      @endforeach

      <div class="sd-menu__rule" role="separator"></div>

      <p class="px-3 pb-2 text-[11px] text-faint leading-relaxed">
        {{ __('languages.your_language_hint') }}
      </p>
    </div>
  </div>

{{-- Its own toggle rather than the shared [data-menu] handler.

     That handler lives in dashboard.blade.php, so it binds on exactly one
     screen — the Add menu beside this one silently does nothing on every
     other page. This control ships in the layout, so its behaviour ships
     with it rather than depending on whichever page happens to be open. --}}
@once
  @push('scripts')
    <script>
      (function () {
        var menu = document.querySelector('[data-language-menu]');
        if (!menu) return;

        var button = menu.querySelector('[data-language-button]');
        var pop = menu.querySelector('[data-menu-pop]');

        function close() {
          pop.hidden = true;
          button.setAttribute('aria-expanded', 'false');
          menu.classList.remove('is-open');
        }

        button.addEventListener('click', function (e) {
          e.stopPropagation();
          var opening = pop.hidden;
          pop.hidden = !opening;
          button.setAttribute('aria-expanded', opening ? 'true' : 'false');
          menu.classList.toggle('is-open', opening);
        });

        pop.addEventListener('click', function (e) { e.stopPropagation(); });

        document.addEventListener('click', close);
        document.addEventListener('keydown', function (e) {
          if (e.key === 'Escape') close();
        });
      }());
    </script>
  @endpush
@endonce
@endif
