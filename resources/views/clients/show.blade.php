@extends('layouts.app')

@section('title', $client->displayName($settings->name_format))

{{-- A fixed-height workspace on desktop: the shell stops scrolling and each
     column takes the slack. Below the three-column breakpoint this class does
     nothing and the page scrolls as every other page does. --}}
@section('shellClass', 'styledesk_shell--workspace')

@php
    use App\Support\ClientOptions;

    $name = $client->displayName($settings->name_format);
    $methods = ClientOptions::communicationMethods();

    $primaryPhone = $client->primaryPhone();
    $primaryEmail = $client->primaryEmail();

    $address = collect([$client->address, $client->city, $client->state, $client->postal_code])->filter()->join(', ');
    $country = $client->country ? config('locations.countries.'.$client->country) : null;
@endphp

@section('content')
  {{-- Full content width. The profile is a workspace rather than a document:
       the middle column is worked in for minutes at a time, and a centred
       760px column would leave two thirds of a desktop empty while the
       activity list wraps. --}}
  <main class="w-full px-6 lg:px-8 pt-4 pb-24 xl:pb-0 xl:h-full xl:flex xl:flex-col xl:overflow-hidden">

    {{-- --------------------------------------------------- action row --}}
    {{-- The three page actions on a row of their own, above the identity.
         They never wrap: leaving, booking and the overflow menu are one
         cluster, and a Create booking that has dropped onto its own line
         reads as belonging to whatever is above it.

         Sticky below the app header on the layouts where the page itself
         scrolls, so the actions stay reachable without scrolling back to the
         top. In the three-column layout the page does not scroll at all, so
         there is nothing to stick to. --}}
    <div class="styledesk_actionrow">
      <a href="{{ route('clients.index') }}" data-tip="{{ __('clients.module.workspace.back') }}"
         aria-label="{{ __('clients.module.workspace.back') }}"
         class="styledesk_action styledesk_action--icon shrink-0">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M14 6l-6 6 6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
      </a>

      {{-- The primary action, disabled until there is a booking module to
           open. A button that looks live and is not costs more than one
           that admits it is coming. --}}
      <button type="button" disabled aria-disabled="true"
              class="h-9 px-4 rounded-lg bg-brand text-white text-[13px] font-semibold opacity-50 cursor-not-allowed shrink-0 truncate">
        {{ __('clients.module.workspace.quick.create_booking') }}
      </button>

      @include('clients.partials._actions-menu')
    </div>

    {{-- ---------------------------------------------------- name card --}}
    {{-- Stacked, not side by side: the photo, then who they are, then the
         facts about them. Beside the name it took a column of width from the
         chips and pushed them into wrapping earlier than they needed to. --}}
    <header class="mt-3 xl:shrink-0">
      <span class="sd-avatar styledesk_avatar--identity" aria-hidden="true">{{ $client->initials() }}</span>

      <div class="min-w-0 mt-2.5">
        {{-- Name and reference on one row: they are quoted together on the
             phone and printed together on a receipt. --}}
        <div class="flex flex-wrap items-center gap-2.5">
          <h1 class="text-[22px] sm:text-[24px] font-bold text-head tracking-tight leading-tight min-w-0 truncate">{{ $name }}</h1>
          <span class="styledesk_metachip styledesk_metachip--ref font-mono">{{ $client->client_ref }}</span>
        </div>

        {{-- The facts that identify them, as chips of one shape. Each carries
             its value rather than its label — "+1 202-555-1043" says what it
             is, where "Primary phone" makes the reader open something to find
             out. Only the ones that do something are links.

             justify-start rather than the default: they flow from the left
             and wrap onto a second line, never spreading to fill the row. --}}
        <div class="flex flex-wrap justify-start items-center gap-2 mt-2">
          {{-- A dot before the word: status is the one chip here that is a
               state rather than a value, and the dot is what says so at a
               glance among five that all look alike. --}}
          <span class="styledesk_metachip {{ $client->statusClass() }}">
            <span class="styledesk_statusdot" aria-hidden="true"></span>
            {{ $client->statusLabel() }}
          </span>

          <span class="styledesk_metachip styledesk_metachip--since">
            {{ __('clients.module.workspace.client_since', ['date' => $client->created_at->isoFormat('MMM Y')]) }}
          </span>

          @if ($client->date_of_birth)
            <span class="styledesk_metachip styledesk_metachip--dob">
              {{ __('clients.module.workspace.dob', ['date' => $client->date_of_birth->isoFormat('D MMM Y')]) }}
            </span>
          @endif

          @if ($canViewContact && $primaryPhone)
            <a href="tel:{{ $primaryPhone->number }}" class="styledesk_metachip styledesk_metachip--phone"
               data-tip="{{ __('clients.module.workspace.contact.call') }}">
              {{ $primaryPhone->number }}
            </a>
          @endif

          @if ($canViewContact && $primaryEmail)
            <a href="mailto:{{ $primaryEmail->email }}" class="styledesk_metachip styledesk_metachip--email"
               data-tip="{{ __('clients.module.workspace.contact.send_email') }}">
              {{ $primaryEmail->email }}
            </a>
          @endif

          @if ($client->preferredLocation)
            <a href="{{ route('settings.locations.show', $client->preferredLocation) }}"
               class="styledesk_metachip styledesk_metachip--place">
              {{ $client->preferredLocation->name }}
            </a>
          @endif

          @if (filled($client->preferred_name) && $client->preferred_name !== $client->first_name)
            <span class="styledesk_metachip">
              {{ __('clients.module.workspace.identity.preferred_name', ['name' => $client->preferred_name]) }}
            </span>
          @endif
        </div>
      </div>
    </header>

    @if ($client->isArchived())
      <div class="sd-alert sd-alert--info mt-4" role="status">
        <div class="flex items-start gap-2.5">
          <svg width="17" height="17" viewBox="0 0 24 24" fill="none" class="shrink-0 mt-px" aria-hidden="true"><circle cx="12" cy="12" r="8.5" stroke="currentColor" stroke-width="1.7"/><path d="M12 11v5.5M12 8v.4" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"/></svg>
          <p class="min-w-0">{{ __('clients.status.explainer') }}</p>
        </div>
      </div>
    @endif

    {{-- A line, not a gap. It separates the header from the workspace at the
         cost of one pixel, where the equivalent whitespace would be 40. --}}
    <hr class="mt-5 border-line xl:shrink-0">

    {{-- 22 / 53 / 25, divided by rules rather than gutters. The column
         borders only exist where the columns do: below lg they become the
         horizontal separators between stacked sections. --}}
    <div class="grid items-stretch lg:grid-cols-[minmax(0,22fr)_minmax(0,78fr)] xl:grid-cols-[minmax(0,22fr)_minmax(0,53fr)_minmax(0,25fr)] xl:flex-1 xl:min-h-0">

      {{-- ------------------------------------------------- left column
           The column stretches so its rule runs the full height of the row;
           the sticky wrapper inside it is what actually follows the scroll.
           A stretched element cannot stick — it is already as tall as its
           row — which is why the two jobs are given to two elements.

           Scrolls exactly as the right column does: as tall as what is left
           of the viewport below the two headers, so a scrollbar appears only
           when the cards genuinely do not fit and the height re-measures
           itself when the window is resized. overflow-x hidden for the same
           reason it is there — nobody expects this column to scroll
           sideways.

           pr-6 is the gutter before the dividing rule, and the scrollbar
           sits in it: the thumb is 8px at the inner edge, leaving ~16px
           between it and the content. Column 3 gets to the same place with
           pr-2.5, because it has no rule to clear. --}}
      <aside class="contents lg:block lg:h-full lg:pr-6 lg:border-r lg:border-line min-w-0 overflow-x-hidden
                    xl:overflow-y-auto styledesk_scroll">
        {{-- Sticky only in the two-column layout, where the page itself
             scrolls. In the three-column one the panel scrolls instead, and
             a sticky child inside a scroller sticks to the wrong thing. --}}
        {{-- Enough air to show the stack has ended, and no more: 200px of
             padding here would guarantee a scrollbar on every profile,
             including the ones whose cards already fit. --}}
        <div class="contents lg:block lg:sticky lg:top-4 xl:static xl:pb-6">
          <div class="order-1 lg:order-none py-5">@include('clients.partials._identity')</div>
          <div class="order-4 lg:order-none py-5 border-t border-line">@include('clients.partials._contact')</div>
          <div class="order-5 lg:order-none py-5 border-t border-line">@include('clients.partials._preferred')</div>
          <div class="order-6 lg:order-none py-5 border-t border-line">@include('clients.partials._client-preferences')</div>
        </div>
      </aside>

      {{-- ----------------------------------------------- centre column --}}
      <div class="order-3 lg:order-none min-w-0 lg:h-full lg:pl-6 xl:pr-6 xl:border-r xl:border-line py-5 border-t border-line lg:border-t-0
                  xl:overflow-y-auto styledesk_scroll">
        <div class="xl:pb-[200px]">
          @include('clients.partials._summary')
          @include('clients.partials._workspace')
        </div>
      </div>

      {{-- ------------------------------------------------ right column --}}
      {{-- The column is exactly as tall as what is left of the viewport
           below the two headers — it is a flex child of a full-height main
           — so `overflow-y: auto` produces a scrollbar only when the cards
           genuinely do not fit, and produces none when they do. Resizing
           the window re-measures it without any script.

           overflow-x hidden as well: a card whose content is a hair too
           wide would otherwise put a sideways scrollbar under a column
           nobody expects to scroll that way.

           pr-2.5 is the gap the scrollbar sits in, so the thumb never
           touches the edge of a card. --}}
      <aside class="order-7 lg:order-none min-w-0 overflow-x-hidden xl:h-full xl:pl-6 xl:pr-2.5 py-5 border-t border-line xl:border-t-0 lg:col-span-2 xl:col-span-1
                    xl:overflow-y-auto styledesk_scroll">
        {{-- Enough air to show the stack has ended, and no more: 200px of
             padding here would guarantee a scrollbar on every profile,
             including the ones whose cards already fit. --}}
        <div class="xl:pb-6">
          @include('clients.partials._context')
        </div>
      </aside>
    </div>
  </main>
