<script>
  /* The category dialog: add and edit through one form, pointed at the right
     route and filled in — rather than two copies of three fields that can
     drift apart. */
  (function () {
    var modal = document.getElementById('categoryModal');
    if (!modal) return;

    var form = modal.querySelector('[data-category-form]');
    var method = modal.querySelector('[data-category-method]');
    var title = modal.querySelector('#categoryModalTitle');
    var systemNote = modal.querySelector('[data-category-system]');
    var addAction = @json(route('settings.services.store'));
    var editAction = @json(route('settings.services.update', ['serviceCategory' => '__ID__']));
    var addLabel = @json(__('services.categories_ui.add'));
    var editLabel = @json(__('services.categories_ui.edit'));

    var nameField = form.querySelector('[name="name"]');
    var checkUrl = @json(route('settings.services.name-in-use'));

    /**
     * Which row the duplicate-name check should ignore.
     *
     * One dialog serves add and edit, so the URL cannot be fixed in the
     * markup: editing "Colour" and leaving the name alone would otherwise
     * report it as already taken — by itself.
     */
    function aimNameCheck(ignoreId) {
      nameField.setAttribute('data-remote-check', ignoreId ? checkUrl + '?ignore=' + ignoreId : checkUrl);
    }

    function open() {
      modal.hidden = false;
      document.body.style.overflow = 'hidden';
      nameField.focus();
    }

    /* A dialog reopened after a refusal must not still be showing it: the
       fields have been reset, so the messages under them are about values
       nobody can see any more. */
    function clearErrors() {
      if (window.SD && typeof window.SD.clearErrors === 'function') {
        window.SD.clearErrors(form);
      }
    }

    function close() {
      modal.hidden = true;
      document.body.style.overflow = '';
    }

    document.querySelectorAll('[data-category-add]').forEach(function (button) {
      button.addEventListener('click', function () {
        form.reset();
        clearErrors();
        aimNameCheck(null);
        form.setAttribute('action', addAction);
        method.value = 'POST';
        title.textContent = addLabel;
        systemNote.hidden = true;
        open();
      });
    });

    document.addEventListener('click', function (e) {
      var button = e.target.closest('[data-category-edit]');
      if (!button) return;

      var data = JSON.parse(button.getAttribute('data-category'));

      form.reset();
      clearErrors();
      aimNameCheck(data.id);
      form.setAttribute('action', editAction.replace('__ID__', data.id));
      method.value = 'PATCH';
      title.textContent = editLabel;
      systemNote.hidden = !data.system;

      form.querySelector('[name="name"]').value = data.name || '';
      form.querySelector('[name="description"]').value = data.description || '';

      open();
    });

    modal.querySelectorAll('[data-category-close]').forEach(function (button) {
      button.addEventListener('click', close);
    });

    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && !modal.hidden) close();
    });
  }());

</script>
