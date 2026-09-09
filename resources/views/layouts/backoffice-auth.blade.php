{{--
    The console's signed-out screens.

    One narrow card on a dark ground. Unmistakably not the salon sign-in: an
    administrator who has both open should never wonder which one is asking.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title') — {{ __('backoffice.title') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-[#15161c] text-ink antialiased flex items-center justify-center px-4 py-10">

<div class="w-full max-w-[400px]">
    <div class="text-center mb-6">
        <p class="text-[15px] font-bold text-white tracking-tight">{{ __('backoffice.title') }}</p>
        <p class="text-[11.5px] text-white/40 mt-0.5">{{ __('backoffice.subtitle') }}</p>
    </div>

    <div class="bg-white rounded-card p-6">
        <h1 class="text-[19px] font-bold text-head tracking-tight">@yield('title')</h1>

        @hasSection('intro')
            <p class="text-[13px] text-sub mt-1.5 leading-relaxed">@yield('intro')</p>
        @endif

        {{-- Why the last session ended. Being dropped at a bare form reads as
             the product losing your session; being told reads as it protecting
             it. --}}
        @if (session(App\Http\Middleware\AuthenticateBackoffice::TIMED_OUT))
            <p class="mt-4 rounded-lg border border-line bg-hover px-3.5 py-2.5 text-[12.5px] text-sub" role="status">
                {{ __('backoffice.auth.timed_out') }}
            </p>
        @elseif (session(App\Http\Middleware\AuthenticateBackoffice::DISABLED))
            <p class="mt-4 rounded-lg border border-danger/30 bg-danger/5 px-3.5 py-2.5 text-[12.5px] text-danger" role="alert">
                {{ __('backoffice.auth.disabled') }}
            </p>
        @endif

        @if (session('status'))
            <p class="mt-4 rounded-lg border border-brand/30 bg-brand/5 px-3.5 py-2.5 text-[12.5px] text-head" role="status">
                {{ session('status') }}
            </p>
        @endif

        <div class="mt-5">@yield('content')</div>
    </div>

    <p class="text-center text-[11.5px] text-white/30 mt-5">{{ __('backoffice.auth.restricted') }}</p>
</div>

</body>
</html>
