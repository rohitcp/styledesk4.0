{{--
    Add and edit in one dialog.

    A dialog rather than a page here, unlike resources themselves: a category
    is three fields and is edited from within a list the reader is comparing
    it against. Sending them to another screen to rename one would lose that
    context for no gain.
--}}
<div id="categoryModal" class="styledesk_modal" hidden>
    <div class="styledesk_modal__scrim" data-category-close></div>

    <div class="styledesk_modal__panel" role="dialog" aria-modal="true" aria-labelledby="categoryModalTitle">
        <div class="styledesk_modal__head">
            <h2 id="categoryModalTitle" class="text-[15px] font-semibold text-head">{{ __('resources.categories_ui.add') }}</h2>

            <button type="button" class="styledesk_modal__close" data-category-close aria-label="{{ __('common.close') }}">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
            </button>
        </div>

        <form method="POST" action="{{ route('settings.resources.store') }}" data-category-form>
            @csrf
            <input type="hidden" name="_method" value="POST" data-category-method>

            <div class="styledesk_modal__body space-y-4">
                <x-text-field name="name" :label="__('resources.categories_ui.columns.name')" required maxlength="80" />

                <x-combo name="group" :label="__('resources.categories_ui.columns.group')"
                         :options="__('resources.category_groups')"
                         :placeholder="__('resources.categories_ui.no_group')" />

                <div>
                    <label for="categoryCapacity" class="block text-[13px] font-medium text-ink mb-1.5">
                        {{ __('resources.capacity') }} <span class="text-danger">*</span>
                    </label>
                    <input id="categoryCapacity" name="default_capacity" type="number" class="sd-input" required
                           min="1" max="{{ config('resources.max_capacity') }}" value="{{ config('resources.default_capacity') }}">
                    <p class="text-[12px] text-sub mt-1.5 leading-relaxed">{{ __('resources.categories_ui.capacity_hint') }}</p>
                </div>

                {{-- Said only where it applies. A system category can be
                     renamed and regrouped like any other; what it cannot be
                     is removed, and the reader finds that out here rather
                     than from a menu entry that is simply missing. --}}
                <p class="text-[12px] text-sub leading-relaxed" data-category-system hidden>
                    {{ __('resources.categories_ui.system_note') }}
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
