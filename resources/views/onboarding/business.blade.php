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
                <input id="bizName" name="name" type="text" class="sd-input" placeholder="Bella Beauty Studio"
                       autocomplete="organization" value="{{ old('name', $tenant?->name) }}"
                       aria-describedby="bizName-error" required autofocus>
                @error('name')
                    <p id="bizName-error" role="alert" class="mt-1.5 text-[12px] text-danger">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="bizSlug" class="block text-[13px] font-medium text-ink mb-1.5">
                    Business URL <span class="text-danger" aria-hidden="true">*</span>
                </label>
                <div class="sd-group">
                    <input id="bizSlug" name="slug" type="text" class="sd-group__field" placeholder="serenityspa"
                           spellcheck="false" autocapitalize="none" autocorrect="off"
                           value="{{ old('slug', $tenant?->slug) }}" aria-describedby="bizSlug-error slug-status">
                    <span class="sd-group__suffix">.{{ config('tenancy.tenant_domain_suffix') }}</span>
                </div>
                @error('slug')
                    <p id="bizSlug-error" role="alert" class="mt-1.5 text-[12px] text-danger">{{ $message }}</p>
                @enderror
                <p id="slug-status" class="mt-1.5 text-[12px] text-faint" role="status" aria-live="polite">
                    Filled in from your business name — edit it if you'd like something shorter.
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

            <div>
                <label for="bizSite" class="block text-[13px] font-medium text-ink mb-1.5">
                    Website <span class="font-normal text-faint">Optional</span>
                </label>
                <div class="sd-group">
                    <label class="sr-only" for="bizScheme">URL scheme</label>
                    <select id="bizScheme" name="website_scheme" class="sd-group__scheme">
                        @foreach (['https://www.', 'https://', 'http://www.', 'http://'] as $scheme)
                            <option value="{{ $scheme }}" @selected(old('website_scheme') === $scheme)>{{ $scheme }}</option>
                        @endforeach
                    </select>
                    <input id="bizSite" name="website" type="text" class="sd-group__field" placeholder="bellabeauty.com"
                           autocomplete="url"
                           value="{{ old('website', $tenant?->website ? preg_replace('#^https?://(www\.)?#', '', $tenant->website) : '') }}">
                </div>
            </div>
        </section>

        <section class="bg-white border border-line rounded-card p-5 sm:p-6">
            <p class="text-[13px] font-medium text-ink">Business logo <span class="font-normal text-faint">Optional</span></p>
            <p class="text-[12px] text-faint mt-0.5">JPG, PNG or WEBP, up to 2&nbsp;MB. You can add this later.</p>

            <div class="flex flex-wrap items-center gap-4 mt-4">
                <span id="logo-frame" class="h-16 w-16 rounded-card border border-line bg-hover grid place-items-center text-faint shrink-0 overflow-hidden">
                    @if ($tenant?->logo_path)
                        <img id="logo-preview" src="{{ Storage::disk('public')->url($tenant->logo_path) }}" alt="" class="h-full w-full object-cover">
                    @else
                        <img id="logo-preview" src="" alt="" hidden class="h-full w-full object-cover">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" aria-hidden="true"><rect x="3.5" y="5" width="17" height="14" rx="2.5" stroke="currentColor" stroke-width="1.7"/><circle cx="9" cy="10" r="1.8" stroke="currentColor" stroke-width="1.7"/><path d="M4 17l5-4.5 4 3.5 3-2.5 4 3.5" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/></svg>
                    @endif
                </span>

                <div>
                    <input id="logo" name="logo" type="file" accept="image/jpeg,image/png,image/webp" class="sr-only">
                    <div class="flex items-center gap-2">
                        <label for="logo" class="inline-flex items-center h-9 px-4 rounded-md border border-stroke bg-white hover:bg-hover text-ink text-[13px] font-semibold cursor-pointer transition-colors">Upload logo</label>
                        <button type="button" id="logo-remove" hidden class="h-9 px-3 rounded-md text-sub hover:bg-hover text-[13px] font-semibold transition-colors">Remove</button>
                    </div>
                    @error('logo')
                        <p id="logo-error" role="alert" class="mt-1.5 text-[12px] text-danger">{{ $message }}</p>
                    @enderror
                    <p id="logo-name" class="mt-1.5 text-[12px] text-faint" role="status" aria-live="polite"></p>
                </div>
            </div>
        </section>
    </form>

    <div class="pt-6">
        <button type="submit" form="stepForm"
                class="h-11 px-6 rounded-lg bg-brand hover:bg-brand-dark text-white text-[14px] font-semibold transition-colors">
            Continue
        </button>
    </div>
@endsection

@section('rail')
    <h2 class="text-[15px] font-semibold text-head">Why we ask</h2>
    <p class="text-[13px] text-sub leading-relaxed mt-3">
        Your business name and URL appear on the booking page clients see. The
        URL becomes your own address on StyleDesk, so pick something short and
        recognisable.
    </p>
    <p class="text-[13px] text-sub leading-relaxed mt-3">
        Nothing here is final — every field is editable later in Settings.
    </p>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        /* ---- Slug suggested from the business name ----------------------
           Only while the user has not taken the field over: once they type
           their own slug, overwriting it on every keystroke of the name
           would be maddening. */
        var name = document.getElementById('bizName');
        var slug = document.getElementById('bizSlug');
        var touched = slug.value !== '';

        slug.addEventListener('input', function () { touched = true; });

        name.addEventListener('input', function () {
            if (touched) return;

            slug.value = name.value.toLowerCase()
                .normalize('NFD').replace(/[̀-ͯ]/g, '')
                .replace(/[^a-z0-9]+/g, '-')
                .replace(/^-+|-+$/g, '')
                .slice(0, 60);
        });

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
            if (placeholder) placeholder.hidden = true;
            remove.hidden = false;
            fileName.textContent = file.name;
        }

        function clearLogo() {
            input.value = '';
            preview.hidden = true;
            preview.removeAttribute('src');
            if (placeholder) placeholder.hidden = false;
            remove.hidden = true;
            fileName.textContent = '';
        }

        input.addEventListener('change', function () {
            var file = input.files && input.files[0];
            file ? showLogo(file) : clearLogo();
        });

        remove.addEventListener('click', clearLogo);

        if (!preview.hidden && preview.getAttribute('src')) {
            remove.hidden = false;
        }
    });
</script>
@endpush
