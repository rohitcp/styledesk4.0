{{--
    A transient confirmation, centred at the top of the viewport.

    Rendered from a `toast` flash rather than from `status`, because `status`
    is already used for several unrelated things — a page can flash it meaning
    "here is a notice" and would otherwise get a green success toast for a
    message that is not a success.

    Also exposed as window.styledesk.toast(message, type) so a script can raise
    one without a round trip. Deliberately NOT window.SD.toast: the prototype
    bundle already owns that name (prototype/styledesk.js), and because the
    bundle is a deferred module it executes after this inline script — so
    assigning there is silently overwritten and the call goes to the other
    implementation.
--}}
@php
    $toast = session('toast');
    $toast = is_string($toast) ? ['type' => 'success', 'message' => $toast] : $toast;
@endphp

<div id="sd-toast-host" aria-live="polite" aria-atomic="true"
     @class(['styledesk_toast', 'styledesk_toast--'.($toast['type'] ?? 'success')])
     @if (! $toast) hidden @endif>
    @if ($toast)
        <div class="styledesk_toast__body" role="status">
            <span class="styledesk_toast__icon">
                @if (($toast['type'] ?? 'success') === 'danger')
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.8"/>
                        <path d="M12 7.5v5M12 16v.4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                @else
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <path d="M5 12.5l4.5 4.5L19 7.5" stroke="currentColor" stroke-width="2.4"
                              stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                @endif
            </span>
            {{-- A second line where one was flashed, and no markup at all
                 where one was not: every existing caller flashes a message
                 only, and an empty element under it would still take space. --}}
            <span class="min-w-0 flex-1">
                {{ $toast['message'] }}

                @if (! empty($toast['hint']))
                    <span class="block text-[12px] opacity-80 mt-0.5">{{ $toast['hint'] }}</span>
                @endif
            </span>
            <button type="button" class="styledesk_toast__close" data-toast-close aria-label="{{ __('common.dismiss') }}">
                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="2.6" stroke-linecap="round"/>
                </svg>
            </button>
        </div>
    @endif
</div>

@once
    @push('scripts')
        <script>
            /* Toasts.
               One host element holds at most one toast, which is what makes
               "only one per save" true by construction rather than by the
               caller remembering not to raise two. */
            (function () {
                var host = document.getElementById('sd-toast-host');
                if (!host) return;

                var timer = null;

                function dismiss() {
                    window.clearTimeout(timer);
                    if (host.hidden) return;

                    host.classList.add('is-leaving');
                    /* Wait for the exit animation, but not on its event: a
                       reduced-motion user's animation may not fire at all. */
                    window.setTimeout(function () {
                        host.hidden = true;
                        host.classList.remove('is-leaving');
                        host.innerHTML = '';
                    }, 180);
                }

                var TICK = '<path d="M5 12.5l4.5 4.5L19 7.5" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/>';
                var ALERT = '<circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.8"/>' +
                            '<path d="M12 7.5v5M12 16v.4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>';

                /* Encoded rather than interpolated into the attribute: the
                   word is a translation, and a language whose word for this
                   carries an apostrophe would otherwise end the JavaScript
                   string it sits in. */
                var DISMISS = @json(__('common.dismiss'));

                function show(message, type) {
                    window.clearTimeout(timer);
                    host.className = 'styledesk_toast styledesk_toast--' + (type || 'success');
                    host.innerHTML =
                        '<div class="styledesk_toast__body" role="' + (type === 'danger' ? 'alert' : 'status') + '">' +
                          '<span class="styledesk_toast__icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true">' +
                          (type === 'danger' ? ALERT : TICK) + '</svg></span>' +
                          '<span class="min-w-0 flex-1"></span>' +
                          '<button type="button" class="styledesk_toast__close" data-toast-close aria-label=' + JSON.stringify(DISMISS) + '>' +
                          '<svg width="11" height="11" viewBox="0 0 24 24" fill="none" aria-hidden="true">' +
                          '<path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="2.6" stroke-linecap="round"/></svg></button>' +
                        '</div>';
                    /* textContent, not string concatenation: the message can
                       carry a business name someone typed. */
                    host.querySelector('.min-w-0').textContent = message;
                    host.hidden = false;
                    arm();
                }

                function arm() {
                    /* Errors stay until dismissed. A success is a receipt and
                       can expire; a failure is an instruction to do something,
                       and one that disappears while being read is the same as
                       one that was never shown. */
                    if (host.classList.contains('styledesk_toast--danger')) return;

                    timer = window.setTimeout(dismiss, 4000);
                }

                host.addEventListener('click', function (e) {
                    if (e.target.closest('[data-toast-close]')) dismiss();
                });

                /* Hovering pauses the countdown — a toast that vanishes while
                   being read is the same as one that was never shown. */
                host.addEventListener('mouseenter', function () { window.clearTimeout(timer); });
                host.addEventListener('mouseleave', function () { if (!host.hidden) arm(); });

                if (!host.hidden) arm();

                /* Own namespace. SD.toast belongs to the prototype bundle,
                   which loads after this script and would overwrite it. */
                window.styledesk = window.styledesk || {};
                window.styledesk.toast = show;
                window.styledesk.dismissToast = dismiss;
            }());
        </script>
    @endpush
@endonce
