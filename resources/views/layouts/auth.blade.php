<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title') — StyleDesk</title>

    {{-- The house mark. These pages belong to StyleDesk rather than to any
         one business — nobody is signed in yet — so there is no tenant
         favicon to prefer over it. --}}
    <link rel="icon" href="{{ asset('images/styledesk-favicon.svg') }}" type="image/svg+xml">
    <link rel="apple-touch-icon" href="{{ asset('images/styledesk-favicon.svg') }}">

    {{--
        Brand bootstrap. Runs before the body paints, which is the whole point:
        reading the saved palette from branding.js at the bottom of the page
        would show every visitor a flash of StyleDesk purple before their own
        colours arrived. Deliberately tiny and dependency-free — it is a paint
        fix, not a copy of the store. Ported from the prototype's <head>.
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
<body class="bg-white text-ink text-[13px]">

<div class="relative min-h-screen lg:grid lg:grid-cols-2">

    {{-- The page's own top-right action, offered by the pages that have one.

         A slot rather than markup in the layout: this file is shared by every
         auth screen, and a "Create account" button hard-coded here would
         appear on the sign-up page pointing at itself.

         Positioned against the page rather than the form column, so it lands
         in the corner on a desktop where the right half is the value panel —
         which centres its own content vertically, leaving the corner free. --}}
    @hasSection('top-action')
        <div class="absolute top-5 right-5 sm:top-6 sm:right-6 lg:right-8 z-10 flex items-center gap-3">
            @yield('top-action')
        </div>
    @endif

    {{-- ===================== Form ===================== --}}
    <div class="flex items-center justify-center px-5 sm:px-8 py-10 sm:py-14">
        <div class="w-full max-w-[420px]">

            {{-- The wordmark itself, rather than a drawn glyph beside the
                 word: it is one asset and it is the brand's own drawing of
                 its name. The alt text carries the name, so the link is not
                 announced as an image. --}}
            {{-- Half the gap it used to keep. The mark and the heading below
                 it are one introduction — "styledesk" then "Log in to
                 StyleDesk" — and 40px read as two unrelated things stacked. --}}
            <a href="{{ url('/') }}" class="inline-block mb-5">
                {{-- 34px rather than a Tailwind step: 28 was a touch small
                     against the 32px heading below it, and h-8 / h-9 land
                     either side of where it wants to be. width and height are
                     the drawn size at that height (the mark is 403.5 × 100),
                     so the space is reserved before the SVG arrives. --}}
                <img src="{{ asset('images/styledesk-logo.svg') }}" alt="StyleDesk"
                     class="h-[34px] w-auto" width="137" height="34">
            </a>

            {{-- Size is overridable because the headings differ in length:
                 "Log in to StyleDesk" fits on one line at 32px in this 420px
                 column, "Create your StyleDesk account" needs 462px and wraps.
                 Pages with a long heading opt down rather than every heading
                 shrinking to suit the longest. --}}
            <h1 class="@yield('heading-class', 'text-[28px] sm:text-[32px]') font-bold text-head tracking-tight leading-[1.15]">@yield('heading')</h1>
            <p class="text-[15px] text-sub mt-3 leading-relaxed">@yield('subheading')</p>

            {{-- Server-side validation and status, in the prototype's alert styling. --}}
            @if (session('status'))
                <div class="sd-alert sd-alert--success mt-6" role="status">
                    <div class="flex items-start gap-2.5">
                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" class="shrink-0 mt-px" aria-hidden="true"><path d="M5 12.5l4.5 4.5L19 7.5" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        <p class="min-w-0">{{ session('status') }}</p>
                    </div>
                </div>
            @endif

            @if ($errors->any())
                <div class="sd-alert sd-alert--danger mt-6" role="alert">
                    <div class="flex items-start gap-2.5">
                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" class="shrink-0 mt-px" aria-hidden="true"><circle cx="12" cy="12" r="8.5" stroke="currentColor" stroke-width="1.7"/><path d="M12 7.5v5M12 16v.4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                        <p class="min-w-0">{{ $errors->first() }}</p>
                    </div>
                </div>
            @endif

            @yield('form')

            <footer class="mt-10 pt-6 border-t border-line flex flex-wrap items-center gap-x-5 gap-y-2">
                <p class="text-[12px] text-faint">&copy; {{ date('Y') }} StyleDesk</p>
                <nav class="flex items-center gap-5 sm:ml-auto" aria-label="Legal">
                    <a href="#" class="text-[12px] text-sub hover:text-ink transition-colors">Terms</a>
                    <a href="#" class="text-[12px] text-sub hover:text-ink transition-colors">Privacy</a>
                    <a href="#" class="text-[12px] text-sub hover:text-ink transition-colors">Support</a>
                </nav>
            </footer>
        </div>
    </div>

    {{-- ===================== Value panel ===================== --}}
    <aside class="hidden lg:flex items-center border-l border-line bg-gradient-to-b from-white via-[#faf9fd] to-[#f2effa] px-10 xl:px-16 py-14">
        <div class="w-full max-w-[520px] mx-auto">
            @yield('value-panel')
        </div>
    </aside>
</div>

@stack('scripts')
</body>
</html>
