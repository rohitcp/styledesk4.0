{{-- One figure. Read, not pressed. --}}
@props(['label', 'value', 'note' => null, 'change' => null, 'href' => null])

<div class="sd-card px-4 py-3.5">
    <p class="text-[12px] text-sub">{{ $label }}</p>
    <p class="text-[20px] font-bold text-head mt-1 tabular-nums">{{ $value }}</p>

    {{-- Null means there was nothing to compare against, which is not the
         same as no change — so nothing is shown rather than "+0%". --}}
    @if ($change !== null)
        <p @class([
            'text-[12px] font-semibold mt-0.5',
            'text-emerald-700' => $change >= 0,
            'text-danger' => $change < 0,
        ])>
            {{ $change >= 0 ? '+' : '' }}{{ $change }}% {{ __('sales.vs_previous') }}
        </p>
    @elseif ($note)
        <p class="text-[11.5px] text-faint mt-0.5">{{ $note }}</p>
    @endif
</div>
