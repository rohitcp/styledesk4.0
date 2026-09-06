<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title') — StyleDesk</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    {{--
        The business's palette, rendered by the server before first paint.

        It was read from localStorage here, which made the brand a property of
        one browser: a colleague opening the same app saw StyleDesk purple, and
        an email or a booking page — neither of which runs this script — could
        never be branded at all. The palette belongs to the business, so the
        server is what states it.

        Placed after the bundle, not before it. prototype.css declares the
        same :root properties as StyleDesk's own defaults, and at equal
        specificity the later rule wins — in front of it, every tenant's
        palette was silently overwritten by the house purple.
    --}}
    <style>{!! App\Support\BrandPalette::forTenant(auth()->user()?->tenant)->css() !!}</style>

    {{-- A business's own favicon when it has uploaded one, the house mark
         otherwise. There used to be no fallback at all: public/favicon.ico is
         an empty file, so a tenant without a logo got the browser's blank
         page icon. --}}
    <link rel="icon"
          href="{{ App\Support\Branding::faviconUrl(auth()->user()?->tenant) ?? asset('images/styledesk-favicon.svg') }}">

    {{--
        Stale-asset guard.

        Inline on purpose: it has to work when the bundle it is watching has
        failed to load, so it cannot live in that bundle. A page left open
        across a deploy points at hashed files that no longer exist; without
        this the page simply stops responding to clicks and looks like a broken
        feature rather than an out-of-date tab.
    --}}
    <script>
        window.addEventListener('error', function (event) {
            var el = event.target;

            if (!el || (el.tagName !== 'SCRIPT' && el.tagName !== 'LINK')) return;
            if (document.getElementById('sd-stale-assets')) return;

            var bar = document.createElement('div');
            bar.id = 'sd-stale-assets';
            bar.setAttribute('role', 'alert');
            bar.style.cssText = 'position:fixed;inset:0 0 auto 0;z-index:9999;display:flex;gap:.75rem;' +
                'align-items:center;justify-content:center;padding:.75rem 1rem;background:#b91c1c;' +
                'color:#fff;font:500 13px/1.4 Inter,system-ui,sans-serif';
            bar.innerHTML = 'This page is out of date, so parts of it will not work. ' +
                '<button type="button" style="height:28px;padding:0 .75rem;border:0;border-radius:4px;' +
                'background:#fff;color:#b91c1c;font-weight:600;cursor:pointer">Reload</button>';
            bar.querySelector('button').addEventListener('click', function () { location.reload(true); });

            document.body.appendChild(bar);
        }, true);
    </script>

</head>
{{-- `shellClass` lets one page opt into a different shell. The client
     profile uses it to become a fixed-height workspace whose columns scroll
     on their own; every other page leaves it empty and scrolls normally. --}}
{{-- White canvas, set once here rather than per page.
     The app is one continuous workspace: a grey outer background meant that
     every screen had to opt into a white card to look finished, and that the
     colour changed depending on which page you were on. Cards keep their
     borders and rules, which is what separates them now. --}}
<body class="styledesk_shell @yield('shellClass') bg-white text-ink text-[13px]">

