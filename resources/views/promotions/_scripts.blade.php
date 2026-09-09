@php
    /* The two shapes the value field takes, translated once here — a
       multi-line @json inside the script is not something Blade parses. */
    $discountWords = [
        'percent' => ['label' => __('promotions.form.percent'), 'hint' => __('promotions.form.percent_hint')],
        'fixed' => ['label' => __('promotions.form.amount'), 'hint' => __('promotions.form.fixed_hint')],
    ];
@endphp

<script>
  /* The promotion form's conditional fields.
     
     Every one of them is "this question only exists because of that answer",
     and the fields keep posting while hidden — so switching a radio back and
     forth does not cost somebody the list they built. */
  (function () {
    var form = document.querySelector('[data-promotion-form]');
    if (!form) return;

    function show(el, on) {
      if (el) el.hidden = !on;
    }

    /* A coupon is typed in; an offer applies itself and has no code. */
    var codeBlock = form.querySelector('[data-promotion-code]');

    form.querySelectorAll('[data-promotion-type]').forEach(function (radio) {
      radio.addEventListener('change', function () {
        show(codeBlock, radio.value === 'coupon' && radio.checked);
      });
    });

    /* What it applies to decides which list is asked for. */
    var services = form.querySelector('[data-applies-services]');
    var categories = form.querySelector('[data-applies-categories]');

    form.querySelectorAll('[data-applies-to]').forEach(function (radio) {
      radio.addEventListener('change', function () {
        show(services, radio.value === 'services' && radio.checked);
        show(categories, radio.value === 'categories' && radio.checked);
      });
    });

    var locationList = form.querySelector('[data-location-list]');

    form.querySelectorAll('[data-location-mode]').forEach(function (radio) {
      radio.addEventListener('change', function () {
        show(locationList, radio.value === 'selected' && radio.checked);
      });
    });

    var clientList = form.querySelector('[data-eligibility-clients]');

    form.querySelectorAll('[data-eligibility]').forEach(function (radio) {
      radio.addEventListener('change', function () {
        show(clientList, radio.value === 'selected' && radio.checked);
      });
    });

    /* No expiry and an end date are the same question asked twice, so one
       switches the other off. Disabled rather than cleared: a reader who
       ticks it by mistake gets their date back. */
    var noExpiry = form.querySelector('[data-no-expiry]');
    var endsWrapper = form.querySelector('[data-ends-wrapper]');

    if (noExpiry && endsWrapper) {
      var sync = function () {
        /* The whole field dims and stops taking input. The picker draws its
           own control, so disabling one input is not enough — everything
           inside goes at once. */
        endsWrapper.classList.toggle('opacity-50', noExpiry.checked);
        endsWrapper.classList.toggle('pointer-events-none', noExpiry.checked);

        endsWrapper.querySelectorAll('input, button').forEach(function (el) {
          el.disabled = noExpiry.checked;
        });
      };

      noExpiry.addEventListener('change', sync);
      sync();
    }

    /* The second field is the first field's answer.

       A percentage and a sum of money are not the same question: one is
       capped at a hundred and counted in whole points, the other is pennies
       with a currency symbol on it. The box changes with the choice rather
       than staying a neutral "Amount" that means whichever the reader last
       thought about. */
    var discountType = form.querySelector('[data-discount-type]');
    var valueField = form.querySelector('[data-discount-value]');

    if (discountType && valueField) {
      var valueLabel = form.querySelector('[data-value-label]');
      var valuePrefix = form.querySelector('[data-value-prefix]');
      var valueSuffix = form.querySelector('[data-value-suffix]');
      var valueHint = form.querySelector('[data-value-hint]');

      var words = @json($discountWords);

      discountType.addEventListener('change', function () {
        var percent = discountType.value === 'percent';

        valueLabel.textContent = words[percent ? 'percent' : 'fixed'].label;
        valueHint.textContent = words[percent ? 'percent' : 'fixed'].hint;

        show(valuePrefix, !percent);
        show(valueSuffix, percent);

        /* Whole points up to a hundred, or pennies with no ceiling. */
        valueField.step = percent ? '1' : '0.01';

        if (percent) {
          valueField.max = '100';
        } else {
          valueField.removeAttribute('max');
        }

        valueField.style.paddingRight = percent ? '2rem' : '';
        valueField.style.paddingLeft = percent ? '' : '1.75rem';

        /* A value that cannot mean the same thing in the new units is
           cleared rather than silently reinterpreted — 2000 meant £20 a
           moment ago and would now mean 2000%. */
        if (percent && Number(valueField.value) > 100) {
          valueField.value = '';
        }
      });
    }

    /* A code somebody can read down a telephone: no O/0 or I/1 confusion,
       and short enough to say out loud. */
    var generate = form.querySelector('[data-generate-code]');
    var codeField = form.querySelector('[data-code-field]');

    if (generate && codeField) {
      generate.addEventListener('click', function () {
        var alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        var code = '';

        for (var i = 0; i < 8; i++) {
          code += alphabet[Math.floor(Math.random() * alphabet.length)];
        }

        codeField.value = code;
      });

      /* Typed in capitals as it is entered, so what the reader sees is what
         gets saved. */
      codeField.addEventListener('input', function () {
        var at = codeField.selectionStart;

        codeField.value = codeField.value.toUpperCase();
        codeField.setSelectionRange(at, at);
      });
    }
  }());
</script>
