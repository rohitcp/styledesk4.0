{{--
    One confirmation dialog, shared by every screen.

    Replaces window.confirm, which cannot be styled, cannot say what will
    happen in the product's own words, and blocks the page — including, in a
    browser-automated session, everything that comes after it.

    The wording is supplied by whatever opens it, so this file never has to
    know what is being confirmed. It carries no default message: a dialog that
    said "Are you sure?" when a caller forgot to pass one would be asking
    someone to approve something the screen never named.
--}}
<div id="sdConfirm" class="styledesk_modal" hidden>
    <div class="styledesk_modal__scrim" data-confirm-dismiss></div>

    <div class="styledesk_modal__panel" role="dialog" aria-modal="true" aria-labelledby="sdConfirmTitle"
         aria-describedby="sdConfirmBody">
        <div class="styledesk_modal__head">
            <h2 id="sdConfirmTitle" class="text-[15px] font-semibold text-head"></h2>

            <button type="button" class="styledesk_modal__close" data-confirm-dismiss
                    aria-label="{{ __('common.close') }}">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
            </button>
        </div>

        <div class="styledesk_modal__body">
            <p id="sdConfirmBody" class="text-[14px] text-ink leading-relaxed"></p>
        </div>

        <div class="styledesk_modalfoot">
            {{-- The action's own name, never "OK": the button should say what
                 pressing it does, so a reader who skipped the sentence still
                 knows. It comes first, because it is what the dialog is
                 about. --}}
            <button type="button" data-confirm-accept
                    class="h-9 px-4 inline-flex items-center rounded-lg bg-danger hover:opacity-90 text-white text-[13px] font-semibold transition-colors">
            </button>

            {{-- Named by the caller when "Cancel" would be ambiguous: beside
                 "Discard", Cancel reads as a second way of abandoning the
                 work rather than as the way back to it. --}}
            <button type="button" data-confirm-dismiss
                    class="styledesk_action" data-confirm-dismiss-label>
                {{ __('common.cancel') }}
            </button>
        </div>
    </div>
</div>
