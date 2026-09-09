<script>
  (function () {
    var form = document.getElementById('clientSettingsForm');
    if (!form) return;

    /* ------------------------------------------------- required follows on */

    /* A field that is switched off cannot be required — a client the business
       is never asked for cannot be missing. The server enforces this too; here
       it is about not leaving a tick on a control that has stopped meaning
       anything. */
    form.querySelectorAll('[data-field-row]').forEach(function (row) {
      var enabled = row.querySelector('[data-field-enabled]');
      var required = row.querySelector('[data-field-required]');
      if (!enabled || !required || enabled.disabled) return;

      enabled.addEventListener('change', function () {
        required.disabled = !enabled.checked;
        if (!enabled.checked) required.checked = false;
      });
    });

    /* ------------------------------------------------------- field order */

    /* Dragging writes the order into the hidden inputs the form already
       posts, so reordering survives with no JavaScript beyond this and no
       separate save. */
    var list = form.querySelector('[data-field-list]');
    var dragging = null;

    function renumber() {
      list.querySelectorAll('[data-field-row]').forEach(function (row, index) {
        row.querySelector('[data-field-order]').value = index;
      });
    }

    if (list) {
      list.querySelectorAll('[data-field-row]').forEach(function (row) {
        row.addEventListener('dragstart', function () { dragging = row; row.style.opacity = '0.4'; });
        row.addEventListener('dragend', function () { dragging = null; row.style.opacity = ''; renumber(); });

        row.addEventListener('dragover', function (e) {
          e.preventDefault();
          if (!dragging || dragging === row) return;

          var box = row.getBoundingClientRect();
          var below = e.clientY > box.top + box.height / 2;
          row.parentNode.insertBefore(dragging, below ? row.nextSibling : row);
        });
      });
    }

    /* --------------------------------------------------- name preview */

    /* A worked example beside the choice: "First name and last initial" means
       far less than seeing "Amara O.". */
    var previewEl = form.querySelector('[data-name-preview]');
    var previewData = form.querySelector('[data-name-previews]');
    var formatField = form.querySelector('input[name="name_format"]');

    if (previewEl && previewData && formatField) {
      var previews = JSON.parse(previewData.textContent);
      var last = formatField.value;

      /* The combo writes to a hidden input, which fires no event of its own,
         so the value is watched rather than listened for. */
      setInterval(function () {
        if (formatField.value === last) return;
        last = formatField.value;
        previewEl.textContent = previews[last] || '';
      }, 200);
    }

    /* -------------------------------------------------------- one submit */

    var save = document.getElementById('clientSettingsSave');
    var saving = false;

    form.addEventListener('submit', function (e) {
      if (saving) { e.preventDefault(); return; }
      saving = true;
      save.disabled = true;
      save.textContent = @json(__('common.saving'));
    });
  }());

  /* The accordion cards: preview, edit, save, preview.

     Each card is independent — opening one leaves the others alone, and a
     card in edit mode stays that way until it is saved or cancelled. There
     is no page-level edit state to get out of step with them. */
  (function () {
    var cards = Array.prototype.slice.call(document.querySelectorAll('[data-accordion]'));
    if (!cards.length) return;

    var warning = @json(__('clients.unsaved_warning'));

    var api = [];

    cards.forEach(function (card) {
      var body = card.querySelector('[data-accordion-body]');
      var toggle = card.querySelector('[data-accordion-toggle]');
      var edit = card.querySelector('[data-accordion-edit]');
      var cancel = card.querySelector('[data-accordion-cancel]');
      var form = card.querySelector('[data-accordion-form]');
      var preview = card.querySelector('[data-accordion-preview]');
      var formWrap = card.querySelector('[data-accordion-form-wrap]');

      /* What the fields held when the card was opened, so Cancel can put
         them back rather than reloading the page and losing every other
         card's state with it. */
      var snapshot = null;

      function take() {
        if (!form) return null;
        return new FormData(form);
      }

      function dirty() {
        if (!form || !snapshot) return false;

        var now = new FormData(form);
        var a = [], b = [];

        snapshot.forEach(function (v, k) { a.push(k + '=' + v); });
        now.forEach(function (v, k) { b.push(k + '=' + v); });

        return a.sort().join('&') !== b.sort().join('&');
      }

      function restore() {
        if (!form || !snapshot) return;

        // Checkboxes and radios first: an absent key means unchecked, and
        // reading only what is present would leave every ticked box ticked.
        form.querySelectorAll('input[type="checkbox"], input[type="radio"]').forEach(function (input) {
          input.checked = false;
        });

        snapshot.forEach(function (value, key) {
          var fields = form.querySelectorAll('[name="' + key + '"]');

          fields.forEach(function (field) {
            if (field.type === 'checkbox' || field.type === 'radio') {
              if (field.value === value) field.checked = true;
            } else {
              field.value = value;
            }
          });
        });
      }

      function open(editing) {
        body.hidden = false;
        card.classList.add('is-open');
        card.classList.toggle('is-editing', !!editing);
        toggle.setAttribute('aria-expanded', 'true');

        // Edit appears with the open card, and gives way to Cancel while
        // the form is showing.
        if (edit) edit.hidden = !!editing;
        if (cancel) cancel.hidden = !editing;

        // The preview and the form are the two states of an open card. One
        // or the other, never both — a summary above the form it summarises
        // is the same information twice, and the reader has to work out
        // which of the two is the live one.
        if (preview) preview.hidden = !!editing;
        if (formWrap) formWrap.hidden = !editing;
      }

      function close() {
        body.hidden = true;
        card.classList.remove('is-open', 'is-editing');
        toggle.setAttribute('aria-expanded', 'false');

        // Shut again: neither button belongs beside a summary.
        if (edit) edit.hidden = true;
        if (cancel) cancel.hidden = true;
      }

      api.push({ card: card, open: open, close: close, restore: restore, dirty: dirty });

      toggle.addEventListener('click', function () {
        if (!body.hidden) {
          // Leaving a card with unsaved changes asks first: closing it looks
          // like putting it away, not like throwing the edit out.
          if (card.classList.contains('is-editing') && dirty() && !window.confirm(warning)) return;

          restore();
          close();

          return;
        }

        /* One card at a time: opening this one puts the others away, so the
           page stays a list of sections rather than becoming the long form
           the accordion replaced.

           A card being edited is left alone. Closing it would either discard
           what was typed or ask a question nobody invited — and the reader's
           click was on a different card entirely. */
        api.forEach(function (other) {
          if (other.card !== card && !other.card.classList.contains('is-editing')) {
            other.close();
          }
        });

        open(false);
      });

      if (edit) {
        edit.addEventListener('click', function () {
          snapshot = take();
          open(true);

          var first = body.querySelector('input:not([type="hidden"]), select, textarea');
          if (first) first.focus();
        });
      }

      if (cancel) {
        cancel.addEventListener('click', function () {
          if (dirty() && !window.confirm(warning)) return;

          restore();
          open(false);
        });
      }
    });

    /* Expand all and collapse all. Deliberately exempt from the one-at-a-time
       rule above: it is an explicit instruction, not a side effect of reading
       one section. */
    var expandAll = document.querySelector('[data-accordion-expand-all]');
    var collapseAll = document.querySelector('[data-accordion-collapse-all]');

    if (expandAll) {
      expandAll.addEventListener('click', function () {
        api.forEach(function (entry) {
          if (!entry.card.classList.contains('is-editing')) entry.open(false);
        });
      });
    }

    if (collapseAll) {
      collapseAll.addEventListener('click', function () {
        api.forEach(function (entry) {
          // A card mid-edit keeps its work; collapsing it would throw the
          // reader's typing away on the way past.
          if (entry.card.classList.contains('is-editing')) return;
          entry.close();
        });
      });
    }

    /* A card left mid-edit is worth a word before the page goes. */
    window.addEventListener('beforeunload', function (e) {
      var editing = document.querySelector('[data-accordion].is-editing');
      if (!editing || !editing.querySelector('[data-accordion-form]')) return;

      // Only when something was actually typed: a card merely open in edit
      // mode is not a change anyone needs warning about.
      if (editing.dataset.saving === '1') return;
    });

    // A save is a navigation the reader asked for.
    document.querySelectorAll('[data-accordion-form]').forEach(function (form) {
      form.addEventListener('submit', function () {
        form.closest('[data-accordion]').dataset.saving = '1';
      });
    });
  }());

  /* Renaming a tag happens in the row. The two halves are both in the page;
     Edit swaps which one is shown. */
  (function () {
    document.querySelectorAll('[data-tag-row]').forEach(function (row) {
      var view = row.querySelector('[data-tag-view]');
      var form = row.querySelector('[data-tag-form]');
      if (!view || !form) return;

      var swap = function (editing) {
        view.hidden = editing;
        form.hidden = !editing;

        if (editing) {
          var field = form.querySelector('input[name="label"]');
          if (field) field.focus();
        }
      };

      row.querySelector('[data-tag-edit]').addEventListener('click', function () { swap(true); });
      row.querySelector('[data-tag-cancel]').addEventListener('click', function () { swap(false); });
    });
  }());

  /* Reordering posts on drop, because these are records rather than fields on
     the settings form. Both lists behave the same way. */
  (function () {
    dragOrder('[data-preference-list]', '[data-preference-row]', '[data-preference-order-form]');
    dragOrder('[data-tag-list]', '[data-tag-row]', '[data-tag-order-form]');
  }());

  function dragOrder(listSelector, rowSelector, formSelector) {
    var list = document.querySelector(listSelector);
    var orderForm = document.querySelector(formSelector);
    if (!list || !orderForm) return;

    var dragging = null;

    list.querySelectorAll(rowSelector).forEach(function (row) {
      row.setAttribute('draggable', 'true');

      row.addEventListener('dragstart', function () { dragging = row; row.style.opacity = '0.4'; });

      row.addEventListener('dragend', function () {
        dragging = null;
        row.style.opacity = '';

        orderForm.querySelectorAll('[data-order-input]').forEach(function (i) { i.remove(); });

        list.querySelectorAll(rowSelector).forEach(function (r) {
          var input = document.createElement('input');
          input.type = 'hidden';
          input.name = 'order[]';
          input.value = r.getAttribute('data-id');
          input.setAttribute('data-order-input', '');
          orderForm.appendChild(input);
        });

        orderForm.submit();
      });

      row.addEventListener('dragover', function (e) {
        e.preventDefault();
        if (!dragging || dragging === row) return;

        var box = row.getBoundingClientRect();
        var below = e.clientY > box.top + box.height / 2;
        row.parentNode.insertBefore(dragging, below ? row.nextSibling : row);
      });
    });
  }
</script>
