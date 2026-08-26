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
      <!-- Primary icon rail -->
      <nav class="hidden lg:flex self-stretch items-center gap-1 shrink-0" aria-label="Primary">
        <a href="{{ route('dashboard') }}" class="sd-navicon grid sd-tip is-active" data-tip="Dashboard" aria-label="Dashboard" aria-current="page">
          <x-icon name="grid-2" size="18" />
        </a>
        <a href="#" class="sd-navicon grid sd-tip" data-tip="Calendar" aria-label="Calendar">
          <x-icon name="calendar" size="18" />
        </a>
        <div class="sd-menu" data-menu>
          <a href="#" data-pending-route="clients.html" class="sd-navicon grid sd-tip" data-tip="Clients" aria-label="Clients"
             aria-haspopup="true" aria-expanded="false">
            <x-icon name="user" size="18" />
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
            <x-icon name="tag" size="18" />
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
          <x-icon name="users" size="18" />
        </a>
        <a href="#" class="sd-navicon grid sd-tip" data-tip="Sales" aria-label="Sales">
          <x-icon name="credit-card" size="18" />
        </a>
        <a href="#" class="sd-navicon grid sd-tip" data-tip="Marketing" aria-label="Marketing">
          <x-icon name="bullhorn" size="18" />
        </a>
        <a href="#" class="sd-navicon grid sd-tip" data-tip="Reports" aria-label="Reports">
          <x-icon name="chart-simple" size="18" />
        </a>
      </nav>

      <!-- Search — centred between the rail and the account cluster -->
      <div class="flex-1 min-w-0 flex justify-center">
      <div class="w-full max-w-[420px] relative xl:absolute xl:left-1/2 xl:top-1/2 xl:-translate-x-1/2 xl:-translate-y-1/2 xl:w-[420px]">
        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-white/70 pointer-events-none">
          <x-icon name="magnifying-glass" size="16" />
        </span>
        <input type="search" class="sd-input-dark has-prefix has-suffix" placeholder="Type for search and recent items…" aria-label="Search" />
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

        {{-- Hidden from anyone who cannot open it. The route enforces the
             same rule, so this is tidiness rather than the control: a manager
             who guesses the URL is redirected, not shown a 403. --}}
        @if (auth()->user()?->canManageSettings())
          <a href="{{ route('settings.index') }}"
             class="sd-navicon sd-tip hidden sm:grid @if (request()->routeIs('settings.*')) is-active @endif"
             data-tip="App settings" aria-label="App settings">
            <x-icon name="gear" size="18" />
          </a>
        @endif
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
