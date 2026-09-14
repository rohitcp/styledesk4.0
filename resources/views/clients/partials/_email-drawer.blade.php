{{--
    Send Email — a composer, not a dialog.

    It floats at the bottom right and takes nothing over: the sender is
    usually looking at the very thing they are writing about — a balance, a
    missed appointment — and a modal that greys out the profile hides the one
    screen they need. Minimised it becomes a title bar, so a half-written
    message survives a look at the client's history.

    Everything it needs is fetched when it opens rather than rendered with the
    page. A profile is opened many times a day and written from rarely, and
    eight templates rendered against the client on every page load is work
    nobody asked for.

    No `aria-modal`, deliberately: nothing behind it is inert, and claiming
    otherwise would tell a screen reader the opposite of what is true.
--}}
<div data-email-drawer hidden class="styledesk_compose" data-state="open"
     role="dialog" aria-label="{{ __('client_email.send.title') }}">

    <header class="styledesk_compose__head">
        {{-- The whole bar restores a minimised composer, the way Gmail's
             does: the title is the biggest target and the one people aim
             for. --}}
        <button type="button" class="styledesk_compose__title" data-email-restore>
            {{ __('client_email.send.new_message') }}
        </button>

        <button type="button" class="styledesk_compose__icon" data-email-minimise
                aria-label="{{ __('client_email.send.minimise') }}"
                data-tip="{{ __('client_email.send.minimise') }}">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M5 12h14" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/></svg>
        </button>

        <button type="button" class="styledesk_compose__icon" data-email-expand
                aria-label="{{ __('client_email.send.expand') }}"
                data-tip="{{ __('client_email.send.expand') }}">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M9 4H4v5M15 20h5v-5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </button>

        <button type="button" class="styledesk_compose__icon" data-email-close
                aria-label="{{ __('client_email.send.cancel') }}"
                data-tip="{{ __('client_email.send.cancel') }}">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"/></svg>
        </button>
    </header>

    <div class="styledesk_compose__body">

        <form data-email-form class="flex-1 min-h-0 flex flex-col">
            <div class="flex-1 min-h-0 overflow-y-auto styledesk_scroll p-4 space-y-4">

                {{-- Why this cannot be sent, said before anything is typed
                     rather than after. --}}
                <p data-email-blocked hidden class="sd-alert sd-alert--warn text-[12.5px]" role="alert"></p>
                <p data-email-error hidden class="sd-alert sd-alert--danger text-[12.5px]" role="alert"></p>

                {{-- A template still holding a placeholder the server had no
                     answer for. Nearly always a wording that asks about an
                     appointment when none has been chosen, which the Related
                     booking field below fixes. --}}
                <p data-email-unresolved hidden class="sd-alert sd-alert--warn text-[12.5px]" role="status">
                    {{ __('client_email.send.unresolved') }}
                </p>

                {{-- To, From and Reply-to across one row rather than stacked.

                     They are three answers to the same question — who this is
                     between — and read together in a glance. Stacked they took
                     a third of a composer that has a message to fit in, and
                     the reader scrolled past the subject line to reach the
                     body. Wraps to two on a narrow panel, where a row of three
                     would be three cramped columns. --}}
                <div class="rounded-card border border-line bg-[#fbfbfc] p-3 text-[13px]
                            grid gap-3 sm:grid-cols-3">
                    <div class="min-w-0">
                        <span class="block text-[12px] text-sub">{{ __('client_email.send.to') }}</span>
                        <span class="block font-semibold text-head truncate" data-email-to-name></span>

                        {{-- One address, and it is a fact; several, and it is
                             a choice. The select is shown only in the second
                             case: a dropdown with one option in it is a
                             control that asks a question with one answer. --}}
                        <span class="block text-[12.5px] text-sub break-all" data-email-to-address></span>

                        <select class="sd-input !h-9 text-[12.5px] mt-1.5" data-email-to-select hidden
                                aria-label="{{ __('client_email.send.to') }}"></select>
                    </div>

                    <div class="min-w-0">
                        <span class="block text-[12px] text-sub">{{ __('client_email.send.from') }}</span>
                        <span class="block font-semibold text-head truncate" data-email-from-label></span>
                        <span class="block text-[12.5px] text-sub break-all" data-email-from-address></span>
                    </div>

                    {{-- Where a reply lands, which is not always where it was
                         sent from. Read-only: it is a setting rather than a
                         per-message decision, and the screen that changes it
                         is App Settings. --}}
                    <div class="min-w-0" data-email-reply-row hidden>
                        <span class="block text-[12px] text-sub">{{ __('client_email.send.reply_to') }}</span>
                        <span class="block text-[12.5px] text-sub break-all" data-email-reply-address></span>
                    </div>
                </div>

                <div>
                    <label for="email-template" class="block text-[13px] font-medium text-ink mb-1.5">
                        {{ __('client_email.send.template') }}
                    </label>
                    <select id="email-template" data-email-template class="sd-input"
                            data-search-label="{{ __('client_email.send.search_templates') }}">
                        <option value="">{{ __('client_email.send.no_template') }}</option>
                    </select>
                </div>

                {{-- Only where the chosen wording asks about an appointment.

                     A thank-you for a visit needs to know which visit; "your
                     card is about to expire" does not, and offering the field
                     anyway is a question with no answer attached to most of
                     the templates on the list. --}}
                <div data-email-booking-field hidden>
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

            {{-- Send on the left, the way a composer reads: the action is
                 the first thing the eye lands on when the message is done,
                 not the last thing after a row of tools. --}}
            <footer class="styledesk_compose__foot">
                <button type="submit" data-email-submit
                        class="h-9 px-5 rounded-lg bg-brand hover:bg-brand-dark text-white text-[13px] font-semibold transition-colors disabled:opacity-50">
                    {{ __('client_email.send.submit') }}
                </button>

                <span class="flex-1"></span>

                {{-- Throw it away. Asks first where there is something to
                     lose, which is the discard prompt rather than this
                     button's own business. --}}
                <button type="button" class="styledesk_compose__icon" data-email-discard
                        aria-label="{{ __('client_email.send.discard') }}"
                        data-tip="{{ __('client_email.send.discard') }}">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M5 7h14M10 7V5h4v2M7 7l1 12h8l1-12" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </button>
            </footer>
        </form>
    </div>
</div>

{{-- Closing with something written asks before it throws it away. A composer
     that emptied itself on a mis-clicked × is one nobody trusts with more
     than a sentence. --}}
<div data-email-discard-ask hidden class="styledesk_modal">
    <div class="styledesk_modal__scrim" data-email-keep></div>

    <div class="styledesk_modal__panel" role="dialog" aria-modal="true" aria-labelledby="emailDiscardTitle">
        <div class="styledesk_modal__head">
            <h2 id="emailDiscardTitle" class="text-[15px] font-semibold text-head">
                {{ __('client_email.send.discard_title') }}
            </h2>
        </div>

        <div class="styledesk_modal__body">
            <p class="text-[13px] text-sub leading-relaxed">{{ __('client_email.send.discard_body') }}</p>
        </div>

        <div class="styledesk_modalfoot">
            <button type="button" data-email-discard-confirm
                    class="h-9 px-4 rounded-lg bg-danger hover:opacity-90 text-white text-[13px] font-semibold transition-opacity">
                {{ __('client_email.send.discard') }}
            </button>

            <button type="button" class="styledesk_action" data-email-keep>
                {{ __('client_email.send.keep_draft') }}
            </button>
        </div>
    </div>
</div>
