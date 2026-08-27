{{--
    Warns before an idle session ends, and leaves when it does.

    Without this a tab sits looking signed in until the next click, which then
    lands on a login screen and loses whatever was typed. The countdown runs on
    the same config('session.lifetime') the server enforces, so the two cannot
    disagree about when it happens.

    The server is still the authority; this only decides what an untouched tab
    does in the meantime.
--}}
@auth
    @php
        $timeoutSeconds = \App\Http\Middleware\EnforceSessionTimeout::timeoutSeconds();

        // Two minutes, or a fifth of a very short lifetime: a warning longer
        // than the session it warns about would appear immediately.
        $warnSeconds = (int) min(120, max(30, $timeoutSeconds / 5));
    @endphp

    <div id="sd-timeout" class="styledesk_modal" role="dialog" aria-modal="true"
         aria-labelledby="sd-timeout-title" hidden>
        <div class="styledesk_modal__dialog" style="max-width: 400px">
            <div class="styledesk_modal__head">
                <h2 id="sd-timeout-title" class="styledesk_modal__title">Still there?</h2>
            </div>
            <div class="styledesk_modal__body">
                <p class="text-[13px] text-sub leading-relaxed">
                    You will be signed out in
                    <strong id="sd-timeout-count" class="text-ink">{{ $warnSeconds }}</strong>
                    seconds because this tab has been idle. Anything unsaved will be lost.
                </p>
            </div>
            <div class="styledesk_modal__foot">
                <button type="button" data-timeout-signout
                        class="h-9 px-4 rounded-md text-[13px] font-semibold text-sub hover:bg-hover transition-colors">
                    Sign out now
                </button>
                <button type="button" data-timeout-stay
                        class="h-9 px-4 rounded-md bg-brand hover:bg-brand-dark text-white text-[13px] font-semibold transition-colors">
                    Stay signed in
                </button>
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            (function () {
                var TIMEOUT_MS = {{ $timeoutSeconds * 1000 }};
                var WARN_MS = {{ $warnSeconds * 1000 }};
                var LOGIN_URL = @json(route('login'));
                var KEEP_ALIVE_URL = @json(route('session.keep-alive'));

                if (!TIMEOUT_MS) return;

                var dialog = document.getElementById('sd-timeout');
                var countEl = document.getElementById('sd-timeout-count');
                if (!dialog) return;

                var warnTimer = null;
                var endTimer = null;
                var tickTimer = null;

                function signOut() {
                    var form = document.getElementById('sd-logout-form');
                    if (form) { form.submit(); return; }
                    window.location.href = LOGIN_URL;
                }

                function hide() {
                    dialog.hidden = true;
                    window.clearInterval(tickTimer);
                }

                function warn() {
                    dialog.hidden = false;

                    var remaining = Math.round(WARN_MS / 1000);
                    countEl.textContent = remaining;

                    tickTimer = window.setInterval(function () {
                        remaining -= 1;
                        countEl.textContent = Math.max(0, remaining);
                    }, 1000);
                }

                function schedule() {
                    window.clearTimeout(warnTimer);
                    window.clearTimeout(endTimer);
                    hide();

                    warnTimer = window.setTimeout(warn, Math.max(0, TIMEOUT_MS - WARN_MS));

                    /* A second past the server's limit, so the server has
                       always already decided by the time the tab acts. */
                    endTimer = window.setTimeout(signOut, TIMEOUT_MS + 1000);
                }

                /* Real interaction only. Deliberately not scroll or mousemove:
                   a trackpad nudge or a phone in a pocket would keep a session
                   alive forever, which is the opposite of a timeout. */
                ['click', 'keydown', 'submit'].forEach(function (event) {
                    document.addEventListener(event, function () {
                        if (dialog.hidden) schedule();
                    }, true);
                });

                dialog.addEventListener('click', function (e) {
                    if (e.target.closest('[data-timeout-signout]')) {
                        signOut();

                        return;
                    }

                    if (e.target.closest('[data-timeout-stay]')) {
                        /* Touch the server as well. Dismissing the dialog on
                           its own would restart this countdown while the
                           server's kept running, and the next click would land
                           on a login screen anyway. */
                        var token = document.querySelector('meta[name=csrf-token]');

                        fetch(KEEP_ALIVE_URL, {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': token ? token.getAttribute('content') : '',
                                'Accept': 'application/json'
                            },
                            credentials: 'same-origin'
                        }).finally(schedule);
                    }
                });

                schedule();
            }());
        </script>
    @endpush
@endauth
