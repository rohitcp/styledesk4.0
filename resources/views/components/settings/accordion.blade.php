{{--
    One settings section: preview, edit, save, preview.

    Each card owns its own lifecycle. There is no page-level Edit and no
    page-level Save, because these settings have nothing to do with each
    other — someone changing how notes work should not be handed a form
    containing forty other decisions, nor be able to save them by accident.

    The preview is what the card shows when it is not being edited: a
    sentence, not the form with its inputs disabled. A disabled form is still
    a form to read.
--}}
@props([
    'id',
    'title',
    'description' => null,
    'summary' => null,
    'editable' => true,
    /** The wording on the button that closes edit mode when a card saves as
        it goes — preference and tag lists apply each change immediately. */
    'saves' => true,
])

<section id="{{ $id }}" class="styledesk_accordion" data-accordion data-section="{{ $id }}">
    <header class="styledesk_accordion__head">
        <button type="button" class="styledesk_accordion__toggle" data-accordion-toggle
                aria-expanded="false" aria-controls="{{ $id }}-body">
            <span class="min-w-0">
                <span class="block text-[15px] font-semibold text-head">{{ $title }}</span>

                @if ($description)
                    <span class="block text-[12.5px] text-sub mt-0.5 leading-relaxed">{{ $description }}</span>
                @endif

                {{-- The current configuration, in one line. What the reader
                     came to check, without opening anything. --}}
                @if ($summary)
                    <span class="block text-[13px] text-ink mt-1.5" data-accordion-summary>{{ $summary }}</span>
                @endif
            </span>

            <svg class="styledesk_accordion__chevron shrink-0" width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <path d="M6 9l6 6 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        </button>

        @if ($editable)
            {{-- Both hidden while the card is shut. A closed card is
                 something to read, and an Edit button beside a summary
                 invites a change to settings the reader has not seen yet. --}}
            <span class="styledesk_accordion__actions">
                <button type="button" class="styledesk_action styledesk_action--sm" data-accordion-edit hidden>
                    {{ __('common.edit') }}
                </button>

                <button type="button" class="styledesk_action styledesk_action--sm" data-accordion-cancel hidden>
                    {{ $saves ? __('common.cancel') : __('common.done') }}
                </button>
            </span>
        @endif
    </header>

    <div id="{{ $id }}-body" class="styledesk_accordion__body" data-accordion-body hidden>
        {{-- What the card shows when it is open but not being edited: the
             saved configuration, read as a sentence rather than as a form
             with its controls disabled. A disabled form is still a form. --}}
        @isset($preview)
            <div data-accordion-preview>{{ $preview }}</div>
        @endisset

        <div @isset($preview) data-accordion-form-wrap hidden @endisset>
            {{ $slot }}
        </div>
    </div>
</section>
