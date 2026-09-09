{{-- A figure that is also a filter: pressing it narrows the table below to
     the transactions it counts. --}}
@props(['label', 'value', 'note' => null, 'change' => null, 'href'])

<a href="{{ $href }}" class="sd-card px-4 py-3.5 block hover:border-brand/60 transition-colors">
    <p class="text-[12px] text-sub">{{ $label }}</p>
    <p class="text-[20px] font-bold text-head mt-1 tabular-nums">{{ $value }}</p>
    <p class="text-[11.5px] text-link font-semibold mt-0.5">{{ __('sales.show_these') }}</p>
</a>
