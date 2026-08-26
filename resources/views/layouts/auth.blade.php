<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title') — {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-[#fafbfc] text-ink text-[13px] font-sans">
    <div class="min-h-screen flex items-center justify-center px-4 py-12">
        <div class="w-full max-w-[400px]">
            <h1 class="text-center text-[20px] font-semibold text-head mb-1">{{ config('app.name') }}</h1>
            <p class="text-center text-sub mb-6">@yield('subtitle')</p>

            <div class="styledesk_card">
                @if (session('status'))
                    <div class="styledesk_alert--success mb-4">{{ session('status') }}</div>
                @endif

                @if ($errors->any())
                    <div class="styledesk_alert--danger mb-4">
                        {{ $errors->first() }}
                    </div>
                @endif

                @yield('form')
            </div>

            <p class="text-center text-sub mt-5">@yield('footer')</p>
        </div>
    </div>
</body>
</html>
