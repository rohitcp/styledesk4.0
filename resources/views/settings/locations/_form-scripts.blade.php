{{--
    The location form's behaviour, shared by create and edit.

    Progressive: every control below only decides what is on screen or adds a
    row of inputs. With no JavaScript the form still posts a complete week —
    each day keeps the period it was rendered with, and its toggle still says
    open or closed.
--}}
<script>
  (function () {
    var form = document.getElementById('locationForm');
    if (!form) return;

    /* ------------------------------------------------------ day toggles */

    /* Closed days hide their times rather than disabling them. A row of
       greyed-out time inputs takes as much space as a working one and says
       nothing the word "Closed" does not say better. */
    function syncDay(day) {
      var toggle = day.querySelector('[data-day-toggle]');
      var periods = day.querySelector('[data-periods]');
      var closed = day.querySelector('[data-closed-label]');

      periods.hidden = !toggle.checked;
      closed.hidden = toggle.checked;
    }

    var days = Array.prototype.slice.call(form.querySelectorAll('[data-day]'));

    days.forEach(function (day) {
      day.querySelector('[data-day-toggle]').addEventListener('change', function () {
        syncDay(day);
      });
    });

    /* ---------------------------------------------------- split periods */

    /* The name index is the period's position in the day, and the server
       reads it as the order to store them in. Renumbering after every add or
       remove is what keeps 9–1 before 2–7 once a middle row is deleted. */
    function renumber(day) {
      var dayNumber = day.getAttribute('data-day');

      Array.prototype.slice.call(day.querySelectorAll('[data-period]')).forEach(function (period, index) {
        period.querySelectorAll('input[type="time"]').forEach(function (input) {
          var field = input.name.slice(input.name.lastIndexOf('[') + 1, -1);
          input.name = 'hours[' + dayNumber + '][' + index + '][' + field + ']';
        });

        /* The first period has no remove button: clearing the only period is
           what the day's own toggle is for. */
        period.querySelector('[data-remove-period]').hidden = index === 0;
      });
    }

    days.forEach(function (day) {
      day.querySelector('[data-add-period]').addEventListener('click', function () {
        var periods = Array.prototype.slice.call(day.querySelectorAll('[data-period]'));
        var copy = periods[periods.length - 1].cloneNode(true);

        /* Blank, not a copy of the times above it. A duplicated period is an
           overlap the business did not ask for, and one it would have to
           notice before it could correct it. */
        copy.querySelectorAll('input[type="time"]').forEach(function (input) { input.value = ''; });

        periods[periods.length - 1].after(copy);
        bindRemove(copy, day);
        renumber(day);
      });

      day.querySelectorAll('[data-period]').forEach(function (period) { bindRemove(period, day); });
    });

    function bindRemove(period, day) {
      period.querySelector('[data-remove-period]').addEventListener('click', function () {
        period.remove();
        renumber(day);
      });
    }

    /* --------------------------------------------- apply Monday to weekdays */

    var copyButton = form.querySelector('[data-copy-weekdays]');

    if (copyButton) {
      copyButton.addEventListener('click', function () {
        var monday = form.querySelector('[data-day="1"]');
        if (!monday) return;

        /* Monday's times are read from the inputs, not from their markup.
           Cloning innerHTML copied the values the page was rendered with, so
           a Monday somebody had just retyped was applied to the rest of the
           week as whatever it used to be — the button appeared to work and
           quietly wrote the wrong hours. */
        var source = Array.prototype.slice.call(monday.querySelectorAll('[data-period]'))
          .map(function (period) {
            var times = period.querySelectorAll('input[type="time"]');
            return { opens_at: times[0].value, closes_at: times[1].value };
          });

        var mondayOpen = monday.querySelector('[data-day-toggle]').checked;

        /* Tuesday to Friday only. Saturday and Sunday are the two days most
           likely to differ, and a button that silently overwrote a weekend
           people had set up would be worse than no button. */
        [2, 3, 4, 5].forEach(function (dayNumber) {
          var day = form.querySelector('[data-day="' + dayNumber + '"]');
          if (!day) return;

          var periods = day.querySelector('[data-periods]');
          var template = day.querySelector('[data-period]');
          var addButton = day.querySelector('[data-add-period]');

          Array.prototype.slice.call(day.querySelectorAll('[data-period]')).forEach(function (period) {
            period.remove();
          });

          source.forEach(function (times) {
            var row = template.cloneNode(true);
            var inputs = row.querySelectorAll('input[type="time"]');
            inputs[0].value = times.opens_at;
            inputs[1].value = times.closes_at;

            periods.insertBefore(row, addButton);
            bindRemove(row, day);
          });

          day.querySelector('[data-day-toggle]').checked = mondayOpen;

          renumber(day);
          syncDay(day);
        });

        if (window.styledesk && window.styledesk.toast) {
          window.styledesk.toast("Monday's hours applied to Tuesday through Friday.", 'success');
        }
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
      save.textContent = @json($saveLabel);
    });
  }());
</script>
