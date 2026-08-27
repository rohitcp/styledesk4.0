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

    @if ($faviconUrl = App\Support\Branding::faviconUrl(auth()->user()?->tenant))
        <link rel="icon" href="{{ $faviconUrl }}">
    @endif

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
<body class="styledesk_shell bg-[#fafbfc] text-ink text-[13px]">

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
      @include('layouts.partials.nav-rail')

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
            <a href="#" data-pending-route="add-booking.html" class="sd-menu__item" role="menuitem">Add booking</a>
            <a href="add-booking.html?walkin=1" class="sd-menu__item" role="menuitem">Walk-in</a>
            <div class="sd-menu__rule" role="separator"></div>
            <a href="#" data-pending-route="add-client.html" class="sd-menu__item" role="menuitem">Add contact</a>
          </div>
        </div>
        <a href="#" data-pending-route="add-booking.html" class="sd-navicon grid sd-tip sm:hidden" data-tip="Add booking" aria-label="Add booking">
          <x-icon name="plus" size="18" />
        </a>

        <button class="sd-navicon sd-tip hidden lg:grid" data-tip="Mentions">
          <x-icon name="at" size="18" />
        </button>
        <button class="sd-navicon sd-tip hidden lg:grid" data-tip="Activity">
          <x-icon name="wifi" size="18" />
        </button>
        <a href="#" data-pending-route="designsystem.html" class="sd-navicon sd-tip hidden sm:grid" data-tip="Design system">
          <x-icon name="circle-question" size="18" />
        </a>

        @include('layouts.partials.language-selector')

        {{-- Hidden from anyone who cannot open it. The route enforces the
             same rule, so this is tidiness rather than the control: a manager
             who guesses the URL is redirected, not shown a 403. --}}
        @if (auth()->user()?->canManageSettings())
          <a href="{{ route('settings.index') }}"
             class="sd-navicon sd-tip hidden sm:grid @if (request()->routeIs('settings.*')) is-active @endif"
             data-tip="{{ __('navigation.app_settings') }}" aria-label="{{ __('navigation.app_settings') }}">
            <x-icon name="gear" size="18" />
          </a>
        @endif
        <div data-account-menu></div>

      </div>
    </div>
  </header>

@include('layouts.partials.nav-drawer')

@include('partials.session-timeout')

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
            'name' => $sdUser->name,
            'email' => $sdUser->email,
            'initials' => strtoupper(mb_substr($sdUser->first_name, 0, 1).mb_substr($sdUser->last_name, 0, 1)),
            'org' => tenancy()->initialized ? tenant('name') : 'No business yet',
        ];
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
