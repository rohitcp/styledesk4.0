<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title') — StyleDesk</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-[#fafbfc] text-ink text-[13px] min-h-screen flex flex-col">

<header class="bg-brand">
    <div class="w-full px-4 sm:px-6 h-14 flex items-center gap-3">
        <span class="text-white/70 text-[13px] truncate">Set up your business</span>
        <div class="ml-auto flex items-center gap-3">
            <span class="hidden md:inline-flex sd-pill-dark">14 days left in your free trial</span>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="text-white/70 hover:text-white text-[13px] font-medium transition-colors">Log out</button>
            </form>
        </div>
    </div>
</header>

{{-- Two columns from lg up: form left, a narrower context rail right.
     Same split as the auth pages, but weighted to the form rather than 50/50. --}}
<div class="flex-1 lg:grid lg:grid-cols-12">

    <main class="lg:col-span-8 min-w-0 w-full px-4 sm:px-6 pt-7 sm:pt-10 pb-24">
        <div class="max-w-[780px]">

            <div class="pb-5 mb-6 border-b border-line">
                <div class="flex flex-wrap items-start gap-x-4 gap-y-1">
                    <div class="min-w-0">
                        <h1 class="text-[24px] sm:text-[28px] font-bold text-head tracking-tight">@yield('heading')</h1>
                        <p class="text-[14px] text-sub mt-1.5">@yield('subheading')</p>
                    </div>
                    @php
                        $stepIndex = collect($progress)->search(fn ($s) => $s['current']) + 1;
                    @endphp
                    <span class="ml-auto shrink-0 pt-1 text-[12px] font-medium text-sub">Step {{ $stepIndex }} of {{ count($progress) }}</span>
                </div>

                {{-- Width comes from server-held progress, not from a browser
                     draft: clearing localStorage must not move the bar. --}}
                <div class="sd-progress mt-4">
                    <span style="width: {{ round($stepIndex / count($progress) * 100) }}%"></span>
                </div>

                <ol class="flex flex-wrap items-center gap-x-4 gap-y-1 mt-3">
                    @foreach ($progress as $step)
                        <li class="text-[12px] font-medium
                            {{ $step['current'] ? 'text-brand' : ($step['done'] ? 'text-success' : 'text-faint') }}">
                            @if ($step['done'] && ! $step['current'])
                                <span aria-hidden="true">✓</span>
                            @endif
                            {{ $step['label'] }}
                        </li>
                    @endforeach
                </ol>
            </div>

            @if ($errors->any())
                <div class="sd-alert sd-alert--danger mb-6" role="alert">
                    <div class="flex items-start gap-2.5">
                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" class="shrink-0 mt-px" aria-hidden="true"><circle cx="12" cy="12" r="8.5" stroke="currentColor" stroke-width="1.7"/><path d="M12 7.5v5M12 16v.4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                        <p class="min-w-0">{{ $errors->first() }}</p>
                    </div>
                </div>
            @endif

            @yield('form')
        </div>
    </main>

    <aside class="hidden lg:block lg:col-span-4 border-l border-line bg-white px-6 xl:px-8 pt-10">
        <div class="max-w-[320px]">
            @yield('rail')
        </div>
    </aside>
</div>

<footer class="border-t border-line bg-white">
    <div class="w-full px-4 sm:px-6 py-5 flex flex-wrap items-center gap-x-6 gap-y-2">
        <p class="text-[12px] text-faint">&copy; {{ date('Y') }} StyleDesk. All rights reserved.</p>
        <nav class="flex items-center gap-5 sm:ml-auto" aria-label="Legal">
            <a href="#" class="text-[12px] text-sub hover:text-ink transition-colors">Terms</a>
            <a href="#" class="text-[12px] text-sub hover:text-ink transition-colors">Privacy</a>
            <a href="#" class="text-[12px] text-sub hover:text-ink transition-colors">Support</a>
        </nav>
    </div>
</footer>

@stack('scripts')
</body>
</html>
