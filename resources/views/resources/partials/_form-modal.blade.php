{{--
    Add and edit in one dialog.

    The same eight fields either way — the only difference is where it posts
    and what it starts with — and two dialogs would be two places to keep a
    field in step.
--}}
<div id="resourceModal" class="styledesk_modal" hidden>
    <div class="styledesk_modal__scrim" data-resource-close></div>

    <div class="styledesk_modal__panel" role="dialog" aria-modal="true" aria-labelledby="resourceModalTitle">
        <div class="styledesk_modal__head">
            <h2 id="resourceModalTitle" class="text-[15px] font-semibold text-head">{{ __('resources.add') }}</h2>

            <button type="button" class="styledesk_modal__close" data-resource-close aria-label="{{ __('common.close') }}">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
            </button>
        </div>

        <form method="POST" action="{{ route('resources.store') }}" data-resource-form>
            @csrf
            {{-- Switched to PATCH by the script when editing, so one form
                 serves both and there is no second copy of these fields. --}}
            <input type="hidden" name="_method" value="POST" data-resource-method>

            <div class="styledesk_modal__body space-y-4">
                <x-text-field name="name" :label="__('resources.name')" required maxlength="120" />

                <div class="grid sm:grid-cols-2 gap-4">
                    <x-combo name="resource_category_id" :label="__('resources.category')"
                             :options="$categories->pluck('name', 'id')"
                             :placeholder="__('resources.uncategorised')" />

                    <x-combo name="location_id" :label="__('resources.location')"
                             :options="$locations->pluck('name', 'id')"
                             :placeholder="__('resources.all_locations')" />
                </div>

                <div>
                    <label for="capacity" class="block text-[13px] font-medium text-ink mb-1.5">{{ __('resources.capacity') }}</label>
                    <input id="capacity" name="capacity" type="number" class="sd-input" required
                           min="1" max="{{ config('resources.max_capacity') }}" value="1">
                    <p class="text-[12px] text-sub mt-1.5 leading-relaxed">{{ __('resources.capacity_hint') }}</p>
                </div>

                <div>
                    <label for="resourceDescription" class="block text-[13px] font-medium text-ink mb-1.5">
                        {{ __('resources.description') }}
                        <span class="text-faint font-normal">{{ __('common.optional') }}</span>
                    </label>
                    <textarea id="resourceDescription" name="description" rows="3" class="sd-input !h-auto py-2.5" maxlength="1000"></textarea>
                </div>
            </div>

            <div class="styledesk_modalfoot">
                <button type="submit"
                        class="h-9 px-4 rounded-lg bg-brand hover:bg-brand-dark text-white text-[13px] font-semibold transition-colors">
                    {{ __('common.save') }}
                </button>

                <button type="button" class="styledesk_action" data-resource-close>{{ __('common.cancel') }}</button>
            </div>
        </form>
    </div>
</div>
