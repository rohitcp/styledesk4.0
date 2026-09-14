{{--
    The Send Email composer's behaviour, shared by every screen that offers it.

    The client profile and a booking's own page both open the same composer;
    extracting it means the two cannot drift into behaving differently, and a
    fix to the draft handling or the template variables lands on both.

    `emailBookingId`, where a page sets it, is the appointment this message is
    about. A booking's page has one by definition — everything written from
    there concerns it — so the templates render against it from the start and
    the Related booking field has nothing left to ask.
--}}
@php $emailBookingId = $emailBookingId ?? null; @endphp

<script>
    /*
     * Send Email.
     *
     * Everything is fetched when the drawer opens rather than rendered with
     * the page: a profile is opened many times a day and written from rarely.
     * Nothing here is a rule — the server validates the same things again,
     * because a screen is a convenience and an endpoint is the boundary.
     */
    (function () {
      const drawer = document.querySelector('[data-email-drawer]');

      /* Every way into the composer, not just the first.

         The button in the quick actions and each address in the Contact card
         all open the same drawer — they are one action offered where the
         reader happens to be looking. A single querySelector bound the first
         and left the addresses opening nothing. */
      const triggers = document.querySelectorAll('[data-send-email]');
      const trigger = triggers[0];

      if (!drawer || !trigger) {
        return;
      }

      const form = drawer.querySelector('[data-email-form]');
      const subject = drawer.querySelector('[data-email-subject]');
      const message = drawer.querySelector('[data-email-message]');
      const templates = drawer.querySelector('[data-email-template]');
      const toSelect = drawer.querySelector('[data-email-to-select]');
      const bookings = drawer.querySelector('[data-email-booking]');
      const bookingField = drawer.querySelector('[data-email-booking-field]');
      const submit = drawer.querySelector('[data-email-submit]');
      const blocked = drawer.querySelector('[data-email-blocked]');
      const failure = drawer.querySelector('[data-email-error]');
      const unresolved = drawer.querySelector('[data-email-unresolved]');

      let loaded = null;

      function show(el, text) {
        el.textContent = text || '';
        el.hidden = !text;
      }

      const discardAsk = document.querySelector('[data-email-discard-ask]');

      /* The appointment this composer is about, where the page has one. A
         booking's own screen does by definition; a client profile does not,
         and asks. */
      const fixedBooking = @json($emailBookingId);

      /* A message parked by the send that reloaded this page.
         
         On DOMContentLoaded, not now: this script sits in the page's content
         and the toast component's own runs further down the layout, so
         `window.styledesk.toast` does not exist yet at this point. Reading
         the message here found nothing to show it with and cleared the key
         on the way past, which is how a successful send ended up silent.

         Cleared once it has actually been shown, so a later refresh does not
         congratulate somebody twice. */
      document.addEventListener('DOMContentLoaded', function () {
        let parked = null;

        try {
          parked = sessionStorage.getItem('styledesk:toast');
        } catch (error) {
          return;
        }

        if (!parked) {
          return;
        }

        if (window.styledesk && window.styledesk.toast) {
          window.styledesk.toast(parked, 'success');
        }

        try {
          sessionStorage.removeItem('styledesk:toast');
        } catch (error) {
          /* Nothing to clean up if it could not be read in the first place. */
        }
      });

      /* Anything worth asking about before it is thrown away. The template
         and the booking are not: choosing one and changing your mind is not
         a draft, and a prompt for it would be a prompt nobody reads. */
      function hasDraft() {
        return subject.value.trim() !== '' || message.value.trim() !== '';
      }

      function setState(state) {
        drawer.dataset.state = state;
      }

      /* Shut and emptied. Every other exit either keeps the draft or asks. */
      function discard() {
        drawer.hidden = true;
        subject.value = '';
        message.value = '';
        templates.value = '';
        bookings.value = '';
        show(failure, '');
        if (discardAsk) discardAsk.hidden = true;
      }

      function close() {
        discard();
      }

      async function open() {
        /* Already open, just out of the way. Restored rather than reloaded:
           refetching would overwrite whatever is half-written, which is the
           one thing minimising is for. */
        if (!drawer.hidden) {
          setState('open');
          subject.focus();

          return;
        }

        drawer.hidden = false;
        setState('open');
        show(failure, '');
        subject.value = '';
        message.value = '';

        try {
          const composeUrl = new URL(trigger.dataset.composeUrl, window.location.origin);

          if (fixedBooking) {
            composeUrl.searchParams.set('booking_id', fixedBooking);
          }

          const response = await fetch(composeUrl, {
            headers: { Accept: 'application/json' },
          });
          loaded = await response.json();
        } catch (error) {
          show(failure, @json(__('client_email.errors.failed')));
          return;
        }

        drawer.querySelector('[data-email-to-name]').textContent = loaded.to.name || '';
        drawer.querySelector('[data-email-from-label]').textContent = loaded.from.label || '';
        drawer.querySelector('[data-email-from-address]').textContent = loaded.from.email || '';

        /* Where a reply lands, where that is somewhere else. Hidden when the
           two agree, because repeating an address is not information. */
        const replyRow = drawer.querySelector('[data-email-reply-row]');
        const replyTo = loaded.from.reply_to || '';

        drawer.querySelector('[data-email-reply-address]').textContent = replyTo;
        replyRow.hidden = !replyTo || replyTo === loaded.from.email;

        /* One address is a fact; several is a choice. The select appears only
           in the second case — a dropdown with one option asks a question
           with one answer. */
        const addresses = loaded.to.options || [];
        const toLine = drawer.querySelector('[data-email-to-address]');

        toSelect.innerHTML = '';

        addresses.forEach(function (row) {
          const option = document.createElement('option');
          option.value = row.email;
          option.textContent = row.label ? row.email + ' · ' + row.label : row.email;
          toSelect.appendChild(option);
        });

        if (addresses.length > 1) {
          toSelect.hidden = false;
          toSelect.value = loaded.to.email || addresses[0].email;
          toLine.hidden = true;
        } else {
          toSelect.hidden = true;
          toLine.hidden = false;
          toLine.textContent = loaded.to.email || '';
        }

        /* A drawer that cannot send says why and locks its own button, rather
           than letting somebody write a message that has nowhere to go. */
        show(blocked, loaded.blocked);
        submit.disabled = Boolean(loaded.blocked);

        if (bookingField) bookingField.hidden = true;

        templates.length = 1;
        (loaded.templates || []).forEach(function (template) {
          const option = document.createElement('option');
          option.value = template.key;
          option.textContent = template.name;
          templates.appendChild(option);
        });

        bookings.length = 1;
        (loaded.bookings || []).forEach(function (booking) {
          const option = document.createElement('option');
          option.value = booking.id;
          option.textContent = booking.label;
          bookings.appendChild(option);
        });

        /* Fixed to this page's appointment, and left out of the way. The send
           reads the select, so setting it is what attaches the message to the
           booking as well as rendering the wording against it. */
        if (fixedBooking) {
          bookings.value = String(fixedBooking);
        }

        /* Searchable, and told about its options every time.

           Built here rather than at page load for two reasons: SD comes from
           the module bundle, which has not run while this inline script is
           parsing, and the drawer is hidden until it is opened, so a widget
           built inside it has nothing to measure against. The options arrive
           with the payload, so an already-built combo is refreshed rather
           than rebuilt. */
        if (window.SD && typeof window.SD.combo === 'function') {
          [templates, bookings].forEach(function (select) {
            if (select.dataset.comboReady) {
              window.SD.comboRefresh(select);
            } else {
              window.SD.combo(select, {
                searchPlaceholder: select.dataset.searchLabel || '',
                width: '100%',
              });
            }
          });
        }
      }

      /* Choosing a template fills the two fields in. It replaces what is
         there: a template merged into a half-written message is neither. */
      function applyTemplate() {
        const chosen = (loaded?.templates || []).find(function (t) { return t.key === templates.value; });

        if (chosen) {
          subject.value = chosen.subject;
          message.value = chosen.body;
        }

        /* The appointment field appears with the wording that needs it, and
           goes away with it — taking its answer, so a booking chosen for one
           template is not quietly attached to a message that replaced it.

           Never on a booking's own page: the appointment is not a question
           there, it is where the reader is standing. */
        if (bookingField && !fixedBooking) {
          const wanted = Boolean(chosen && chosen.needs_booking);

          bookingField.hidden = !wanted;

          if (!wanted) {
            bookings.value = '';
          }
        }

        flagUnresolved();
      }

      /* A template still holding an unresolved placeholder is one the server
         had no answer for — almost always because it asks about an
         appointment and none has been chosen. Said plainly rather than left
         for the client to find in their inbox.

         The word "placeholder" rather than the braces themselves: this is a
         Blade file, and a pair of braces written in a comment is compiled as
         an echo long before anybody reads it as prose. */
      function flagUnresolved() {
        if (!unresolved) {
          return;
        }

        const text = subject.value + ' ' + message.value;

        unresolved.hidden = !/\{\{\s*[a-z_]+\s*\}\}/i.test(text);
      }

      templates.addEventListener('change', applyTemplate);
      subject.addEventListener('input', flagUnresolved);
      message.addEventListener('input', flagUnresolved);

      /* Picking the appointment re-renders the wording against it. The
         templates arrive already rendered, so the only way to fill in a date
         that was blank is to ask for them again with the booking named. */
      bookings.addEventListener('change', async function () {
        if (!templates.value) {
          return;
        }

        const url = new URL(trigger.dataset.composeUrl, window.location.origin);

        if (bookings.value) {
          url.searchParams.set('booking_id', bookings.value);
        }

        try {
          const response = await fetch(url, { headers: { Accept: 'application/json' } });
          const fresh = await response.json();

          if (fresh?.templates) {
            loaded.templates = fresh.templates;
            applyTemplate();
          }
        } catch (error) {
          /* The wording stays as it was. Nothing is lost — the sender can
             still edit it by hand. */
        }
      });

      triggers.forEach(function (button) {
        button.addEventListener('click', open);
      });

      /* Minimise, expand, restore — the three the header offers. */
      drawer.querySelectorAll('[data-email-minimise]').forEach(function (button) {
        button.addEventListener('click', function (event) {
          event.stopPropagation();
          setState('minimised');
        });
      });

      drawer.querySelectorAll('[data-email-expand]').forEach(function (button) {
        button.addEventListener('click', function (event) {
          event.stopPropagation();
          setState(drawer.dataset.state === 'full' ? 'open' : 'full');
        });
      });

      drawer.querySelectorAll('[data-email-restore]').forEach(function (button) {
        button.addEventListener('click', function () {
          setState(drawer.dataset.state === 'minimised' ? 'open' : 'minimised');
        });
      });

      /* Closing something half-written asks first. A composer that emptied
         itself on a mis-clicked × is one nobody trusts with more than a
         sentence. */
      function requestClose() {
        if (!hasDraft()) {
          discard();

          return;
        }

        if (discardAsk) {
          discardAsk.hidden = false;
        } else {
          discard();
        }
      }

      if (discardAsk) {
        discardAsk.querySelectorAll('[data-email-keep]').forEach(function (button) {
          button.addEventListener('click', function () { discardAsk.hidden = true; });
        });

        discardAsk.querySelectorAll('[data-email-discard-confirm]').forEach(function (button) {
          button.addEventListener('click', discard);
        });
      }

      drawer.querySelectorAll('[data-email-discard]').forEach(function (button) {
        button.addEventListener('click', requestClose);
      });

      drawer.querySelectorAll('[data-email-close]').forEach(function (button) {
        button.addEventListener('click', function (event) {
          event.stopPropagation();
          requestClose();
        });
      });

      document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && !drawer.hidden) {
          close();
        }
      });

      form.addEventListener('submit', async function (event) {
        event.preventDefault();

        if (submit.disabled) {
          return;
        }

        submit.disabled = true;
        show(failure, '');

        try {
          const response = await fetch(trigger.dataset.sendUrl, {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              Accept: 'application/json',
              'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
            },
            body: JSON.stringify({
              subject: subject.value,
              message: message.value,
              template_key: templates.value || null,
              booking_id: bookings.value || null,
              /* Which of the client's addresses, where they have more than
                 one. Absent means "their primary", which is what the server
                 falls back to. */
              to: toSelect.hidden ? null : (toSelect.value || null),
            }),
          });

          const json = await response.json().catch(function () { return {}; });

          if (!response.ok) {
            show(failure, Object.values(json?.errors ?? {}).flat()[0] || json?.message || '');
            return;
          }

          /* Said on the page the reader lands on, not on the one that is
             about to be replaced. The reload is what brings the new message
             into the email history, and a toast raised before it would be
             wiped out half a second later — so it is parked and read back on
             the other side. */
          try {
            sessionStorage.setItem('styledesk:toast', json?.message || '');
          } catch (error) {
            /* Private browsing, or storage switched off. The email still
               sent; only the confirmation is lost. */
          }

          close();
          window.location.reload();
        } catch (error) {
          show(failure, @json(__('client_email.errors.failed')));
        } finally {
          submit.disabled = false;
        }
      });
    }());
</script>
