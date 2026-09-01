{{--
    Add and edit in one dialog.

    A dialog rather than a page: a reason is three fields and is edited from
    within the list the reader is comparing it against. Sending them to
    another screen to rename one would lose that context for no gain. The
    service and resource catalogues work the same way.
--}}
<div id="reasonModal" class="styledesk_modal" hidden>
    <div class="styledesk_modal__scrim" data-reason-close></div>

    <div class="styledesk_modal__panel" role="dialog" aria-modal="true" aria-labelledby="reasonModalTitle">
        <div class="styledesk_modal__head">
            <h2 id="reasonModalTitle" class="text-[15px] font-semibold text-head">{{ __('reasons.add_title') }}</h2>

            <button type="button" class="styledesk_modal__close" data-reason-close aria-label="{{ __('common.close') }}">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
            </button>
        </div>

        <form method="POST" action="{{ route('settings.reasons.store', $type) }}" data-reason-form>
            @csrf
            <input type="hidden" name="_method" value="POST" data-reason-method>

            <div class="styledesk_modal__body space-y-4">
                <div>
                    <label for="reasonName" class="block text-[13px] font-medium text-ink mb-1.5">
                        {{ __('reasons.name') }} <span class="text-danger" aria-hidden="true">*</span>
                    </label>
                    <input id="reasonName" name="name" type="text" class="sd-input" maxlength="120" required
                           data-capitalize placeholder="{{ __('reasons.name_placeholder') }}">
                    <p data-error-for="name" role="alert" class="mt-1.5 text-[12px] text-danger"
                       @unless ($errors->has('name')) hidden @endunless>{{ $errors->first('name') }}</p>
                </div>

                <div>
                    <label for="reasonDescription" class="block text-[13px] font-medium text-ink mb-1.5">
                        {{ __('reasons.description') }}
                        <span class="text-faint font-normal">{{ __('common.optional') }}</span>
                    </label>
                    <textarea id="reasonDescription" name="description" rows="2" class="sd-input !h-auto py-2.5"
                              maxlength="300"></textarea>
                    <p class="text-[12px] text-faint mt-1.5">{{ __('reasons.description_hint') }}</p>
                </div>

                {{-- The reasons that are not an answer on their own. "Other"
                     is the obvious one and StyleDesk sets it already; a
                     business can ask the same of any of its own. --}}
                <label class="flex items-start gap-2.5 cursor-pointer">
                    <input type="hidden" name="requires_details" value="0">
                    <input type="checkbox" name="requires_details" value="1" class="mt-0.5" data-reason-details>
                    <span class="min-w-0">
                        <span class="block text-[13px] text-ink">{{ __('reasons.requires_details') }}</span>
                        <span class="block text-[12px] text-faint mt-0.5">{{ __('reasons.requires_details_hint') }}</span>
                    </span>
                </label>

                {{-- Said only where it applies. A StyleDesk reason can be
                     renamed like any other; what it cannot be is removed, and
                     the reader finds that out here rather than from a menu
                     entry that is simply missing. --}}
                <p class="text-[12px] text-sub leading-relaxed" data-reason-system hidden>
                    {{ __('reasons.system_undeletable') }}
                </p>
            </div>

            <div class="styledesk_modalfoot">
                <button type="submit"
                        class="h-9 px-4 rounded-lg bg-brand hover:bg-brand-dark text-white text-[13px] font-semibold transition-colors">
                    {{ __('common.save') }}
                </button>

                <button type="button" class="styledesk_action" data-reason-close>{{ __('common.cancel') }}</button>
            </div>
        </form>
    </div>
</div>
