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
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <path d="M5 12.5l4.5 4.5L19 7.5" stroke="currentColor" stroke-width="2.4"
                          stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </span>
            <span class="min-w-0 flex-1">{{ $toast['message'] }}</span>
            <button type="button" class="styledesk_toast__close" data-toast-close aria-label="Dismiss">
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

                function show(message, type) {
                    window.clearTimeout(timer);
                    host.className = 'styledesk_toast styledesk_toast--' + (type || 'success');
                    host.innerHTML =
                        '<div class="styledesk_toast__body" role="status">' +
                          '<span class="styledesk_toast__icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true">' +
                          '<path d="M5 12.5l4.5 4.5L19 7.5" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/></svg></span>' +
                          '<span class="min-w-0 flex-1"></span>' +
                          '<button type="button" class="styledesk_toast__close" data-toast-close aria-label="Dismiss">' +
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
