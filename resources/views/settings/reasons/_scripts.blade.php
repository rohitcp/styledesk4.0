<script>
  /* The reason dialog: add and edit through one form, pointed at the right
     route and filled in — rather than two copies of three fields that can
     drift apart. The service and resource catalogues do the same. */
  (function () {
    var modal = document.getElementById('reasonModal');
    if (!modal) return;

    var form = modal.querySelector('[data-reason-form]');
    var method = modal.querySelector('[data-reason-method]');
    var title = modal.querySelector('#reasonModalTitle');
    var systemNote = modal.querySelector('[data-reason-system]');
    var addAction = @json(route('settings.reasons.store', $type));
    var addLabel = @json(__('reasons.add_title'));
    var editLabel = @json(__('reasons.edit_title'));

    var nameField = form.querySelector('[name="name"]');
    var descriptionField = form.querySelector('[name="description"]');
    var detailsField = modal.querySelector('[data-reason-details]');

    function open() {
      modal.hidden = false;
      document.body.style.overflow = 'hidden';
      nameField.focus();
    }

    function close() {
      modal.hidden = true;
      document.body.style.overflow = '';
    }

    function fill(values) {
      nameField.value = values.name || '';
      descriptionField.value = values.description || '';
      detailsField.checked = !!values.requires_details;
      /* Said only where it applies: a StyleDesk reason can be renamed like
         any other, and what it cannot be is removed. */
      systemNote.hidden = !values.system;
    }

    document.querySelectorAll('[data-reason-add]').forEach(function (button) {
      button.addEventListener('click', function () {
        form.action = addAction;
        method.value = 'POST';
        title.textContent = addLabel;
        fill({});
        open();
      });
    });

    /* Delegated: the rows are redrawn by every save, and a listener bound to
       one of them would stop working the first time the list reloaded. */
    document.addEventListener('click', function (event) {
      var trigger = event.target.closest('[data-reason-edit]');
      if (!trigger) return;

      var values;

      try {
        values = JSON.parse(trigger.dataset.reason);
      } catch (error) {
        return;
      }

      form.action = values.url;
      method.value = 'PATCH';
      title.textContent = editLabel;
      fill(values);
      open();
    });

    modal.querySelectorAll('[data-reason-close]').forEach(function (button) {
      button.addEventListener('click', close);
    });

    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && !modal.hidden) close();
    });
  }());

  /* Dragging a row to reorder.

     The hidden inputs travel with their rows, so moving a row in the DOM is
     what changes the order that posts — there is no second list to keep in
     step with what the reader can see. */
  (function () {
    var list = document.querySelector('[data-reason-list]');
    if (!list) return;

    var bar = document.querySelector('[data-reorder-bar]');
    var dragging = null;

    list.addEventListener('dragstart', function (e) {
      var row = e.target.closest('[data-reason-row]');
      if (!row) return;

      /* Not when the press started inside the row's menu. The whole row is
         draggable, so pressing a menu item began a drag instead of clicking
         it — the menu looked dead, and no amount of clicking fixed it. */
      if (e.target.closest('[data-rowmenu]')) {
        e.preventDefault();

        return;
      }

      dragging = row;
      row.classList.add('is-dragging');
      e.dataTransfer.effectAllowed = 'move';
      /* Firefox will not start a drag without something on the transfer. */
      e.dataTransfer.setData('text/plain', row.dataset.id);
    });

    list.addEventListener('dragend', function () {
      if (dragging) dragging.classList.remove('is-dragging');
      dragging = null;
    });

    list.addEventListener('dragover', function (e) {
      if (!dragging) return;
      e.preventDefault();

      var over = e.target.closest('[data-reason-row]');
      if (!over || over === dragging) return;

      /* Above or below, decided by which half of the row the pointer is in:
         judging by the row alone makes the last position unreachable. */
      var box = over.getBoundingClientRect();
      var after = e.clientY > box.top + box.height / 2;

      list.insertBefore(dragging, after ? over.nextSibling : over);

      if (bar) bar.hidden = false;
    });
  }());
</script>
