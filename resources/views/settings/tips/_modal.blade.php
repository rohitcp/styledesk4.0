{{--
    One service's tip settings.

    A dialog rather than a page: it is four fields, and it is edited from
    within the table the reader is comparing it against. Sending them to
    another screen to change a percentage would lose that comparison for no
    gain — the reason catalogue works the same way.
--}}
<div id="tipModal" class="styledesk_modal" hidden>
    <div class="styledesk_modal__scrim" data-tip-close></div>

    <div class="styledesk_modal__panel" role="dialog" aria-modal="true" aria-labelledby="tipModalTitle">
        <div class="styledesk_modal__head">
            <h2 id="tipModalTitle" class="text-[15px] font-semibold text-head">{{ __('tips.edit_service') }}</h2>

            <button type="button" class="styledesk_modal__close" data-tip-close aria-label="{{ __('common.close') }}">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
            </button>
        </div>

        <form method="POST" data-tip-form>
            @csrf
            @method('PATCH')

            <div class="styledesk_modal__body space-y-4">
                <label class="flex items-start gap-3 cursor-pointer">
                    <input type="hidden" name="accepts_tips" value="0">
                    <input type="checkbox" name="accepts_tips" value="1" class="mt-0.5" data-tip-accepts>
                    <span class="min-w-0">
                        <span class="block text-[13px] text-ink">{{ __('tips.accepts') }}</span>
                        <span class="block text-[12px] text-faint mt-0.5 leading-relaxed">{{ __('tips.accepts_hint') }}</span>
                    </span>
                </label>

                {{-- Everything below only means anything while the service is
                     tipped, so it goes away when it is not. --}}
                <div class="space-y-4" data-tip-fields>
                    <div class="grid sm:grid-cols-2 gap-4">
                        <div>
                            <label for="tipServiceType" class="block text-[13px] font-medium text-ink mb-1.5">{{ __('tips.tip_type') }}</label>
                            <select id="tipServiceType" name="tip_type" class="sd-input" data-tip-type>
                                <option value="">{{ __('tips.follows_default') }}</option>
                                @foreach (\App\Models\TipSettings::SERVICE_TYPES as $type)
                                    <option value="{{ $type }}">{{ __('tips.types.'.$type) }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label for="tipServiceValue" class="block text-[13px] font-medium text-ink mb-1.5">{{ __('tips.tip_amount') }}</label>
                            {{-- Blank is allowed and means "follow the
                                 business", which is not the same as nought:
                                 one moves when the default does. --}}
                            <input id="tipServiceValue" name="tip_value" type="number" min="0" max="100" class="sd-input"
                                   placeholder="{{ __('tips.follows_default') }}" data-tip-value>
                        </div>
                    </div>

                    <label class="flex items-start gap-3 cursor-pointer">
                        <input type="hidden" name="tip_required" value="0">
                        <input type="checkbox" name="tip_required" value="1" class="mt-0.5" data-tip-required>
                        <span class="min-w-0">
                            <span class="block text-[13px] text-ink">{{ __('tips.require_selection') }}</span>
                            <span class="block text-[12px] text-faint mt-0.5 leading-relaxed">{{ __('tips.require_selection_hint') }}</span>
                        </span>
                    </label>

                    <label class="flex items-start gap-3 cursor-pointer">
                        <input type="hidden" name="allow_no_tip" value="0">
                        <input type="checkbox" name="allow_no_tip" value="1" class="mt-0.5" data-tip-no-tip>
                        <span class="min-w-0">
                            <span class="block text-[13px] text-ink">{{ __('tips.allow_no_tip') }}</span>
                            <span class="block text-[12px] text-faint mt-0.5 leading-relaxed">{{ __('tips.allow_no_tip_hint') }}</span>
                        </span>
                    </label>
                </div>
            </div>

            <div class="styledesk_modalfoot">
                <button type="submit"
                        class="h-9 px-4 rounded-lg bg-brand hover:bg-brand-dark text-white text-[13px] font-semibold transition-colors">
                    {{ __('common.save') }}
                </button>

                <button type="button" class="styledesk_action" data-tip-close>{{ __('common.cancel') }}</button>
            </div>
        </form>
    </div>
</div>
