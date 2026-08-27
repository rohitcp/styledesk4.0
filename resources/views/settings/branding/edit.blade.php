@extends('layouts.app')

@section('title', __('branding.title'))

@section('content')
  <main class="w-full px-4 sm:px-5 lg:px-6 pt-5 sm:pt-6 pb-[200px]">
    <div class="max-w-[1180px]">

      <nav class="text-[13px] text-sub" aria-label="Breadcrumb">
        <a href="{{ route('settings.index') }}" class="hover:text-ink transition-colors">{{ __('navigation.app_settings') }}</a>
        <span class="mx-1.5 text-faint">/</span>
        <span class="text-ink">{{ __('branding.title') }}</span>
      </nav>

      <div class="mt-3 flex flex-wrap items-start gap-4">
        <div class="min-w-0 flex-1">
          <h1 class="text-[24px] sm:text-[28px] font-bold text-head tracking-tight">{{ __('branding.title') }}</h1>
          <p class="text-[14px] text-sub mt-2 max-w-[640px] leading-relaxed">
            {{ __('branding.intro') }}
          </p>
        </div>

        <a href="{{ route('settings.index') }}"
           class="shrink-0 inline-flex items-center gap-1.5 h-9 px-3 rounded-lg border border-stroke bg-white hover:bg-hover text-ink text-[13px] font-semibold transition-colors">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M14 6l-6 6 6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
          {{ __('common.back') }}
        </a>
      </div>

      @if ($errors->any())
        <div class="sd-alert sd-alert--danger mt-5" role="alert">
          <p class="min-w-0">{{ __('branding.correct_fields') }}</p>
        </div>
      @endif

      <form id="brandingForm" method="POST" action="{{ route('settings.branding.update') }}" class="mt-6">
        @csrf
        @method('PATCH')

        {{-- The form on the left, what it produces on the right. The preview
             is the point of this screen, so it stays beside the controls
             rather than below them where a colour change would scroll out of
             sight the moment it was made. --}}
        <div class="grid gap-5 lg:grid-cols-[minmax(0,420px)_minmax(0,1fr)] items-start">

          <div class="space-y-5">

            {{-- ------------------------------------------------ logo --}}
            <section class="bg-white border border-line rounded-card p-5 space-y-4">
              <div>
                <h2 class="text-[15px] font-semibold text-head">{{ __('branding.logo.title') }}</h2>
                <p class="text-[13px] text-sub mt-0.5">
                  {{ __('branding.logo.hint') }}
                </p>
              </div>

              <div class="flex items-center gap-4">
                {{-- Checkerboard behind the preview, so a transparent logo
                     reads as transparent rather than as white-on-white. --}}
                <span data-asset-preview="logo"
                      class="styledesk_checkerboard h-16 w-32 shrink-0 rounded-lg border border-line grid place-items-center overflow-hidden text-faint">
                  @if ($logoUrl)
                    <img src="{{ $logoUrl }}" alt="{{ $tenant->name }} logo" class="max-h-full max-w-full object-contain">
                  @else
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" aria-hidden="true"><rect x="3" y="4" width="18" height="16" rx="2.5" stroke="currentColor" stroke-width="1.7"/><circle cx="9" cy="10" r="1.8" stroke="currentColor" stroke-width="1.7"/><path d="M4 17l4.5-4 4 3.2L16 13l4 3.5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
                  @endif
                </span>

                <div class="min-w-0 flex-1 space-y-2">
                  <input id="logoFile" type="file" class="sr-only"
                         accept=".png,.jpg,.jpeg,.svg,.webp"
                         data-asset-input="logo"
                         data-endpoint="{{ route('settings.branding.logo.upload') }}">

                  <div class="flex flex-wrap items-center gap-2">
                    <label for="logoFile"
                           class="inline-flex items-center h-9 px-3.5 rounded-md border border-stroke bg-white hover:bg-hover text-ink text-[13px] font-semibold cursor-pointer transition-colors">
                      {{ $logoUrl ? __('branding.logo.replace') : __('branding.logo.upload') }}
                    </label>

                    <button type="button" data-asset-remove="logo" @if (! $logoUrl) hidden @endif
                            class="h-9 px-3 rounded-md text-sub hover:text-danger hover:bg-hover text-[13px] font-semibold transition-colors">
                      {{ __('common.remove') }}
                    </button>
                  </div>

                  <p class="text-[12px] text-sub truncate" data-asset-status="logo"></p>
                </div>
              </div>

              <input type="hidden" name="logo_path" value="{{ old('logo_path', $tenant->logo_path) }}" data-asset-path="logo">
              @error('logo_path')<p class="text-[12px] text-danger">{{ $message }}</p>@enderror
            </section>

            {{-- --------------------------------------------- favicon --}}
            <section class="bg-white border border-line rounded-card p-5 space-y-4">
              <div>
                <h2 class="text-[15px] font-semibold text-head">{{ __('branding.favicon.title') }}</h2>
                <p class="text-[13px] text-sub mt-0.5">
                  {{ __('branding.favicon.hint') }}
                </p>
              </div>

              <div class="flex items-center gap-4">
                <span data-asset-preview="favicon"
                      class="styledesk_checkerboard h-16 w-16 shrink-0 rounded-lg border border-line grid place-items-center overflow-hidden text-faint">
                  @if ($faviconUrl)
                    <img src="{{ $faviconUrl }}" alt="" class="max-h-full max-w-full object-contain">
                  @else
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" aria-hidden="true"><rect x="4" y="4" width="16" height="16" rx="4" stroke="currentColor" stroke-width="1.7"/></svg>
                  @endif
                </span>

                <div class="min-w-0 flex-1 space-y-2">
                  <input id="faviconFile" type="file" class="sr-only"
                         accept=".png,.svg,.ico"
                         data-asset-input="favicon"
                         data-endpoint="{{ route('settings.branding.favicon.upload') }}">

                  <div class="flex flex-wrap items-center gap-2">
                    <label for="faviconFile"
                           class="inline-flex items-center h-9 px-3.5 rounded-md border border-stroke bg-white hover:bg-hover text-ink text-[13px] font-semibold cursor-pointer transition-colors">
                      {{ $faviconUrl ? __('branding.favicon.replace') : __('branding.favicon.upload') }}
                    </label>

                    <button type="button" data-asset-remove="favicon" @if (! $faviconUrl) hidden @endif
                            class="h-9 px-3 rounded-md text-sub hover:text-danger hover:bg-hover text-[13px] font-semibold transition-colors">
                      {{ __('common.remove') }}
                    </button>
                  </div>

                  <p class="text-[12px] text-sub truncate" data-asset-status="favicon"></p>
                </div>
              </div>

              <input type="hidden" name="favicon_path" value="{{ old('favicon_path', $tenant->favicon_path) }}" data-asset-path="favicon">
              @error('favicon_path')<p class="text-[12px] text-danger">{{ $message }}</p>@enderror
            </section>

            {{-- ---------------------------------------------- colours --}}
            <section class="bg-white border border-line rounded-card p-5 space-y-4">
              <div>
                <h2 class="text-[15px] font-semibold text-head">{{ __('branding.colours.title') }}</h2>
                <p class="text-[13px] text-sub mt-0.5">
                  {{ __('branding.colours.hint') }}
                </p>
              </div>

              @php
                  $colorFields = [
                      'brand_primary' => [
                          'label' => __('branding.colours.primary'),
                          'hint' => __('branding.colours.primary_hint'),
                          'value' => old('brand_primary', $palette->colors['primary']),
                      ],
                      'brand_secondary' => [
                          'label' => __('branding.colours.secondary'),
                          'hint' => __('branding.colours.secondary_hint'),
                          'value' => old('brand_secondary', $palette->colors['secondary']),
                      ],
                      'brand_accent' => [
                          'label' => __('branding.colours.accent'),
                          'hint' => __('branding.colours.accent_hint'),
                          'value' => old('brand_accent', $palette->colors['accent']),
                      ],
                  ];
              @endphp

              @foreach ($colorFields as $field => $meta)
                <div>
                  <label for="{{ $field }}_hex" class="block text-[13px] font-medium text-ink mb-1.5">
                    {{ $meta['label'] }} <span class="text-danger">*</span>
                  </label>

                  <div class="flex items-center gap-2">
                    {{-- The swatch is a native colour input, so the platform's
                         own picker does the picking. The hex box beside it is
                         what people paste a brand guideline into; they are
                         two views of one value, kept in step by script. --}}
                    <input type="color" class="styledesk_swatch shrink-0"
                           id="{{ $field }}_picker" value="{{ $meta['value'] }}"
                           data-color-picker="{{ $field }}"
                           aria-label="{{ __('branding.colours.picker_label', ['name' => $meta['label']]) }}">

                    <input type="text" class="sd-input font-mono" id="{{ $field }}_hex"
                           name="{{ $field }}" value="{{ $meta['value'] }}"
                           data-color-hex="{{ $field }}"
                           spellcheck="false" autocomplete="off" maxlength="7" placeholder="#3d348b">
                  </div>

                  <p class="mt-1.5 text-[12px] text-sub">{{ $meta['hint'] }}</p>
                  <p class="mt-1 text-[12px] text-danger" data-color-error="{{ $field }}" hidden>
                    {{ __('branding.colours.invalid') }}
                  </p>
                  @error($field)<p class="mt-1 text-[12px] text-danger">{{ $message }}</p>@enderror
                </div>
              @endforeach

              {{-- Said while it can still be changed. A business that picks a
                   pale primary is about to send unreadable buttons to every
                   client it has, and finding that out from a client is worse
                   than finding it out here. --}}
              <div class="pt-4 border-t border-line">
                <p class="text-[12px] text-sub">
                  {{ __('branding.colours.contrast') }}
                  <span class="font-semibold" data-contrast-label>{{ __('branding.grades.'.$primaryGrade['key']) }}</span>
                  <span class="text-faint" data-contrast-ratio>({{ $primaryGrade['ratio'] }}:1)</span>
                </p>
                <p class="mt-1 text-[12px] text-danger" data-contrast-warning @if ($primaryGrade['ok']) hidden @endif>
                  {{ __('branding.colours.contrast_warning') }}
                </p>
              </div>
            </section>

            <div class="flex flex-wrap items-center gap-3">
              <button type="submit" id="brandingSave"
                      class="h-9 px-4 rounded-lg bg-brand hover:bg-brand-dark text-white text-[13px] font-semibold transition-colors disabled:opacity-60 disabled:pointer-events-none">
                {{ __('common.save_changes') }}
              </button>

              <a href="{{ route('settings.index') }}"
                 class="h-9 px-3.5 inline-flex items-center rounded-lg border border-stroke bg-white hover:bg-hover text-ink text-[13px] font-semibold transition-colors">
                {{ __('common.cancel') }}
              </a>

              @unless ($isDefault)
                <button type="button" data-branding-reset
                        class="ml-auto h-9 px-3.5 rounded-lg text-danger hover:bg-hover text-[13px] font-semibold transition-colors">
                  {{ __('branding.reset') }}
                </button>
              @endunless
            </div>
          </div>

          {{-- --------------------------------------------- previews --}}
          @include('settings.branding._preview')
        </div>
      </form>

      <form id="brandingResetForm" method="POST" action="{{ route('settings.branding.reset') }}" class="hidden">
        @csrf
        @method('DELETE')
      </form>
    </div>
  </main>
