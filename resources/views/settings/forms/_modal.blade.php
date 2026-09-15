{{--
    Create a form, without leaving the list.

    A dialog rather than a page: naming a form is five fields, and the reader
    is usually looking at the forms they already have while deciding what this
    one is. Sending them to a screen of their own to type a name would lose
    that for no gain — the same reasoning as the service and resource
    catalogues.

    Status is not asked for. A form is created as a draft and goes live by
    being published, so the dialog says so rather than offering a control with
    one legal answer.
--}}
<div id="formModal" class="styledesk_modal" hidden
     @if ($errors->hasAny(['name', 'type', 'category_id', 'internal_description', 'layout'])) data-form-open-on-load @endif>
    <div class="styledesk_modal__scrim" data-form-close></div>

    <div class="styledesk_modal__panel" role="dialog" aria-modal="true" aria-labelledby="formModalTitle">
        <div class="styledesk_modal__head">
            <h2 id="formModalTitle" class="text-[15px] font-semibold text-head">{{ __('forms.new.title') }}</h2>

            <button type="button" class="styledesk_modal__close" data-form-close aria-label="{{ __('common.close') }}">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
            </button>
        </div>

        @php
            /* Built above rather than inline: the encoding directive's
               argument is parsed by counting brackets rather than by reading
               PHP, so a call with nested parentheses inside the tag does not
               parse. */
            $formMessages = App\Support\LiveValidation::messages();

            /* What turns a native select into the design system's combo is
               `data-combo`; the options ride along as json. Built here for
               the reason above — a bracketed literal inside the attribute
               would be cut short by the directive parser. */
            $typeCombo = [
                'searchLabel' => __('forms.new.search_type'),
                'searchPlaceholder' => __('common.search'),
            ];

            $categoryCombo = [
                'searchLabel' => __('forms.new.search_category'),
                'searchPlaceholder' => __('common.search'),
            ];
        @endphp

        {{-- Refused in the dialog while it is typed, and again on the server.
             The rules live on the fields; this only says which words to
             refuse them in. --}}
        <form method="POST" action="{{ route('settings.forms.store') }}" data-form-create
              data-validate-form
              data-validation-messages='@json($formMessages)'>
            @csrf

            <div class="styledesk_modal__body space-y-4">
                <p class="text-[13px] text-sub">{{ __('forms.new.hint') }}</p>

                <x-text-field name="name" :label="__('forms.new.name')" required
                              :maxlength="config('forms.limits.name')"
                              :rules="'required|max:'.config('forms.limits.name')"
                              :placeholder="__('forms.new.name_placeholder')" />

                {{-- Searchable, because ten types is a list somebody reads
                     rather than scans. A native select underneath, so the
                     control works before the script that dresses it runs. --}}
                <div>
                    <label for="formType" class="block text-[13px] font-medium text-ink mb-1.5">
                        {{ __('forms.new.type') }}
                    </label>
                    <select id="formType" name="type" class="sd-input" required
                            data-rules="required"
                            data-combo data-combo-options='@json($typeCombo)'>
                        {{-- Starts unchosen. Defaulting to the first kind
                             would have every form somebody forgot to set
                             filed as an intake form, silently and
                             plausibly. --}}
                        <option value="">{{ __('forms.new.choose_type') }}</option>
                        @foreach ($types as $type)
                            <option value="{{ $type }}" @selected(old('type') === $type)>{{ __('forms.types.'.$type) }}</option>
                        @endforeach
                    </select>

                    <p data-error-for="formType" role="alert" class="mt-1.5 text-[12px] text-danger" hidden></p>
                </div>

                <div>
                    <label for="formCategory" class="block text-[13px] font-medium text-ink mb-1.5">
                        {{ __('forms.new.category') }}
                        <span class="text-faint font-normal">{{ __('common.optional') }}</span>
                    </label>
                    <select id="formCategory" name="category_id" class="sd-input"
                            data-combo data-combo-options='@json($categoryCombo)'>
                        <option value="">{{ __('forms.uncategorised') }}</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}" @selected((string) old('category_id') === (string) $category->id)>{{ $category->label() }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="formDescription" class="block text-[13px] font-medium text-ink mb-1.5">
                        {{ __('forms.new.description') }}
                        <span class="text-faint font-normal">{{ __('common.optional') }}</span>
                    </label>
                    <textarea id="formDescription" name="internal_description" rows="2" class="sd-input !h-auto py-2.5"
                              data-rules="max:{{ config('forms.limits.internal_description') }}"
                              maxlength="{{ config('forms.limits.internal_description') }}">{{ old('internal_description') }}</textarea>

                    <p class="mt-1.5 text-[12px] text-sub">{{ __('forms.new.description_hint') }}</p>
                    <p data-error-for="formDescription" role="alert" class="mt-1.5 text-[12px] text-danger" hidden></p>
                </div>

                {{-- Two answers, so they are both on screen with what each
                     one means. A dropdown would hide the difference that
                     decides it. --}}
                <fieldset>
                    <legend class="block text-[13px] font-medium text-ink mb-1.5">{{ __('forms.new.layout') }}</legend>

                    <div class="grid gap-2 sm:grid-cols-2">
                        @foreach ($layouts as $layout)
                            <x-choice type="radio" name="layout" :value="$layout"
                                      :label="__('forms.layouts.'.$layout)"
                                      :hint="__('forms.layout_hints.'.$layout)"
                                      :checked="old('layout', 'classic') === $layout" />
                        @endforeach
                    </div>
                </fieldset>

                <p class="text-[12px] text-sub leading-relaxed bg-hover rounded-lg px-3 py-2">
                    {{ __('forms.new.status_note') }}
                </p>
            </div>

            <div class="styledesk_modalfoot">
                {{-- Disabled the moment it is pressed, with the waiting said
                     rather than implied: two presses on a slow connection is
                     two forms, and the second is one somebody has to find and
                     archive. --}}
                <button type="submit" data-form-submit
                        data-busy-label="{{ __('forms.new.creating') }}"
                        class="h-9 px-4 rounded-lg bg-brand hover:bg-brand-dark text-white text-[13px] font-semibold transition-colors disabled:opacity-60">
                    {{ __('forms.new.submit') }}
                </button>

                <button type="button" class="styledesk_action" data-form-close>{{ __('forms.new.cancel') }}</button>
            </div>
        </form>
    </div>
</div>
