{{--
    A section's saved configuration, as a list of facts.

    One partial for all nine cards, so no two of them describe their settings
    in a different shape — the value of a preview is that it reads the same
    everywhere and can be scanned without being learned.
--}}
@props(['rows' => [], 'note' => null])

<dl class="divide-y divide-line">
    @foreach ($rows as $label => $value)
        <div class="flex flex-wrap items-baseline gap-x-4 gap-y-1 py-2.5">
            <dt class="text-[13px] text-sub w-[220px] shrink-0">{{ $label }}</dt>
            <dd class="text-[13px] text-head min-w-0 flex-1">
                {{-- A row may hand over a rendered component rather than a
                     string — a consent state is drawn, not spelled — so
                     HtmlString passes through and everything else is escaped
                     as before. --}}
                @if ($value instanceof \Illuminate\Support\HtmlString)
                    {!! $value !!}
                @else
                    {{ $value !== '' && $value !== null ? $value : __('common.none') }}
                @endif
            </dd>
        </div>
    @endforeach
</dl>

@if ($note)
    <p class="text-[12px] text-sub mt-2 leading-relaxed">{{ $note }}</p>
@endif
