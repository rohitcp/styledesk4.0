<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', config('app.name'))</title>

    {{--
        Tenant branding.

        Painted here, before the body renders, rather than from a component
        further down the page: reading the palette later would show every
        visitor StyleDesk purple before the business's own colours arrived.
        The CSS custom properties are the same ones resources/css/app.css maps
        its brand utilities onto via `@theme inline`.
    --}}
    @if (tenancy()->initialized && ($branding = tenant('branding')))
        <style>
            :root {
                @foreach ($branding as $token => $value)
                    --sd-{{ $token }}: {{ $value }};
                @endforeach
            }
        </style>
    @endif

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-canvas text-ink text-[13px] font-sans">
    @yield('content')
</body>
</html>
