{{--
    An image field that uploads as you choose it, showing real progress.

    The percentage comes from XHR upload events, so it reflects bytes actually
    sent rather than a timer pretending to be one. Without JavaScript the file
    input posts with the form as normal, which is why both the input and the
    hidden path field exist.

    Usage: an x-image-upload tag with name, endpoint and label.

    Written out rather than shown as markup: Blade compiles component tags
    before stripping comments, so an x-* tag inside a comment is compiled too.
--}}
@props([
    'name',
    'endpoint',
    'label' => 'Image',
    'hint' => 'JPG, PNG or WEBP, up to 2 MB.',
    'optional' => true,
    'current' => null,
])

@php
    $uploadId = 'upload-'.$name;
@endphp

<div {{ $attributes }} data-image-upload data-endpoint="{{ $endpoint }}" data-field="{{ $name }}">
    <span class="block text-[13px] font-medium text-ink mb-1.5">
        {{ $label }}@if ($optional) <span class="text-faint font-normal">{{ __('common.optional') }}</span>@endif
    </span>

    <div class="flex items-center gap-3">
        <span data-upload-preview
              class="h-11 w-11 shrink-0 rounded-lg border border-line bg-hover grid place-items-center overflow-hidden text-faint">
            @if ($current)
                <img src="{{ $current }}" alt="" class="h-full w-full object-cover">
            @else
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true"><rect x="3" y="4" width="18" height="16" rx="2.5" stroke="currentColor" stroke-width="1.7"/><circle cx="9" cy="10" r="1.8" stroke="currentColor" stroke-width="1.7"/><path d="M4 17l4.5-4 4 3.2L16 13l4 3.5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
            @endif
        </span>

        <input id="{{ $uploadId }}" name="{{ $name }}" type="file"
               accept="image/jpeg,image/png,image/webp" class="sr-only">

        <label for="{{ $uploadId }}"
               class="styledesk_action">
            {{ __('common.upload.choose') }}
        </label>

        <button type="button" data-upload-remove hidden
                data-confirm-title="{{ __('common.remove') }}"
                data-confirm="{{ __('branding.remove_confirm') }}"
                data-confirm-label="{{ __('common.remove') }}"
                class="h-9 px-3 rounded-md text-sub hover:text-danger hover:bg-hover text-[13px] font-semibold transition-colors">
            {{ __('common.remove') }}
        </button>

        <span class="min-w-0 flex-1 text-[12px] text-sub truncate" data-upload-name></span>
    </div>

    {{-- Progress, hidden until there is something to report. --}}
    <div data-upload-progress hidden class="mt-3">
        <div class="flex items-center gap-3">
            <span class="min-w-0 flex-1 text-[12px] text-sub truncate" data-upload-meta></span>
            <span class="text-[12px] font-medium text-sub shrink-0" data-upload-pct>0%</span>
        </div>
        <div class="sd-progress mt-1.5" role="progressbar" aria-valuemin="0" aria-valuemax="100"
             aria-valuenow="0" aria-label="Upload progress">
            <span data-upload-bar style="width:0%"></span>
        </div>
    </div>

    <p class="mt-1.5 text-[12px] text-sub" data-upload-hint>{{ $hint }}</p>
    <p class="mt-1.5 text-[12px] text-danger" data-upload-error hidden role="alert"></p>

    {{-- Set once the async upload succeeds; the server prefers this over the
         file input, so a form submitted after a successful upload does not
         send the bytes twice. --}}
    <input type="hidden" name="{{ $name }}_path" data-upload-path value="">
</div>

@once
    @push('scripts')
        <script>
            /* Image uploads with real progress. */
            document.querySelectorAll('[data-image-upload]').forEach(function (root) {
                var input = root.querySelector('input[type=file]');
                var endpoint = root.getAttribute('data-endpoint');
                var progress = root.querySelector('[data-upload-progress]');
                var bar = root.querySelector('[data-upload-bar]');
                var pct = root.querySelector('[data-upload-pct]');
                var meta = root.querySelector('[data-upload-meta]');
                var nameEl = root.querySelector('[data-upload-name]');
                var errorEl = root.querySelector('[data-upload-error]');
                var pathEl = root.querySelector('[data-upload-path]');
                var removeBtn = root.querySelector('[data-upload-remove]');
                var preview = root.querySelector('[data-upload-preview]');
                var token = document.querySelector('meta[name=csrf-token]');
                var request = null;

                function human(bytes) {
                    return bytes < 1024 * 1024
                        ? Math.round(bytes / 1024) + ' KB'
                        : (bytes / 1024 / 1024).toFixed(1) + ' MB';
                }

                function fail(message) {
                    errorEl.textContent = message;
                    errorEl.hidden = false;
                    progress.hidden = true;
                    input.value = '';
                }

                function reset() {
                    if (request) request.abort();
                    pathEl.value = '';
                    input.value = '';
                    nameEl.textContent = '';
                    progress.hidden = true;
                    errorEl.hidden = true;
                    removeBtn.hidden = true;
                    preview.innerHTML = '';
                }

                removeBtn.addEventListener('click', reset);

                input.addEventListener('change', function () {
                    var file = input.files && input.files[0];
                    if (!file) return;

                    errorEl.hidden = true;

                    /* Checked here as well as on the server. The server is the
                       authority; this only saves someone watching a 40 MB file
                       upload before being told it was never allowed. */
                    if (file.size > 2 * 1024 * 1024) {
                        fail(@json(__('common.upload.too_large')));
                        return;
                    }

                    nameEl.textContent = file.name;
                    meta.textContent = human(file.size);
                    removeBtn.hidden = false;
                    progress.hidden = false;
                    bar.style.width = '0%';
                    pct.textContent = '0%';

                    var body = new FormData();
                    body.append('image', file);

                    request = new XMLHttpRequest();
                    request.open('POST', endpoint);
                    request.setRequestHeader('Accept', 'application/json');
                    if (token) request.setRequestHeader('X-CSRF-TOKEN', token.getAttribute('content'));

                    request.upload.addEventListener('progress', function (e) {
                        if (!e.lengthComputable) return;
                        var value = Math.round((e.loaded / e.total) * 100);
                        bar.style.width = value + '%';
                        pct.textContent = value + '%';
                        progress.querySelector('[role=progressbar]')?.setAttribute('aria-valuenow', value);
                    });

                    request.addEventListener('load', function () {
                        var body = {};
                        try { body = JSON.parse(request.responseText); } catch (e) { /* handled below */ }

                        if (request.status >= 200 && request.status < 300 && body.path) {
                            pathEl.value = body.path;
                            bar.style.width = '100%';
                            pct.textContent = '100%';
                            if (body.url) {
                                preview.innerHTML = '<img alt="" class="h-full w-full object-cover">';
                                preview.querySelector('img').src = body.url;
                            }
                            /* The file input is cleared once the path is held:
                               otherwise the form posts the bytes a second time
                               alongside a path that already points at them. */
                            input.value = '';
                            return;
                        }

                        fail(body.message || @json(__('common.upload.failed')));
                    });

                    request.addEventListener('error', function () {
                        fail('We could not reach StyleDesk. Check your connection and try again.');
                    });

                    request.send(body);
                });
            });
        </script>
    @endpush
@endonce
