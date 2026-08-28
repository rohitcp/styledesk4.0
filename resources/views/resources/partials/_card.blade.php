{{--
    One resource.

    A card rather than a table row: a resource is read as a thing — what it
    is, where it is, whether it can be booked right now — and the reason it
    cannot be is a sentence, which a cell cannot hold.
--}}
@php
    $status = $resource->availabilityStatus();
    $block = $resource->blockAt();

    /**
     * Assembled here rather than inline in the attribute.
     *
     * Blade's json directive counts brackets instead of reading PHP, so an
     * array literal written inside the tag ends the attribute at its first
     * closing bracket. The combo component carries the same warning; this
     * file learned it the same way.
     */
    $editable = [
        'id' => $resource->id,
        'name' => $resource->name,
        'category' => $resource->resource_category_id,
        'location' => $resource->location_id,
        'capacity' => $resource->capacity,
        'description' => $resource->description,
    ];
@endphp

<article @class([
    'styledesk_resource',
    'styledesk_resource--blocked' => $status === 'blocked',
    'styledesk_resource--inactive' => $status === 'inactive',
])>
    <div class="flex items-start gap-2">
        <div class="min-w-0 flex-1">
            <h3 class="text-[14px] font-semibold text-head truncate">{{ $resource->name }}</h3>

            <p class="text-[12px] text-sub mt-0.5 truncate">
                {{ $resource->location?->name ?? __('common.none') }}
            </p>
        </div>

        <span @class([
            'styledesk_badge shrink-0',
            'styledesk_badge--active' => $status === 'available',
            'styledesk_badge--setup' => $status === 'blocked',
            'styledesk_badge--soon' => $status === 'inactive',
        ])>{{ $resource->availabilityLabel() }}</span>
    </div>

    {{-- Capacity in words, because "2" beside a room name is a number
         without a unit — it could be a floor or a chair count. --}}
    <p class="text-[12px] text-sub mt-2">
        {{ $resource->capacity === 1
            ? __('resources.holds_one')
            : __('resources.holds_many', ['count' => $resource->capacity]) }}
    </p>

    @if ($block)
        {{-- Why, and until when. A block with no end date says so rather
             than showing a blank where a date should be. --}}
        <p class="text-[12px] text-amber-800 mt-1.5 leading-relaxed">
            {{ $block->reasonLabel() }} ·
            {{ $block->ends_at
                ? __('resources.blocked_until', ['date' => $block->ends_at->isoFormat('D MMM Y')])
                : __('resources.blocked_indefinitely') }}
        </p>
    @endif

    @if ($resource->description)
        <p class="text-[12px] text-sub mt-1.5 leading-relaxed line-clamp-2">{{ $resource->description }}</p>
    @endif

    <div class="flex flex-wrap items-center gap-1.5 mt-3">
        @if ($canEdit)
            <button type="button" class="styledesk_action styledesk_action--sm"
                    data-resource-edit="{{ $resource->id }}"
                    data-resource='@json($editable)'>
                {{ __('common.edit') }}
            </button>
        @endif

        @if ($canBlock)
            @if ($block)
                <form method="POST" action="{{ route('resources.unblock', [$resource, $block]) }}">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="styledesk_action styledesk_action--sm">{{ __('resources.unblock') }}</button>
                </form>
            @else
                <button type="button" class="styledesk_action styledesk_action--sm"
                        data-resource-block="{{ $resource->id }}"
                        data-resource-name="{{ $resource->name }}">
                    {{ __('resources.block') }}
                </button>
            @endif
        @endif

        @if ($canEdit)
            <form method="POST" action="{{ route('resources.toggle', $resource) }}" class="ml-auto">
                @csrf
                @method('PATCH')
                <button type="submit" class="styledesk_action styledesk_action--sm"
                        @if ($resource->is_active)
                            data-confirm-title="{{ __('resources.retire') }}"
                            data-confirm="{{ __('resources.retire_confirm', ['name' => $resource->name]) }}"
                            data-confirm-label="{{ __('resources.retire') }}"
                        @endif>
                    {{ $resource->is_active ? __('resources.retire') : __('resources.restore') }}
                </button>
            </form>
        @endif
    </div>
</article>
