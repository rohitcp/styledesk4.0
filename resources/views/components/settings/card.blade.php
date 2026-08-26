@props(['title', 'description' => null])

<section class="bg-white border border-line rounded-card p-5">
    <h2 class="text-[15px] font-semibold text-head">{{ $title }}</h2>
    @if ($description)
        <p class="text-[13px] text-sub mt-1">{{ $description }}</p>
    @endif

    <dl class="mt-3">{{ $slot }}</dl>
</section>
