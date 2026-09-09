{{--
    The template editor.

    Preview on the left, settings on the right, per the recommended layout. The
    preview is the real email rendered by the server from whatever is in the
    form — not an approximation drawn in the browser, which would drift from
    the thing clients actually receive the first time either changed.

    Deliberately not a drag-and-drop builder. The owner writes words and
    chooses which blocks appear; StyleDesk owns the layout, so a business
    cannot ship a broken email and one fix reaches all of them.
--}}
@extends('layouts.app')

@section('title', $creating ? __('email_templates.editor.new') : $template->name)

@section('content')
  <main class="w-full px-4 sm:px-5 lg:px-6 pt-5 sm:pt-6 pb-[120px]">

    <nav class="text-[13px] text-sub" aria-label="Breadcrumb">
      <a href="{{ route('settings.index') }}" class="hover:text-ink transition-colors">{{ __('navigation.app_settings') }}</a>
      <span class="mx-1.5 text-faint">/</span>
      <a href="{{ route('settings.email-templates.index') }}" class="hover:text-ink transition-colors">{{ __('email_templates.title') }}</a>
      <span class="mx-1.5 text-faint">/</span>
      <span class="text-ink">{{ $creating ? __('email_templates.editor.new') : $template->name }}</span>
    </nav>

    <div class="mt-3 flex flex-wrap items-start gap-4">
      <div class="min-w-0 flex-1">
        <h1 class="text-[24px] sm:text-[28px] font-bold text-head tracking-tight">
          {{ $creating ? __('email_templates.editor.new') : $template->name }}
        </h1>

        @if (! $creating && $template->isTransactional())
          <p class="text-[13px] text-sub mt-2">
            {{ __('email_templates.editor.fires_on') }}
            <strong class="font-semibold text-head">{{ $template->triggerLabel() }}</strong>
          </p>
        @elseif (! $creating)
          <p class="text-[13px] text-sub mt-2">{{ __('email_templates.editor.chosen_by_team') }}</p>
        @endif
      </div>

      <div class="shrink-0 flex items-center gap-2.5">
        @if (! $creating)
          <form method="POST" action="{{ route('settings.email-templates.duplicate', $template->key) }}">
            @csrf
            <button type="submit" class="styledesk_action">{{ __('email_templates.editor.duplicate') }}</button>
          </form>
        @endif

        {{-- Available while creating as well as editing: what it sends is what
             is on the screen, so there is nothing that has to be saved first. --}}
        <button type="button" data-test-open class="styledesk_action">
          {{ __('email_templates.editor.send_test') }}
        </button>

        <a href="{{ route('settings.email-templates.index') }}" class="styledesk_action">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M14 6l-6 6 6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
          {{ __('common.back') }}
        </a>
      </div>
    </div>

    @if (session('status'))
      <div class="sd-alert sd-alert--info mt-5" role="status"><p class="min-w-0">{{ session('status') }}</p></div>
    @endif

    @if ($errors->any())
      <div class="sd-alert sd-alert--danger mt-5" role="alert"><p class="min-w-0">{{ $errors->first() }}</p></div>
    @endif

    <form method="POST"
          action="{{ $creating ? route('settings.email-templates.store') : route('settings.email-templates.update', $template->key) }}"
          data-template-form
          data-preview-url="{{ $creating ? route('settings.email-templates.preview-draft') : route('settings.email-templates.preview', $template->key) }}"
          data-test-url="{{ $creating ? route('settings.email-templates.test-draft') : route('settings.email-templates.test', $template->key) }}"
          class="mt-5 grid lg:grid-cols-[minmax(0,1fr)_minmax(0,420px)] gap-5 items-start">
      @csrf
      @unless ($creating) @method('PATCH') @endunless

      {{-- ------------------------------------------------------- preview --}}
      {{-- Left, and it stays put while the form on the right scrolls: the
           whole point of the split is watching the email change as you type,
           and a preview that scrolls away defeats it. --}}
      <section class="bg-white border border-line rounded-card overflow-hidden lg:sticky lg:top-4 order-1">
        <div class="flex items-center justify-between gap-3 px-4 py-3 border-b border-line">
          <h2 class="text-[13px] font-semibold text-head">
            {{ __('email_templates.editor.preview') }}
            <span data-preview-busy hidden class="ml-1.5 text-[11.5px] font-normal text-sub">
              {{ __('email_templates.editor.updating') }}
            </span>
          </h2>

          {{-- Desktop and mobile are the same rendered email at two widths —
               the frame changes, the HTML does not, which is the only honest
               way to preview a responsive email. --}}
          <div class="flex items-center gap-1.5">
            <button type="button" data-preview-width="100%" class="styledesk_action styledesk_action--sm is-active">
              {{ __('email_templates.editor.desktop') }}
            </button>
            <button type="button" data-preview-width="390px" class="styledesk_action styledesk_action--sm">
              {{ __('email_templates.editor.mobile') }}
            </button>
          </div>
        </div>

        <div class="bg-[#f5f5f5] p-4">
          <iframe data-preview-frame title="{{ __('email_templates.editor.preview') }}"
                  class="block mx-auto w-full h-[720px] bg-white border-0 rounded-lg transition-[width] duration-200"></iframe>
        </div>
      </section>

      {{-- ------------------------------------------------------ settings --}}
      <div class="space-y-4 order-2">

        <section class="bg-white border border-line rounded-card p-5 space-y-4">
          <div>
            <label for="name" class="block text-[13px] font-medium text-ink mb-1.5">{{ __('email_templates.editor.name') }}</label>
            <input id="name" name="name" type="text" class="sd-input" required maxlength="{{ $limits['name'] }}"
                   value="{{ old('name', $template->name) }}">
          </div>

          <div>
            <div class="flex items-center justify-between gap-3 mb-1.5">
              <label for="subject" class="block text-[13px] font-medium text-ink">{{ __('email_templates.editor.subject') }}</label>
              {{-- On every field that takes them, not only the message: a
                   subject line is where the business name is most wanted, and
                   asking somebody to type it from memory is asking for a typo
                   that reaches every client. --}}
              <button type="button" data-variable-menu-open data-for="subject"
                      class="text-[12.5px] font-semibold text-link hover:underline">
                + {{ __('email_templates.editor.insert_variable') }}
              </button>
            </div>
            <input id="subject" name="subject" type="text" class="sd-input" required maxlength="{{ $limits['subject'] }}"
                   data-variable-target value="{{ old('subject', $template->subject) }}">
            <p class="mt-1.5 text-[12px] text-sub">{{ __('email_templates.editor.subject_hint') }}</p>
          </div>

          <div>
            <div class="flex items-center justify-between gap-3 mb-1.5">
              <label for="heading" class="block text-[13px] font-medium text-ink">{{ __('email_templates.editor.heading') }}</label>
              <button type="button" data-variable-menu-open data-for="heading"
                      class="text-[12.5px] font-semibold text-link hover:underline">
                + {{ __('email_templates.editor.insert_variable') }}
              </button>
            </div>
            <input id="heading" name="heading" type="text" class="sd-input" maxlength="{{ $limits['heading'] }}"
                   data-variable-target value="{{ old('heading', $template->heading) }}">
          </div>

          <div>
            <div class="flex items-center justify-between gap-3 mb-1.5">
              <label for="intro" class="block text-[13px] font-medium text-ink">{{ __('email_templates.editor.intro') }}</label>

              {{-- The menu offers only variables the renderer can resolve —
                   one catalogue for both, so an inserted variable always
                   works. --}}
              <button type="button" data-variable-menu-open data-for="intro"
                      class="text-[12.5px] font-semibold text-link hover:underline">
                + {{ __('email_templates.editor.insert_variable') }}
              </button>
            </div>
            <textarea id="intro" name="intro" rows="8" class="sd-input" maxlength="{{ $limits['body'] }}"
                      data-variable-target>{{ old('intro', $template->intro) }}</textarea>
          </div>

          <div>
            <label for="supporting_message" class="block text-[13px] font-medium text-ink mb-1.5">
              {{ __('email_templates.editor.supporting') }}
            </label>
            <textarea id="supporting_message" name="supporting_message" rows="4" class="sd-input"
                      maxlength="{{ $limits['body'] }}" data-variable-target>{{ old('supporting_message', $template->supporting_message) }}</textarea>
            <p class="mt-1.5 text-[12px] text-sub">{{ __('email_templates.editor.supporting_hint') }}</p>
          </div>
        </section>

        {{-- ---------------------------------------------------- the blocks --}}
        <section class="bg-white border border-line rounded-card p-5">
          <h2 class="text-[15px] font-semibold text-head">{{ __('email_templates.editor.blocks') }}</h2>
          <p class="text-[13px] text-sub mt-1">{{ __('email_templates.editor.blocks_hint') }}</p>

          <div class="mt-3 space-y-2">
            @foreach ($blocks as $block => $config)
              {{-- Configured in its own card below, because choosing a
                   campaign is more than a switch. --}}
              @continue($block === 'coupon')
              @php $always = $config['always'] ?? false; @endphp
              <label @class(['flex items-center gap-2.5', 'opacity-50' => $always])>
                <input type="checkbox" name="blocks[{{ $block }}]" value="1" class="sd-check"
                       data-preview-input
                       @checked($template->shows($block)) @disabled($always)>
                <span class="text-[13px] text-ink">{{ __('email_templates.blocks.'.$block) }}</span>
                @if ($always)
                  <span class="text-[11.5px] text-faint">{{ __('email_templates.editor.always') }}</span>
                @endif
              </label>
            @endforeach
          </div>
        </section>

        {{-- --------------------------------------------- the details card --}}
        {{-- The house MultiSelect rather than a grid of checkboxes: nine
             tick-boxes is a wall, and every other list of this shape in the
             product is a combo. It posts the chosen rows as a list; the
             controller turns that back into the shown/hidden map the row
             stores. --}}
        <section class="bg-white border border-line rounded-card p-5">
          <h2 class="text-[15px] font-semibold text-head">{{ __('email_templates.editor.detail_fields') }}</h2>
          <p class="text-[13px] text-sub mt-1">{{ __('email_templates.editor.detail_fields_hint') }}</p>

          {{-- The props are built above rather than written inline in the
               directive: Blade counts brackets rather than reading PHP, so a
               multi-line encode with commas inside it does not parse and the
               tail prints on the page.

               And this comment deliberately names no directive. Blade compiles
               directives inside comments too — a comment mentioning one opens a
               block that the next real close tag ends, swallowing everything
               between. That is what hid this control. --}}
          @php
              $fieldProps = [
                  'options' => $detailFields,
                  'modelValue' => collect($detailFields)
                      ->keys()
                      ->filter(fn (string $field) => $template->showsDetail($field))
                      ->values()
                      ->all(),
                  'name' => 'detail_fields',
                  'placeholder' => __('email_templates.editor.detail_fields_placeholder'),
                  'ariaLabel' => __('email_templates.editor.detail_fields'),
                  'showPrimary' => false,
              ];
          @endphp

          <div class="mt-3" data-vue-component="MultiSelect" data-props='@json($fieldProps)'></div>

          {{-- What each chosen row will actually contain. Choosing between
               "Date" and "Reference" asks the reader to imagine the value;
               showing it removes the guess. Sample data, the same the preview
               draws with. --}}
          <dl class="mt-3 rounded-card border border-line bg-[#fbfbfc] divide-y divide-line text-[13px]">
            @foreach ($detailFields as $field => $label)
              <div data-detail-sample="{{ $field }}"
                   @class(['flex items-baseline gap-3 px-3 py-2', 'hidden' => ! $template->showsDetail($field)])>
                <dt class="text-sub shrink-0 w-24">{{ $label }}</dt>
                <dd class="min-w-0 flex-1 font-medium text-head truncate">{{ $detailSamples[$field] ?? '' }}</dd>

                {{-- The same value, in the wording. Somebody who has just read
                     "Service · Swedish Massage" and wants it in a sentence
                     should not have to go and find the token in a menu. --}}
                @if (isset($detailTokens[$field]))
                  <button type="button" class="shrink-0 text-[12px] font-semibold text-link hover:underline"
                          data-insert-token="{{ $detailTokens[$field] }}"
                          title="{{ __('email_templates.editor.insert_into_message', ['token' => $detailTokens[$field]]) }}">
                    {{ __('email_templates.editor.insert') }}
                  </button>
                @endif
              </div>
            @endforeach

            <p data-detail-empty @class(['px-3 py-2 text-[12.5px] text-sub', 'hidden' => count(array_filter($detailFields, fn ($l, $f) => $template->showsDetail($f), ARRAY_FILTER_USE_BOTH))])>
              {{ __('email_templates.editor.no_rows') }}
            </p>
          </dl>

          {{-- Revealed by choosing Service above. A template that names
               services describes those rather than the booking's — aftercare
               for a Swedish Massage says Swedish Massage whichever appointment
               it was sent about. Left empty it follows the booking, which is
               what a confirmation wants. --}}
          <div data-service-picker @class(['mt-4', 'hidden' => ! $template->showsDetail('service')])>
            <p class="text-[13px] font-medium text-ink">{{ __('email_templates.editor.services') }}</p>
            <p class="text-[12px] text-sub mt-0.5 mb-2">{{ __('email_templates.editor.services_hint') }}</p>

            @if (count($services))
              @php
                  $serviceProps = [
                      'options' => $services,
                      'modelValue' => $template->serviceIds(),
                      'name' => 'detail_services',
                      'placeholder' => __('email_templates.editor.services_placeholder'),
                      'ariaLabel' => __('email_templates.editor.services'),
                      'showPrimary' => false,
                  ];
              @endphp

              <div data-vue-component="MultiSelect" data-props='@json($serviceProps)'></div>
            @else
              <p class="text-[13px] text-sub">{{ __('email_templates.editor.no_services') }}</p>
            @endif
          </div>
        </section>

        {{-- ---------------------------------------------------- the coupon --}}
        <section class="bg-white border border-line rounded-card p-5 space-y-4">
          <div>
            <h2 class="text-[15px] font-semibold text-head">{{ __('email_templates.coupon.title') }}</h2>
            <p class="text-[13px] text-sub mt-1">{{ __('email_templates.coupon.hint') }}</p>
          </div>

          <label class="flex items-center gap-2.5">
            <input type="checkbox" name="blocks[coupon]" value="1" class="sd-check"
                   data-preview-input @checked($template->shows('coupon'))>
            <span class="text-[13px] font-medium text-ink">{{ __('email_templates.coupon.show') }}</span>
          </label>

          <div>
            <label for="promotion_id" class="block text-[13px] font-medium text-ink mb-1.5">
              {{ __('email_templates.coupon.campaign') }}
            </label>

            @if ($coupons->isEmpty())
              {{-- Nothing to offer. Said plainly, with the way to fix it,
                   rather than an empty dropdown that looks broken. --}}
              <p class="text-[13px] text-sub">
                {{ __('email_templates.coupon.none') }}
                <a href="{{ route('promotions.index') }}" class="font-semibold text-link hover:underline">
                  {{ __('email_templates.coupon.manage') }}
                </a>
              </p>
            @else
              <select id="promotion_id" name="promotion_id" class="sd-input" data-preview-input>
                <option value="">{{ __('email_templates.coupon.no_campaign') }}</option>
                @foreach ($coupons as $coupon)
                  <option value="{{ $coupon->id }}" @selected((int) old('promotion_id', $template->promotion_id) === $coupon->id)>
                    {{ $coupon->name }} — {{ $coupon->code }} ({{ $coupon->discountLabel() }})
                  </option>
                @endforeach
              </select>
              <p class="mt-1.5 text-[12px] text-sub">{{ __('email_templates.coupon.expiry_note') }}</p>
            @endif
          </div>
        </section>

        {{-- ------------------------------------------------------- the CTA --}}
        <section class="bg-white border border-line rounded-card p-5 space-y-4">
          <label class="flex items-center gap-2.5">
            <input type="checkbox" name="cta_enabled" value="1" class="sd-check"
                   data-preview-input @checked(old('cta_enabled', $template->cta_enabled))>
            <span class="text-[13px] font-medium text-ink">{{ __('email_templates.editor.show_button') }}</span>
          </label>

          <div>
            <label for="cta_label" class="block text-[13px] font-medium text-ink mb-1.5">{{ __('email_templates.editor.button_text') }}</label>
            <input id="cta_label" name="cta_label" type="text" class="sd-input" maxlength="60"
                   data-preview-input value="{{ old('cta_label', $template->cta_label) }}">
          </div>

          <div>
            <label for="cta_action" class="block text-[13px] font-medium text-ink mb-1.5">{{ __('email_templates.editor.button_action') }}</label>
            <select id="cta_action" name="cta_action" class="sd-input" data-preview-input>
              <option value="">{{ __('email_templates.editor.no_action') }}</option>
              @foreach ($ctaActions as $action)
                <option value="{{ $action }}" @selected(old('cta_action', $template->cta_action) === $action)>
                  {{ __('email_templates.cta_actions.'.$action) }}
                </option>
              @endforeach
            </select>
            <p class="mt-1.5 text-[12px] text-sub">{{ __('email_templates.editor.button_action_hint') }}</p>
          </div>
        </section>

        <div class="flex flex-wrap items-center gap-3">
          <button type="submit" class="h-10 px-4 rounded-lg bg-brand hover:bg-brand-dark text-white text-[13px] font-semibold transition-colors">
            {{ $creating ? __('email_templates.editor.create') : __('email_templates.editor.save') }}
          </button>

          <a href="{{ route('settings.email-templates.index') }}" class="styledesk_action">{{ __('common.cancel') }}</a>
        </div>
      </div>
    </form>

    {{-- ----------------------------------------------------- test email --}}
    {{-- A dialog rather than a form at the foot of the page: sending a test is
         something you do while looking at the preview, and a control three
         screens down is one nobody finds. It posts the editor's current values,
         so the email that arrives is the one on screen — not the saved
         version, which is the one thing a test must never send. --}}
    <dialog data-test-dialog class="m-auto w-[min(92vw,26rem)] rounded-xl border border-line bg-white p-0 backdrop:bg-black/40">
      <div class="px-5 pt-5">
        <h2 class="text-[16px] font-bold text-head">{{ __('email_templates.editor.send_test') }}</h2>
        <p class="text-[13px] text-sub mt-1.5">{{ __('email_templates.editor.send_test_hint') }}</p>
      </div>

      <div class="px-5 pt-4">
        <label for="test_email" class="block text-[13px] font-medium text-ink mb-1.5">
          {{ __('email_templates.editor.test_address') }}
        </label>
        <input id="test_email" type="email" class="sd-input" required value="{{ auth()->user()->email }}">

        <p data-test-error hidden class="mt-2 text-[12.5px] text-danger" role="alert"></p>
        <p data-test-done hidden class="mt-2 text-[12.5px] text-brand font-semibold" role="status"></p>
      </div>

      <div class="flex items-center justify-end gap-2.5 px-5 py-4 mt-4 border-t border-line bg-[#fbfbfc]">
        <button type="button" data-test-close
                class="h-10 px-4 rounded-lg border border-line bg-white hover:bg-black/[0.03] text-[13px] font-semibold text-head transition-colors">
          {{ __('common.cancel') }}
        </button>
        <button type="button" data-test-send
                class="h-10 px-4 rounded-lg bg-brand hover:bg-brand-dark text-white text-[13px] font-semibold transition-colors disabled:opacity-50">
          {{ __('email_templates.editor.send_test') }}
        </button>
      </div>
    </dialog>

    {{-- ------------------------------------------------- variable picker --}}
    {{-- `m-auto` restores what a <dialog> does by default: centre itself in
         the viewport. Without it the browser falls back to the top of the
         page, which is where this was opening. --}}
    <dialog data-variable-menu
            class="m-auto w-[min(92vw,30rem)] rounded-xl border border-line bg-white p-0 backdrop:bg-black/40">
      <div class="flex items-center justify-between gap-3 px-5 py-4 border-b border-line">
        <h2 class="text-[15px] font-bold text-head">{{ __('email_templates.editor.insert_variable') }}</h2>
        <button type="button" data-variable-menu-close class="sd-iconbtn grid place-items-center" aria-label="{{ __('common.cancel') }}">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"/></svg>
        </button>
      </div>

      <div class="px-5 py-3 border-b border-line">
        <input type="search" data-variable-search class="sd-input"
               placeholder="{{ __('email_templates.editor.search_variables') }}">
      </div>

      {{-- Step two. Choosing a Service variable asks which service — the
           generic token describes whatever the booking is for, and sometimes
           an owner means one particular treatment by name and price. --}}
      <div data-service-step hidden class="max-h-[50vh] overflow-y-auto styledesk_scroll p-5">
        <button type="button" data-service-step-back class="text-[12.5px] font-semibold text-link hover:underline">
          ← {{ __('email_templates.editor.all_variables') }}
        </button>

        <p class="mt-3 text-[12.5px] text-sub" data-service-step-hint></p>

        <div class="mt-3 space-y-1.5">
          {{-- "Whatever the booking is for" stays first and is the normal
               answer; naming one is the exception. --}}
          <button type="button" data-service-generic
                  class="w-full text-left rounded-lg border border-line px-3 py-2 hover:border-brand/60 hover:bg-brand/[0.04] transition-colors">
            <span class="block text-[13px] font-medium text-head">{{ __('email_templates.editor.any_service') }}</span>
            <span class="block text-[11.5px] text-faint">{{ __('email_templates.editor.any_service_hint') }}</span>
          </button>

          @foreach ($services as $id => $name)
            <button type="button" data-service-option="{{ $id }}"
                    data-search="{{ mb_strtolower($name) }}"
                    class="w-full text-left rounded-lg border border-line px-3 py-2 hover:border-brand/60 hover:bg-brand/[0.04] transition-colors">
              <span class="block text-[13px] font-medium text-head">{{ $name }}</span>
            </button>
          @endforeach
        </div>
      </div>

      <div data-variable-step class="max-h-[50vh] overflow-y-auto styledesk_scroll p-5 space-y-4">
        @foreach ($variables as $group)
          <div data-variable-group>
            <p class="text-[11.5px] font-semibold uppercase tracking-wide text-sub">{{ $group['label'] }}</p>
            <div class="mt-2 grid grid-cols-2 gap-1.5">
              @foreach ($group['variables'] as $variable)
                <button type="button" data-variable="{{ $variable['token'] }}"
                        data-search="{{ mb_strtolower($group['label'].' '.$variable['label'].' '.$variable['token']) }}"
                        class="text-left rounded-lg border border-line px-2.5 py-1.5 hover:border-brand/60 hover:bg-brand/[0.04] transition-colors">
                  <span class="block text-[12.5px] font-medium text-head">{{ $variable['label'] }}</span>
                  <span class="block text-[11px] text-faint font-mono truncate">{{ $variable['token'] }}</span>
                </button>
              @endforeach
            </div>
          </div>
        @endforeach
      </div>
    </dialog>
  </main>
