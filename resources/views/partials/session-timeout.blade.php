{{--
    Warns before an idle session ends, and blocks the page when it has.

    Two dialogs and one deadline. The deadline is a wall-clock timestamp, not
    a countdown: a long setTimeout is throttled in a background tab and does
    not run at all while the machine is asleep, which is how a tab left open
    overnight could still look signed in the next morning. Comparing "what
    time is it now" against "when does this expire" is immune to both — the
    tab notices the moment it wakes.

    The server is still the authority. This only decides what an untouched tab
    shows in the meantime, and what it does when a request comes back 401.
--}}
@auth
    @php
        $timeoutSeconds = \App\Http\Middleware\EnforceSessionTimeout::timeoutSeconds();
        $warnSeconds = \App\Http\Middleware\EnforceSessionTimeout::warningSeconds();
    @endphp

    {{-- About to expire: dismissible, because there is still a session to
         keep and the reader may want to get back to it. --}}
    <div id="sd-timeout" class="styledesk_modal" role="dialog" aria-modal="true"
         aria-labelledby="sd-timeout-title" hidden>
        <div class="styledesk_modal__dialog" style="max-width: 420px">
            <div class="styledesk_modal__head">
                <h2 id="sd-timeout-title" class="styledesk_modal__title">{{ __('common.session.expiring') }}</h2>
            </div>
            <div class="styledesk_modal__body">
                <p class="text-[13px] text-sub leading-relaxed">
                    {{ __('common.session.expiring_body') }}
                </p>
                {{-- The countdown is a placeholder inside the sentence rather
                     than a label with a number after it: languages put the two
                     in different orders, and a fixed order reads as broken in
                     at least one of them. The markup is ours, not a reader's,
                     so it is printed unescaped. --}}
                <p class="text-[13px] text-ink mt-3">
                    {!! __('common.session.countdown', ['time' => '<strong id="sd-timeout-count" class="tabular-nums">02:00</strong>']) !!}
                </p>
            </div>
            <div class="styledesk_modal__foot">
                <button type="button" data-timeout-signout
                        class="h-9 px-4 rounded-md text-[13px] font-semibold text-sub hover:bg-hover transition-colors">
                    {{ __('common.session.sign_out') }}
                </button>
                <button type="button" data-timeout-stay
                        class="h-9 px-4 rounded-md bg-brand hover:bg-brand-dark text-white text-[13px] font-semibold transition-colors">
                    {{ __('common.session.stay') }}
                </button>
            </div>
        </div>
    </div>

    {{-- Already expired: no close, no scrim click, no Escape. Dismissing it
         would leave the reader looking at a page they can no longer use, and
         every action behind it would fail. --}}
    <div id="sd-expired" class="styledesk_modal" role="alertdialog" aria-modal="true"
         aria-labelledby="sd-expired-title" hidden>
        <div class="styledesk_modal__dialog" style="max-width: 420px">
            <div class="styledesk_modal__head">
                <h2 id="sd-expired-title" class="styledesk_modal__title">{{ __('common.session.expired') }}</h2>
            </div>
            <div class="styledesk_modal__body">
                <p class="text-[13px] text-sub leading-relaxed">
                    {{ __('common.session.expired_body') }}
                </p>
            </div>
            <div class="styledesk_modal__foot">
                <button type="button" data-timeout-signin
                        class="h-9 px-4 rounded-md bg-brand hover:bg-brand-dark text-white text-[13px] font-semibold transition-colors">
                    {{ __('common.session.sign_in') }}
                </button>
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            window.styledeskSession = {
                timeoutMs: {{ $timeoutSeconds * 1000 }},
                warnMs: {{ $warnSeconds * 1000 }},
                loginUrl: @json(route('login')),
                keepAliveUrl: @json(route('session.keep-alive'))
            };
        </script>
    @endpush
@endauth
