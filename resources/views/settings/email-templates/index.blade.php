{{--
    App Settings → Email Templates.

    The whole catalogue, not only what the business has customised: an owner
    looking for "Booking Confirmation" wants to find it, not to discover it
    exists once they have already edited it. A template with no row of its own
    is StyleDesk's default, and says so.
--}}
@extends('layouts.app')

@section('title', __('email_templates.title'))

@section('content')
  <main class="w-full px-4 sm:px-5 lg:px-6 pt-5 sm:pt-6 pb-[200px]">

    <nav class="text-[13px] text-sub" aria-label="Breadcrumb">
      <a href="{{ route('settings.index') }}" class="hover:text-ink transition-colors">{{ __('navigation.app_settings') }}</a>
      <span class="mx-1.5 text-faint">/</span>
      <span class="text-ink">{{ __('email_templates.title') }}</span>
    </nav>

    <div class="mt-3 flex flex-wrap items-start gap-4">
      <div class="min-w-0 flex-1">
        <h1 class="text-[24px] sm:text-[28px] font-bold text-head tracking-tight">{{ __('email_templates.title') }}</h1>
        <p class="text-[14px] text-sub mt-2 max-w-[640px] leading-relaxed">{{ __('email_templates.intro') }}</p>
      </div>

      <div class="shrink-0 flex items-center gap-2.5">
        <a href="{{ route('settings.email-templates.create') }}"
           class="inline-flex items-center gap-1.5 h-9 px-3.5 rounded-lg bg-brand hover:bg-brand-dark text-white text-[13px] font-semibold transition-colors">
          <x-icon name="plus" size="14" />
          {{ __('email_templates.list.create') }}
        </a>

        <a href="{{ route('settings.index') }}" class="styledesk_action">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M14 6l-6 6 6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
          {{ __('common.back') }}
        </a>
      </div>
    </div>

    @if (session('status'))
      <div class="sd-alert sd-alert--info mt-5" role="status"><p class="min-w-0">{{ session('status') }}</p></div>
    @endif

    {{-- Said once. Every email wears one design, and an owner who expected a
         drag-and-drop builder should learn why here rather than by hunting
         for one. --}}
    <div class="sd-alert sd-alert--info mt-5" role="status">
      <div class="flex items-start gap-2.5">
        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" class="shrink-0 mt-px" aria-hidden="true"><circle cx="12" cy="12" r="8.5" stroke="currentColor" stroke-width="1.7"/><path d="M12 11v5.5M12 8v.4" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"/></svg>
        <p class="min-w-0">
          <strong class="font-semibold">{{ __('email_templates.theme.name') }}</strong> —
          {{ __('email_templates.theme.note') }}
        </p>
      </div>
    </div>

    {{-- ---------------------------------------------------------- filters --}}
    <form method="GET" action="{{ route('settings.email-templates.index') }}"
          class="mt-5 flex flex-wrap items-end gap-3">

      <div class="min-w-0 flex-1 basis-72">
        <label for="search" class="block text-[12px] font-medium text-ink mb-1.5">{{ __('email_templates.list.search') }}</label>
        <input id="search" name="search" type="search" class="sd-input"
               placeholder="{{ __('email_templates.list.search_placeholder') }}"
               value="{{ $filters['search'] }}">
      </div>

      <div>
        <label for="type" class="block text-[12px] font-medium text-ink mb-1.5">{{ __('email_templates.list.type') }}</label>
        <select id="type" name="type" class="sd-input" onchange="this.form.submit()">
          <option value="">{{ __('email_templates.list.all_types') }} ({{ $counts['all'] }})</option>
          <option value="transactional" @selected($filters['type'] === 'transactional')>
            {{ __('email_templates.types.transactional') }} ({{ $counts['transactional'] }})
          </option>
          <option value="standard" @selected($filters['type'] === 'standard')>
            {{ __('email_templates.types.standard') }} ({{ $counts['standard'] }})
          </option>
        </select>
      </div>

      <div>
        <label for="status" class="block text-[12px] font-medium text-ink mb-1.5">{{ __('email_templates.list.status') }}</label>
        <select id="status" name="status" class="sd-input" onchange="this.form.submit()">
          <option value="">{{ __('email_templates.list.any_status') }}</option>
          <option value="active" @selected($filters['status'] === 'active')>{{ __('email_templates.list.active') }}</option>
          <option value="disabled" @selected($filters['status'] === 'disabled')>
            {{ __('email_templates.list.disabled_label') }} ({{ $counts['disabled'] }})
          </option>
        </select>
      </div>

      <button type="submit" class="h-10 px-4 rounded-lg bg-brand hover:bg-brand-dark text-white text-[13px] font-semibold transition-colors">
        {{ __('email_templates.list.apply') }}
      </button>

      @if ($filters['search'] !== '' || $filters['type'] !== '' || $filters['status'] !== '')
        <a href="{{ route('settings.email-templates.index') }}"
           class="h-10 inline-flex items-center px-3 text-[13px] font-semibold text-link hover:underline">
          {{ __('email_templates.list.clear') }}
        </a>
      @endif
    </form>

    {{-- ------------------------------------------------------------ table --}}
    <section class="bg-white border border-line rounded-card mt-4 overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full min-w-[900px] text-[13px]">
          <thead class="bg-[#fbfbfc]">
            <tr class="border-b border-line text-left text-[11.5px] uppercase tracking-wide text-sub">
              <th scope="col" class="px-4 py-3 font-semibold">{{ __('email_templates.list.name') }}</th>
              <th scope="col" class="px-4 py-3 font-semibold">{{ __('email_templates.list.type') }}</th>
              <th scope="col" class="px-4 py-3 font-semibold">{{ __('email_templates.list.trigger') }}</th>
              <th scope="col" class="px-4 py-3 font-semibold">{{ __('email_templates.list.status') }}</th>
              <th scope="col" class="px-4 py-3 font-semibold whitespace-nowrap">{{ __('email_templates.list.updated') }}</th>
              <th scope="col" class="px-4 py-3 font-semibold text-right">{{ __('email_templates.list.actions') }}</th>
            </tr>
          </thead>

          <tbody class="divide-y divide-line">
            @forelse ($templates as $template)
              <tr class="odd:bg-white even:bg-[#fcfcfd] align-top">
                <td class="px-4 py-3">
                  <a href="{{ route('settings.email-templates.edit', $template->key) }}"
                     class="block font-semibold text-head hover:text-brand hover:underline">{{ $template->name }}</a>
                  {{-- The subject, because it is the line a client sees before
                       they open anything. --}}
                  <span class="block text-[12px] text-sub mt-0.5">{{ $template->subject }}</span>
                </td>

                <td class="px-4 py-3 whitespace-nowrap">
                  <span @class([
                      'styledesk_badge',
                      'styledesk_badge--soon' => $template->isTransactional(),
                      'styledesk_badge--setup' => ! $template->isTransactional(),
                  ])>{{ $template->typeLabel() }}</span>
                </td>

                <td class="px-4 py-3 text-sub">
                  {{ $template->triggerLabel() ?? __('email_templates.list.no_trigger') }}
                  @if (in_array($template->trigger, config('email_templates.unbuilt_triggers'), true))
                    {{-- Honest rather than hidden: the wording can be written
                         now, and nothing will fire it until the module exists. --}}
                    <span class="block text-[11.5px] text-faint">{{ __('email_templates.list.not_yet_fired') }}</span>
                  @endif
                </td>

                <td class="px-4 py-3 whitespace-nowrap">
                  <span @class([
                      'styledesk_badge',
                      'styledesk_badge--active' => $template->is_active,
                      'styledesk_badge--setup' => ! $template->is_active,
                  ])>
                    {{ $template->is_active ? __('email_templates.list.active') : __('email_templates.list.disabled_label') }}
                  </span>
                </td>

                <td class="px-4 py-3 whitespace-nowrap text-sub">
                  @if ($template->exists)
                    {{ \App\Support\TimeFormat::dateTime($template->updated_at) }}
                    <span class="block text-[11.5px] text-faint">
                      {{ $template->updatedBy?->name ?? __('email_templates.list.styledesk') }}
                    </span>
                  @else
                    {{-- Never touched. Saying "StyleDesk default" is more use
                         than an em dash: it tells the owner why there is no
                         date rather than leaving them to wonder. --}}
                    <span class="text-faint">{{ __('email_templates.list.styledesk_default') }}</span>
                  @endif
                </td>

                {{-- The house row menu, as every other listing uses: a kebab
                     rather than four buttons competing for the same corner.
                     Rendered in Blade because this list is 35 rows of code
                     rather than a paged grid, but the markup, the classes and
                     the behaviour are the same ones data-grid.js draws — so it
                     looks and works identically. --}}
                <td class="px-4 py-3">
                  <div class="flex justify-end">
                    <div class="styledesk_rowmenu">
                      <button type="button" class="styledesk_rowmenu__button" data-row-menu
                              aria-haspopup="true" aria-expanded="false"
                              aria-label="{{ __('email_templates.list.actions_for', ['name' => $template->name]) }}">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                          <circle cx="5" cy="12" r="1.7"/><circle cx="12" cy="12" r="1.7"/><circle cx="19" cy="12" r="1.7"/>
                        </svg>
                      </button>

                      <div class="styledesk_rowmenu__pop" role="menu" hidden>
                        <a href="{{ route('settings.email-templates.edit', $template->key) }}"
                           class="styledesk_rowmenu__item" role="menuitem">{{ __('email_templates.list.edit') }}</a>

                        {{-- Anything that changes something is a button that
                             posts, never a link: a link that mutates is one a
                             browser may follow while prefetching. --}}
                        <button type="submit" role="menuitem" class="styledesk_rowmenu__item w-full"
                                form="duplicate-{{ $loop->index }}">{{ __('email_templates.list.duplicate') }}</button>

                        <span class="styledesk_rowmenu__rule" role="separator"></span>

                        <button type="submit" role="menuitem" class="styledesk_rowmenu__item w-full"
                                form="toggle-{{ $loop->index }}">
                          {{ $template->is_active ? __('email_templates.list.disable') : __('email_templates.list.enable') }}
                        </button>

                        {{-- Only where there is something to undo. Reset on an
                             untouched template is a menu entry that does
                             nothing. --}}
                        @if ($template->exists)
                          <button type="submit" role="menuitem"
                                  class="styledesk_rowmenu__item styledesk_rowmenu__item--danger w-full"
                                  form="reset-{{ $loop->index }}">{{ __('email_templates.list.reset_action') }}</button>
                        @endif
                      </div>
                    </div>
                  </div>

                  {{-- The forms the menu buttons submit, outside the menu: the
                       panel is moved to the end of <body> when it opens, and a
                       form nested inside it would travel with it — out of the
                       table and, in some browsers, out of the DOM the button
                       expects. --}}
                  <form id="duplicate-{{ $loop->index }}" method="POST" class="hidden"
                        action="{{ route('settings.email-templates.duplicate', $template->key) }}">@csrf</form>

                  <form id="toggle-{{ $loop->index }}" method="POST" class="hidden"
                        action="{{ route('settings.email-templates.toggle', $template->key) }}">@csrf @method('PATCH')</form>

                  @if ($template->exists)
                    <form id="reset-{{ $loop->index }}" method="POST" class="hidden"
                          action="{{ route('settings.email-templates.reset', $template->key) }}">@csrf @method('DELETE')</form>
                  @endif
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="6" class="px-4 py-12 text-center text-[13px] text-sub">
                  {{ __('email_templates.list.no_matches') }}
                </td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </section>
  </main>
