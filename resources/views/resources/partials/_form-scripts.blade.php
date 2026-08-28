<script>
  /* One submission. A second POST would create a second chair with the same
     name, which nobody would notice until the calendar offered both. The
     client and service forms guard themselves the same way. */
  (function () {
    var form = document.getElementById('resourceForm');
    if (!form) return;

    var saving = false;

    form.addEventListener('submit', function (e) {
      if (saving) { e.preventDefault(); return; }
      saving = true;

      var save = document.getElementById('resourceSave');
      save.disabled = true;
      save.textContent = @json(__('common.saving'));
    });
  }());

  /* The custom-hours week, shown only when the resource keeps its own.

     Hidden rather than removed, so a reader who switches to custom, fills in
     a week, then looks at the other option does not lose what they typed on
     the way back. */
  (function () {
    var week = document.querySelector('[data-custom-hours]');
    if (!week) return;

    document.querySelectorAll('[data-availability-type] input[type="radio"]').forEach(function (radio) {
      radio.addEventListener('change', function () {
        week.hidden = radio.value !== 'custom';
      });
    });

    /* A day that is not available has no hours to give, so its pickers go
       and a plain sentence takes their place — two empty time fields beside
       an unticked day read as fields somebody forgot to fill in. */
    week.addEventListener('change', function (e) {
      var toggle = e.target.closest('[data-day-toggle]');
      if (!toggle) return;

      var row = toggle.closest('[data-day-row]');
      row.querySelector('[data-day-times]').hidden = !toggle.checked;
      row.querySelector('[data-day-closed]').hidden = toggle.checked;
    });
  }());
</script>