@endsection

@push('scripts')
  <script>
    (function () {
      var form = document.getElementById('brandingForm');
      if (!form) return;

      /* ------------------------------------------------------- colours */

      /* The preview is repainted by writing custom properties onto the
         preview element itself rather than onto :root. The page around it
         still has to look like the app the user is in — repainting the whole
         chrome as they drag a picker would make the settings screen itself
         change colour under them before they had decided anything. */
      var preview = document.querySelector('[data-brand-preview]');

      function parseHex(value) {
        var hex = String(value || '').trim().replace(/^#/, '');
        if (/^[0-9a-fA-F]{3}$/.test(hex)) {
          hex = hex[0] + hex[0] + hex[1] + hex[1] + hex[2] + hex[2];
        }
        return /^[0-9a-fA-F]{6}$/.test(hex) ? '#' + hex.toLowerCase() : null;
      }

      function channels(hex) {
        return [
          parseInt(hex.slice(1, 3), 16),
          parseInt(hex.slice(3, 5), 16),
          parseInt(hex.slice(5, 7), 16)
        ];
      }

      function luminance(hex) {
        return channels(hex).map(function (v) {
          v = v / 255;
          return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4);
        }).reduce(function (sum, v, i) {
          return sum + [0.2126, 0.7152, 0.0722][i] * v;
        }, 0);
      }

      function contrast(a, b) {
        var la = luminance(a), lb = luminance(b);
        return (Math.max(la, lb) + 0.05) / (Math.min(la, lb) + 0.05);
      }

      function mix(hex, target, amount) {
        var a = channels(hex), b = channels(target);
        return '#' + a.map(function (v, i) {
          var blended = Math.round(v + (b[i] - v) * amount);
          return ('0' + Math.max(0, Math.min(255, blended)).toString(16)).slice(-2);
        }).join('');
      }

      /* Kept in step with BrandPalette on the server, deliberately including
         the low 0.02 thresholds: the two have to agree, or the preview would
         show one hover shade and the saved page another. */
      function hoverFor(hex) { return luminance(hex) < 0.02 ? mix(hex, '#ffffff', 0.18) : mix(hex, '#000000', 0.20); }
      function bannerFor(hex) { return luminance(hex) < 0.02 ? mix(hex, '#ffffff', 0.22) : mix(hex, '#000000', 0.28); }
      function inkFor(hex) { return contrast(hex, '#ffffff') >= contrast(hex, '#0f0f10') ? '#ffffff' : '#0f0f10'; }

      function currentColor(field, fallback) {
        return parseHex(form.querySelector('[data-color-hex="' + field + '"]').value) || fallback;
      }

      function repaint() {
        var primary = currentColor('brand_primary', '#3d348b');
        var secondary = currentColor('brand_secondary', '#0d9488');
        var accent = currentColor('brand_accent', '#b45309');

        if (!preview) return;

        preview.style.setProperty('--sd-brand', primary);
        preview.style.setProperty('--sd-brand-dark', hoverFor(primary));
        preview.style.setProperty('--sd-banner', bannerFor(primary));
        preview.style.setProperty('--sd-link', primary);
        preview.style.setProperty('--sd-btn', primary);
        preview.style.setProperty('--sd-btn-ink', inkFor(primary));
        preview.style.setProperty('--sd-accent', accent);
        preview.style.setProperty('--sd-secondary', secondary);

        /* The contrast reading, recomputed as they type. Measured against
           white, not against inkFor(primary): that returns whichever of black
           and white scores better, so it always passes and the warning could
           never fire. White is what the chrome actually prints. */
        var ratio = contrast(primary, '#ffffff');
        /* The grades come from the server, so the reading beside the picker
           is worded the same as the one rendered on load. */
        var grades = @json(__('branding.grades'));
        var label = ratio >= 7 ? grades.aaa : ratio >= 4.5 ? grades.aa : ratio >= 3 ? grades.large : grades.fails;

        form.querySelector('[data-contrast-label]').textContent = label;
        form.querySelector('[data-contrast-ratio]').textContent = '(' + (Math.round(ratio * 10) / 10) + ':1)';
        form.querySelector('[data-contrast-warning]').hidden = ratio >= 4.5;
      }

      form.querySelectorAll('[data-color-hex]').forEach(function (hexField) {
        var field = hexField.getAttribute('data-color-hex');
        var picker = form.querySelector('[data-color-picker="' + field + '"]');
        var error = form.querySelector('[data-color-error="' + field + '"]');

        hexField.addEventListener('input', function () {
          var parsed = parseHex(hexField.value);

          /* Only flagged once there is something to flag. Complaining at "#3"
             while somebody is halfway through typing "#3d348b" is a warning
             about nothing. */
          error.hidden = parsed !== null || hexField.value.trim() === '';

          if (parsed) {
            picker.value = parsed;
            repaint();
          }
        });

        /* Normalised when they leave the field, so "3D348B" and "#3d348b"
           are stored the same way whichever the brand guideline used. */
        hexField.addEventListener('blur', function () {
          var parsed = parseHex(hexField.value);
          if (parsed) { hexField.value = parsed; error.hidden = true; }
        });

        picker.addEventListener('input', function () {
          hexField.value = picker.value;
          error.hidden = true;
          repaint();
        });
      });

      repaint();

      /* ------------------------------------------------------- uploads */

      form.querySelectorAll('[data-asset-input]').forEach(function (input) {
        var name = input.getAttribute('data-asset-input');
        var endpoint = input.getAttribute('data-endpoint');
        var pathField = form.querySelector('[data-asset-path="' + name + '"]');
        var box = form.querySelector('[data-asset-preview="' + name + '"]');
        var status = form.querySelector('[data-asset-status="' + name + '"]');
        var remove = form.querySelector('[data-asset-remove="' + name + '"]');

        function show(url) {
          box.innerHTML = '';
          var img = document.createElement('img');
          img.src = url;
          img.alt = '';
          img.className = 'max-h-full max-w-full object-contain';
          box.appendChild(img);

          /* The document's own icon follows the favicon immediately, so the
             tab shows what was just chosen rather than waiting for a save. */
          if (name === 'favicon') {
            var link = document.querySelector('link[rel="icon"]');
            if (link) link.href = url;
          }

          previewAssets();
        }

        input.addEventListener('change', function () {
          var file = input.files && input.files[0];
          if (!file) return;

          status.textContent = @json(__('branding.upload.uploading'));
          status.classList.remove('text-danger');

          var body = new FormData();
          body.append(name, file);
          body.append('_token', form.querySelector('input[name="_token"]').value);

          fetch(endpoint, { method: 'POST', body: body, headers: { 'Accept': 'application/json' } })
            .then(function (response) {
              return response.json().then(function (data) {
                if (!response.ok) throw new Error(data.message || @json(__('branding.upload.failed')));
                return data;
              });
            })
            .then(function (data) {
              pathField.value = data.path;
              show(data.url);
              status.textContent = file.name;
              remove.hidden = false;
            })
            .catch(function (error) {
              /* Said beside the field, not in an alert. The person can act on
                 "use a PNG"; they cannot act on a dialog they have dismissed. */
              status.textContent = error.message;
              status.classList.add('text-danger');
              input.value = '';
            });
        });

        remove.addEventListener('click', function () {
          pathField.value = '';
          input.value = '';
          box.innerHTML = '';
          status.textContent = @json(__('branding.upload.removed'));
          remove.hidden = true;
          previewAssets();
        });
      });

      /* The preview panel mirrors whichever assets are currently chosen. */
      function previewAssets() {
        var logo = form.querySelector('[data-asset-path="logo"]').value;
        var logoBox = form.querySelector('[data-asset-preview="logo"] img');

        document.querySelectorAll('[data-preview-logo]').forEach(function (slot) {
          slot.innerHTML = '';

          if (logo && logoBox) {
            var img = document.createElement('img');
            img.src = logoBox.src;
            img.alt = '';
            img.className = 'max-h-full max-w-full object-contain';
            slot.appendChild(img);
          } else {
            slot.textContent = slot.getAttribute('data-preview-logo') || '';
          }
        });
      }

      previewAssets();

      /* -------------------------------------------------------- actions */

      document.querySelector('[data-branding-reset]')?.addEventListener('click', function () {
        if (!window.confirm(@json(__('branding.reset_confirm')))) return;

        document.getElementById('brandingResetForm').submit();
      });

      /* One submission. */
      var save = document.getElementById('brandingSave');
      var saving = false;

      form.addEventListener('submit', function (e) {
        if (saving) { e.preventDefault(); return; }
        saving = true;
        save.disabled = true;
        save.textContent = @json(__('common.saving'));
      });
    }());
  </script>
@endpush