@endsection

@push('scripts')
  <script>
    /*
     * The row menu.
     *
     * The same behaviour data-grid.js gives a Tabulator listing, for a table
     * rendered in Blade. The panel is positioned fixed and moved to <body> on
     * open, because the table scrolls sideways and a scroll container clips
     * anything absolutely positioned inside it — the menu on the last column
     * was cut off at the table's edge. Fixed cannot follow a scrolling button,
     * so it closes on scroll rather than drifting away from it.
     */
    (function () {
      let open = null;

      function close() {
        if (!open) return;

        open.panel.hidden = true;
        open.wrap.appendChild(open.panel);
        open.wrap.classList.remove('is-open');
        open.button.setAttribute('aria-expanded', 'false');
        open = null;
      }

      document.querySelectorAll('[data-row-menu]').forEach(function (button) {
        const wrap = button.closest('.styledesk_rowmenu');
        const panel = wrap.querySelector('.styledesk_rowmenu__pop');

        button.addEventListener('click', function (event) {
          event.stopPropagation();

          const wasOpen = open?.button === button;
          close();

          if (wasOpen) return;

          document.body.appendChild(panel);
          panel.hidden = false;

          const box = button.getBoundingClientRect();
          panel.style.top = `${box.bottom + 4}px`;
          /* Right-aligned to the button, and never off the left edge. */
          panel.style.left = `${Math.max(8, box.right - panel.offsetWidth)}px`;

          wrap.classList.add('is-open');
          button.setAttribute('aria-expanded', 'true');
          open = { button, panel, wrap };
        });
      });

      document.addEventListener('click', close);
      document.addEventListener('scroll', close, true);
      document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') close();
      });
    }());
  </script>
@endpush
