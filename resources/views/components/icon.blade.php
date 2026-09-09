{{--
    <x-icon name="calendar-days" /> — one vendored Font Awesome Pro Light icon.

    `size` sets both width and height, since every icon in the set is square.
--}}
@props(['name', 'size' => 18])

{!! \App\Support\Icon::inline($name, (int) $size, $attributes) !!}
