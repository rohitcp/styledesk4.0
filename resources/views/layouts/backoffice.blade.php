{{--
    The platform console.

    Desktop-first, as the brief asks: this is a tool used at a desk on a wide
    screen, not on a phone between clients. It still collapses — the navigation
    becomes a bar above the content below `lg` — because "desktop-first" is
    about where the design starts, not about breaking on a laptop.

    Deliberately unlike the salon application's chrome. Somebody with both open
    should never have to check which window they are in before pressing
    Suspend, so the console is dark where the product is light.
--}}
@php
    $admin = auth('backoffice')->user();

    /* Only what this administrator can actually reach. Hidden rather than
       shown-and-refused: a door that is offered and then closed teaches the
       reader that the product is broken, rather than that the door is not
       theirs. */
    $items = collect(config('backoffice.navigation'))
        ->filter(fn (array $item) => $admin?->can($item['permission']))
        ->values();
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    {{-- Never indexed, never followed. An internal console has no business in
         a search result, whatever a stray link might do. --}}
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title') — {{ __('backoffice.title') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-[#f4f5f7] text-ink antialiased">

<div class="lg:flex lg:min-h-screen">

    {{-- ------------------------------------------------------ navigation --}}
    <aside class="lg:w-[248px] lg:shrink-0 bg-[#15161c] text-white lg:min-h-screen lg:flex lg:flex-col">
        <div class="px-5 py-4 lg:py-5 flex items-center justify-between lg:block">
            <a href="{{ route('backoffice.dashboard') }}" class="block">
                <span class="block text-[15px] font-bold tracking-tight">{{ __('backoffice.title') }}</span>
                <span class="block text-[11px] text-white/45 mt-0.5">{{ __('backoffice.subtitle') }}</span>
            </a>
        </div>

        <nav class="px-3 pb-3 lg:flex-1" aria-label="{{ __('backoffice.nav.label') }}">
            <ul class="flex lg:block gap-1 overflow-x-auto lg:overflow-visible">
                @foreach ($items as $item)
                    @php $current = request()->routeIs($item['route']) || request()->routeIs($item['route'].'.*'); @endphp
                    <li>
                        <a href="{{ route($item['route']) }}"
                           @if ($current) aria-current="page" @endif
                           @class([
                               'flex items-center gap-2.5 whitespace-nowrap rounded-lg px-3 py-2 text-[13px] font-medium transition-colors',
                               'bg-white/10 text-white' => $current,
                               'text-white/65 hover:text-white hover:bg-white/5' => ! $current,
                           ])>
                            {{ __('backoffice.nav.'.$item['key']) }}
                        </a>
                    </li>
                @endforeach
            </ul>
        </nav>

        {{-- Who is signed in, at the foot of the column. The console can
             suspend a customer's business, so whose finger is on it should be
             on screen at all times rather than behind a menu. --}}
        @if ($admin)
            <div class="border-t border-white/10 px-4 py-3.5">
                <p class="text-[13px] font-semibold leading-tight truncate">{{ $admin->name }}</p>
                <p class="text-[11.5px] text-white/45 truncate">{{ $admin->roleLabel() }}</p>

                <div class="flex items-center gap-3 mt-2.5">
                    <a href="{{ route('backoffice.profile') }}"
                       class="text-[12px] font-semibold text-white/70 hover:text-white transition-colors">
                        {{ __('backoffice.nav.profile') }}
                    </a>

                    <form method="POST" action="{{ route('backoffice.logout') }}">
                        @csrf
                        <button type="submit" class="text-[12px] font-semibold text-white/70 hover:text-white transition-colors">
                            {{ __('backoffice.nav.logout') }}
                        </button>
                    </form>
                </div>
            </div>
        @endif
    </aside>

    {{-- --------------------------------------------------------- content --}}
    {{-- A reading width by default, because prose and forms are unreadable
         across a 27" monitor. A screen whose point is a wide table opts out
         with `@section('container', 'max-w-none')` rather than every page
         paying for the one that needs the room. --}}
    <main class="flex-1 min-w-0 px-5 lg:px-8 py-6 lg:py-8">
        <div class="@yield('container', 'max-w-[1240px]')">
            @if (session('status'))
                <p class="mb-4 rounded-lg border border-brand/30 bg-brand/5 px-4 py-2.5 text-[13px] text-head" role="status">
                    {{ session('status') }}
                </p>
            @endif

            @yield('content')
        </div>
    </main>
</div>

</body>
</html>
