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
    var addAction = @json(route('settings.resources.store'));
    var editAction = @json(route('settings.resources.update', ['resourceCategory' => '__ID__']));
    var addLabel = @json(__('resources.categories_ui.add'));
    var editLabel = @json(__('resources.categories_ui.edit'));

    function open() {
      modal.hidden = false;
      document.body.style.overflow = 'hidden';
      form.querySelector('[name="name"]').focus();
    }

    function close() {
      modal.hidden = true;
      document.body.style.overflow = '';
    }

    /* A combo is a Vue island: it is told rather than written to, or its
       hidden input is overwritten on the next render while the button carries
       on showing the old label. */
    function setGroup(value) {
      document.dispatchEvent(new CustomEvent('styledesk:filter-set', {
        detail: { name: 'group', values: value ? [String(value)] : [] },
      }));
    }

    document.querySelectorAll('[data-category-add]').forEach(function (button) {
      button.addEventListener('click', function () {
        form.reset();
        form.setAttribute('action', addAction);
        method.value = 'POST';
        title.textContent = addLabel;
        systemNote.hidden = true;
        setGroup('');
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
      form.querySelector('[name="default_capacity"]').value = data.capacity || 1;
      setGroup(data.group);

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

<script>
    /*
     * The numbering preview.
     *
     * Redrawn from the prefix and the width as they are typed, so the reader
     * sees "RES-001" rather than having to picture what two fields called
     * "prefix" and "width" will produce. The number itself is whatever the
     * server said was next — this only re-dresses it, and never invents one:
     * which number is free is a question about the database.
     */
    (function () {
        var form = document.querySelector('[data-code-format]');
        if (!form) return;

        var prefix = form.querySelector('[data-code-prefix]');
        var padding = form.querySelector('[data-code-padding]');
        var preview = form.querySelector('[data-code-preview]');

        /* The digits of the code the server rendered, so a business already
           on RES-014 previews 015 rather than 001. */
        var current = (preview.textContent.match(/(\d+)\s*$/) || [])[1] || '1';
        var fallback = @json(config('resources.code.prefix'));

        function paint() {
            var width = Number(padding.value) || 1;
            var number = String(Number(current));

            while (number.length < width) number = '0' + number;

            preview.textContent = (prefix.value.trim() || fallback) + number;
        }

        prefix.addEventListener('input', paint);
        padding.addEventListener('change', paint);
    }());
</script>
