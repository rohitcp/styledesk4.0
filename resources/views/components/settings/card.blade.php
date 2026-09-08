{{--
    One titled block of read-only settings.

    `edit` turns the header into an action row carrying the same pencil
    affordance the account screens use. It is a link, not a toggle: a settings
    card summarises a whole module, so editing it means going to that module's
    own page rather than opening fields in place.
--}}
@props(['title', 'description' => null, 'edit' => null, 'editLabel' => null])

<section class="bg-white border border-line rounded-card p-5">
    <div class="flex items-start justify-between gap-4">
        <div class="min-w-0">
            <h2 class="text-[15px] font-semibold text-head">{{ $title }}</h2>
            @if ($description)
                <p class="text-[13px] text-sub mt-1">{{ $description }}</p>
            @endif
        </div>

        @if ($edit)
            {{-- The visible word is the same on every card, so the accessible
                 name carries the card it belongs to — otherwise a screen
                 reader announces eight identical "Edit" links. --}}
            <a href="{{ $edit }}" class="styledesk_action shrink-0"
               aria-label="{{ $editLabel ?? __('common.edit_section', ['section' => $title]) }}">
                <x-icon name="pen-to-square" size="14" />
                {{ __('common.edit') }}
            </a>
        @endif
    </div>

    <dl class="mt-3">{{ $slot }}</dl>
</section>
