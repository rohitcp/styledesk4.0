<script>
  (function () {
    var modal = document.getElementById('tipModal');
    if (!modal) return;

    var form = modal.querySelector('[data-tip-form]');
    var title = modal.querySelector('#tipModalTitle');
    var accepts = modal.querySelector('[data-tip-accepts]');
    var fields = modal.querySelector('[data-tip-fields]');
    var type = modal.querySelector('[data-tip-type]');
    var value = modal.querySelector('[data-tip-value]');
    var required = modal.querySelector('[data-tip-required]');
    var noTip = modal.querySelector('[data-tip-no-tip]');
    var label = @json(__('tips.edit_service_for', ['name' => '__NAME__']));

    /* The rest of the card only means anything while the service is tipped. */
    function sync() {
      fields.hidden = !accepts.checked;
    }

    accepts.addEventListener('change', sync);

    function close() {
      modal.hidden = true;
      document.body.style.overflow = '';
    }

    /* Delegated: the rows are redrawn by every save, and a listener bound to
       one of them would stop working the first time the page reloaded. */
    document.addEventListener('click', function (event) {
      var trigger = event.target.closest('[data-tip-edit]');
      if (!trigger) return;

      var data;

      try {
        data = JSON.parse(trigger.dataset.tipEdit);
      } catch (error) {
        return;
      }

      form.action = data.url;
      title.textContent = label.replace('__NAME__', data.name);

      accepts.checked = !!data.accepts_tips;
      type.value = data.tip_type || '';
      /* Blank rather than nought where the service has no opinion — the
         placeholder says it follows the business. */
      value.value = data.tip_value === null || data.tip_value === undefined ? '' : data.tip_value;
      required.checked = !!data.tip_required;
      noTip.checked = !!data.allow_no_tip;

      sync();

      modal.hidden = false;
      document.body.style.overflow = 'hidden';
      accepts.focus();
    });

    modal.querySelectorAll('[data-tip-close]').forEach(function (button) {
      button.addEventListener('click', close);
    });

    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && !modal.hidden) close();
    });
  }());
</script>

<script>
  /* The suggestion card follows the tip type.

     A percentage and a sum of money are two different questions, and the
     till row for one has no meaning under the other: a business set to flat
     sums does not offer 15%, and showing the row would be offering a choice
     its own settings say it does not make.

     Both rows stay in the form and both keep posting, so switching type and
     back finds the list where it was left. */
  (function () {
    var select = document.querySelector('[data-tip-type-select]');
    if (!select) return;

    var label = document.querySelector('[data-tip-default-label]');
    var prefix = document.querySelector('[data-tip-default-prefix]');
    var suffix = document.querySelector('[data-tip-default-suffix]');
    var value = document.querySelector('[data-tip-default-value]');
    var percentRow = document.querySelector('[data-tip-percent-row]');
    var amountRow = document.querySelector('[data-tip-amount-row]');
    var words = @json([
        'percent' => __('tips.default_tip_percent'),
        'fixed' => __('tips.default_tip_amount'),
    ]);

    function sync() {
      var money = select.value === 'fixed';

      if (label) label.textContent = words[money ? 'fixed' : 'percent'];
      if (prefix) prefix.hidden = !money;
      if (suffix) suffix.hidden = money;

      if (value) {
        value.classList.toggle('styledesk_input--prefixed', money);
        /* A share of a bill stops at the whole bill; money does not. */
        value.max = money ? '100000' : '100';
      }

      if (percentRow) percentRow.hidden = money;
      if (amountRow) amountRow.hidden = !money;
    }

    select.addEventListener('change', sync);
    sync();
  }());
</script>

<script>
  /* The switch saves itself.
     
     It is the only control in its form, and a toggle that needed a Save
     button beside it would be a toggle that looks like it has already taken
     effect and has not. */
  (function () {
    var form = document.querySelector('[data-tips-switch]');
    if (!form) return;

    var toggle = form.querySelector('[data-tips-enabled] input[type="checkbox"]');
    if (!toggle) return;

    toggle.addEventListener('change', function () {
      form.submit();
    });
  }());
</script>
