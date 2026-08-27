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

  /* Preference reordering posts on drop, because these are records rather
     than fields on the settings form. */
  (function () {
    var list = document.querySelector('[data-preference-list]');
    var orderForm = document.querySelector('[data-preference-order-form]');
    if (!list || !orderForm) return;

    var dragging = null;

    list.querySelectorAll('[data-preference-row]').forEach(function (row) {
      row.setAttribute('draggable', 'true');

      row.addEventListener('dragstart', function () { dragging = row; row.style.opacity = '0.4'; });

      row.addEventListener('dragend', function () {
        dragging = null;
        row.style.opacity = '';

        orderForm.querySelectorAll('[data-order-input]').forEach(function (i) { i.remove(); });

        list.querySelectorAll('[data-preference-row]').forEach(function (r) {
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
  }());
</script>
