@extends('layouts.onboarding')

@section('title', 'Business')
@section('heading', 'Tell us about your business')
@section('subheading', 'This creates your StyleDesk workspace. You can change any of it later in Settings.')

@section('form')
    <form id="stepForm" method="POST" action="{{ route('onboarding.business.store') }}"
          enctype="multipart/form-data" class="mt-6 space-y-5">
        @csrf

        <section class="bg-white border border-line rounded-card p-5 sm:p-6 space-y-5">

            <div>
                <label for="bizName" class="block text-[13px] font-medium text-ink mb-1.5">
                    Business name <span class="text-danger" aria-hidden="true">*</span>
                </label>
                <input id="bizName" name="name" type="text" class="sd-input" data-capitalize placeholder="Bella Beauty Studio"
                       autocomplete="organization" value="{{ old('name', $tenant?->name) }}"
                       aria-describedby="bizName-error" required autofocus>
                @error('name')
                    <p id="bizName-error" role="alert" class="mt-1.5 text-[12px] text-danger">{{ $message }}</p>
                @enderror
            </div>

            {{-- The address, suggested from the name and editable. Only the
                 subdomain is a field; the domain after it is text, because it
                 is not the business's to change.

                 The wrapper carries the rules the script needs — where to ask
                 whether an address is free, and what to say about the answer
                 — so the component is markup rather than a page-local
                 script. --}}
            @php
                /* Assembled here rather than inline in the attribute: Blade's
                   json directive counts brackets instead of reading PHP, so
                   an array literal written inside the tag ends at its first
                   closing bracket. */
                $slugLabels = [
                    'empty' => __('onboarding.business.slug.hint'),
                    'checking' => __('onboarding.business.slug.checking'),
                    'available' => __('onboarding.business.slug.available'),
                    'taken' => __('onboarding.business.slug.taken'),
                    'reserved' => __('onboarding.business.slug.reserved'),
                    'invalid' => __('onboarding.business.slug.invalid'),
                ];
            @endphp

            <div data-subdomain
                 data-subdomain-source="#bizName"
                 data-check-url="{{ route('onboarding.business.slug') }}"
                 data-labels='@json($slugLabels)'>
                <div class="flex flex-wrap items-baseline justify-between gap-2 mb-1.5">
                    <label for="bizSlug" class="block text-[13px] font-medium text-ink">
                        Business URL <span class="text-danger" aria-hidden="true">*</span>
                    </label>

                    {{-- Offered rather than automatic. Once someone has
                         chosen their own address, renaming the business must
                         not quietly take it back — but they still need a way
                         to ask for it back. --}}
                    <button type="button" data-subdomain-regenerate
                            class="text-[12px] font-medium text-link hover:underline">
                        {{ __('onboarding.business.slug.regenerate') }}
                    </button>
                </div>

                <div class="sd-group">
                    <input id="bizSlug" name="slug" type="text" class="sd-group__field" placeholder="serenityspa"
                           spellcheck="false" autocapitalize="none" autocorrect="off" data-subdomain-field
                           value="{{ old('slug', $tenant?->slug) }}" aria-describedby="bizSlug-error slug-status">
                    <span class="sd-group__suffix">.{{ config('tenancy.tenant_domain_suffix') }}</span>
                </div>

                @error('slug')
                    <p id="bizSlug-error" role="alert" class="mt-1.5 text-[12px] text-danger">{{ $message }}</p>
                @enderror

                <p id="slug-status" class="mt-1.5 text-[12px] text-faint" role="status" aria-live="polite"
                   data-subdomain-status>
                    {{ __('onboarding.business.slug.hint') }}
                </p>
            </div>

            <div>
                <fieldset>
                    <legend class="block text-[13px] font-medium text-ink mb-2">
                        Business type <span class="text-danger" aria-hidden="true">*</span>
                        <span class="font-normal text-faint ml-1">Select one or more</span>
                    </legend>

                    @php
                        $selectedTypes = array_map('intval', (array) old('business_type_ids', $tenant?->businessTypes->pluck('id')->all() ?? []));
                    @endphp

                    {{-- An empty catalogue is said out loud rather than
                         rendered as a heading with nothing under it.

                         Production shipped exactly that: the table is created
                         by a migration and filled by a seeder, a deploy runs
                         migrations only, and the step became a required field
                         with no options — silently unanswerable. The seeding
                         moved into a migration; this is what would have made
                         the fault legible in the first place. --}}
                    @if ($businessTypes->isEmpty())
                        <p class="text-[13px] text-danger" role="alert">
                            {{ __('onboarding.business.types.none') }}
                        </p>
                    @endif

                    <div id="types" class="grid sm:grid-cols-2 gap-2.5">
                        @foreach ($businessTypes as $type)
                            <div>
                                {{-- A real checkbox, visually hidden: the group posts natively,
                                     works without JavaScript, and the browser supplies the
                                     keyboard and screen-reader behaviour. --}}
                                <input type="checkbox" id="type-{{ $type->id }}" name="business_type_ids[]"
                                       value="{{ $type->id }}" class="sr-only styledesk_typechip__input"
                                       @checked(in_array($type->id, $selectedTypes, true))>
                                <label for="type-{{ $type->id }}" class="styledesk_typechip">
                                    <span class="styledesk_typechip__box">
                                        <svg class="styledesk_typechip__check" width="11" height="11" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                            <path d="M5 12l5 5 9-11" stroke="#fff" stroke-width="3.4" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
                                    </span>
                                    <x-type-icon :icon="$type->icon" class="styledesk_typechip__icon shrink-0" />
                                    <span class="truncate">{{ $type->name }}</span>
                                </label>
                            </div>
                        @endforeach
                    </div>

                    @error('business_type_ids')
                        <p id="types-error" role="alert" class="mt-2 text-[12px] text-danger">{{ $message }}</p>
                    @enderror
                </fieldset>
            </div>

            {{-- Countries, currencies and language are one island: the
                 currency suggestion depends on the primary country, and split
                 across separate islands that would need an event bus to cross
                 between two Vue apps. --}}
            @php
                $marketProps = [
                    'countries' => $countries,
                    'currencies' => $currencies,
                    'countryCurrencies' => $countryCurrencies,
                    'languages' => $languages,
                    'selectedCountries' => $selectedCountries,
                    'selectedCurrencies' => $selectedCurrencies,
                    'selectedLanguages' => $selectedLanguages,
                    'selectedLanguage' => old('default_language', $tenant?->default_language ?? config('currencies.default_language')),
                ];
            @endphp

            <div data-vue-component="OperatingMarkets" data-props='@json($marketProps)'></div>

            @error('country_codes')<p class="mt-1.5 text-[12px] text-danger">{{ $message }}</p>@enderror
            @error('currency_code')<p class="mt-1.5 text-[12px] text-danger">{{ $message }}</p>@enderror
            @error('secondary_currency_codes.*')<p class="mt-1.5 text-[12px] text-danger">{{ $message }}</p>@enderror
            @error('secondary_language_codes.*')<p class="mt-1.5 text-[12px] text-danger">{{ $message }}</p>@enderror
            @error('default_language')<p class="mt-1.5 text-[12px] text-danger">{{ $message }}</p>@enderror

            <div class="grid sm:grid-cols-2 gap-x-5 gap-y-5">
                <div>
                    <label for="bizPhone" class="block text-[13px] font-medium text-ink mb-1.5">
                        Business phone <span class="text-danger" aria-hidden="true">*</span>
                    </label>
                    <div class="relative" data-phone data-phone-country="{{ old('business_phone_country', $tenant?->business_phone_country ?? 'US') }}">
                        <div class="sd-phone">
                            <button type="button" class="sd-phone__country" data-phone-toggle aria-haspopup="listbox" aria-expanded="false">
                                <span class="sd-phone__flag" data-phone-flag>&#127482;&#127480;</span>
                                <span class="font-medium" data-phone-code>+1</span>
                            </button>
                            <input id="bizPhone" name="business_phone" type="tel" class="sd-phone__field"
                                   data-phone-input autocomplete="tel-national"
                                   value="{{ old('business_phone', $tenant?->business_phone) }}"
                                   aria-describedby="bizPhone-error" required>
                        </div>
                        <div class="sd-pop" data-phone-pop hidden></div>
                        <input type="hidden" name="business_phone_country" data-phone-country-value
                               value="{{ old('business_phone_country', $tenant?->business_phone_country ?? 'US') }}">
                    </div>
                    @error('business_phone')
                        <p id="bizPhone-error" role="alert" class="mt-1.5 text-[12px] text-danger">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="bizEmail" class="block text-[13px] font-medium text-ink mb-1.5">
                        Business email <span class="text-danger" aria-hidden="true">*</span>
                    </label>
                    <input id="bizEmail" name="business_email" type="email" class="sd-input" autocomplete="email"
                           value="{{ old('business_email', $tenant?->business_email ?? auth()->user()->email) }}"
                           aria-describedby="bizEmail-error bizEmail-hint" required>
                    @error('business_email')
                        <p id="bizEmail-error" role="alert" class="mt-1.5 text-[12px] text-danger">{{ $message }}</p>
                    @enderror
                    <p id="bizEmail-hint" class="mt-1.5 text-[12px] text-faint">
                        Pre-filled from your account — change it if clients should reply elsewhere.
                    </p>
                </div>
            </div>

            {{-- Checked as it is typed. The wrapper carries the message the
                 script says, so the wording lives with the rest of the copy
                 rather than inside a bundle. --}}
            <div data-website data-invalid-message="{{ __('business.validation.url_invalid') }}">
                <label for="bizSite" class="block text-[13px] font-medium text-ink mb-1.5">
                    Website <span class="font-normal text-faint">Optional</span>
                </label>
                <div class="sd-group">
                    <label class="sr-only" for="bizScheme">URL scheme</label>
                    <select id="bizScheme" name="website_scheme" class="sd-group__scheme" data-website-scheme>
                        @foreach (config('business_profile.website_schemes') as $scheme)
                            <option value="{{ $scheme }}" @selected(old('website_scheme') === $scheme)>{{ $scheme }}</option>
                        @endforeach
                    </select>
                    <input id="bizSite" name="website" type="text" class="sd-group__field" placeholder="bellabeauty.com"
                           autocomplete="url" spellcheck="false" autocapitalize="none" data-website-field
                           value="{{ old('website', $tenant?->website ? preg_replace('#^https?://(www\.)?#', '', $tenant->website) : '') }}">
                </div>

                <p id="bizSite-error" data-error-for="bizSite" role="alert" class="mt-1.5 text-[12px] text-danger"
                   @unless ($errors->has('website')) hidden @endunless>{{ $errors->first('website') }}</p>
            </div>
        </section>

        <section class="bg-white border border-line rounded-card p-5 sm:p-6">
            <p class="text-[13px] font-medium text-ink">Business logo <span class="font-normal text-faint">Optional</span></p>
            <p class="text-[12px] text-faint mt-0.5">JPG, PNG, SVG or WEBP, up to 2&nbsp;MB. You can add this later.</p>

            <div class="flex flex-wrap items-center gap-4 mt-4">
                <span id="logo-frame" class="h-16 w-16 rounded-card border border-line bg-hover grid place-items-center text-faint shrink-0 overflow-hidden">
                    @if ($tenant?->logo_path)
                        <img id="logo-preview" src="{{ Storage::disk('brand')->url($tenant->logo_path) }}" alt="" class="h-full w-full object-cover">
                    @else
                        <img id="logo-preview" src="" alt="" hidden class="h-full w-full object-cover">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" aria-hidden="true"><rect x="3.5" y="5" width="17" height="14" rx="2.5" stroke="currentColor" stroke-width="1.7"/><circle cx="9" cy="10" r="1.8" stroke="currentColor" stroke-width="1.7"/><path d="M4 17l5-4.5 4 3.5 3-2.5 4 3.5" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/></svg>
                    @endif
                </span>

                <div>
                    <input id="logo" name="logo" type="file" accept="image/jpeg,image/png,image/svg+xml,image/webp" class="sr-only">
                    <div class="flex items-center gap-2">
                        <label for="logo" class="styledesk_action">Upload logo</label>
                        <button type="button" id="logo-remove" hidden
                                data-confirm-title="{{ __('common.remove') }}"
                                data-confirm="{{ __('branding.remove_confirm') }}"
                                data-confirm-label="{{ __('common.remove') }}"
                                class="h-9 px-3 rounded-md text-sub hover:bg-hover text-[13px] font-semibold transition-colors">Remove</button>
                    </div>
                    @error('logo')
                        <p id="logo-error" role="alert" class="mt-1.5 text-[12px] text-danger">{{ $message }}</p>
                    @enderror
                    <p id="logo-name" class="mt-1.5 text-[12px] text-faint" role="status" aria-live="polite"></p>
                </div>
            </div>

            {{-- Upload progress. The prototype drives this from FileReader
                 events and notes that the Laravel build should feed it from a
                 real upload instead — which is what happens here, so the
                 percentage reflects bytes actually sent. --}}
            <div id="logo-progress" hidden class="mt-4 rounded-lg border border-line bg-white p-3">
                <div class="flex items-center gap-3">
                    <span class="h-9 w-9 rounded-md bg-hover grid place-items-center text-sub shrink-0" aria-hidden="true">
                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none"><rect x="3" y="4" width="18" height="16" rx="2.5" stroke="currentColor" stroke-width="1.7"/><circle cx="9" cy="10" r="1.8" stroke="currentColor" stroke-width="1.7"/><path d="M4 17l4.5-4 4 3.2L16 13l4 3.5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="text-[13px] font-medium text-ink truncate" data-upload-name>&nbsp;</p>
                        <p class="text-[12px] text-faint" data-upload-meta>&nbsp;</p>
                    </div>
                    <span class="text-[12px] font-medium text-sub shrink-0" data-upload-pct>0%</span>
                    <button type="button" data-upload-cancel
                            class="h-7 w-7 grid place-items-center rounded-md text-faint hover:bg-hover hover:text-danger shrink-0 transition-colors"
                            aria-label="{{ __('common.upload.cancel') }}">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"/></svg>
                    </button>
                </div>
                <div class="sd-progress mt-2.5" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" aria-label="{{ __('common.upload.progress') }}">
                    <span data-upload-bar style="width:0%"></span>
                </div>
            </div>

            {{-- Set once the async upload succeeds. Without JavaScript this
                 stays empty and the file input posts with the form instead. --}}
            <input type="hidden" name="logo_uploaded" id="logo-uploaded" value="">
        </section>
    </form>

    <div class="flex flex-wrap items-center gap-3 pt-6">
        {{-- Fires once. A slow POST gives the reader nothing to look at, so
             they click again — and a second submit here is a second tenant. --}}
        <button type="submit" form="stepForm" data-submit-once data-busy-label="{{ __('common.saving') }}"
                class="h-11 px-6 rounded-lg bg-brand hover:bg-brand-dark text-white text-[14px] font-semibold transition-colors disabled:opacity-60 disabled:pointer-events-none">
            Continue
        </button>
    </div>
