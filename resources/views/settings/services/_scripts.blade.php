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

    function open() {
      modal.hidden = false;
      document.body.style.overflow = 'hidden';
      form.querySelector('[name="name"]').focus();
    }

    function close() {
      modal.hidden = true;
      document.body.style.overflow = '';
    }

    document.querySelectorAll('[data-category-add]').forEach(function (button) {
      button.addEventListener('click', function () {
        form.reset();
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

  /* Dragging a row to reorder.

     The hidden inputs travel with their rows, so moving a row in the DOM is
     what changes the order that posts — there is no second list to keep in
     step with what the reader can see. */
  (function () {
    var list = document.querySelector('[data-category-list]');
    if (!list) return;

    var bar = document.querySelector('[data-reorder-bar]');
    var dragging = null;

    list.addEventListener('dragstart', function (e) {
      var row = e.target.closest('[data-category-row]');
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

      var over = e.target.closest('[data-category-row]');
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
