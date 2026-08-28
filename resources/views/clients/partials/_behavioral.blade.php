{{--
    The behavioural tags this client carries.

    A tinted card rather than a plain section: the right column is a stack of
    contexts, and colour is what lets a reader find the one they want without
    reading all four headings.
--}}
@php $assignedKeys = $assignedBehavioral->pluck('key')->all(); @endphp

<section class="styledesk_infocard styledesk_infocard--violet mt-5">
    <div class="flex items-center gap-2">
        <h2 class="styledesk_infocard__title flex-1 min-w-0">{{ __('clients.behavioral.title') }}</h2>

        @if ($canEdit)
            <button type="button" class="styledesk_cardbtn sd-tip shrink-0" data-behavioral-open
                    data-tip="{{ __('clients.behavioral.manage') }}"
                    aria-label="{{ __('clients.behavioral.manage') }}">
                <x-icon name="plus" size="14" />
            </button>
        @endif
    </div>

    @if ($assignedBehavioral->isEmpty())
        {{-- Nothing counted yet, and nothing pretending otherwise. --}}
        <p class="text-[13px] mt-1 leading-relaxed">{{ __('clients.behavioral.none_yet') }}</p>
    @else
        <div class="flex flex-wrap gap-1.5 mt-2">
            @foreach ($assignedBehavioral as $tag)
                <span class="styledesk_tagchip" data-tip="{{ $tag['rule'] }}">
                    {{ $tag['label'] }}

                    @if ($canEdit)
                        {{-- Removing one posts the rest: the set is the record,
                             so every change to it is stated in full. --}}
                        <form method="POST" action="{{ route('clients.behavioral', $client) }}" class="contents">
                            @csrf
                            @method('PATCH')
                            @foreach (array_diff($assignedKeys, [$tag['key']]) as $keep)
                                <input type="hidden" name="tags[]" value="{{ $keep }}">
                            @endforeach

                            <button type="submit" class="styledesk_tagchip__remove"
                                    data-confirm-title="{{ __('common.confirm.remove_behavioral_title') }}"
                                    data-confirm="{{ __('common.confirm.remove_behavioral', ['label' => $tag['label']]) }}"
                                    data-confirm-label="{{ __('common.remove') }}"
                                    aria-label="{{ __('common.remove') }} {{ $tag['label'] }}">
                                <svg width="10" height="10" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="2.6" stroke-linecap="round"/></svg>
                            </button>
                        </form>
                    @endif
                </span>
            @endforeach
        </div>
    @endif
</section>

@if ($canEdit)
    {{-- The modal: every tag this business applies, with the ones on this
         client ticked. A whole set in, a whole set out. --}}
    <div id="behavioralModal" class="styledesk_modal" hidden>
        <div class="styledesk_modal__scrim" data-behavioral-close></div>

        <div class="styledesk_modal__panel" role="dialog" aria-modal="true" aria-labelledby="behavioralTitle">
            <div class="styledesk_modal__head">
                <h2 id="behavioralTitle" class="text-[15px] font-semibold text-head">{{ __('clients.behavioral.manage') }}</h2>

                <button type="button" class="styledesk_modal__close" data-behavioral-close aria-label="{{ __('common.close') }}">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                </button>
            </div>

            <form method="POST" action="{{ route('clients.behavioral', $client) }}">
                @csrf
                @method('PATCH')

                <div class="styledesk_modal__body space-y-3">
                    <div class="relative">
                        <span class="styledesk_input__prefix pointer-events-none" aria-hidden="true">
                            <x-icon name="magnifying-glass" size="14" />
                        </span>
                        <input type="search" class="sd-input styledesk_input--prefixed !h-9" data-behavioral-search
                               placeholder="{{ __('clients.behavioral.search') }}"
                               aria-label="{{ __('clients.behavioral.search') }}">
                    </div>

                    <div class="max-h-[340px] overflow-y-auto styledesk_scroll styledesk_choicelist pr-1">
                        @foreach ($availableBehavioral as $tag)
                            <label class="styledesk_choice" data-behavioral-option
                                   data-label="{{ Str::lower($tag->label().' '.$tag->categoryLabel()) }}">
                                <input type="checkbox" name="tags[]" value="{{ $tag->tag_key }}" class="sd-check"
                                       @checked(in_array($tag->tag_key, $assignedKeys, true))>

                                <span class="styledesk_choice__label">
                                    {{ $tag->label() }}
                                    <span class="styledesk_choice__hint">{{ $tag->categoryLabel() }} · {{ $tag->rule() }}</span>
                                </span>
                            </label>
                        @endforeach
                    </div>

                    <p class="text-[12px] text-sub" data-behavioral-empty hidden>{{ __('clients.behavioral.no_matches') }}</p>
                </div>

                <div class="styledesk_modalfoot">
                    <button type="submit"
                            class="h-9 px-4 rounded-lg bg-brand hover:bg-brand-dark text-white text-[13px] font-semibold transition-colors">
                        {{ __('common.save_changes') }}
                    </button>

                    <button type="button" class="styledesk_action" data-behavioral-close>{{ __('common.cancel') }}</button>
                </div>
            </form>
        </div>
    </div>
@endif
