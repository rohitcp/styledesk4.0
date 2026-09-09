@php
    /* Built here rather than inline: a multi-line @json() inside a script
       expression is not parsed by Blade. */
    $durationWords = json_encode([
        'minutes' => __('services.minutes_short', ['count' => ':count']),
        'hours' => __('services.hours_short', ['count' => ':count']),
        'both' => __('services.hours_minutes_short', ['hours' => ':hours', 'minutes' => ':minutes']),
    ], JSON_THROW_ON_ERROR);
@endphp

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


  /* The duration, in the words the rest of the application uses for it.

     The field takes minutes because a number box cannot take anything else;
     this says what those minutes will be called everywhere the service is
     read, so the form and the listing never appear to disagree. The wording
     is the server's, so it stays translated. */
  (function () {
    var field = document.getElementById('serviceDuration');
    var reads = document.getElementById('serviceDurationReads');
    if (!field || !reads) return;

    var words = {!! $durationWords !!};

    function label(minutes) {
      var hours = Math.floor(minutes / 60);
      var rest = minutes % 60;

      if (hours === 0) { return words.minutes.replace(':count', rest); }
      if (rest === 0) { return words.hours.replace(':count', hours); }

      return words.both.replace(':hours', hours).replace(':minutes', rest);
    }

    field.addEventListener('input', function () {
      var minutes = parseInt(field.value, 10);

      /* An empty or half-typed box says nothing rather than "0 min", which
         reads as an answer the reader did not give. */
      reads.textContent = isNaN(minutes) || minutes < 0 ? '' : label(minutes);
    });
  }());
</script>