{{--
    Application shell, ported from the prototype's dashboard.html: the
    announcement banner, the app bar with its primary icon rail, search and
    account cluster. Every authenticated page extends this layout, which is
    why it lives here rather than inside the dashboard view.

    Links to prototype pages whose modules do not exist yet are rendered as
    href="#" carrying data-pending-route, so nothing 404s and the remaining
    work is greppable.
--}}
  <!-- ===== Announcement banner ===== -->
  <div class="bg-banner text-white">
    <div class="w-full px-4 sm:px-5 lg:px-6 h-9 flex items-center gap-4 text-[12px]">

      <a href="#" class="hidden lg:flex items-center gap-2 shrink-0 hover:text-white/80 transition-colors">
        <x-icon name="circle-play" size="15" />
        <span class="font-medium">Watch Now: Getting started with StyleDesk</span>
      </a>

      <div class="flex-1 flex items-center justify-center gap-2.5 min-w-0">
        @php $sdTrialDays = tenancy()->initialized ? tenant()->trialDaysRemaining() : null; @endphp
        <span class="truncate">
            @if ($sdTrialDays !== null)
                {{ $sdTrialDays }} {{ Str::plural('day', $sdTrialDays) }} remaining in your free trial
            @else
                Your trial ends soon
            @endif
        </span>
        <a href="#" class="sd-pill-dark">Subscribe now</a>
      </div>

      <a href="#" class="hidden lg:flex items-center gap-1.5 shrink-0 hover:text-white/80 transition-colors">
        <x-icon name="user-plus" size="15" />
        <span class="font-medium">Invite team members</span>
      </a>

    </div>
  </div>

  <!-- ===== App bar ===== -->
  <header class="sticky top-0 z-30 bg-brand border-b border-white/10">
    <div class="relative w-full px-3 sm:px-5 lg:px-6 h-14 flex items-center gap-2 sm:gap-3">
      {{-- Below lg the icon rail is hidden; this is what replaces it. Placed
           where the rail sits so navigation stays in the same corner at every
           width. --}}
      <button type="button" data-drawer-toggle
              class="sd-navicon grid lg:hidden shrink-0" aria-label="{{ __('navigation.main_menu') }}"
              aria-expanded="false" aria-controls="sd-drawer">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true">
          <path d="M4 7h16M4 12h16M4 17h16" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
        </svg>
      </button>

      <!-- Primary icon rail -->
      @include('layouts.partials.nav-rail', ['navCounts' => \App\Support\Nav::counts()])

      <!-- Search — centred between the rail and the account cluster -->
      <div class="flex-1 min-w-0 flex justify-center">
      <div class="w-full max-w-[420px] relative xl:absolute xl:left-1/2 xl:top-1/2 xl:-translate-x-1/2 xl:-translate-y-1/2 xl:w-[420px]">
        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-white/70 pointer-events-none">
          <x-icon name="magnifying-glass" size="16" />
        </span>
        <input type="search" class="sd-input-dark has-prefix has-suffix" placeholder="{{ __('navigation.search_placeholder') }}" aria-label="{{ __('common.search') }}" />
        <kbd class="hidden sm:grid absolute right-3 top-1/2 -translate-y-1/2 h-5 w-5 place-items-center rounded bg-white/15 text-white/70 text-[11px] font-semibold pointer-events-none">/</kbd>
      </div>
      </div>

      <!-- Account cluster -->
      <div class="ml-auto flex items-center gap-1 shrink-0">

        <div class="sd-menu sd-menu--right hidden sm:block" data-menu>
          <button type="button" class="inline-flex items-center gap-1.5 h-9 px-3.5 rounded-lg bg-white hover:bg-white/90 text-head text-[13px] font-semibold transition-colors"
                  aria-haspopup="true" aria-expanded="false">
            Add
            <x-icon name="plus" size="14" />
          </button>
          <div class="sd-menu__pop" data-menu-pop hidden role="menu" aria-label="Add">
            <a href="{{ route('clients.create') }}" class="sd-menu__item" role="menuitem">{{ __('bookings.add.client') }}</a>
            <a href="{{ route('bookings.create') }}" class="sd-menu__item" role="menuitem">{{ __('bookings.add.booking') }}</a>
            <a href="{{ route('bookings.create', ['walk-in' => 1]) }}" class="sd-menu__item" role="menuitem">{{ __('bookings.add.walk_in') }}</a>
            <div class="sd-menu__rule" role="separator"></div>
            {{-- Not a link. Leave is designed and not built, and an entry
                 that looks like every other one and then does nothing reads
                 as a broken product rather than as work still to come. --}}
            <span class="sd-menu__item sd-menu__item--soon" role="menuitem" aria-disabled="true"
                  data-pending-route="add-leave.html">{{ __('bookings.add.leave') }} <span class="sd-menu__soon">{{ __('navigation.coming_soon') }}</span></span>
          </div>
        </div>
        <a href="{{ route('bookings.create') }}" class="sd-navicon grid sd-tip sm:hidden"
           data-tip="{{ __('bookings.add.booking') }}" data-tip-placement="right"
           aria-label="{{ __('bookings.add.booking') }}">
          <x-icon name="plus" size="18" />
        </a>

        <button class="sd-navicon sd-tip hidden lg:grid" data-tip="Mentions" data-tip-placement="right" aria-label="Mentions">
          <x-icon name="at" size="18" />
        </button>
        {{-- Business activity, in a tab of its own.

             A link rather than a panel: the activity log is something
             somebody sits and reads — scrolled, filtered, followed into a
             record and come back from — and a drawer that shuts when you
             click past it fights all four. Opening it in a new tab leaves
             whatever they were working on exactly where it was.

             The count is rendered here rather than fetched, so the badge is
             right the moment the page paints. --}}
        @php $activityUnread = \App\Support\ActivityStream::unread(auth()->user()); @endphp

        <a href="{{ route('activity.index') }}" target="_blank" rel="noopener"
           class="sd-navicon sd-tip relative hidden lg:grid"
           data-tip="{{ __('activity.open') }}" data-tip-placement="right"
           aria-label="{{ __('activity.open') }}">
          <x-icon name="bell" size="18" />

          @if ($activityUnread > 0)
            <span class="styledesk_activity__badge">{{ $activityUnread > 99 ? '99+' : $activityUnread }}</span>
          @endif
        </a>
        <span class="sd-navicon sd-navicon--soon sd-tip hidden sm:grid" data-pending-route="designsystem.html"
              data-tip="Design system · {{ __('navigation.coming_soon') }}" data-tip-placement="right"
              role="img" aria-label="Design system" aria-disabled="true">
          <x-icon name="circle-question" size="18" />
        </span>

        @include('layouts.partials.language-selector')

        {{-- Hidden from anyone who cannot open it. The route enforces the
             same rule, so this is tidiness rather than the control: a manager
             who guesses the URL is redirected, not shown a 403. --}}
        @if (auth()->user()?->canManageSettings())
          <a href="{{ route('settings.index') }}"
             class="sd-navicon sd-tip hidden sm:grid @if (request()->routeIs('settings.*')) is-active @endif"
             data-tip="{{ __('navigation.app_settings') }}" data-tip-placement="right" aria-label="{{ __('navigation.app_settings') }}">
            <x-icon name="gear" size="18" />
          </a>
        @endif
        <div data-account-menu></div>

      </div>
    </div>
  </header>

