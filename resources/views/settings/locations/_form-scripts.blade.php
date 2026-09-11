{{--
    The location form's behaviour, shared by create and edit.

    The submit guard, and the state field following the country. The opening
    hours — the day toggles, the split periods and "Copy Monday to Tue–Fri" —
    belong to the BusinessHours island, the same one onboarding step 2 mounts,
    so this file does not reimplement any of them.
--}}
@php
    $formRegions = config('locations.regions');
@endphp

<script>
  (function () {
    var form = document.getElementById('locationForm');
    if (!form) return;

    /* ------------------------------------------- the state follows the country */

    /* Which countries have regions, and what they are. Encoded rather than
       interpolated, because region names carry apostrophes and accents. */
    var REGIONS = @json($formRegions);

    var stateField = form.querySelector('[data-state-field]');

    if (stateField) {
      var comboWrap = stateField.querySelector('[data-state-combo]');
      var textWrap = stateField.querySelector('[data-state-text]');
      var textInput = textWrap.querySelector('input');

      /* Exactly one of the two posts. The combo's answer lives in a hidden
         input the island owns, so it is found each time rather than cached —
         the island mounts after this script runs. */
      function showRegionList(regions) {
        var hasRegions = Object.keys(regions).length > 0;

        /* The hidden attribute, not a class: live-validation skips a field
           inside [hidden], which is what stops the control that must not post
           from also refusing to let the form submit. */
        comboWrap.toggleAttribute('hidden', !hasRegions);
        textWrap.toggleAttribute('hidden', hasRegions);

        textInput.disabled = hasRegions;

        var comboInput = comboWrap.querySelector('input[type="hidden"]');
        if (comboInput) comboInput.disabled = !hasRegions;

        /* Nothing carries over between the two: a province typed by hand is
           not a code, and a code is not a name. Leaving either behind would
           post the previous country's answer against the new one. */
        if (hasRegions) {
          textInput.value = '';
        }

        document.dispatchEvent(new CustomEvent('styledesk:combo-options', {
          detail: { name: 'state', options: regions },
        }));
      }

      /* The country control announces itself on mount as well as on change,
         so this runs once at load with the country already chosen — which is
         what disables the control that must not post. */
      form.addEventListener('styledesk:selection', function (e) {
        var detail = e.detail || {};

        if (detail.name !== 'country') return;

        showRegionList(REGIONS[(detail.values || [])[0]] || {});
      });
    }

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
