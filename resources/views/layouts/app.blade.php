<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title') — StyleDesk</title>

    {{--
        Brand bootstrap, before the body paints. Reading the palette later
        would show every visitor StyleDesk purple before the tenant's own
        colours arrived. A paint fix, not a copy of the store.
    --}}
    <script>
        (function () {
            try {
                var raw = window.localStorage.getItem('styledesk.branding.v1');
                if (!raw) return;
                var c = (JSON.parse(raw) || {}).colors || {};
                var map = { brand: '--sd-brand', brandDark: '--sd-brand-dark',
                            banner: '--sd-banner', link: '--sd-link',
                            accent: '--sd-accent', button: '--sd-btn',
                            buttonInk: '--sd-btn-ink', secondary: '--sd-secondary' };
                Object.keys(map).forEach(function (k) {
                    if (c[k]) document.documentElement.style.setProperty(map[k], c[k]);
                });
            } catch (e) { /* a broken palette must never stop the page rendering */ }
        }());
    </script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-[#fafbfc] text-ink text-[13px]">

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
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.7"/><path d="M10.5 9l5 3-5 3V9z" fill="currentColor"/></svg>
        <span class="font-medium">Watch Now: Getting started with StyleDesk</span>
      </a>

      <div class="flex-1 flex items-center justify-center gap-2.5 min-w-0">
        <span class="truncate">Your trial ends in <b class="font-semibold">14 days</b></span>
        <a href="#" class="sd-pill-dark">Subscribe now</a>
      </div>

      <a href="#" class="hidden lg:flex items-center gap-1.5 shrink-0 hover:text-white/80 transition-colors">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none"><path d="M16 20v-1.5a4 4 0 00-4-4H7a4 4 0 00-4 4V20" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/><circle cx="9.5" cy="7" r="3.2" stroke="currentColor" stroke-width="1.7"/><path d="M19 8v5M21.5 10.5h-5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>
        <span class="font-medium">Invite team members</span>
      </a>

    </div>
  </div>

  <!-- ===== App bar ===== -->
  <header class="sticky top-0 z-30 bg-brand border-b border-white/10">
    <div class="relative w-full px-3 sm:px-5 lg:px-6 h-14 flex items-center gap-2 sm:gap-3">
      <!-- Primary icon rail -->
      <nav class="hidden lg:flex self-stretch items-center gap-0.5 shrink-0" aria-label="Primary">
        <a href="{{ route('dashboard') }}" class="sd-navicon grid sd-tip is-active" data-tip="Dashboard" aria-label="Dashboard" aria-current="page">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true"><rect x="4" y="4" width="7" height="7" rx="1.6" stroke="currentColor" stroke-width="1.7"/><rect x="13" y="4" width="7" height="7" rx="1.6" stroke="currentColor" stroke-width="1.7"/><rect x="4" y="13" width="7" height="7" rx="1.6" stroke="currentColor" stroke-width="1.7"/><rect x="13" y="13" width="7" height="7" rx="1.6" stroke="currentColor" stroke-width="1.7"/></svg>
        </a>
        <a href="#" class="sd-navicon grid sd-tip" data-tip="Calendar" aria-label="Calendar">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true"><rect x="4" y="5" width="16" height="16" rx="2.5" stroke="currentColor" stroke-width="1.8"/><path d="M4 9.5h16M8.5 3v3.5M15.5 3v3.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
        </a>
        <div class="sd-menu" data-menu>
          <a href="#" data-pending-route="clients.html" class="sd-navicon grid sd-tip" data-tip="Clients" aria-label="Clients"
             aria-haspopup="true" aria-expanded="false">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="8" r="3.6" stroke="currentColor" stroke-width="1.8"/><path d="M5 20a7 7 0 0114 0" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
          </a>
          <div class="sd-menu__pop" data-menu-pop hidden role="menu" aria-label="Clients menu">
            <a href="#" data-pending-route="clients.html" class="sd-menu__item" role="menuitem">All Clients</a>
            <a href="#" class="sd-menu__item" role="menuitem">Groups</a>
            <a href="#" class="sd-menu__item" role="menuitem">Forms &amp; Waivers</a>
            <a href="#" class="sd-menu__item" role="menuitem">Memberships &amp; Packages</a>
          </div>
        </div>
        <div class="sd-menu" data-menu>
          <a href="#" data-pending-route="services.html" class="sd-navicon grid sd-tip" data-tip="Services &amp; resources"
             aria-label="Services and resources" aria-haspopup="true" aria-expanded="false">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M11.6 3.5H20V12l-8.4 8.4a1.7 1.7 0 01-2.4 0l-6.1-6.1a1.7 1.7 0 010-2.4z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/><circle cx="16.2" cy="7.8" r="1.4" fill="currentColor"/></svg>
          </a>
          <div class="sd-menu__pop" data-menu-pop hidden role="menu" aria-label="Services and resources menu">
            <a href="#" data-pending-route="services.html" class="sd-menu__item" role="menuitem">All Services</a>
            <a href="#" data-pending-route="service-categories.html" class="sd-menu__item" role="menuitem">Categories</a>
            <a href="#" data-pending-route="service-addons.html" class="sd-menu__item" role="menuitem">Add-ons</a>
            <div class="sd-menu__rule" role="separator"></div>
            <a href="#" data-pending-route="resources.html" class="sd-menu__item" role="menuitem">All Resources</a>
            <a href="#" data-pending-route="resource-types.html" class="sd-menu__item" role="menuitem">Resource Types</a>
            <a href="#" data-pending-route="resource-availability.html" class="sd-menu__item" role="menuitem">Availability</a>
            <a href="#" data-pending-route="resource-maintenance.html" class="sd-menu__item" role="menuitem">Maintenance</a>
          </div>
        </div>
        <a href="#" class="sd-navicon grid sd-tip" data-tip="Staff" aria-label="Staff">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="9" cy="8" r="3.2" stroke="currentColor" stroke-width="1.7"/><path d="M3.5 19.5a5.5 5.5 0 0111 0" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/><path d="M16 5.4a3.2 3.2 0 010 5.2M17 14.7a5.5 5.5 0 013.5 4.8" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>
        </a>
        <a href="#" class="sd-navicon grid sd-tip" data-tip="Sales" aria-label="Sales">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true"><rect x="3" y="6" width="18" height="12" rx="2.5" stroke="currentColor" stroke-width="1.7"/><circle cx="12" cy="12" r="2.6" stroke="currentColor" stroke-width="1.7"/><path d="M6.4 12h.01M17.6 12h.01" stroke="currentColor" stroke-width="2.1" stroke-linecap="round"/></svg>
        </a>
        <a href="#" class="sd-navicon grid sd-tip" data-tip="Marketing" aria-label="Marketing">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M3.5 10v4A1.5 1.5 0 005 15.5h2L14 20V4L7 8.5H5A1.5 1.5 0 003.5 10z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/><path d="M17.5 9.4a3.6 3.6 0 010 5.2" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>
        </a>
        <a href="#" class="sd-navicon grid sd-tip" data-tip="Reports" aria-label="Reports">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M5 20V12M12 20V5M19 20v-6" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"/></svg>
        </a>
      </nav>

      <!-- Search — centred between the rail and the account cluster -->
      <div class="flex-1 min-w-0 flex justify-center">
      <div class="w-full max-w-[420px] relative xl:absolute xl:left-1/2 xl:top-1/2 xl:-translate-x-1/2 xl:-translate-y-1/2 xl:w-[420px]">
        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-white/70 pointer-events-none">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none"><circle cx="11" cy="11" r="6.5" stroke="currentColor" stroke-width="1.8"/><path d="M16 16l4 4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
        </span>
        <input type="search" class="sd-input-dark has-prefix has-suffix" placeholder="Type for search and recent items…" aria-label="Search" />
        <kbd class="hidden sm:grid absolute right-3 top-1/2 -translate-y-1/2 h-5 w-5 place-items-center rounded bg-white/15 text-white/70 text-[11px] font-semibold pointer-events-none">/</kbd>
      </div>
      </div>

      <!-- Account cluster -->
      <div class="ml-auto flex items-center gap-1 sm:gap-1.5 shrink-0">

        <div class="sd-menu sd-menu--right hidden sm:block" data-menu>
          <button type="button" class="inline-flex items-center gap-1.5 h-9 px-3.5 rounded-lg bg-white hover:bg-white/90 text-head text-[13px] font-semibold transition-colors"
                  aria-haspopup="true" aria-expanded="false">
            Add
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/></svg>
          </button>
          <div class="sd-menu__pop" data-menu-pop hidden role="menu" aria-label="Add">
            <a href="#" data-pending-route="add-booking.html" class="sd-menu__item" role="menuitem">Add booking</a>
            <a href="add-booking.html?walkin=1" class="sd-menu__item" role="menuitem">Walk-in</a>
            <div class="sd-menu__rule" role="separator"></div>
            <a href="#" data-pending-route="add-client.html" class="sd-menu__item" role="menuitem">Add contact</a>
          </div>
        </div>
        <a href="#" data-pending-route="add-booking.html" class="sd-navicon grid sd-tip sm:hidden" data-tip="Add booking" aria-label="Add booking">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
        </a>

        <button class="sd-navicon sd-tip hidden lg:grid" data-tip="Mentions">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="3.6" stroke="currentColor" stroke-width="1.7"/><path d="M15.6 12v1.6a2.6 2.6 0 005.2 0V12a8.8 8.8 0 10-3.6 7.1" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>
        </button>
        <button class="sd-navicon sd-tip hidden lg:grid" data-tip="Activity">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M4.5 14.5a10.5 10.5 0 0115 0" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><path d="M8 17a6 6 0 018 0" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><circle cx="12" cy="19.2" r="1.5" fill="currentColor"/></svg>
        </button>
        <a href="#" data-pending-route="designsystem.html" class="sd-navicon sd-tip hidden sm:grid" data-tip="Design system">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.7"/><path d="M9.6 9.4a2.5 2.5 0 114.4 2c-.9.8-1.7 1.2-1.7 2.4" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/><path d="M12 17.2v.4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
        </a>

        <a href="#" data-pending-route="app-settings.html" class="sd-navicon sd-tip hidden sm:grid" data-tip="App settings" aria-label="App settings">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="3.1" stroke="currentColor" stroke-width="1.7"/><path d="M19.1 14.4a1.6 1.6 0 00.3 1.8l.1.1a1.9 1.9 0 11-2.7 2.7l-.1-.1a1.6 1.6 0 00-1.8-.3 1.6 1.6 0 00-1 1.5v.2a1.9 1.9 0 11-3.8 0v-.1a1.6 1.6 0 00-1-1.5 1.6 1.6 0 00-1.8.3l-.1.1a1.9 1.9 0 11-2.7-2.7l.1-.1a1.6 1.6 0 00.3-1.8 1.6 1.6 0 00-1.5-1h-.2a1.9 1.9 0 110-3.8h.1a1.6 1.6 0 001.5-1 1.6 1.6 0 00-.3-1.8l-.1-.1A1.9 1.9 0 117.1 4.8l.1.1a1.6 1.6 0 001.8.3h.1a1.6 1.6 0 001-1.5v-.2a1.9 1.9 0 113.8 0v.1a1.6 1.6 0 001 1.5 1.6 1.6 0 001.8-.3l.1-.1a1.9 1.9 0 112.7 2.7l-.1.1a1.6 1.6 0 00-.3 1.8v.1a1.6 1.6 0 001.5 1h.2a1.9 1.9 0 110 3.8h-.1a1.6 1.6 0 00-1.5 1z" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </a>
        <div data-account-menu></div>

      </div>
    </div>
  </header>

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
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            if (!window.SD || typeof window.SD.accountMenuAll !== 'function') return;

            var boot = {};
            try {
                boot = JSON.parse(document.getElementById('sd-account-boot').textContent);
            } catch (e) { /* fall back to the module defaults */ }

            window.SD.accountMenuAll(boot);
        });
    </script>
@endauth

@stack('scripts')
</body>
</html>
