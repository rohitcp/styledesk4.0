@extends('layouts.onboarding')

@section('title', 'Business')
@section('heading', 'Tell us about your business')
@section('subheading', 'This creates your StyleDesk workspace. You can change any of it later in Settings.')

@section('form')
    <form method="POST" action="{{ route('onboarding.business.store') }}" enctype="multipart/form-data" class="mt-6 space-y-5">
        @csrf

        <div>
            <label for="name" class="block text-[13px] font-medium text-ink mb-1.5">Business name</label>
            <input id="name" name="name" type="text" class="sd-input" placeholder="Bella Beauty Studio"
                   value="{{ old('name', $tenant?->name) }}" required autofocus>
            @error('name')<p class="mt-1.5 text-[12px] text-danger">{{ $message }}</p>@enderror
        </div>

        <fieldset>
            <legend class="block text-[13px] font-medium text-ink mb-1.5">Business type</legend>
            <p class="text-[12px] text-sub mb-2.5">Pick everything that applies.</p>
            @php
                $selectedTypes = old('business_type_ids', $tenant?->businessTypes->pluck('id')->all() ?? []);
            @endphp
            <div class="grid sm:grid-cols-3 gap-x-4 gap-y-2">
                @foreach ($businessTypes as $type)
                    <label class="flex items-center gap-2.5 cursor-pointer">
                        <input type="checkbox" name="business_type_ids[]" value="{{ $type->id }}" class="sd-check"
                               @checked(in_array($type->id, $selectedTypes))>
                        <span class="text-[13px] text-ink">{{ $type->name }}</span>
                    </label>
                @endforeach
            </div>
            @error('business_type_ids')<p class="mt-1.5 text-[12px] text-danger">{{ $message }}</p>@enderror
        </fieldset>

        <div>
            <label for="slug" class="block text-[13px] font-medium text-ink mb-1.5">Business URL</label>
            <div class="flex items-stretch">
                <input id="slug" name="slug" type="text" class="sd-input rounded-r-none" placeholder="bella-beauty-studio"
                       value="{{ old('slug', $tenant?->slug) }}"
                       pattern="[a-z0-9]([a-z0-9-]*[a-z0-9])?">
                <span class="inline-flex items-center px-3 rounded-r-md border border-l-0 border-stroke bg-hover text-[13px] text-sub">
                    .{{ config('tenancy.tenant_domain_suffix') }}
                </span>
            </div>
            <p class="mt-1.5 text-[12px] text-sub">
                This is where clients book with you. Leave it blank and we'll build one from your business name.
            </p>
            @error('slug')<p class="mt-1.5 text-[12px] text-danger">{{ $message }}</p>@enderror
        </div>

        <div class="grid sm:grid-cols-2 gap-x-4 gap-y-5">
            <div>
                <label for="business_phone" class="block text-[13px] font-medium text-ink mb-1.5">Business phone</label>
                <input id="business_phone" name="business_phone" type="tel" class="sd-input" autocomplete="tel"
                       value="{{ old('business_phone', $tenant?->business_phone) }}" required>
                @error('business_phone')<p class="mt-1.5 text-[12px] text-danger">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="business_email" class="block text-[13px] font-medium text-ink mb-1.5">Business email</label>
                <input id="business_email" name="business_email" type="email" class="sd-input" autocomplete="email"
                       value="{{ old('business_email', $tenant?->business_email ?? auth()->user()->email) }}">
                @error('business_email')<p class="mt-1.5 text-[12px] text-danger">{{ $message }}</p>@enderror
            </div>
        </div>

        <div>
            <label for="website" class="block text-[13px] font-medium text-ink mb-1.5">Website</label>
            <div class="flex items-stretch">
                <select name="website_scheme" aria-label="URL scheme"
                        class="sd-input w-auto rounded-r-none border-r-0 text-sub">
                    @foreach (['https://www.', 'https://', 'http://www.', 'http://'] as $scheme)
                        <option value="{{ $scheme }}" @selected(old('website_scheme') === $scheme)>{{ $scheme }}</option>
                    @endforeach
                </select>
                <input id="website" name="website" type="text" class="sd-input rounded-l-none" placeholder="bellabeauty.com"
                       value="{{ old('website', $tenant?->website ? preg_replace('#^https?://(www\.)?#', '', $tenant->website) : '') }}">
            </div>
        </div>

        <div>
            <label for="logo" class="block text-[13px] font-medium text-ink mb-1.5">Upload logo</label>
            <div class="flex items-center gap-4">
                @if ($tenant?->logo_path)
                    <img id="logo-preview" src="{{ Storage::disk('public')->url($tenant->logo_path) }}" alt=""
                         class="h-14 w-14 rounded-md object-cover border border-line">
                @else
                    <img id="logo-preview" src="" alt="" hidden
                         class="h-14 w-14 rounded-md object-cover border border-line">
                @endif

                <input id="logo" name="logo" type="file" accept="image/jpeg,image/png,image/webp"
                       class="block w-full text-[13px] text-sub file:mr-3 file:h-9 file:px-3 file:rounded-md file:border file:border-stroke file:bg-white file:text-ink file:text-[13px] file:font-semibold">
            </div>
            <p class="mt-1.5 text-[12px] text-sub">JPG, PNG or WEBP, up to 2&nbsp;MB. You can add this later.</p>
            @error('logo')<p class="mt-1.5 text-[12px] text-danger">{{ $message }}</p>@enderror
        </div>

        <div class="pt-2">
            <button type="submit" class="h-11 px-6 rounded-lg bg-brand hover:bg-brand-dark text-white text-[14px] font-semibold transition-colors">
                Continue
            </button>
        </div>
    </form>
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
    /* Logo preview. Reads the chosen file locally rather than uploading it
       first, so the user sees what they picked before committing the step. */
    document.addEventListener('DOMContentLoaded', function () {
        var input = document.getElementById('logo');
        var preview = document.getElementById('logo-preview');

        if (!input || !preview) return;

        input.addEventListener('change', function () {
            var file = input.files && input.files[0];

            if (!file) {
                preview.hidden = true;
                return;
            }

            preview.src = URL.createObjectURL(file);
            preview.hidden = false;
        });
    });
</script>
@endpush