@endsection

@push('scripts')
  <script>
    /* Tabs. The panels are all in the page, so switching one costs nothing
       and the browser's own Back button is left alone — the tab is a view of
       one record, not a place. */
    (function () {
      var tabs = Array.prototype.slice.call(document.querySelectorAll('[data-tab]'));
      if (!tabs.length) return;

      function show(name) {
        tabs.forEach(function (tab) {
          var on = tab.getAttribute('data-tab') === name;
          tab.setAttribute('aria-selected', on ? 'true' : 'false');
          tab.tabIndex = on ? 0 : -1;
          tab.classList.toggle('is-active', on);
        });

        document.querySelectorAll('[data-panel]').forEach(function (panel) {
          panel.hidden = panel.getAttribute('data-panel') !== name;
        });
      }

      tabs.forEach(function (tab) {
        tab.addEventListener('click', function () { show(tab.getAttribute('data-tab')); });

        tab.addEventListener('keydown', function (e) {
          var step = e.key === 'ArrowRight' ? 1 : e.key === 'ArrowLeft' ? -1 : 0;
          if (!step) return;
          e.preventDefault();
          var next = tabs[(tabs.indexOf(tab) + step + tabs.length) % tabs.length];
          next.focus();
          show(next.getAttribute('data-tab'));
        });
      });

      /* A control elsewhere on the page can ask for a tab — "Add note" in the
         actions menu opens the one the note is written in. */
      document.querySelectorAll('[data-open-tab]').forEach(function (opener) {
        opener.addEventListener('click', function () {
          show(opener.getAttribute('data-open-tab'));

          var focus = document.querySelector(opener.getAttribute('data-focus') || '');
          if (focus) focus.focus();
        });
      });
    }());

    /* Activity search and filters, over the events already on the page. */
    (function () {
      var search = document.getElementById('activitySearch');
      var events = Array.prototype.slice.call(document.querySelectorAll('[data-event]'));
      var empty = document.getElementById('activityEmpty');
      if (!events.length) return;

      var filter = 'all';

      function apply() {
        var term = (search && search.value || '').trim().toLowerCase();
        var shown = 0;

        events.forEach(function (event) {
          var matchesFilter = filter === 'all' || event.getAttribute('data-event') === filter;
          var matchesTerm = !term || event.textContent.toLowerCase().indexOf(term) !== -1;
          var on = matchesFilter && matchesTerm;

          event.hidden = !on;
          if (on) shown++;
        });

        if (empty) empty.hidden = shown !== 0;
      }

      if (search) search.addEventListener('input', apply);

      document.querySelectorAll('[data-activity-filter]').forEach(function (button) {
        button.addEventListener('click', function () {
          filter = button.getAttribute('data-activity-filter');

          document.querySelectorAll('[data-activity-filter]').forEach(function (other) {
            other.classList.toggle('is-active', other === button);
            other.setAttribute('aria-pressed', other === button ? 'true' : 'false');
          });

          apply();
        });
      });
    }());

    /* The header's actions menu, and any other row menu on the page. */
    (function () {
      var menus = Array.prototype.slice.call(document.querySelectorAll('[data-rowmenu]'));
      if (!menus.length) return;

      function closeAll(except) {
        menus.forEach(function (menu) {
          if (menu === except) return;
          menu.querySelector('[data-rowmenu-pop]').hidden = true;
          menu.querySelector('[data-rowmenu-button]').setAttribute('aria-expanded', 'false');
          menu.classList.remove('is-open');
        });
      }

      menus.forEach(function (menu) {
        var button = menu.querySelector('[data-rowmenu-button]');
        var pop = menu.querySelector('[data-rowmenu-pop]');

        button.addEventListener('click', function (e) {
          e.stopPropagation();
          var opening = pop.hidden;
          closeAll(menu);
          pop.hidden = !opening;
          button.setAttribute('aria-expanded', opening ? 'true' : 'false');
          menu.classList.toggle('is-open', opening);
          if (opening) place(button, pop);
        });

        pop.addEventListener('click', function (e) { e.stopPropagation(); });
      });

      /* The panel is fixed, so it is positioned against its button rather
         than by the flow, and closed by a scroll it cannot follow. */
      function place(button, pop) {
        var rect = button.getBoundingClientRect();

        pop.style.visibility = 'hidden';
        var height = pop.offsetHeight;
        var width = pop.offsetWidth;
        pop.style.visibility = '';

        var below = window.innerHeight - rect.bottom;
        var top = below < height + 12 ? rect.top - height - 4 : rect.bottom + 4;

        pop.style.top = Math.max(8, top) + 'px';
        pop.style.left = Math.max(8, rect.right - width) + 'px';
      }

      document.addEventListener('click', function () { closeAll(null); });
      document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeAll(null); });
      window.addEventListener('scroll', function () { closeAll(null); }, true);
      window.addEventListener('resize', function () { closeAll(null); });
    }());

    /* The client tag card and its modal.

       Saving posts the whole set and redraws the chips from what came back,
       rather than from what the modal thought it was sending: the card then
       shows the record rather than an optimistic guess at it. */
    (function () {
      var card = document.querySelector('[data-client-tags]');
      var modal = document.getElementById('clientTagsModal');
      if (!card || !modal) return;

      var form = modal.querySelector('[data-tags-form]');
      var search = modal.querySelector('[data-tags-search]');
      var options = Array.prototype.slice.call(modal.querySelectorAll('[data-tags-option]'));
      var noMatches = modal.querySelector('[data-tags-no-matches]');
      var list = card.querySelector('[data-tags-list]');
      var token = document.querySelector('meta[name="csrf-token"]');

      var strings = {
        none: @json(__('clients.module.workspace.tags.none')),
        remove: @json(__('common.remove')),
        confirmTitle: @json(__('common.confirm.remove_tag_title')),
        confirm: @json(__('common.confirm.remove_tag', ['label' => ':label']))
      };

      function open() {
        modal.hidden = false;
        document.body.style.overflow = 'hidden';
        if (search) { search.value = ''; filter(); search.focus(); }
      }

      function close() {
        modal.hidden = true;
        document.body.style.overflow = '';
      }

      function filter() {
        var term = (search.value || '').trim().toLowerCase();
        var shown = 0;

        options.forEach(function (option) {
          var on = !term || option.getAttribute('data-label').indexOf(term) !== -1;
          option.hidden = !on;
          if (on) shown++;
        });

        if (noMatches) noMatches.hidden = shown !== 0;
      }

      /* The card, drawn from the set the server confirmed. */
      function render(tags) {
        list.innerHTML = '';

        if (!tags.length) {
          var empty = document.createElement('span');
          empty.className = 'text-[12px] text-faint';
          empty.setAttribute('data-tags-empty', '');
          empty.textContent = strings.none;
          list.appendChild(empty);
        }

        tags.forEach(function (tag) {
          var chip = document.createElement('span');
          chip.className = 'styledesk_clienttag';
          chip.style.setProperty('--tag-ink', tag.hex);
          chip.setAttribute('data-tag-id', tag.id);
          chip.appendChild(document.createTextNode(tag.label));

          var remove = document.createElement('button');
          remove.type = 'button';
          remove.className = 'styledesk_clienttag__remove';
          remove.setAttribute('data-tag-remove', tag.id);
          remove.setAttribute('data-confirm-title', strings.confirmTitle);
          remove.setAttribute('data-confirm', strings.confirm.replace(':label', tag.label));
          remove.setAttribute('data-confirm-label', strings.remove);
          remove.setAttribute('aria-label', strings.remove + ' ' + tag.label);
          remove.innerHTML = '<svg width="10" height="10" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="2.6" stroke-linecap="round"/></svg>';
          chip.appendChild(remove);

          list.appendChild(chip);
        });

        /* The checkboxes follow the card, so reopening the modal shows what
           is on the client rather than what was ticked last time. */
        var assigned = tags.map(function (tag) { return String(tag.id); });
        modal.querySelectorAll('input[name="tags[]"]').forEach(function (box) {
          box.checked = assigned.indexOf(box.value) !== -1;
        });
      }

      function save(ids) {
        var body = new FormData();
        body.append('_token', token ? token.getAttribute('content') : '');
        body.append('_method', 'PATCH');
        ids.forEach(function (id) { body.append('tags[]', id); });

        return fetch(form.getAttribute('action'), {
          method: 'POST',
          body: body,
          headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
          credentials: 'same-origin'
        }).then(function (response) {
          if (!response.ok) throw new Error(response.status);
          return response.json();
        }).then(function (data) {
          render(data.tags);
          if (window.styledesk && window.styledesk.toast) window.styledesk.toast(data.message, 'success');
          return data;
        });
      }

      document.querySelectorAll('[data-tags-open]').forEach(function (button) {
        button.addEventListener('click', open);
      });

      modal.querySelectorAll('[data-tags-close]').forEach(function (button) {
        button.addEventListener('click', close);
      });

      if (search) search.addEventListener('input', filter);

      document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && !modal.hidden) close();
      });

      form.addEventListener('submit', function (e) {
        e.preventDefault();

        var ids = Array.prototype.slice.call(modal.querySelectorAll('input[name="tags[]"]:checked'))
          .map(function (box) { return box.value; });

        save(ids).then(close).catch(function () {
          /* If the request could not be made, hand the browser the form it
             would have posted anyway rather than losing the change. */
          form.submit();
        });
      });

      /* Removing from the card posts the rest: the set is the record, so
         every change to it is stated in full.

         Every × on the card is locked while one save is in flight. Each
         click reads the set off the DOM, so a second click landing before
         the first came back would send a set that still contained the tag
         the first one removed — and put it straight back on. */
      var saving = false;

      list.addEventListener('click', function (e) {
        var button = e.target.closest('[data-tag-remove]');
        if (!button || saving) return;

        var removed = button.getAttribute('data-tag-remove');
        var ids = Array.prototype.slice.call(list.querySelectorAll('[data-tag-id]'))
          .map(function (chip) { return chip.getAttribute('data-tag-id'); })
          .filter(function (id) { return id !== removed; });

        saving = true;
        list.querySelectorAll('[data-tag-remove]').forEach(function (b) { b.disabled = true; });

        save(ids)
          .catch(function () {
            list.querySelectorAll('[data-tag-remove]').forEach(function (b) { b.disabled = false; });
          })
          .then(function () { saving = false; });
      });
    }());

    /* The behavioural tag modal: open, search, save. The list is in the page
       already, so searching it is filtering rows rather than asking the
       server what it just sent. */
    (function () {
      var modal = document.getElementById('behavioralModal');
      if (!modal) return;

      var search = modal.querySelector('[data-behavioral-search]');
      var options = Array.prototype.slice.call(modal.querySelectorAll('[data-behavioral-option]'));
      var empty = modal.querySelector('[data-behavioral-empty]');

      function open() {
        modal.hidden = false;
        document.body.style.overflow = 'hidden';
        if (search) { search.value = ''; filter(); search.focus(); }
      }

      function close() {
        modal.hidden = true;
        document.body.style.overflow = '';
      }

      function filter() {
        var term = (search.value || '').trim().toLowerCase();
        var shown = 0;

        options.forEach(function (option) {
          var on = !term || option.getAttribute('data-label').indexOf(term) !== -1;
          option.hidden = !on;
          if (on) shown++;
        });

        if (empty) empty.hidden = shown !== 0;
      }

      document.querySelectorAll('[data-behavioral-open]').forEach(function (button) {
        button.addEventListener('click', open);
      });

      modal.querySelectorAll('[data-behavioral-close]').forEach(function (button) {
        button.addEventListener('click', close);
      });

      if (search) search.addEventListener('input', filter);

      document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && !modal.hidden) close();
      });
    }());

    /* Show more / show less, wherever a block is longer than its space.
       The short and the full text are both in the page: swapping which one
       is shown means the reader never waits, and a screen reader is never
       handed a truncation. */
    (function () {
      document.querySelectorAll('[data-disclosure]').forEach(function (block) {
        var toggle = block.querySelector('[data-disclosure-toggle]');
        if (!toggle) return;

        var short = block.querySelector('[data-disclosure-short]');
        var full = block.querySelector('[data-disclosure-full]');

        toggle.addEventListener('click', function () {
          var expanded = !full.hidden;

          full.hidden = expanded;
          short.hidden = !expanded;
          toggle.textContent = expanded ? toggle.dataset.more : toggle.dataset.less;
        });
      });
    }());

    /* Copy a contact detail. Feedback on the button itself rather than a
       toast: the reader is looking at the thing they just copied. */
    (function () {
      var copied = @json(__('clients.module.workspace.contact.copied'));

      document.querySelectorAll('[data-copy]').forEach(function (button) {
        button.addEventListener('click', function () {
          var value = button.getAttribute('data-copy');
          if (!navigator.clipboard) return;

          navigator.clipboard.writeText(value).then(function () {
            var label = button.getAttribute('aria-label');
            button.setAttribute('aria-label', copied);
            button.classList.add('is-copied');

            window.setTimeout(function () {
              button.setAttribute('aria-label', label);
              button.classList.remove('is-copied');
            }, 1600);
          });
        });
      });
    }());
  </script>
@endpush
