{{--
    A tab the navigation names and the next phase builds.

    An honest "not yet" rather than a half-built screen. The list underneath is
    what the tab will hold, so a reader can tell whether to wait for it or go
    and find the answer somewhere else.
--}}
@props(['title', 'intro', 'items' => []])

<section {{ $attributes->merge(['class' => 'sd-card p-8 text-center']) }}>
    <p class="text-[15px] font-semibold text-head">{{ $title }}</p>

    <p class="text-[13px] text-sub mt-1.5 max-w-[460px] mx-auto leading-relaxed">{{ $intro }}</p>

    <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-1 mt-4 text-[11.5px] font-semibold text-slate-600">
        {{ __('backoffice.tabs.coming_soon') }}
    </span>

    @if ($items !== [])
        <ul class="mt-6 mx-auto max-w-[520px] grid sm:grid-cols-2 gap-x-6 gap-y-1.5 text-left">
            @foreach ($items as $item)
                <li class="text-[12.5px] text-sub flex items-start gap-2">
                    <span class="text-faint mt-px" aria-hidden="true">•</span>
                    <span>{{ $item }}</span>
                </li>
            @endforeach
        </ul>
    @endif
</section>
