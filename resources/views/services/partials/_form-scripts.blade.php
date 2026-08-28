<script>
  /* One submission. A second POST would create a second service with the same
     name and price, which nobody would notice until it appeared twice in the
     booking list. The clients form guards itself the same way. */
  (function () {
    var form = document.getElementById('serviceForm');
    if (!form) return;

    var saving = false;

    form.addEventListener('submit', function (e) {
      if (saving) { e.preventDefault(); return; }
      saving = true;

      var save = document.getElementById('serviceSave');
      save.disabled = true;
      save.textContent = @json(__('common.saving'));
    });
  }());

</script>
