{{--
    Taking a resource out of service.

    A reason and a period rather than a switch: the calendar has to show why
    the room is gone and when it is back, and "until further notice" has to
    be sayable without inventing a date.
--}}
<div id="resourceBlockModal" class="styledesk_modal" hidden>
    <div class="styledesk_modal__scrim" data-block-close></div>

    <div class="styledesk_modal__panel" role="dialog" aria-modal="true" aria-labelledby="resourceBlockTitle">
        <div class="styledesk_modal__head">
            <h2 id="resourceBlockTitle" class="text-[15px] font-semibold text-head">{{ __('resources.block') }}</h2>

            <button type="button" class="styledesk_modal__close" data-block-close aria-label="{{ __('common.close') }}">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
            </button>
        </div>

        <form method="POST" data-block-form>
            @csrf

            <div class="styledesk_modal__body space-y-4">
                <x-combo name="reason" :label="__('resources.block_reason')" required
                         :options="collect(config('resources.block_reasons'))->mapWithKeys(fn ($reason) => [$reason => __('resources.block_reasons.'.$reason)])"
                         selected="maintenance" />

                <div class="grid sm:grid-cols-2 gap-4">
                    <x-date-field name="starts_at" :label="__('resources.block_from')" required />
                    <x-date-field name="ends_at" :label="__('resources.block_until')"
                                  :hint="__('resources.block_until_hint')" optional />
                </div>

                <x-text-field name="note" :label="__('resources.block_note')" maxlength="255" />
            </div>

            <div class="styledesk_modalfoot">
                <button type="submit"
                        class="h-9 px-4 rounded-lg bg-brand hover:bg-brand-dark text-white text-[13px] font-semibold transition-colors">
                    {{ __('resources.block') }}
                </button>

                <button type="button" class="styledesk_action" data-block-close>{{ __('common.cancel') }}</button>
            </div>
        </form>
    </div>
</div>
