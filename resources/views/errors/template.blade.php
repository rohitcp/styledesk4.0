@php
    $meta = config('errors.'.$code, ['title' => 'Something went wrong', 'description' => 'An unexpected error occurred.']);

    /**
     * Where "back to safety" should point.
     *
     * Wrapped because this view has to render when things are broken: during a
     * 500 the session, database or tenancy may all be unavailable, and an
     * error page that throws while reporting an error leaves the visitor with
     * a blank screen.
     */
    try {
        $signedIn = auth()->hasUser() || auth()->check();
    } catch (\Throwable $e) {
        $signedIn = false;
    }

    try {
        $home = $signedIn ? route('dashboard') : url('/');
    } catch (\Throwable $e) {
        $home = url('/');
    }
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $code }} — {{ $meta['title'] }} · StyleDesk</title>

    {{--
        Styles are inlined rather than pulled through @vite on purpose. The
        Vite helper throws when the build manifest is missing, which would turn
        any error page into a second, uglier error. This page must render on
        its own, whatever else is broken.
    --}}
    <style>
        :root {
            --sd-brand: #3d348b;
            --sd-brand-dark: #2f2870;
            --sd-ink: #23272f;
            --sd-head: #0f0f10;
            --sd-sub: #6b7280;
            --sd-faint: #9ca3af;
            --sd-line: #e5e7eb;
            --sd-stroke: #d1d5db;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2.5rem 1.25rem;
            background: #fafbfc;
            color: var(--sd-ink);
            font-family: 'Inter', ui-sans-serif, system-ui, -apple-system, 'Segoe UI', sans-serif;
            -webkit-font-smoothing: antialiased;
        }

        .sd-error { width: 100%; max-width: 520px; }

        .sd-error__brand {
            /* flex, not inline-flex: as an inline element the code pill sat
               on the same line as the wordmark instead of below it. */
            display: flex;
            width: fit-content;
            align-items: center;
            gap: 0.625rem;
            color: var(--sd-head);
            text-decoration: none;
            margin-bottom: 2.25rem;
        }

        .sd-error__brand span { font-size: 18px; font-weight: 700; letter-spacing: -0.01em; }

        .sd-error__code {
            display: inline-block;
            font-size: 12px;
            font-weight: 600;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: var(--sd-brand);
            background: #efedf7;
            border-radius: 999px;
            padding: 0.3rem 0.7rem;
            margin-bottom: 1rem;
        }

        .sd-error__title {
            margin: 0;
            font-size: 28px;
            font-weight: 700;
            line-height: 1.15;
            letter-spacing: -0.02em;
            color: var(--sd-head);
        }

        .sd-error__body {
            margin: 0.875rem 0 0;
            font-size: 15px;
            line-height: 1.6;
            color: var(--sd-sub);
        }

        .sd-error__actions {
            display: flex;
            flex-wrap: wrap;
            gap: 0.625rem;
            margin-top: 2rem;
        }

        .sd-error__btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            height: 2.75rem;
            padding: 0 1.25rem;
            border-radius: 0.5rem;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            border: 1px solid transparent;
            transition: background-color 0.12s ease;
        }

        .sd-error__btn--primary { background: var(--sd-brand); color: #fff; }
        .sd-error__btn--primary:hover { background: var(--sd-brand-dark); }

        .sd-error__btn--ghost { background: #fff; border-color: var(--sd-stroke); color: var(--sd-ink); }
        .sd-error__btn--ghost:hover { background: #f3f4f6; }

        .sd-error__foot {
            margin-top: 2.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid var(--sd-line);
            font-size: 12px;
            color: var(--sd-faint);
        }

        .sd-error__ref { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; }

        @media (prefers-reduced-motion: reduce) {
            .sd-error__btn { transition: none; }
        }
    </style>
</head>
<body>
    <main class="sd-error">
        <a class="sd-error__brand" href="{{ $home }}">
            <svg width="26" height="26" viewBox="0 0 32 32" fill="currentColor" aria-hidden="true">
                <path d="M6.5 21.5 L14 6 L18.5 6 L11 21.5 Z"/>
                <path d="M14.5 21.5 L22 6 L26.5 6 L19 21.5 Z"/>
                <rect x="4" y="24.6" width="24" height="3.6" rx="1.8"/>
            </svg>
            <span>StyleDesk</span>
        </a>

        <p class="sd-error__code">Error {{ $code }}</p>

        <h1 class="sd-error__title">{{ $meta['title'] }}</h1>
        <p class="sd-error__body">{{ $meta['description'] }}</p>

        <div class="sd-error__actions">
            @if ($code === 401)
                <a class="sd-error__btn sd-error__btn--primary" href="{{ route('login') }}">Sign in</a>
            @elseif ($code === 419)
                <a class="sd-error__btn sd-error__btn--primary" href="{{ url()->current() }}">Reload the page</a>
            @else
                <a class="sd-error__btn sd-error__btn--primary" href="{{ $home }}">
                    {{ $signedIn ? 'Back to dashboard' : 'Back to StyleDesk' }}
                </a>
            @endif

            <button type="button" class="sd-error__btn sd-error__btn--ghost" onclick="history.back()">Go back</button>
        </div>

        <p class="sd-error__foot">
            @if ($code >= 500)
                {{-- The request id is the one thing worth surfacing: it lets
                     support find this exact failure in the log without the
                     visitor seeing anything internal. --}}
                If this keeps happening, contact support and quote
                <span class="sd-error__ref">{{ request()->header('X-Request-Id', substr(md5((string) microtime(true)), 0, 12)) }}</span>.
            @else
                &copy; {{ date('Y') }} StyleDesk
            @endif
        </p>
    </main>
</body>
</html>
