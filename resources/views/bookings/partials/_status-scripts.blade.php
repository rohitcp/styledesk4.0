<script>
  /* The header's actions and the dialogues behind them.

     One handler for all four, because they are one interaction: open the
     dialog named by the button, close it on the scrim, the × or Escape. */
  (function () {
    function modalFor(action) {
      return document.querySelector('[data-status-modal="' + action + '"]');
    }

    function close(modal) {
      modal.hidden = true;
      document.body.style.overflow = '';
    }

    /* The reason list, made searchable.

       Done as the dialog opens rather than at page load, for two reasons that
       both had to be got right. This file is a plain inline script and runs
       while the page is still parsing, so `window.SD` — defined by the module
       bundle, which executes later — is not there yet; and the dialog is
       `hidden` until it is opened, so a widget built inside it has nothing to
       measure itself against.

       SD.combo keeps the native select underneath as the value holder, takes
       it out of the tab order and dispatches `change` on it when a choice is
       made. So the form posts exactly as it did and the requires-details
       check below goes on reading `.options` and `.selectedIndex` as though
       nothing had happened. It no-ops on a select it has already upgraded. */
    function makeReasonSearchable(modal) {
      var select = modal.querySelector('[data-status-reason]');

      if (!select || select.dataset.comboReady || !window.SD || typeof window.SD.combo !== 'function') {
        return;
      }

      window.SD.combo(select, {
        searchPlaceholder: select.dataset.searchLabel || '',
        width: '100%',
      });
    }

    document.querySelectorAll('[data-status-action]').forEach(function (button) {
      button.addEventListener('click', function () {
        var modal = modalFor(button.dataset.statusAction);
        if (!modal) return;

        modal.hidden = false;
        document.body.style.overflow = 'hidden';

        makeReasonSearchable(modal);

        /* The combo's button, where there is one: focusing the select itself
           would put the cursor on a control that is now sr-only. */
        var first = modal.querySelector('[data-status-reason]');
        var target = first && first.id ? document.getElementById(first.id + '-combo') : null;

        if (target || first) (target || first).focus();
      });
    });

    document.querySelectorAll('[data-status-modal]').forEach(function (modal) {
      modal.querySelectorAll('[data-status-close]').forEach(function (button) {
        button.addEventListener('click', function () { close(modal); });
      });

      /* The explanation a reason asks for.

         Which reason that is belongs to the business rather than to this
         file — "Other" is the obvious one and they can ask the same of any —
         so it is read off the chosen option rather than from its name. */
      var reason = modal.querySelector('[data-status-reason]');
      var field = modal.querySelector('[data-status-details-field]');
      var details = modal.querySelector('[data-status-details]');

      if (reason && field && details) {
        var sync = function () {
          var option = reason.options[reason.selectedIndex];
          var wanted = !!option && option.dataset.requiresDetails === '1';

          field.hidden = !wanted;
          /* Required only while it is on screen: a hidden required field is
             a form the browser refuses to submit and will not say why. */
          details.required = wanted;
          if (!wanted) details.value = '';
        };

        reason.addEventListener('change', sync);
        sync();
      }
    });

    /* Arrived here to do one particular thing.

       The booking screen links here with ?action=cancelled when a membership
       benefit is reserved against this appointment and the desk chose to call
       it off. The button is clicked rather than the modal opened directly, so
       an action this reader may not take simply is not there and nothing
       happens — the permission is enforced in one place, where it is drawn. */
    (function () {
      var wanted = new URLSearchParams(window.location.search).get('action');
      if (!wanted) return;

      var button = document.querySelector('[data-status-action="' + CSS.escape(wanted) + '"]');
      if (button) button.click();
    }());

    document.addEventListener('keydown', function (e) {
      if (e.key !== 'Escape') return;

      document.querySelectorAll('[data-status-modal]').forEach(function (modal) {
        if (!modal.hidden) close(modal);
      });
    });
  }());

  /* What is actually free on the day being moved to.

     Asked of the booking's own slots endpoint, which ignores this
     appointment's current time — otherwise moving one by ten minutes would
     be found to clash with itself. */
  (function () {
    var form = document.querySelector('[data-reschedule-form]');
    if (!form) return;

    var date = form.querySelector('[data-reschedule-date]');
    var time = form.querySelector('[data-reschedule-time]');
    var hint = form.querySelector('[data-reschedule-hint]');
    var staff = form.querySelector('[data-reschedule-staff]');
    var location = form.querySelector('[data-reschedule-location]');
    var current = form.dataset.currentTime;
    var request = 0;

    function say(message) {
      if (!hint) return;
      hint.textContent = message || '';
      hint.hidden = !message;
    }

    function fill(slots, keep) {
      time.innerHTML = '';

      if (!slots.length) {
        time.appendChild(new Option(form.dataset.emptyText, ''));

        return;
      }

      slots.forEach(function (slot) {
        var option = new Option(slot, slot);
        /* The time it is already at stays chosen where the day has not
           changed, so opening the dialog and picking a new staff member
           does not silently blank the answer. */
        if (slot === keep) option.selected = true;
        time.appendChild(option);
      });
    }

    function load() {
      if (!date.value) {
        say(form.dataset.promptText);

        return;
      }

      var token = ++request;
      var params = new URLSearchParams({ date: date.value });
      if (staff && staff.value) params.set('staff_id', staff.value);
      if (location && location.value) params.set('location_id', location.value);

      say(form.dataset.loadingText);
      time.disabled = true;

      fetch(form.dataset.slotsUrl + '?' + params.toString(), {
        headers: { 'Accept': 'application/json' },
      })
        .then(function (response) { return response.json(); })
        .then(function (data) {
          /* A slower answer to an older question must not overwrite a
             faster answer to the current one. */
          if (token !== request) return;

          fill(data.slots || [], current);
          time.disabled = false;
          say(data.message || (data.slots && data.slots.length ? '' : form.dataset.emptyText));
        })
        .catch(function () {
          if (token !== request) return;

          time.disabled = false;
          say(form.dataset.emptyText);
        });
    }

    date.addEventListener('change', load);
    if (staff) staff.addEventListener('change', load);
    if (location) location.addEventListener('change', load);

    load();
  }());
</script>
