{{--
    The focused layout.

    One task, no application chrome: no nav rail, no drawer, no account menu.
    Used where a screen is a piece of work rather than a place — building a
    fortnight's schedule is a sitting, and the surrounding navigation is an
    invitation to abandon it half-finished.

    The head is the application layout's, so this page loads the same bundle,
    the same tenant palette and the same stale-asset guard. What differs is
    everything below it: a header that only offers the way out, a scrolling
    body, and a footer that stays put while the body moves.
--}}
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
<body class="min-h-screen bg-canvas text-ink antialiased flex flex-col">

@include('partials.confirm-dialog')

<x-toast />

@yield('content')

</body>
</html>
