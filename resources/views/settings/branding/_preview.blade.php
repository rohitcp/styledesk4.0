{{--
    What the branding actually produces.

    Every panel below is scoped inside data-brand-preview, and the script
    writes the palette onto that element rather than onto :root. Repainting the
    whole page as someone drags a colour picker would change the settings
    screen they are standing on before they had decided anything — and would
    leave them unable to tell what they had saved from what they were trying.

    These are representations, not the real templates. Said plainly on the
    panel, because a preview that claims to be the email and is not is worse
    than no preview.
--}}
<div data-brand-preview class="space-y-4 lg:sticky lg:top-4">

  <div class="flex flex-wrap items-baseline gap-2">
    <h2 class="text-[15px] font-semibold text-head">{{ __('branding.preview.title') }}</h2>
    <p class="text-[12px] text-sub">{{ __('branding.preview.hint') }}</p>
  </div>

  {{-- ------------------------------------------------ app navigation --}}
  <section class="bg-white border border-line rounded-card overflow-hidden">
    <p class="px-4 py-2.5 border-b border-line text-[12px] font-medium text-sub">{{ __('branding.preview.in_app') }}</p>

    <div>
      {{-- The two-tone chrome: the banner strip sits above the app bar and is
           always the darker of the two, whatever the brand colour is. --}}
      <div style="background: var(--sd-banner)" class="px-4 py-1.5">
        <span class="text-[11px] text-white/90">{{ __('branding.preview.trial') }}</span>
      </div>

      <div style="background: var(--sd-brand)" class="px-4 py-3 flex items-center gap-3">
        <span data-preview-logo="{{ __('branding.preview.your_logo') }}"
              class="h-7 max-w-[120px] flex items-center text-[12px] text-white/70"></span>

        <span class="ml-auto h-7 w-7 rounded-full bg-white/20 grid place-items-center text-[11px] text-white font-semibold">
          {{ mb_substr($tenant->name, 0, 1) }}
        </span>
      </div>
    </div>

    <div class="p-4 space-y-3">
      <div class="flex flex-wrap items-center gap-2">
        <button type="button" tabindex="-1"
                style="background: var(--sd-brand); color: var(--sd-btn-ink)"
                class="h-9 px-4 rounded-lg text-[13px] font-semibold pointer-events-none">
          {{ __('branding.preview.book_appointment') }}
        </button>

        <button type="button" tabindex="-1"
                class="h-9 px-4 rounded-lg border border-stroke bg-white text-ink text-[13px] font-semibold pointer-events-none">
          {{ __('common.cancel') }}
        </button>

        <span style="background: var(--sd-secondary)"
              class="inline-flex items-center h-6 px-2.5 rounded-full text-[11px] font-semibold text-white">
          {{ __('branding.preview.confirmed') }}
        </span>

        <span style="background: var(--sd-accent)"
              class="inline-flex items-center h-6 px-2.5 rounded-full text-[11px] font-semibold text-white">
          {{ __('branding.preview.new') }}
        </span>
      </div>

      <p class="text-[13px] text-sub">
        {!! __('branding.preview.link_sentence', [
            'link' => '<a href="#" tabindex="-1" style="color: var(--sd-link)" class="font-medium pointer-events-none">'
                .e(__('branding.preview.this_one')).'</a>',
        ]) !!}
      </p>
    </div>
  </section>

  {{-- ------------------------------------------------- booking page --}}
  <section class="bg-white border border-line rounded-card overflow-hidden">
    <p class="px-4 py-2.5 border-b border-line text-[12px] font-medium text-sub">{{ __('branding.preview.booking_page') }}</p>

    <div class="p-4">
      <div class="rounded-lg border border-line overflow-hidden">
        <div style="background: var(--sd-brand)" class="px-4 py-5 text-center">
          <span data-preview-logo="{{ $tenant->name }}"
                class="h-9 inline-flex items-center justify-center text-[14px] font-semibold text-white"></span>
          <p class="text-[12px] text-white/80 mt-1">{{ __('branding.preview.book_online') }}</p>
        </div>

        <div class="p-4 space-y-2.5">
          @foreach (["Women's Cut & Finish", 'Balayage'] as $service)
            <div class="flex items-center gap-3 rounded-md border border-line px-3 py-2.5">
              <span class="min-w-0 flex-1">
                <span class="block text-[13px] font-medium text-head truncate">{{ $service }}</span>
                <span class="block text-[12px] text-sub">{{ __('branding.preview.minutes', ['count' => 60]) }}</span>
              </span>
              <span style="color: var(--sd-brand)" class="text-[13px] font-semibold">{{ __('branding.preview.select') }}</span>
            </div>
          @endforeach

          <button type="button" tabindex="-1"
                  style="background: var(--sd-brand); color: var(--sd-btn-ink)"
                  class="w-full h-10 rounded-lg text-[13px] font-semibold pointer-events-none">
            {{ __('branding.preview.continue') }}
          </button>
        </div>
      </div>
    </div>
  </section>

  {{-- ------------------------------------------------------- email --}}
  <section class="bg-white border border-line rounded-card overflow-hidden">
    <p class="px-4 py-2.5 border-b border-line text-[12px] font-medium text-sub">
      {{ __('branding.preview.emails') }}
    </p>

    <div class="p-4">
      <div class="rounded-lg border border-line overflow-hidden">
        <div style="background: var(--sd-brand)" class="px-4 py-4">
          <span data-preview-logo="{{ $tenant->name }}"
                class="h-7 inline-flex items-center text-[13px] font-semibold text-white"></span>
        </div>

        <div class="p-4 space-y-3">
          <p class="text-[14px] font-semibold text-head">{{ __('branding.preview.email_subject') }}</p>
          <p class="text-[13px] text-sub leading-relaxed">
            {{ __('branding.preview.email_body', ['business' => $tenant->name]) }}
          </p>

          <button type="button" tabindex="-1"
                  style="background: var(--sd-brand); color: var(--sd-btn-ink)"
                  class="h-9 px-4 rounded-lg text-[13px] font-semibold pointer-events-none">
            {{ __('branding.preview.view_appointment') }}
          </button>

          <p class="text-[11px] text-faint pt-2 border-t border-line">
            {{ __('branding.preview.email_footer', ['business' => $tenant->name]) }}
          </p>
        </div>
      </div>
    </div>
  </section>

  {{-- --------------------------------------------- receipt / invoice --}}
  <section class="bg-white border border-line rounded-card overflow-hidden">
    <p class="px-4 py-2.5 border-b border-line text-[12px] font-medium text-sub">{{ __('branding.preview.receipts') }}</p>

    <div class="p-4">
      <div class="rounded-lg border border-line p-4">
        <div class="flex items-start gap-3 pb-3 border-b border-line">
          <span data-preview-logo="{{ $tenant->name }}"
                class="h-8 max-w-[120px] flex items-center text-[13px] font-semibold text-head"></span>

          <span class="ml-auto text-right">
            <span class="block text-[12px] text-sub">{{ __('branding.preview.receipt') }}</span>
            <span class="block text-[12px] font-mono text-ink">#1042</span>
          </span>
        </div>

        <div class="py-3 space-y-1.5 text-[13px]">
          <div class="flex items-center gap-3">
            <span class="min-w-0 flex-1 text-ink truncate">Women's Cut &amp; Finish</span>
            <span class="text-ink">65.00</span>
          </div>
          <div class="flex items-center gap-3 text-sub">
            <span class="min-w-0 flex-1 truncate">{{ __('branding.preview.tax') }}</span>
            <span>5.20</span>
          </div>
        </div>

        <div class="flex items-center gap-3 pt-3 border-t border-line">
          <span class="min-w-0 flex-1 text-[13px] font-semibold text-head">{{ __('branding.preview.total') }}</span>
          <span style="color: var(--sd-brand)" class="text-[15px] font-bold">70.20</span>
        </div>
      </div>
    </div>
  </section>

  {{-- Named plainly. The list is what the spec promises branding reaches, and
       a business is entitled to know that changing a colour here changes what
       its clients receive. --}}
  <div class="rounded-card border border-line bg-white p-4">
    <p class="text-[12px] font-medium text-sub">{{ __('branding.preview.where_used') }}</p>
    <p class="text-[12px] text-sub mt-1.5 leading-relaxed">
      {{ __('branding.preview.where_used_body') }}
    </p>
    <p class="text-[11px] text-faint mt-2">
      {{ __('branding.preview.representations') }}
    </p>
  </div>
</div>
