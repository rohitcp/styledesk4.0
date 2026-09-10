@php
    /* Worked out here rather than in the script: Blade cannot interpolate
       into a multi-line json directive, and the saving line needs the
       currency symbol and the sentence around the number. */
    $savingWords = __('membership.form.saving_preview', ['amount' => '__AMOUNT__']);

    /* The discount box is labelled for whichever kind of discount is chosen. */
    $discountWords = [
        'percent' => __('membership.form.discount_percentage'),
        'fixed' => __('membership.form.discount_value'),
    ];
@endphp

<script>
  /* The membership form's two moving parts.

     Neither is validation — the server decides — and neither removes a field
     from the post. Everything stays in the DOM while hidden, so switching a
     radio back and forth does not cost somebody the list they built. */
  (function () {
    var form = document.querySelector('[data-membership-form]');
    if (!form) return;

    var symbol = form.dataset.currency || '';

    /* ------------------------------------------------ selected locations */

    var locationList = form.querySelector('[data-location-list]');

    form.querySelectorAll('[data-location-mode] input[type="radio"], input[data-location-mode]').forEach(function (radio) {
      radio.addEventListener('change', function () {
        if (locationList) locationList.hidden = radio.value !== 'selected' || !radio.checked;
      });
    });

    /* ------------------------------------------------- the service rows */

    var rows = form.querySelector('[data-service-rows]');
    var template = form.querySelector('[data-service-template]');
    var addButton = form.querySelector('[data-add-service]');

    /* Names carry an index, and the indexes only have to be distinct — the
       server reindexes them on save. Counting from the rows already there
       is what keeps a row added after two deletions from colliding. */
    var nextIndex = rows ? rows.querySelectorAll('[data-service-row]').length : 0;

    function syncRemoveButtons() {
      if (!rows) return;

      var all = rows.querySelectorAll('[data-service-row]');

      /* The last row's Remove is disabled rather than hidden: a membership
         has to include something, and a list you can empty is one where the
         only way back is to reload the page. */
      all.forEach(function (row) {
        var button = row.querySelector('[data-remove-service]');
        if (button) button.disabled = all.length === 1;
      });
    }

    if (addButton && template && rows) {
      addButton.addEventListener('click', function () {
        var markup = template.innerHTML.replace(/__INDEX__/g, String(nextIndex++));
        var holder = document.createElement('div');
        holder.innerHTML = markup.trim();

        var row = holder.firstElementChild;
        if (!row) return;

        rows.appendChild(row);
        syncRemoveButtons();

        /* A cloned row is plain markup: the combo has to be built on it the
           way app.js builds the ones that were on the page at load. Guarded
           because a page whose scripts failed still has a working native
           select, which is the point of upgrading rather than replacing. */
        var select = row.querySelector('[data-service-select]');

        if (select && window.SD && typeof window.SD.combo === 'function') {
            var options = {};

            try {
                options = JSON.parse(select.dataset.comboOptions || '{}');
            } catch (error) {
                /* Malformed options are not a reason to leave the row
                   without a dropdown. */
            }

            window.SD.combo(select, options);
        }

        if (select) select.focus();
        syncServiceOptions();
      });
    }

    /* A service belongs to one line or none.

       Two lines naming the same service are two rows claiming the same
       entitlement, and the credit engine would have to pick one — so a
       service already spoken for is taken out of every other row's list
       rather than left there to be chosen and then refused. Removing the
       line hands it straight back. The server checks the same thing; this
       only means nobody has to be told about it after the fact. */
    function syncServiceOptions() {
      if (!rows) return;

      var selects = rows.querySelectorAll('[data-service-select]');
      var taken = {};

      selects.forEach(function (select) {
        if (select.value) taken[select.value] = true;
      });

      selects.forEach(function (select) {
        Array.prototype.forEach.call(select.options, function (option) {
          /* Never the row's own answer, or it would disappear from the one
             field that is meant to be showing it. */
          option.disabled = option.value !== '' && option.value !== select.value && !!taken[option.value];
        });
      });
    }

    if (rows) {
      rows.addEventListener('click', function (event) {
        var button = event.target.closest('[data-remove-service]');
        if (!button || button.disabled) return;

        var row = button.closest('[data-service-row]');
        if (row) row.remove();

        syncRemoveButtons();
        syncServiceOptions();
      });

      /* The combo posts its answer through the native select and fires
         change on it, so one listener covers both. */
      rows.addEventListener('change', function (event) {
        if (event.target.closest('[data-service-select]')) syncServiceOptions();
      });
    }

    syncServiceOptions();

    /* Credits follows quantity until somebody says otherwise.

       Four massages are four credits in nearly every package, so typing the
       quantity fills the credits in — but only while the credits box has
       not been touched by hand. Once it has, it is that person's number and
       nothing overwrites it; emptying it hands the box back to the
       quantity. Delegated, so rows added later behave the same. */
    if (rows) {
      rows.addEventListener('input', function (event) {
        var field = event.target;
        var row = field.closest ? field.closest('[data-service-row]') : null;
        if (!row) return;

        if (field.matches('[data-service-credits]')) {
          /* A programmatic value assignment fires no input event, so
             anything arriving here was typed. */
          if (field.value === '') {
            delete field.dataset.serviceCreditsEdited;
          } else {
            field.dataset.serviceCreditsEdited = '1';
          }

          return;
        }

        if (!field.matches('[data-service-quantity]')) return;

        var credits = row.querySelector('[data-service-credits]');
        if (credits && credits.dataset.serviceCreditsEdited === undefined) {
          credits.value = field.value;
        }
      });

      /* Choosing a service fills the credits from what that service costs to
         redeem. The business set that number on the service itself, and
         asking for it again here is asking the same question twice — but it
         is only a starting point: a plan may grant more or fewer, and a
         credits box somebody has typed in is left alone. */
      rows.addEventListener('change', function (event) {
        var select = event.target.closest('[data-service-select]');
        if (!select) return;

        var row = select.closest('[data-service-row]');
        var credits = row?.querySelector('[data-service-credits]');
        var quantity = row?.querySelector('[data-service-quantity]');

        if (!credits || credits.dataset.serviceCreditsEdited !== undefined) return;

        var usage = select.options[select.selectedIndex]?.dataset.creditUsage;

        if (usage) {
          /* Per booking, times how many the plan includes: two of a
             two-credit massage is four credits, not two. */
          credits.value = String(Number(usage) * Math.max(1, Number(quantity?.value) || 1));
        }
      });
    }

    syncRemoveButtons();

    /* -------------------------------------------------- the picture */

    var imageInput = form.querySelector('[data-image-input]');
    var imageId = form.querySelector('[data-image-id]');
    var imagePreview = form.querySelector('[data-image-preview]');
    var imageStatus = form.querySelector('[data-image-status]');
    var imageRemove = form.querySelector('[data-image-remove]');

    function token() {
      var field = form.querySelector('input[name="_token"]');
      return field ? field.value : '';
    }

    function drawImage(url) {
      imagePreview.innerHTML = '';

      var img = document.createElement('img');
      img.src = url;
      img.alt = '';
      img.className = 'h-full w-full object-cover';
      imagePreview.appendChild(img);
    }

    if (imageInput && imageId && imagePreview) {
      imageInput.addEventListener('change', function () {
        var file = imageInput.files && imageInput.files[0];
        if (!file) return;

        imageStatus.textContent = @json(__('membership.images.uploading'));
        imageStatus.classList.remove('text-danger');

        var body = new FormData();
        body.append('image', file);
        body.append('_token', token());

        fetch(imageInput.dataset.endpoint, {
          method: 'POST',
          body: body,
          headers: { 'Accept': 'application/json' },
        })
          .then(function (response) {
            return response.json().then(function (data) {
              if (!response.ok) {
                /* Laravel's validation shape first, then its plain message —
                   "use a JPG" is something the reader can act on and
                   "upload failed" is not. */
                var detail = data.errors && data.errors.image && data.errors.image[0];
                throw new Error(detail || data.message || @json(__('membership.images.failed')));
              }
              return data;
            });
          })
          .then(function (data) {
            imageId.value = data.id;
            drawImage(data.url);
            imageStatus.textContent = file.name;
            imageRemove.hidden = false;
          })
          .catch(function (error) {
            /* Said beside the field, not in a dialog. The person can act on
               "the file is too large"; they cannot act on an alert they have
               already dismissed. */
            imageStatus.textContent = error.message;
            imageStatus.classList.add('text-danger');
            imageInput.value = '';
          });
      });
    }

    if (imageRemove && imageId) {
      imageRemove.addEventListener('click', function () {
        var removed = imageId.value;

        /* Cleared here and confirmed on save. The file itself is only
           discarded when it was never attached to anything — one that
           belongs to a saved plan is the save's business, so somebody who
           removes a picture and then abandons the form still has their
           membership as they left it. */
        imageId.value = '';
        imagePreview.innerHTML = '';
        imageStatus.textContent = '';
        imageStatus.classList.remove('text-danger');
        imageRemove.hidden = true;
        if (imageInput) imageInput.value = '';

        if (!removed || removed === @json((string) ($plan?->image_file_id ?? ''))) return;

        fetch(imageInput.dataset.discard.replace('__ID__', removed), {
          method: 'DELETE',
          headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': token() },
        }).catch(function () {
          /* Nothing to say. The picture is already gone from the form, and
             an orphaned upload is not the reader's problem. */
        });
      });
    }

    /* --------------------------------------------- the package's saving */

    /* Once per currency: a saving is the gap between two numbers in the same
       money, and there is no sense in which C$300 is more than $150. Each
       block works its own out, and each wears its own symbol. */
    var savingWords = @json($savingWords);

    function showSaving(row) {
      var saving = row.querySelector('[data-saving]');
      var price = row.querySelector('[data-price]');
      var regular = row.querySelector('[data-regular-value]');

      if (!saving || !price || !regular) return;

      var difference = parseFloat(regular.value) - parseFloat(price.value);

      /* Nothing rather than "saves $0.00", and nothing rather than a
         negative — a package priced above its own regular value is a
         mistake the server refuses, not a saving to advertise. */
      if (!isFinite(difference) || difference <= 0) {
        saving.hidden = true;
        return;
      }

      saving.textContent = savingWords.replace(
        '__AMOUNT__',
        (row.dataset.symbol || symbol) + difference.toFixed(2)
      );
      saving.hidden = false;
    }

    form.querySelectorAll('[data-price-row]').forEach(function (row) {
      row.addEventListener('input', function (event) {
        if (event.target.closest('[data-price], [data-regular-value]')) showSaving(row);
      });

      showSaving(row);
    });

    /* ------------------------------------------------- discount, % or money */

    /* A rate and a sum of money read the same in a bare number box, so the
       box says which it is: the label, the affix and the step all follow the
       discount type. The combo has no native select to listen to — it
       announces its answer on the form as styledesk:selection. */
    var discountValue = form.querySelector('[data-discount-value]');
    var discountLabel = form.querySelector('[data-discount-label]');
    var discountPrefix = form.querySelector('[data-discount-prefix]');
    var discountSuffix = form.querySelector('[data-discount-suffix]');
    var discountWords = @json($discountWords);

    function showDiscountKind(type) {
      if (!discountValue) return;

      var money = type === 'fixed';

      if (discountLabel) discountLabel.textContent = discountWords[money ? 'fixed' : 'percent'];
      if (discountPrefix) discountPrefix.hidden = !money;
      if (discountSuffix) discountSuffix.hidden = money;

      /* Pennies with no ceiling, or whole points up to a hundred. */
      discountValue.step = money ? '0.01' : '1';
      if (money) {
        discountValue.removeAttribute('max');
      } else {
        discountValue.max = '100';
      }

      discountValue.style.paddingLeft = money ? '1.75rem' : '';
      discountValue.style.paddingRight = money ? '' : '2rem';
    }

    form.addEventListener('styledesk:selection', function (event) {
      if (event.detail && event.detail.name === 'discount_type') {
        showDiscountKind((event.detail.values || [])[0] || '');
      }
    });
  }());
</script>
