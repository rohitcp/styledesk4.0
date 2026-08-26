<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title') — StyleDesk</title>

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
</head>
<body class="bg-white text-ink text-[13px]">

<div class="min-h-screen lg:grid lg:grid-cols-2">

    {{-- ===================== Form ===================== --}}
    <div class="flex items-center justify-center px-5 sm:px-8 py-10 sm:py-14">
        <div class="w-full max-w-[420px]">

            <a href="{{ url('/') }}" class="inline-flex items-center gap-2.5 text-head mb-10">
                <svg width="26" height="26" viewBox="0 0 32 32" fill="currentColor" aria-hidden="true">
                    <path d="M6.5 21.5 L14 6 L18.5 6 L11 21.5 Z"/>
                    <path d="M14.5 21.5 L22 6 L26.5 6 L19 21.5 Z"/>
                    <rect x="4" y="24.6" width="24" height="3.6" rx="1.8"/>
                </svg>
                <span class="text-[18px] font-bold tracking-tight">StyleDesk</span>
            </a>

            <h1 class="text-[28px] sm:text-[32px] font-bold text-head tracking-tight leading-[1.15]">@yield('heading')</h1>
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

</body>
</html>
