{{--
    The location form's behaviour, shared by create and edit.

    Only the submit guard. The opening hours — the day toggles, the split
    periods and "Copy Monday to Tue–Fri" — belong to the BusinessHours island,
    the same one onboarding step 2 mounts, so this file does not reimplement
    any of them.
--}}
<script>
  (function () {
    var form = document.getElementById('locationForm');
    if (!form) return;

    /* --------------------------------------------------------- one submit */

    /* Prevents the duplicate submission §14 asks for. A second POST would
       create a second branch with the same address, which is a row somebody
       then has to work out how to remove. */
    var save = document.getElementById('locationSave');
    var saving = false;

    form.addEventListener('submit', function (e) {
      if (saving) {
        e.preventDefault();
        return;
      }
      saving = true;
      save.disabled = true;
      save.textContent = @json(__('common.saving'));
    });
  }());
</script>
