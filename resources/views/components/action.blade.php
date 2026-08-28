{{--
    The app's secondary action button.

    One component for Add another number, Add shift, Copy Monday's hours, Add
    exception, Back and Cancel — everything that is a real action but not the
    answer to "what do I press on this screen". That stays the filled primary
    button.

    Renders an <a> when given an href and a <button> otherwise, because "go
    somewhere" and "do something here" are different to a browser even when
    they look identical: only the first should open in a new tab, be
    bookmarkable, or appear in the link list of a screen reader.

    The styling lives in one CSS class rather than in a Tailwind string
    repeated at each call site — a string copied into six files is six things
    to keep in step, which is how the app ended up with three heights and a
    text link all meaning "add another".
--}}
@props([
    'href' => null,
    'icon' => null,
    'iconAfter' => null,
    'size' => null,
    'tone' => null,
    'type' => 'button',
])

@php
    $actionClasses = collect([
        'styledesk_action',
        $size === 'sm' ? 'styledesk_action--sm' : null,
        $tone === 'danger' ? 'styledesk_action--danger' : null,
    ])->filter()->join(' ');

    $iconSize = $size === 'sm' ? 11 : 13;
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->class($actionClasses) }}>
        @if ($icon)<x-icon :name="$icon" :size="$iconSize" />@endif
        {{ $slot }}
        @if ($iconAfter)<x-icon :name="$iconAfter" :size="$iconSize" />@endif
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->class($actionClasses) }}>
        @if ($icon)<x-icon :name="$icon" :size="$iconSize" />@endif
        {{ $slot }}
        @if ($iconAfter)<x-icon :name="$iconAfter" :size="$iconSize" />@endif
    </button>
@endif
