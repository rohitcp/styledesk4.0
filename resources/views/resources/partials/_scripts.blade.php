<script>
  /* The resource dialogs: add, edit and mark unavailable.

     One form serves add and edit — the script points it at the right route
     and fills it in, rather than the page carrying two copies of the same
     eight fields that can drift apart. */
  (function () {
    var modal = document.getElementById('resourceModal');
    var blockModal = document.getElementById('resourceBlockModal');

    function open(dialog) {
      if (!dialog) return;
      dialog.hidden = false;
      document.body.style.overflow = 'hidden';
    }

    function close(dialog) {
      if (!dialog) return;
      dialog.hidden = true;
      document.body.style.overflow = '';
    }

    if (modal) {
      var form = modal.querySelector('[data-resource-form]');
      var method = modal.querySelector('[data-resource-method]');
      var title = modal.querySelector('#resourceModalTitle');
      var addAction = @json(route('resources.store'));
      var editAction = @json(route('resources.update', ['resource' => '__ID__']));
      var addLabel = @json(__('resources.add'));
      var editLabel = @json(__('resources.edit'));

      /* Set a field whether it is an input or one of the Vue combos, which
         keep their value in a hidden input the island writes. */
      function fill(name, value) {
        var field = form.querySelector('[name="' + name + '"]');
        if (field) field.value = value === null || value === undefined ? '' : value;
      }

      document.querySelectorAll('[data-resource-add]').forEach(function (button) {
        button.addEventListener('click', function () {
          form.reset();
          form.setAttribute('action', addAction);
          method.value = 'POST';
          title.textContent = addLabel;
          open(modal);
          form.querySelector('[name="name"]').focus();
        });
      });

      document.querySelectorAll('[data-resource-edit]').forEach(function (button) {
        button.addEventListener('click', function () {
          var data = JSON.parse(button.getAttribute('data-resource'));

          form.reset();
          form.setAttribute('action', editAction.replace('__ID__', data.id));
          method.value = 'PATCH';
          title.textContent = editLabel;

          fill('name', data.name);
          fill('resource_category_id', data.category);
          fill('location_id', data.location);
          fill('capacity', data.capacity);
          fill('description', data.description);

          open(modal);
          form.querySelector('[name="name"]').focus();
        });
      });

      modal.querySelectorAll('[data-resource-close]').forEach(function (button) {
        button.addEventListener('click', function () { close(modal); });
      });
    }

    if (blockModal) {
      var blockForm = blockModal.querySelector('[data-block-form]');
      var blockTitle = blockModal.querySelector('#resourceBlockTitle');
      var blockAction = @json(route('resources.block', ['resource' => '__ID__']));
      var blockTemplate = @json(__('resources.block_title', ['name' => '__NAME__']));

      document.querySelectorAll('[data-resource-block]').forEach(function (button) {
        button.addEventListener('click', function () {
          blockForm.reset();
          blockForm.setAttribute('action', blockAction.replace('__ID__', button.getAttribute('data-resource-block')));
          /* Built server-side per language: the name does not sit in the
             same place in every sentence. */
          blockTitle.textContent = blockTemplate.replace('__NAME__', button.getAttribute('data-resource-name'));
          open(blockModal);
        });
      });

      blockModal.querySelectorAll('[data-block-close]').forEach(function (button) {
        button.addEventListener('click', function () { close(blockModal); });
      });
    }

    document.addEventListener('keydown', function (e) {
      if (e.key !== 'Escape') return;
      if (modal && !modal.hidden) close(modal);
      if (blockModal && !blockModal.hidden) close(blockModal);
    });
  }());
</script>
