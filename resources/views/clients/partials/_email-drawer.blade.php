{{--
    Send Email — a drawer over the profile.

    A drawer rather than a page: the sender is usually looking at the very
    thing they are writing about — a balance, a missed appointment — and
    sending them to a second screen loses it.

    Everything it needs is fetched when it opens rather than rendered with the
    page. A profile is opened many times a day and written from rarely, and
    eight templates rendered against the client on every page load is work
    nobody asked for.
--}}
<div data-email-drawer hidden class="fixed inset-0 z-[80] flex justify-end">
    <div class="absolute inset-0 bg-black/30" aria-hidden="true" data-email-close></div>

    <div class="relative w-full sm:max-w-[480px] h-full bg-white shadow-xl flex flex-col"
         role="dialog" aria-modal="true" aria-label="{{ __('client_email.send.title') }}">

        <header class="shrink-0 flex items-center gap-3 px-4 py-3 border-b border-line">
            <h2 class="min-w-0 flex-1 text-[15px] font-bold text-head">{{ __('client_email.send.title') }}</h2>
            <button type="button" class="sd-iconbtn grid place-items-center shrink-0"
                    data-email-close aria-label="{{ __('client_email.send.cancel') }}">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"/></svg>
            </button>
        </header>

        <form data-email-form class="flex-1 min-h-0 flex flex-col">
            <div class="flex-1 min-h-0 overflow-y-auto styledesk_scroll p-4 space-y-4">

                {{-- Why this cannot be sent, said before anything is typed
                     rather than after. --}}
                <p data-email-blocked hidden class="sd-alert sd-alert--warn text-[12.5px]" role="alert"></p>
                <p data-email-error hidden class="sd-alert sd-alert--danger text-[12.5px]" role="alert"></p>

                <div class="rounded-card border border-line bg-[#fbfbfc] p-3 space-y-2 text-[13px]">
                    <div>
                        <span class="block text-[12px] text-sub">{{ __('client_email.send.to') }}</span>
                        <span class="block font-semibold text-head" data-email-to-name></span>
                        <span class="block text-[12.5px] text-sub" data-email-to-address></span>
                    </div>

                    <div class="pt-2 border-t border-line">
                        <span class="block text-[12px] text-sub">{{ __('client_email.send.from') }}</span>
                        <span class="block font-semibold text-head" data-email-from-label></span>
                        <span class="block text-[12.5px] text-sub" data-email-from-address></span>
                    </div>
                </div>

                <div>
                    <label for="email-template" class="block text-[13px] font-medium text-ink mb-1.5">
                        {{ __('client_email.send.template') }}
                    </label>
                    <select id="email-template" data-email-template class="sd-input">
                        <option value="">{{ __('client_email.send.no_template') }}</option>
                    </select>
                </div>

                <div>
                    <label for="email-booking" class="block text-[13px] font-medium text-ink mb-1.5">
                        {{ __('client_email.send.related_booking') }}
                    </label>
                    <select id="email-booking" data-email-booking class="sd-input">
                        <option value="">{{ __('client_email.send.no_booking') }}</option>
                    </select>
                </div>

                <div>
                    <label for="email-subject" class="block text-[13px] font-medium text-ink mb-1.5">
                        {{ __('client_email.send.subject') }}
                    </label>
                    <input id="email-subject" data-email-subject type="text" class="sd-input" required
                           maxlength="{{ config('client_email.limits.subject') }}">
                </div>

                <div>
                    <label for="email-message" class="block text-[13px] font-medium text-ink mb-1.5">
                        {{ __('client_email.send.message') }}
                    </label>
                    <textarea id="email-message" data-email-message rows="12" class="sd-input" required
                              maxlength="{{ config('client_email.limits.message') }}"></textarea>
                </div>
            </div>

            <footer class="shrink-0 flex items-center justify-end gap-2.5 px-4 py-3 border-t border-line bg-[#fbfbfc]">
                <button type="button" data-email-close
                        class="h-10 px-4 rounded-lg border border-line bg-white hover:bg-black/[0.03] text-[13px] font-semibold text-head transition-colors">
                    {{ __('client_email.send.cancel') }}
                </button>
                <button type="submit" data-email-submit
                        class="h-10 px-4 rounded-lg bg-brand hover:bg-brand-dark text-white text-[13px] font-semibold transition-colors disabled:opacity-50">
                    {{ __('client_email.send.submit') }}
                </button>
            </footer>
        </form>
    </div>
</div>
