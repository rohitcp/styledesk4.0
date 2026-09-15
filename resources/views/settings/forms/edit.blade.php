@extends('layouts.app')

@section('title', $form->name)

{{--
    One form.

    Where creating a form lands, and where its questions will be built. What
    is here today is everything about the form that is not a question — live,
    saved and used, rather than a placeholder standing in for the builder.

    The toolbar is the builder's: Back, the form's name, its status, and the
    actions that change it. The panels either side of the canvas arrive with
    the fields they are for; a left rail of field types that cannot be dragged
    anywhere would be furniture.
--}}

@section('content')
  <main class="w-full px-4 sm:px-5 lg:px-6 pt-5 sm:pt-6 pb-[200px]">
    <div class="styledesk_form">

      <nav class="text-[13px] text-sub" aria-label="Breadcrumb">
        <a href="{{ route('settings.index') }}" class="hover:text-ink transition-colors">{{ __('navigation.app_settings') }}</a>
        <span class="mx-1.5 text-faint">/</span>
        <a href="{{ route('settings.forms.index') }}" class="hover:text-ink transition-colors">{{ __('forms.title') }}</a>
        <span class="mx-1.5 text-faint">/</span>
        <span class="text-ink">{{ $form->name }}</span>
      </nav>

      @php
          $statusCombo = [
              'search' => false,
          ];

          $typeCombo = [
              'searchLabel' => __('forms.new.search_type'),
              'searchPlaceholder' => __('common.search'),
          ];

          $categoryCombo = [
              'searchLabel' => __('forms.new.search_category'),
              'searchPlaceholder' => __('common.search'),
          ];

          $badge = match ($form->status) {
              \App\Models\Form::STATUS_ACTIVE => 'styledesk_badge--active',
              \App\Models\Form::STATUS_DRAFT => 'styledesk_badge--setup',
              default => 'styledesk_badge--soon',
          };
      @endphp

      <div class="mt-3 flex flex-wrap items-start gap-4">
        <div class="min-w-0 flex-1">
          <div class="flex flex-wrap items-center gap-2.5">
            <h1 class="text-[24px] sm:text-[28px] font-bold text-head tracking-tight">{{ $form->name }}</h1>
            <span class="styledesk_badge {{ $badge }}">{{ $form->statusLabel() }}</span>
          </div>

          <p class="text-[13px] text-sub mt-1.5">
            {{ $form->typeLabel() }}
            @if ($form->currentVersion)
              <span class="mx-1.5 text-faint">·</span>
              {{ __('forms.edit.version', ['number' => $form->currentVersion->version]) }}
            @endif
          </p>
        </div>

        <div class="shrink-0 flex items-center gap-2">
          <a href="{{ route('settings.forms.index') }}" class="styledesk_action">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M14 6l-6 6 6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
            {{ __('forms.edit.back') }}
          </a>
        </div>
      </div>

      {{-- Said plainly, because a screen that looks like it should have a
           question builder on it and does not is a screen somebody reports as
           broken. Naming what is coming is the honest version of that. --}}
      <div class="mt-5 sd-card p-4 bg-hover">
        <p class="text-[13px] text-sub leading-relaxed">{{ __('forms.edit.builder_next') }}</p>
      </div>

      @if ($submissionCount > 0)
        {{-- What makes an edit consequential. Changing the questions once
             anybody has answered them opens a new version rather than
             rewriting this one, and the reader should know that before they
             start rather than after. --}}
        <p class="mt-3 text-[13px] text-sub">
          {{ __('forms.edit.submissions_note', ['count' => $submissionCount]) }}
        </p>
      @endif

      <form method="POST" action="{{ route('settings.forms.update', $form) }}" class="mt-5 space-y-5">
        @csrf
        @method('PATCH')

        {{-- Look but do not touch, for a reader holding the view permission
             and not the manage one. Refused by the browser rather than by a
             403 after the form has been filled in.

             `space-y-5` as well as `contents`: the outer rule is a
             `> * + *` selector, so it only ever sees this fieldset and the
             cards inside it would come out flush against each other. --}}
        <fieldset @disabled(! $canEdit) class="contents space-y-5">

          <section class="sd-card p-5">
            <h2 class="text-[15px] font-semibold text-head">{{ __('forms.edit.general') }}</h2>
            <p class="text-[13px] text-sub mt-1">{{ __('forms.edit.general_hint') }}</p>

            <div class="mt-4 grid gap-4 sm:grid-cols-2">
              <x-text-field name="name" :label="__('forms.new.name')" required
                            :maxlength="config('forms.limits.name')"
                            :value="$form->name" />

              <div>
                <label for="type" class="block text-[13px] font-medium text-ink mb-1.5">{{ __('forms.new.type') }}</label>
                <select id="type" name="type" class="sd-input" required
                        data-combo data-combo-options='@json($typeCombo)'>
                  @foreach ($types as $type)
                    <option value="{{ $type }}" @selected(old('type', $form->type) === $type)>{{ __('forms.types.'.$type) }}</option>
                  @endforeach
                </select>
              </div>

              <div>
                <label for="category_id" class="block text-[13px] font-medium text-ink mb-1.5">{{ __('forms.new.category') }}</label>
                <select id="category_id" name="category_id" class="sd-input"
                        data-combo data-combo-options='@json($categoryCombo)'>
                  <option value="">{{ __('forms.uncategorised') }}</option>
                  @foreach ($categories as $category)
                    <option value="{{ $category->id }}" @selected((string) old('category_id', $form->category_id) === (string) $category->id)>{{ $category->label() }}</option>
                  @endforeach
                </select>
              </div>

              <div class="sm:col-span-2">
                <label for="internal_description" class="block text-[13px] font-medium text-ink mb-1.5">
                  {{ __('forms.new.description') }}
                  <span class="text-faint font-normal">{{ __('common.optional') }}</span>
                </label>
                <textarea id="internal_description" name="internal_description" rows="2" class="sd-input !h-auto py-2.5"
                          maxlength="{{ config('forms.limits.internal_description') }}">{{ old('internal_description', $form->internal_description) }}</textarea>
                <p class="mt-1.5 text-[12px] text-sub">{{ __('forms.new.description_hint') }}</p>
              </div>
            </div>
          </section>

          {{-- Live or paused, and the address clients open.

               Status is a field here only once there is something to send and
               the reader may send it: going live holds forms.publish, and a
               dropdown on the settings screen must not be a way around that.
               Otherwise it is shown as the fact it is. --}}
          <section class="sd-card p-5">
            <h2 class="text-[15px] font-semibold text-head">{{ __('forms.edit.status') }}</h2>

            <div class="mt-3">
              @if ($canSetStatus)
                <div class="max-w-[260px]">
                  <label for="status" class="sr-only">{{ __('forms.edit.status') }}</label>
                  <select id="status" name="status" class="sd-input"
                          data-combo data-combo-options='@json($statusCombo)'>
                    @foreach ([\App\Models\Form::STATUS_ACTIVE, \App\Models\Form::STATUS_INACTIVE] as $option)
                      <option value="{{ $option }}" @selected(old('status', $form->status) === $option)>
                        {{ __('forms.statuses.'.$option) }}
                      </option>
                    @endforeach
                  </select>
                </div>
              @else
                <span class="styledesk_badge {{ $badge }}">{{ $form->statusLabel() }}</span>
                <p class="mt-2 text-[13px] text-sub">{{ __('forms.edit.status_locked') }}</p>
              @endif
            </div>

            <div class="mt-4 pt-4 border-t border-line">
              <p class="text-[13px] font-medium text-ink">{{ __('forms.edit.public_url') }}</p>

              @if ($publicUrl)
                <p class="text-[12.5px] text-sub mt-1 leading-relaxed">{{ __('forms.edit.public_url_hint') }}</p>

                <div class="mt-2 flex flex-wrap items-center gap-2">
                  {{-- Readonly, never disabled: a disabled field is left out
                       of the submitted data and cannot be selected to copy
                       from either. --}}
                  <input type="text" readonly value="{{ $publicUrl }}" data-form-url
                         class="sd-input is-readonly !w-auto min-w-[320px] flex-1 text-[12.5px]"
                         aria-label="{{ __('forms.edit.public_url') }}">

                  <button type="button" class="styledesk_action shrink-0" data-copy-url
                          data-copied="{{ __('forms.edit.copied') }}">
                    {{ __('forms.edit.copy') }}
                  </button>
                </div>

                {{-- The page behind this link exists. What can still stop it
                     opening is the web server: tenant subdomains have to be
                     served by it, and a link that fails for that reason looks
                     exactly like a broken feature. Said here rather than
                     discovered by handing it to a client. --}}
                <p class="mt-2 text-[12px] text-sub bg-hover rounded-lg px-3 py-2">
                  {{ __('forms.edit.not_live_yet') }}
                </p>
              @else
                <p class="text-[13px] text-sub mt-1">{{ __('forms.edit.public_url_pending') }}</p>
              @endif
            </div>
          </section>

          <section class="sd-card p-5">
            <h2 class="text-[15px] font-semibold text-head">{{ __('forms.edit.presentation') }}</h2>

            <fieldset class="mt-3">
              <legend class="sr-only">{{ __('forms.new.layout') }}</legend>

              <div class="grid gap-2 sm:grid-cols-2">
                @foreach ($layouts as $layout)
                  <x-choice type="radio" name="layout" :value="$layout"
                            :label="__('forms.layouts.'.$layout)"
                            :hint="__('forms.layout_hints.'.$layout)"
                            :checked="old('layout', $form->layout) === $layout" />
                @endforeach
              </div>
            </fieldset>
          </section>

          @if ($canEdit)
            <div>
              <button type="submit" class="h-9 px-4 rounded-lg bg-brand hover:bg-brand-dark text-white text-[13px] font-semibold transition-colors">
                {{ __('common.save_changes') }}
              </button>
            </div>
          @endif
        </fieldset>
      </form>
    </div>
  </main>
@endsection

@push('scripts')
  <script>
    /* Copy the form's address. The field is readonly rather than disabled, so
       somebody without the clipboard permission can still select it by hand. */
    (function () {
      var button = document.querySelector('[data-copy-url]');
      var field = document.querySelector('[data-form-url]');

      if (!button || !field) return;

      button.addEventListener('click', function () {
        var said = button.textContent;

        function confirmed() {
          button.textContent = button.getAttribute('data-copied');
          window.setTimeout(function () { button.textContent = said; }, 1600);
        }

        if (navigator.clipboard && navigator.clipboard.writeText) {
          navigator.clipboard.writeText(field.value).then(confirmed, function () {
            field.select();
          });

          return;
        }

        /* No clipboard API — older Safari, an insecure origin. Selecting the
           text is the honest fallback: the reader presses copy themselves. */
        field.select();
      });
    }());
  </script>
@endpush
