@php
    /* Worked out here rather than in the script: Blade cannot interpolate
       into a multi-line json directive, and the saving line needs the
       currency symbol and the sentence around the number. */
    $savingWords = __('membership.form.saving_preview', ['amount' => '__AMOUNT__']);
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

        var select = row.querySelector('[data-service-select]');
        if (select) select.focus();
      });
    }

    if (rows) {
      rows.addEventListener('click', function (event) {
        var button = event.target.closest('[data-remove-service]');
        if (!button || button.disabled) return;

        var row = button.closest('[data-service-row]');
        if (row) row.remove();

        syncRemoveButtons();
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

    var price = form.querySelector('[data-price]');
    var regular = form.querySelector('[data-regular-value]');
    var saving = form.querySelector('[data-saving]');

    function showSaving() {
      if (!saving || !price || !regular) return;

      var difference = parseFloat(regular.value) - parseFloat(price.value);

      /* Nothing rather than "saves $0.00", and nothing rather than a
         negative — a package priced above its own regular value is a
         mistake the server refuses, not a saving to advertise. */
      if (!isFinite(difference) || difference <= 0) {
        saving.hidden = true;
        return;
      }

      saving.textContent = @json($savingWords).replace('__AMOUNT__', symbol + difference.toFixed(2));
      saving.hidden = false;
    }

    if (price) price.addEventListener('input', showSaving);
    if (regular) regular.addEventListener('input', showSaving);
    showSaving();
  }());
</script>
