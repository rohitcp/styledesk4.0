<script>
  /* The "mark unavailable" dialog.

     The listing's rows are drawn by the shared grid, so the menu entry that
     opens this cannot be bound to a button that exists at page load — the
     grid announces it instead, and this listens. Everything else the dialog
     used to do (add, edit) is now a page of its own. */
  (function () {
    var blockModal = document.getElementById('resourceBlockModal');
    if (!blockModal) return;

    var blockForm = blockModal.querySelector('[data-block-form]');
    var blockTitle = blockModal.querySelector('#resourceBlockTitle');
    var blockAction = @json(route('resources.block', ['resource' => '__ID__']));
    var blockTemplate = @json(__('resources.block_title', ['name' => '__NAME__']));

    function open() {
      blockModal.hidden = false;
      document.body.style.overflow = 'hidden';
    }

    function close() {
      blockModal.hidden = true;
      document.body.style.overflow = '';
    }

    document.addEventListener('styledesk:grid-action', function (e) {
      if (e.detail.event !== 'resource-block') return;

      var row = e.detail.payload;

      blockForm.reset();
      blockForm.setAttribute('action', blockAction.replace('__ID__', row.id));
      /* Built server-side per language: the name does not sit in the same
         place in every sentence. */
      blockTitle.textContent = blockTemplate.replace('__NAME__', row.name);
      open();
    });

    blockModal.querySelectorAll('[data-block-close]').forEach(function (button) {
      button.addEventListener('click', close);
    });

    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && !blockModal.hidden) close();
    });
  }());
</script>
