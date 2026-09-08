{{--
    Read first, edit on request.

    The one piece of script behind every My Account card that shows what is
    stored and keeps the fields out of the way until somebody asks for them.
    Included once per screen, directly after the cards it governs rather than
    pushed to the scripts stack at the end of the body: it then runs while the
    rest of the page is still being parsed, so the fields the markup ships
    open are closed before anything is painted and there is no flash of a form
    nobody asked for.

    The markup always ships in the state a reader with no JavaScript needs —
    fields open, summaries hidden, Edit hidden, Save shown — and this inverts
    it. That way the fallback is not a special case anybody has to remember to
    build; it is what the file already says.

    The contract, all of it optional except the card:

      data-editable-card       one togglable region, carrying
                               data-editing="true|false" for its opening state
      data-editable-edit       the button that opens it (ships hidden)
      data-editable-view       what is shown while closed (ships hidden)
      data-editable-fields     what is shown while open (ships visible)
      data-editable-lock       a control that is simply disabled while closed,
                               for a card whose read view is its own fields
      data-editable-actions    Save and friends, anywhere on the page; shown
                               while any card on it is open (ships visible)
      data-editable-cancel     closes every card and resets its form. Left off
                               where a plain link back to the page is the
                               honest answer, such as a form holding component
                               state that a form reset would not reach.
--}}
<script>
    (function () {
        var cards = document.querySelectorAll('[data-editable-card]');
        if (!cards.length) return;

        var actions = document.querySelectorAll('[data-editable-actions]');
        var each = Array.prototype.forEach;
        var some = Array.prototype.some;

        function isOpen(card) {
            return card.dataset.editing === 'true';
        }

        function paint() {
            each.call(cards, function (card) {
                var on = isOpen(card);

                card.querySelectorAll('[data-editable-view]').forEach(function (el) { el.hidden = on; });
                card.querySelectorAll('[data-editable-fields]').forEach(function (el) { el.hidden = !on; });

                /* A locked control is one whose read view is the control
                   itself — a checkbox matrix reads the same either way, and
                   only needs to stop answering to the mouse. */
                card.querySelectorAll('[data-editable-lock]').forEach(function (el) { el.disabled = !on; });

                var edit = card.querySelector('[data-editable-edit]');
                if (edit) {
                    edit.hidden = on;
                    edit.setAttribute('aria-expanded', on ? 'true' : 'false');
                }
            });

            var live = some.call(cards, isOpen);
            each.call(actions, function (el) { el.hidden = !live; });
        }

        each.call(cards, function (card) {
            var edit = card.querySelector('[data-editable-edit]');
            if (!edit) return;

            edit.addEventListener('click', function () {
                card.dataset.editing = 'true';
                paint();

                /* Straight into the first field: the press was a request to
                   type, not to look at a form. */
                var first = card.querySelector('[data-editable-fields] input:not([type=hidden]):not([disabled]), [data-editable-fields] select, [data-editable-fields] textarea')
                    || card.querySelector('[data-editable-lock]:not([disabled])');

                if (first) first.focus();
            });
        });

        document.querySelectorAll('[data-editable-cancel]').forEach(function (cancel) {
            cancel.addEventListener('click', function (event) {
                event.preventDefault();

                /* reset() puts back what the server sent, so a cancelled edit
                   leaves the summary and the fields agreeing again. */
                var form = cancel.closest('form');
                if (form) form.reset();

                each.call(cards, function (card) { card.dataset.editing = 'false'; });
                paint();

                var edit = cards[0].querySelector('[data-editable-edit]');
                if (edit) edit.focus();
            });
        });

        paint();
    })();
</script>