@include('layouts.partials.nav-drawer', ['navCounts' => \App\Support\Nav::counts()])



@include('partials.session-timeout')
@include('partials.confirm-dialog')

<x-toast />

@yield('content')

  <footer class="border-t border-line bg-white">
    <div class="w-full px-4 sm:px-6 lg:px-6 py-5 flex flex-wrap items-center gap-x-6 gap-y-2">
      <p class="text-[12px] text-faint">&copy; 2026 StyleDesk. All rights reserved.</p>
      <nav class="flex items-center gap-5 sm:ml-auto" aria-label="Legal">
        <a href="#" class="text-[12px] text-sub hover:text-ink transition-colors">Terms</a>
        <a href="#" class="text-[12px] text-sub hover:text-ink transition-colors">Privacy</a>
        <a href="#" class="text-[12px] text-sub hover:text-ink transition-colors">Support</a>
      </nav>
    </div>
  </footer>

{{-- The account cluster lives in this shell, so its initialiser does too.
     SD/SDA are published by the prototype modules imported in resources/js/app.js.

     accountMenuAll() accepts an options object, so the real signed-in user and
     tenant are passed in from the server rather than falling back to the
     prototype's hardcoded "Bertrand Bruant / Nicelydone" placeholder. --}}
@auth
    @php
        $sdUser = auth()->user();
        $sdAccountBoot = [
            'name' => $sdUser->displayName(),
            'email' => $sdUser->email,
            'initials' => $sdUser->initials(),
            'photo' => $sdUser->avatarUrl(),
            'org' => tenancy()->initialized ? tenant('name') : 'No business yet',

            /* The menu's rows, from the app's own routes rather than the
               prototype's .html list — and from App\Support\AccountSection,
               so the menu and the left navigation inside My Account cannot
               offer different sections. */
            'rows' => collect(App\Support\AccountSection::all())
                ->map(fn (array $section) => [
                    'id' => $section['key'],
                    'label' => $section['label'],
                    'url' => route($section['route']),
                ])
                ->all(),

        ];

        /**
         * App Settings is an administrator's screen, so the row is absent
         * rather than present-and-refusing for everybody else.
         *
         * The label and the URL are added only when the row is, not passed
         * with a flag turning them off: this whole array is printed into the
         * page as JSON, so a label sent to somebody who may not open the
         * screen puts "App settings" in their markup — which is the nav item
         * being hidden in appearance only.
         */
        if ($sdUser->canManageSettings()) {
            $sdAccountBoot['settingsUrl'] = route('settings.index');
            $sdAccountBoot['settingsLabel'] = __('navigation.app_settings');
        } else {
            $sdAccountBoot['showSettings'] = false;
        }
    @endphp
    <script id="sd-account-boot" type="application/json">@json($sdAccountBoot)</script>

    {{-- One logout form for the shell. The account menu is built by script,
         so it has no form of its own to submit. --}}
    <form id="sd-logout-form" method="POST" action="{{ route('logout') }}" class="hidden">
        @csrf
    </form>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            if (!window.SD || typeof window.SD.accountMenuAll !== 'function') return;

            var boot = {};
            try {
                boot = JSON.parse(document.getElementById('sd-account-boot').textContent);
            } catch (e) { /* fall back to the module defaults */ }

            /**
             * Sign out has to actually sign out.
             *
             * Without onSignOut the prototype's own handler runs, and that
             * was written for a demo with no login screen: it shows a toast
             * saying what would happen and leaves the session exactly where
             * it was. A POST is used rather than a link because signing out
             * changes state, and a GET that does is one prefetch away from
             * logging someone out for them.
             */
            boot.onSignOut = function () {
                var form = document.getElementById('sd-logout-form');
                if (form) form.submit();
            };

            window.SD.accountMenuAll(boot);
        });
    </script>
@endauth

@stack('scripts')
</body>
</html>
