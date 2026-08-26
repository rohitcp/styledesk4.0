{{--
    Decorative icon for a business-type chip.

    Kept as a Blade partial keyed by name rather than markup in the database,
    so administrators managing the catalogue never paste SVG paths and cannot
    inject markup. Every option is still named in text, so the list reads the
    same to a screen reader and at any zoom level.
--}}
@props(['icon'])

<svg {{ $attributes->merge(['class' => 'shrink-0', 'width' => 20, 'height' => 20]) }}
     viewBox="0 0 24 24" fill="none" aria-hidden="true">
    @switch($icon)
        @case('scissors')
            <circle cx="6.5" cy="6.5" r="2.5" stroke="currentColor" stroke-width="1.7"/><circle cx="6.5" cy="17.5" r="2.5" stroke="currentColor" stroke-width="1.7"/><path d="M8.7 8L20 18M8.7 16L20 6" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>
            @break
        @case('comb')
            <rect x="3.5" y="5" width="17" height="4" rx="1.5" stroke="currentColor" stroke-width="1.7"/><path d="M7.5 9v9M12 9v9M16.5 9v9" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>
            @break
        @case('polish')
            <rect x="7.5" y="9" width="9" height="12" rx="2.5" stroke="currentColor" stroke-width="1.7"/><path d="M10 9V6h4v3" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/><path d="M12 6V3" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>
            @break
        @case('leaf')
            <path d="M20.5 3.5C10 3.5 4 9 4 16.5V21" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/><path d="M20.5 3.5c0 8.5-5.5 13.5-13 13.5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>
            @break
        @case('hand')
            <path d="M9 12V5.5a1.5 1.5 0 013 0V11" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/><path d="M12 11V4.5a1.5 1.5 0 013 0V11" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/><path d="M15 11V6.5a1.5 1.5 0 013 0V14a6 6 0 01-6 6h-1a6 6 0 01-6-6v-1.5a1.5 1.5 0 013 0" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>
            @break
        @case('medical')
            <rect x="3.5" y="3.5" width="17" height="17" rx="4.5" stroke="currentColor" stroke-width="1.7"/><path d="M12 8v8M8 12h8" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>
            @break
        @case('sparkle')
            <path d="M10 3.5l1.5 4.3 4.3 1.5-4.3 1.5L10 15.1 8.5 10.8 4.2 9.3l4.3-1.5z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/><path d="M17.5 14l.8 2.2 2.2.8-2.2.8-.8 2.2-.8-2.2-2.2-.8 2.2-.8z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/>
            @break
        @case('eye')
            <path d="M2.5 12S6 5.5 12 5.5 21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12z" stroke="currentColor" stroke-width="1.7"/><circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.7"/>
            @break
        @case('lipstick')
            <path d="M8.5 21h7v-9h-7z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/><path d="M8.5 12V8l7-5v9z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/>
            @break
        @case('pen')
            <path d="M3 21l1-4L16.5 4.5a2.1 2.1 0 013 3L7 20l-4 1z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/><path d="M14.5 6.5l3 3" stroke="currentColor" stroke-width="1.7"/>
            @break
        @case('heart')
            <path d="M12 20s-7.5-4.6-7.5-10A4.2 4.2 0 0112 7.5a4.2 4.2 0 017.5 2.5c0 5.4-7.5 10-7.5 10z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/>
            @break
        @case('dumbbell')
            <rect x="4" y="8.5" width="3.5" height="7" rx="1.2" stroke="currentColor" stroke-width="1.7"/><rect x="16.5" y="8.5" width="3.5" height="7" rx="1.2" stroke="currentColor" stroke-width="1.7"/><path d="M7.5 12h9M2.5 10.5v3M21.5 10.5v3" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>
            @break
        @default
            <circle cx="6" cy="12" r="1.7" fill="currentColor"/><circle cx="12" cy="12" r="1.7" fill="currentColor"/><circle cx="18" cy="12" r="1.7" fill="currentColor"/>
    @endswitch
</svg>