@endsection

@push('scripts')
  <script>
    (function () {
      const form = document.querySelector('[data-template-form]');
      if (!form) return;

      /* ------------------------------------------------------- preview -- */
      const frame = form.querySelector('[data-preview-frame]');
      const previewUrl = form.dataset.previewUrl;
      let pending = null;

      /*
       * Rendered by the server from the current form values.
       *
       * Debounced, because this is a round trip per keystroke otherwise — and
       * the same endpoint the real email goes through, so what is shown is
       * what will arrive rather than a second implementation's guess at it.
       */
      function refresh() {
        if (!previewUrl || !frame) return;

        window.clearTimeout(pending);
        pending = window.setTimeout(async function () {
          const body = new FormData(form);
          body.set('_method', 'POST');

          const busy = form.querySelector('[data-preview-busy]');
          if (busy) busy.hidden = false;

          try {
            const response = await fetch(previewUrl, {
              method: 'POST',
              headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                Accept: 'text/html',
              },
              body,
            });

            if (response.ok) {
              frame.srcdoc = await response.text();
            }
          } catch (error) {
            /* A preview that cannot load leaves the last good one on screen,
               which is more use than a blank frame. */
          } finally {
            if (busy) busy.hidden = true;
          }
        }, 350);
      }

      form.addEventListener('input', refresh);
      form.addEventListener('change', refresh);
      refresh();

      /*
       * The service picker follows the Service row.
       *
       * MultiSelect posts hidden inputs rather than firing events on a field,
       * so neither the reveal nor the preview can wait for `change` — the DOM
       * is watched instead. One observer for both, because they answer the
       * same question: what does the form say right now.
       */
      const picker = form.querySelector('[data-service-picker]');

      function chosenDetailRows() {
        return Array.from(form.querySelectorAll('input[name="detail_fields[]"]'))
          .map(function (input) { return input.value; });
      }

      function syncServicePicker() {
        if (!picker) return;

        picker.classList.toggle('hidden', !chosenDetailRows().includes('service'));
      }

      /* The sample list shows only the rows that are on, so it reads as the
         card the email will draw rather than as a catalogue. */
      function syncDetailSamples() {
        const chosen = chosenDetailRows();

        form.querySelectorAll('[data-detail-sample]').forEach(function (row) {
          row.classList.toggle('hidden', !chosen.includes(row.dataset.detailSample));
        });

        const empty = form.querySelector('[data-detail-empty]');
        if (empty) empty.classList.toggle('hidden', chosen.length > 0);
      }

      new MutationObserver(function () {
        syncServicePicker();
        syncDetailSamples();
        refresh();
      }).observe(form, { childList: true, subtree: true });

      syncServicePicker();
      syncDetailSamples();

      form.querySelectorAll('[data-preview-width]').forEach(function (button) {
        button.addEventListener('click', function () {
          frame.style.width = button.dataset.previewWidth;
          form.querySelectorAll('[data-preview-width]').forEach(function (other) {
            other.classList.toggle('is-active', other === button);
          });
        });
      });

      /* ---------------------------------------------- variable picker -- */
      const dialog = document.querySelector('[data-variable-menu]');
      const search = dialog?.querySelector('[data-variable-search]');
      let target = null;

      /* Whichever field was last focused receives the variable — inserting
         into the subject when the cursor was in the body would be worse than
         not offering the menu at all. */
      form.querySelectorAll('[data-variable-target]').forEach(function (field) {
        field.addEventListener('focus', function () { target = field; });
      });

      /* Each opener names the field it fills, so the menu cannot drop a
         variable into whatever happened to be focused last. */
      document.querySelectorAll('[data-variable-menu-open]').forEach(function (opener) {
        opener.addEventListener('click', function () {
          target = form.querySelector('#' + opener.dataset.for) || target;
          showVariables();
          dialog?.showModal();
          search?.focus();
        });
      });

      dialog?.querySelector('[data-variable-menu-close]')?.addEventListener('click', function () {
        dialog.close();
      });

      search?.addEventListener('input', function () {
        const term = search.value.trim().toLowerCase();

        dialog.querySelectorAll('[data-variable]').forEach(function (button) {
          button.hidden = term !== '' && !button.dataset.search.includes(term);
        });

        dialog.querySelectorAll('[data-variable-group]').forEach(function (group) {
          group.hidden = !group.querySelector('[data-variable]:not([hidden])');
        });
      });

      /* The row's own insert button. It writes into whichever field was last
         focused, falling back to the message — which is where a sentence
         about a service almost always belongs. */
      form.querySelectorAll('[data-insert-token]').forEach(function (button) {
        button.addEventListener('click', function () {
          insertInto(target || form.querySelector('#intro'), button.dataset.insertToken);
        });
      });

      function insertInto(field, token) {
        if (!field) return;

        /* At the cursor, not appended: somebody who put the caret mid
           sentence meant it to go there. */
        const start = field.selectionStart ?? field.value.length;
        const end = field.selectionEnd ?? field.value.length;

        field.value = field.value.slice(0, start) + token + field.value.slice(end);
        field.selectionStart = field.selectionEnd = start + token.length;

        field.focus();
        refresh();
      }

      /* -------------------------------------------------- test email -- */
      const testDialog = document.querySelector('[data-test-dialog]');
      const testUrl = form.dataset.testUrl;

      document.querySelector('[data-test-open]')?.addEventListener('click', function () {
        if (!testDialog) return;

        /* Assigned through plain lookups, not `a?.b().c = x` — optional
           chaining is not a valid assignment target, and the parse error it
           throws kills this whole script, taking the live preview with it. */
        const failure = testDialog.querySelector('[data-test-error]');
        const done = testDialog.querySelector('[data-test-done]');

        failure.hidden = true;
        done.hidden = true;
        testDialog.showModal();
      });

      testDialog?.querySelector('[data-test-close]')?.addEventListener('click', function () {
        testDialog.close();
      });

      testDialog?.querySelector('[data-test-send]')?.addEventListener('click', async function (event) {
        const button = event.currentTarget;
        const address = testDialog.querySelector('#test_email');
        const failure = testDialog.querySelector('[data-test-error]');
        const done = testDialog.querySelector('[data-test-done]');

        failure.hidden = true;
        done.hidden = true;
        button.disabled = true;

        /* The whole editor plus the address, so what arrives is what is on
           screen. */
        const body = new FormData(form);
        body.set('_method', 'POST');
        body.set('email', address.value);

        try {
          const response = await fetch(testUrl, {
            method: 'POST',
            headers: {
              Accept: 'application/json',
              'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
            },
            body,
          });

          const json = await response.json().catch(function () { return {}; });

          if (!response.ok) {
            failure.textContent = Object.values(json?.errors ?? {}).flat()[0] || json?.message || '';
            failure.hidden = false;

            return;
          }

          done.textContent = json.message ?? '';
          done.hidden = false;
        } catch (error) {
          failure.textContent = @json(__('email_templates.editor.test_failed', ['reason' => '']));
          failure.hidden = false;
        } finally {
          button.disabled = false;
        }
      });

      /* ------------------------------------------------ service step -- */
      const variableStep = dialog?.querySelector('[data-variable-step]');
      const serviceStep = dialog?.querySelector('[data-service-step]');
      const serviceHint = dialog?.querySelector('[data-service-step-hint]');
      let pendingField = null;

      function showVariables() {
        if (!serviceStep) return;
        serviceStep.hidden = true;
        variableStep.hidden = false;
      }

      dialog?.querySelector('[data-service-step-back]')?.addEventListener('click', showVariables);

      function insertService(id) {
        /* A token naming one service by id, or the generic one that follows
           the booking. Both are resolved by the same renderer.

           `@{{` escapes the braces: Blade compiles {{ }} wherever it finds
           them — inside a JS string and inside this comment alike — and an
           unescaped one here is a PHP parse error in the compiled view. */
        insertInto(target, id ? `@{{service.${id}.${pendingField}}}` : `@{{service.${pendingField}}}`);
        dialog.close();
        showVariables();
      }

      dialog?.querySelector('[data-service-generic]')?.addEventListener('click', function () {
        insertService(null);
      });

      dialog?.querySelectorAll('[data-service-option]').forEach(function (option) {
        option.addEventListener('click', function () {
          insertService(option.dataset.serviceOption);
        });
      });

      dialog?.querySelectorAll('[data-variable]').forEach(function (button) {
        button.addEventListener('click', function () {
          const token = button.dataset.variable;
          const serviceField = token.match(/^\{\{service\.(name|duration|price)\}\}$/);

          /* A Service variable asks which service before it is inserted;
             everything else goes straight in. */
          if (serviceField && serviceStep) {
            pendingField = serviceField[1];
            serviceHint.textContent = button.querySelector('span')?.textContent ?? '';
            variableStep.hidden = true;
            serviceStep.hidden = false;

            return;
          }

          dialog.close();
          insertInto(target, token);
        });
      });
    }());
  </script>
@endpush
