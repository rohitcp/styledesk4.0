{{--
    Add and edit in one dialog.

    A dialog rather than a page: a category is two fields and is edited from
    within a list the reader is comparing it against. Sending them to another
    screen to rename one would lose that context for no gain. The resource
    catalogue works the same way.
--}}
<div id="categoryModal" class="styledesk_modal" hidden>
    <div class="styledesk_modal__scrim" data-category-close></div>

    <div class="styledesk_modal__panel" role="dialog" aria-modal="true" aria-labelledby="categoryModalTitle">
        <div class="styledesk_modal__head">
            <h2 id="categoryModalTitle" class="text-[15px] font-semibold text-head">{{ __('services.categories_ui.add') }}</h2>

            <button type="button" class="styledesk_modal__close" data-category-close aria-label="{{ __('common.close') }}">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
            </button>
        </div>

        <form method="POST" action="{{ route('settings.services.store') }}" data-category-form>
            @csrf
            <input type="hidden" name="_method" value="POST" data-category-method>

            <div class="styledesk_modal__body space-y-4">
                <x-text-field name="name" :label="__('services.categories_ui.columns.name')" required maxlength="80" />

                <div>
                    <label for="categoryDescription" class="block text-[13px] font-medium text-ink mb-1.5">
                        {{ __('services.categories_ui.columns.description') }}
                        <span class="text-faint font-normal">{{ __('common.optional') }}</span>
                    </label>
                    <textarea id="categoryDescription" name="description" rows="2" class="sd-input !h-auto py-2.5"
                              maxlength="255"></textarea>
                </div>

                {{-- Said only where it applies. A system category can be
                     renamed like any other; what it cannot be is removed, and
                     the reader finds that out here rather than from a menu
                     entry that is simply missing. --}}
                <p class="text-[12px] text-sub leading-relaxed" data-category-system hidden>
                    {{ __('services.categories_ui.system_note') }}
                </p>
            </div>

            <div class="styledesk_modalfoot">
                <button type="submit"
                        class="h-9 px-4 rounded-lg bg-brand hover:bg-brand-dark text-white text-[13px] font-semibold transition-colors">
                    {{ __('common.save') }}
                </button>

                <button type="button" class="styledesk_action" data-category-close>{{ __('common.cancel') }}</button>
            </div>
        </form>
    </div>
</div>