@endsection

@section('rail')
    <h2 class="text-[15px] font-semibold text-head">Your workspace preview</h2>
    <p class="text-[13px] text-sub mt-1.5 leading-relaxed">This is what StyleDesk creates when you continue.</p>

    {{-- Mirrors the form as it is filled in. aria-hidden because every value
         shown here also appears in the form itself — announcing it twice would
         just make the page noisier to listen to. --}}
    <div class="mt-5 rounded-card border border-line bg-white shadow-sm p-4" aria-hidden="true">
        <div class="flex items-center gap-3">
            <span id="pv-logo" class="h-10 w-10 rounded-lg bg-brand text-white grid place-items-center shrink-0 overflow-hidden">
                <svg width="20" height="20" viewBox="0 0 32 32" fill="currentColor"><path d="M6.5 21.5 L14 6 L18.5 6 L11 21.5 Z"/><path d="M14.5 21.5 L22 6 L26.5 6 L19 21.5 Z"/><rect x="4" y="24.6" width="24" height="3.6" rx="1.8"/></svg>
            </span>
            <div class="min-w-0">
                <p id="pv-name" class="text-[14px] font-semibold text-head truncate">Your business</p>
                <p id="pv-types" class="text-[12px] text-faint truncate">No type selected yet</p>
            </div>
        </div>
        <div class="mt-4 pt-3.5 border-t border-line">
            <p class="text-[11px] font-semibold text-faint uppercase tracking-wide">Workspace address</p>
            <p id="pv-url" class="text-[12px] text-ink font-medium mt-1 break-all">….{{ config('tenancy.tenant_domain_suffix') }}</p>
        </div>
    </div>

    <ul class="mt-8 space-y-4">
        @foreach ([
            'You become the Business Owner, with full access to everything in this workspace.',
            'Your booking link is reserved from the business name and stays yours.',
            'Nothing here is final — every field is editable later in Settings.',
        ] as $point)
            <li class="flex items-start gap-3">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" class="text-brand mt-0.5 shrink-0" aria-hidden="true"><path d="M5 12.5l4.5 4.5L19 7.5" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                <span class="text-[13px] text-ink leading-relaxed">{{ $point }}</span>
            </li>
        @endforeach
    </ul>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        /* The address field is the shared subdomain component now — it
           generates from the name, cleans what is typed, checks whether the
           address is free and offers to regenerate. This page kept a `touched`
           flag and never used it: nothing here ever wrote the suggestion. */
        var name = document.getElementById('bizName');
        var slug = document.getElementById('bizSlug');

        /* ---- Logo preview ----------------------------------------------
           Read locally rather than uploaded first, so the user sees what they
           picked before committing the step. */
        var input = document.getElementById('logo');
        var preview = document.getElementById('logo-preview');
        var placeholder = document.querySelector('#logo-frame svg');
        var remove = document.getElementById('logo-remove');
        var fileName = document.getElementById('logo-name');

        function showLogo(file) {
            preview.src = URL.createObjectURL(file);
            preview.hidden = false;
            // toggleAttribute: the placeholder is an <svg>, and .hidden is an
            // HTMLElement property that SVG elements do not implement.
            if (placeholder) placeholder.toggleAttribute('hidden', true);
            remove.hidden = false;
            fileName.textContent = file.name;
        }

        function clearLogo() {
            input.value = '';
            if (document.getElementById('logo-uploaded')) {
                document.getElementById('logo-uploaded').value = '';
            }
            preview.hidden = true;
            preview.removeAttribute('src');
            if (placeholder) placeholder.toggleAttribute('hidden', false);
            remove.hidden = true;
            fileName.textContent = '';
        }

        var progress = document.getElementById('logo-progress');
        var pgName = progress.querySelector('[data-upload-name]');
        var pgMeta = progress.querySelector('[data-upload-meta]');
        var pgPct = progress.querySelector('[data-upload-pct]');
        var pgBar = progress.querySelector('[data-upload-bar]');
        var pgTrack = progress.querySelector('.sd-progress');
        var pgCancel = progress.querySelector('[data-upload-cancel]');
        var uploaded = document.getElementById('logo-uploaded');
        var request = null;

        function setPercent(value) {
            var pct = Math.max(0, Math.min(100, Math.round(value)));
            pgBar.style.width = pct + '%';
            pgPct.textContent = pct + '%';
            pgTrack.setAttribute('aria-valuenow', String(pct));
        }

        function humanSize(bytes) {
            return bytes < 1024 * 1024
                ? Math.round(bytes / 1024) + ' KB'
                : (bytes / 1024 / 1024).toFixed(1) + ' MB';
        }

        function endUpload() {
            request = null;
            progress.hidden = true;
        }

        function upload(file) {
            var data = new FormData();
            data.append('logo', file);
            data.append('_token', document.querySelector('meta[name=csrf-token]').content);

            request = new XMLHttpRequest();
            request.open('POST', @json(route('onboarding.logo.upload')));
            request.setRequestHeader('Accept', 'application/json');

            request.upload.addEventListener('progress', function (e) {
                if (e.lengthComputable) setPercent((e.loaded / e.total) * 100);
            });

            request.addEventListener('load', function () {
                if (request.status >= 200 && request.status < 300) {
                    var body = JSON.parse(request.responseText);
                    uploaded.value = body.path;

                    // The file has already been sent; leaving it on the input
                    // would upload the same bytes a second time on submit.
                    input.value = '';
                    setPercent(100);
                    endUpload();
                    return;
                }

                var message = 'Upload failed. Please try again.';

                try {
                    var errors = JSON.parse(request.responseText).errors;
                    if (errors && errors.logo) message = errors.logo[0];
                } catch (e) { /* keep the generic message */ }

                pgMeta.textContent = message;
                pgMeta.classList.add('text-danger');
                setPercent(0);
                request = null;
                clearLogo();
            });

            request.addEventListener('error', function () {
                pgMeta.textContent = 'Upload failed. Please check your connection.';
                pgMeta.classList.add('text-danger');
                request = null;
            });

            request.send(data);
        }

        input.addEventListener('change', function () {
            var file = input.files && input.files[0];

            if (!file) {
                clearLogo();
                return;
            }

            showLogo(file);

            pgName.textContent = file.name;
            pgMeta.textContent = humanSize(file.size);
            pgMeta.classList.remove('text-danger');
            setPercent(0);
            progress.hidden = false;

            upload(file);
        });

        pgCancel.addEventListener('click', function () {
            if (request) request.abort();
            endUpload();
            clearLogo();
        });

        remove.addEventListener('click', function () {
            if (request) request.abort();
            endUpload();
            clearLogo();
        });

        if (!preview.hidden && preview.getAttribute('src')) {
            remove.hidden = false;
        }

        /* ---- Workspace preview -----------------------------------------
           Mirrors the form into the rail. Plain DOM updates rather than a Vue
           island: the values live in the form, so a component would need to
           duplicate that state to render it. */
        var suffix = @json(config('tenancy.tenant_domain_suffix'));
        var pvName = document.getElementById('pv-name');
        var pvTypes = document.getElementById('pv-types');
        var pvUrl = document.getElementById('pv-url');
        var pvLogo = document.getElementById('pv-logo');

        function paintPreview() {
            pvName.textContent = name.value.trim() || 'Your business';

            var chosen = [...document.querySelectorAll('.styledesk_typechip__input:checked')]
                .map(function (i) { return document.querySelector('label[for="' + i.id + '"] .truncate').textContent.trim(); });

            pvTypes.textContent = chosen.length ? chosen.join(', ') : 'No type selected yet';

            // Mirrors the server's own fallback: an empty slug becomes one
            // derived from the name, so the preview does not promise an
            // address different from the one that gets reserved.
            var value = slug.value.trim() || name.value.trim().toLowerCase()
                .replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');

            pvUrl.textContent = (value || '…') + '.' + suffix;
        }

        [name, slug].forEach(function (el) { el.addEventListener('input', paintPreview); });
        document.querySelectorAll('.styledesk_typechip__input').forEach(function (el) {
            el.addEventListener('change', paintPreview);
        });

        // Show the chosen logo in the preview tile too.
        input.addEventListener('change', function () {
            var file = input.files && input.files[0];
            pvLogo.innerHTML = file
                ? '<img src="' + URL.createObjectURL(file) + '" alt="" class="h-full w-full object-cover">'
                : pvLogo.dataset.fallback;
        });
        pvLogo.dataset.fallback = pvLogo.innerHTML;

        paintPreview();
    });
</script>
@endpush
