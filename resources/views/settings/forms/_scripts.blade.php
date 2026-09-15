<script>
  /* The create dialog.

     Opening and closing it, and making sure one press is one form. The
     validation underneath is the shared live-validation module reading the
     rules off the fields — this does not duplicate any of it. */
  (function () {
    var modal = document.getElementById('formModal');
    if (!modal) return;

    var form = modal.querySelector('[data-form-create]');
    var submit = modal.querySelector('[data-form-submit]');
    var nameField = form.querySelector('[name="name"]');

    function open() {
      modal.hidden = false;
      document.body.style.overflow = 'hidden';
      if (nameField) nameField.focus();
    }

    function close() {
      modal.hidden = true;
      document.body.style.overflow = '';
    }

    document.querySelectorAll('[data-form-add]').forEach(function (button) {
      button.addEventListener('click', function () {
        /* Not reset: a refused submission comes back with what was typed
           still in the fields, and clearing them here would throw away the
           thing the reader is trying to correct. */
        open();
      });
    });

    modal.querySelectorAll('[data-form-close]').forEach(function (button) {
      button.addEventListener('click', close);
    });

    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && !modal.hidden) close();
    });

    /*
     * One press, one form.
     *
     * Guarded on the form's own submit rather than on the button's click, so
     * a submission raised by pressing Enter in a field is covered too. The
     * button is only disabled once the browser has accepted the submit —
     * disabling it while the fields are still invalid would leave the reader
     * looking at a dead button and an error they cannot resubmit past.
     */
    form.addEventListener('submit', function (e) {
      if (form.dataset.submitting) return;

      /*
       * Only once the submit has actually survived.
       *
       * The live-validation module cancels the event when a rule fails, and
       * it may be listening either side of this one. Checked after the
       * current task rather than here, by which time every listener has run
       * and `defaultPrevented` is the real answer — disabling the button on a
       * refused submit would leave the reader looking at a dead control and
       * an error they cannot resubmit past.
       */
      window.setTimeout(function () {
        if (!submit || e.defaultPrevented) return;

        form.dataset.submitting = '1';
        submit.disabled = true;
        submit.textContent = submit.getAttribute('data-busy-label');
      }, 0);
    });

    /* Reopened on a refusal, with everything still in it. The server is the
       boundary, and a dialog that closed on the way to being told no would
       take the reader's answers with it. */
    if (modal.hasAttribute('data-form-open-on-load')) open();
  }());
</script>
