{{--
    The client tags this client carries — the ones the team puts on by hand,
    as opposed to the behavioural ones StyleDesk works out for itself.

    The name, the avatar and the status used to sit above these; they live in
    the page header now, where the reader looks to find out whose record this
    is. Repeating them here said the same thing twice and cost the column its
    most valuable inches.
--}}
<section data-client-tags>
    <div class="flex items-start gap-2">
        <p class="styledesk_label flex-1 min-w-0">{{ __('clients.module.workspace.tags.title') }}</p>

        @if ($canEdit)
            {{-- The same add button as the ones on the note and behavioural
                 cards, in the neutral colouring the copy buttons beside it
                 use: same shape everywhere, and no colour in a column that
                 has none. --}}
            <button type="button" class="styledesk_cardbtn styledesk_cardbtn--neutral sd-tip shrink-0" data-tags-open
                    data-tip="{{ __('clients.module.workspace.tags.manage') }}"
                    aria-label="{{ __('clients.module.workspace.tags.manage') }}">
                <x-icon name="plus" size="14" />
            </button>
        @endif
    </div>

    <p class="text-[12px] text-sub mt-1.5 leading-relaxed">{{ __('clients.module.workspace.tags.intro') }}</p>

    {{-- Rewritten in place when the modal saves, so the card and the record
         agree without the reader losing their place on the page. --}}
    <div class="flex flex-wrap items-center gap-1.5 mt-2.5" data-tags-list>
        @forelse ($client->tags as $tag)
            <span class="styledesk_clienttag" style="--tag-ink: {{ $tag->hex() }}" data-tag-id="{{ $tag->id }}">
                {{ $tag->label }}

                @if ($canEdit)
                    {{-- Asked before it comes off, and the question names the
                         tag: a row of × controls is not something to answer
                         "are you sure?" to. --}}
                    <button type="button" class="styledesk_clienttag__remove" data-tag-remove="{{ $tag->id }}"
                            data-confirm-title="{{ __('common.confirm.remove_tag_title') }}"
                            data-confirm="{{ __('common.confirm.remove_tag', ['label' => $tag->label]) }}"
                            data-confirm-label="{{ __('common.remove') }}"
                            aria-label="{{ __('common.remove') }} {{ $tag->label }}">
                        <svg width="10" height="10" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="2.6" stroke-linecap="round"/></svg>
                    </button>
                @endif
            </span>
        @empty
            <span class="text-[12px] text-faint" data-tags-empty>{{ __('clients.module.workspace.tags.none') }}</span>
        @endforelse
    </div>

</section>

@if ($canEdit)
    {{-- The modal: every tag this business applies, with the ones on this
         client ticked. A whole set in, a whole set out. --}}
    <div id="clientTagsModal" class="styledesk_modal" hidden>
        <div class="styledesk_modal__scrim" data-tags-close></div>

        <div class="styledesk_modal__panel" role="dialog" aria-modal="true" aria-labelledby="clientTagsTitle">
            <div class="styledesk_modal__head">
                <h2 id="clientTagsTitle" class="text-[15px] font-semibold text-head">{{ __('clients.module.workspace.tags.manage') }}</h2>

                <button type="button" class="styledesk_modal__close" data-tags-close aria-label="{{ __('common.close') }}">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                </button>
            </div>

            {{-- A real form with a real action: saving goes through fetch so
                 the card updates in place, and falls back to a normal post if
                 that fails or scripting is off. --}}
            <form method="POST" action="{{ route('clients.tags', $client) }}" data-tags-form>
                @csrf
                @method('PATCH')

                <div class="styledesk_modal__body space-y-3">
                    <div class="relative">
                        <span class="styledesk_input__prefix pointer-events-none" aria-hidden="true">
                            <x-icon name="magnifying-glass" size="14" />
                        </span>
                        <input type="search" class="sd-input styledesk_input--prefixed !h-9" data-tags-search
                               placeholder="{{ __('clients.module.workspace.tags.search') }}"
                               aria-label="{{ __('clients.module.workspace.tags.search') }}">
                    </div>

                    <div class="max-h-[340px] overflow-y-auto styledesk_scroll styledesk_choicelist pr-1">
                        @foreach ($availableTags as $tag)
                            <label class="styledesk_choice" data-tags-option data-label="{{ Str::lower($tag->label) }}">
                                <input type="checkbox" name="tags[]" value="{{ $tag->id }}" class="sd-check"
                                       @checked($client->tags->contains($tag->id))>

                                <span class="styledesk_choice__label flex items-center gap-2">
                                    <span class="styledesk_clienttag__dot" style="background: {{ $tag->hex() }}" aria-hidden="true"></span>
                                    {{ $tag->label }}
                                </span>
                            </label>
                        @endforeach
                    </div>

                    <p class="text-[12px] text-sub" data-tags-no-matches hidden>{{ __('clients.module.workspace.tags.no_matches') }}</p>
                </div>

                <div class="styledesk_modalfoot">
                    <button type="submit"
                            class="h-9 px-4 rounded-lg bg-brand hover:bg-brand-dark text-white text-[13px] font-semibold transition-colors">
                        {{ __('common.save_changes') }}
                    </button>

                    <button type="button" class="styledesk_action" data-tags-close>{{ __('common.cancel') }}</button>
                </div>
            </form>
        </div>
    </div>
@endif
