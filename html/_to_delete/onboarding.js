/* StyleDesk — signup & onboarding shared module
   ------------------------------------------------------------------
   Front-end scaffolding for the flow in the requirements doc:
   step definitions, draft persistence, slug generation, the progress
   indicator, the resume guard, and shared validation helpers.

   IMPORTANT — this is a prototype store, not the source of truth.
   The spec is explicit (§24): onboarding completion must never be
   determined from front-end state. In the Laravel build every read
   here becomes a server call and every write becomes a persisted
   `onboarding.current_step` / `*_completed` flag on the tenant.
   Persistence is wrapped in try/catch and every page must render
   correctly with an empty store, so a browser that blocks storage
   still works — it just won't resume.
   ------------------------------------------------------------------ */
window.SD = (function () {
  'use strict';

  var KEY = 'styledesk.onboarding.draft';
  var memory = null;                 // fallback when storage is unavailable

  /* ---------- Steps ------------------------------------------------ */

  var STEPS = [
    { id: 'account',  label: 'Account',  url: 'signup.html',                flag: 'account_completed'  },
    { id: 'business', label: 'Business', url: 'onboarding-business.html',   flag: 'business_completed' },
    { id: 'location', label: 'Location', url: 'onboarding-location.html',   flag: 'location_completed' },
    { id: 'services', label: 'Services', url: 'onboarding-services.html',   flag: 'services_completed' },
    { id: 'team',     label: 'Team',     url: 'onboarding-team.html',       flag: 'team_completed'     },
    { id: 'booking',  label: 'Booking',  url: 'onboarding-booking.html',    flag: 'booking_completed'  },
    { id: 'complete', label: 'Complete', url: 'onboarding-complete.html',   flag: null                 }
  ];

  /* Steps that may not be skipped (§23 minimum required setup). */
  var REQUIRED = ['account', 'business', 'location'];

  /* ---------- Store ------------------------------------------------ */

  function blank() {
    return {
      user: null,                    // { firstName, lastName, email, emailVerifiedAt }
      tenant: null,                  // { name, slug, types, phone, email, website, logo }
      location: null,
      hours: null,
      services: [],
      team: [],
      booking: null,
      skipped: {},                   // { services: true, ... }
      current_step: 'account'
    };
  }

  function read() {
    if (memory) return memory;
    try {
      var raw = window.localStorage.getItem(KEY);
      memory = raw ? JSON.parse(raw) : blank();
    } catch (e) {
      memory = blank();              // private mode, blocked storage, bad JSON
    }
    return memory;
  }

  function write(state) {
    memory = state;
    try {
      window.localStorage.setItem(KEY, JSON.stringify(state));
    } catch (e) { /* in-memory only for this session */ }
    return state;
  }

  function patch(changes) {
    var state = read();
    for (var k in changes) {
      if (Object.prototype.hasOwnProperty.call(changes, k)) state[k] = changes[k];
    }
    return write(state);
  }

  function reset() {
    memory = blank();
    try { window.localStorage.removeItem(KEY); } catch (e) {}
    return memory;
  }

  function isDone(stepId) {
    var s = read();
    switch (stepId) {
      case 'account':  return !!(s.user && s.user.emailVerifiedAt);
      case 'business': return !!s.tenant;
      case 'location': return !!s.location;
      case 'services': return !!(s.services && s.services.length) || !!s.skipped.services;
      case 'team':     return !!(s.team && s.team.length > 1) || !!s.skipped.team;
      case 'booking':  return !!s.booking || !!s.skipped.booking;
      default:         return false;
    }
  }

  /* Was this step skipped rather than filled in? Drives the "Setup later"
     rows on the completion checklist (§16). */
  function isSkipped(stepId) {
    return !!read().skipped[stepId];
  }

  function markSkipped(stepId) {
    var s = read();
    s.skipped[stepId] = true;
    return write(s);
  }

  /* First step that still needs attention — the target of the §22 redirect. */
  function nextIncomplete() {
    for (var i = 0; i < STEPS.length - 1; i++) {
      if (!isDone(STEPS[i].id)) return STEPS[i];
    }
    return STEPS[STEPS.length - 1];
  }

  function stepIndex(id) {
    for (var i = 0; i < STEPS.length; i++) if (STEPS[i].id === id) return i;
    return 0;
  }

  /* ---------- Slug (§9) -------------------------------------------- */

  /* Taken slugs stand in for the server-side uniqueness check. */
  var TAKEN = ['bella-beauty-studio', 'glow-salon', 'downtown-spa', 'the-barber-co'];

  function slugify(value) {
    return String(value || '')
      .toLowerCase()
      .normalize('NFD').replace(/[\u0300-\u036f]/g, '')   // strip accents
      .replace(/&/g, ' and ')
      .replace(/[^a-z0-9]+/g, '-')
      .replace(/^-+|-+$/g, '')
      .slice(0, 60);
  }

  /* Appends -2, -3 … until free, mirroring the server's collision handling. */
  function uniqueSlug(value) {
    var base = slugify(value);
    if (!base) return '';
    if (TAKEN.indexOf(base) === -1) return base;
    var n = 2;
    while (TAKEN.indexOf(base + '-' + n) !== -1) n++;
    return base + '-' + n;
  }

  /* ---------- Validation helpers ----------------------------------- */

  var EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/;

  /* §19 — normalise before comparing or storing. */
  function normalizeEmail(value) {
    return String(value || '').trim().toLowerCase();
  }

  function isEmail(value) {
    return EMAIL_RE.test(normalizeEmail(value));
  }

  /* Accounts that already exist, for the §3 duplicate-email state. */
  var REGISTERED = ['owner@bellabeauty.com', 'hello@glowsalon.com', 'rohit@styledesk.com'];

  function emailTaken(value) {
    return REGISTERED.indexOf(normalizeEmail(value)) !== -1;
  }

  /* §2 password rules. Returns each rule's pass/fail plus a 0–4 score,
     so the UI can list the rules in text as well as colour them. */
  var PASSWORD_RULES = [
    { id: 'length',  label: 'At least 8 characters',  test: function (v) { return v.length >= 8; } },
    { id: 'upper',   label: 'One uppercase letter',   test: function (v) { return /[A-Z]/.test(v); } },
    { id: 'lower',   label: 'One lowercase letter',   test: function (v) { return /[a-z]/.test(v); } },
    { id: 'number',  label: 'One number',             test: function (v) { return /[0-9]/.test(v); } },
    { id: 'special', label: 'One special character',  test: function (v) { return /[^A-Za-z0-9]/.test(v); } }
  ];

  var STRENGTH_LABELS = ['', 'Weak', 'Fair', 'Good', 'Strong'];

  function checkPassword(value) {
    var v = String(value || '');
    var results = PASSWORD_RULES.map(function (rule) {
      return { id: rule.id, label: rule.label, pass: rule.test(v) };
    });
    var passed = results.filter(function (r) { return r.pass; }).length;

    // 0–4 score: all five rules is Strong, and length beyond the minimum
    // lifts a password that only just scrapes the rules.
    // Meeting every rule tops out at Good; Strong also needs real length,
    // so "Abcdefg1!" is not sold to the user as a strong password.
    var score = 0;
    if (v.length) {
      score = Math.max(1, Math.min(3, passed - 1));
      if (passed === PASSWORD_RULES.length && v.length >= 12) score = 4;
    }

    return {
      rules: results,
      valid: passed === PASSWORD_RULES.length,
      score: score,
      label: STRENGTH_LABELS[score]
    };
  }


  /* ---------- Phone field ------------------------------------------ */

  /* The eight countries StyleDesk sells into today. `mask` is a digit
     template — '#' is a digit, everything else is a literal separator —
     and `len` falls out of it, so adding a country is one row here.
     Flags are emoji: platforms without flag glyphs (Windows) render the
     two-letter code instead, which is why the dial code is always shown
     next to it rather than relying on the flag alone. */
  var COUNTRIES = [
    { iso: 'US', name: 'United States',  dial: '+1',  flag: '🇺🇸', mask: '(###) ###-####' },
    { iso: 'CA', name: 'Canada',         dial: '+1',  flag: '🇨🇦', mask: '(###) ###-####' },
    { iso: 'GB', name: 'United Kingdom', dial: '+44', flag: '🇬🇧', mask: '#### ######'     },
    { iso: 'MX', name: 'Mexico',         dial: '+52', flag: '🇲🇽', mask: '## #### ####'    },
    { iso: 'FR', name: 'France',         dial: '+33', flag: '🇫🇷', mask: '# ## ## ## ##'   },
    { iso: 'DE', name: 'Germany',        dial: '+49', flag: '🇩🇪', mask: '### ########'    },
    { iso: 'ES', name: 'Spain',          dial: '+34', flag: '🇪🇸', mask: '### ## ## ##'    },
    { iso: 'AU', name: 'Australia',      dial: '+61', flag: '🇦🇺', mask: '# #### ####'     }
  ];

  function maskLength(mask) {
    return (mask.match(/#/g) || []).length;
  }

  function countryByIso(iso) {
    for (var i = 0; i < COUNTRIES.length; i++) if (COUNTRIES[i].iso === iso) return COUNTRIES[i];
    return COUNTRIES[0];
  }

  /* Applies the template to whatever digits exist so far. Separators only
     appear once the digit that follows them has been typed, so the caret
     never jumps ahead of the user. */
  function formatPhone(digits, mask) {
    var out = '', d = 0;
    for (var i = 0; i < mask.length && d < digits.length; i++) {
      if (mask[i] === '#') { out += digits[d++]; }
      else { out += mask[i]; }
    }
    return out;
  }

  /* Upgrades one [data-phone] container into a working field.
     Returns an accessor: value() gives { iso, dial, national, e164, complete }. */
  function phoneField(root) {
    if (!root) return null;

    var toggle = root.querySelector('[data-phone-toggle]');
    var flagEl = root.querySelector('[data-phone-flag]');
    var codeEl = root.querySelector('[data-phone-code]');
    var input  = root.querySelector('[data-phone-input]');
    var pop    = root.querySelector('[data-phone-pop]');
    var current = countryByIso(root.getAttribute('data-phone-country') || 'US');

    /* -- popup markup -- */
    pop.innerHTML =
      '<label class="sr-only" for="' + input.id + '-search">Search countries</label>' +
      '<input id="' + input.id + '-search" type="text" class="sd-pop__search" placeholder="Search country or code…" autocomplete="off" />' +
      '<div class="sd-pop__list" role="listbox" aria-label="Country"></div>';

    var search = pop.querySelector('.sd-pop__search');
    var list   = pop.querySelector('.sd-pop__list');
    var active = -1;
    var shown  = [];

    function paintButton() {
      flagEl.textContent = current.flag;
      codeEl.textContent = current.dial;
      toggle.setAttribute('aria-label', 'Country code: ' + current.name + ' ' + current.dial);
      input.placeholder = formatPhone(new Array(maskLength(current.mask) + 1).join('5'), current.mask);
    }

    function paintList(query) {
      var q = (query || '').trim().toLowerCase().replace(/^\+/, '');
      shown = COUNTRIES.filter(function (c) {
        return !q || c.name.toLowerCase().indexOf(q) !== -1 ||
               c.iso.toLowerCase().indexOf(q) !== -1 ||
               c.dial.replace('+', '').indexOf(q) === 0;
      });
      active = shown.length ? 0 : -1;

      list.innerHTML = shown.length
        ? shown.map(function (c, i) {
            return '<button type="button" role="option" data-iso="' + c.iso + '" aria-selected="' +
                   (c.iso === current.iso) + '" class="sd-pop__opt' + (i === 0 ? ' is-active' : '') + '">' +
                     '<span class="sd-phone__flag">' + c.flag + '</span>' +
                     '<span class="truncate">' + escapeHtml(c.name) + '</span>' +
                     '<span class="sd-pop__dial">' + c.dial + '</span>' +
                   '</button>';
          }).join('')
        : '<p class="sd-pop__empty">No country matches that.</p>';
    }

    function open() {
      paintList('');
      search.value = '';
      pop.hidden = false;
      toggle.setAttribute('aria-expanded', 'true');
      search.focus();
    }

    function close(focusToggle) {
      pop.hidden = true;
      toggle.setAttribute('aria-expanded', 'false');
      if (focusToggle) toggle.focus();
    }

    function choose(iso) {
      var next = countryByIso(iso);
      var digits = input.value.replace(/\D/g, '').slice(0, maskLength(next.mask));
      current = next;
      paintButton();
      input.value = formatPhone(digits, current.mask);
      close(false);
      input.focus();
      root.dispatchEvent(new Event('change', { bubbles: true }));
    }

    toggle.addEventListener('click', function () { pop.hidden ? open() : close(true); });

    search.addEventListener('input', function () { paintList(search.value); });

    search.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') { e.preventDefault(); return close(true); }
      if (e.key === 'Enter')  {
        e.preventDefault();
        if (shown[active]) choose(shown[active].iso);
        return;
      }
      if (e.key !== 'ArrowDown' && e.key !== 'ArrowUp') return;
      e.preventDefault();
      if (!shown.length) return;
      active = e.key === 'ArrowDown'
        ? (active + 1) % shown.length
        : (active - 1 + shown.length) % shown.length;
      var opts = list.querySelectorAll('.sd-pop__opt');
      opts.forEach(function (o, i) { o.classList.toggle('is-active', i === active); });
      if (opts[active]) opts[active].scrollIntoView({ block: 'nearest' });
    });

    list.addEventListener('click', function (e) {
      var opt = e.target.closest('[data-iso]');
      if (opt) choose(opt.getAttribute('data-iso'));
    });

    document.addEventListener('click', function (e) {
      if (!pop.hidden && !root.contains(e.target)) close(false);
    });

    /* -- masking -- */
    input.addEventListener('input', function () {
      var atEnd = input.selectionStart === input.value.length;
      var digits = input.value.replace(/\D/g, '').slice(0, maskLength(current.mask));
      input.value = formatPhone(digits, current.mask);
      if (atEnd) input.setSelectionRange(input.value.length, input.value.length);
    });

    function value() {
      var digits = input.value.replace(/\D/g, '');
      return {
        iso: current.iso,
        dial: current.dial,
        national: input.value,
        e164: digits ? current.dial + digits : '',
        complete: digits.length === maskLength(current.mask)
      };
    }

    /* Restores a saved value — E.164 in, formatted out. */
    function set(e164, iso) {
      if (iso) current = countryByIso(iso);
      paintButton();
      if (!e164) { input.value = ''; return; }
      var digits = String(e164).replace(/\D/g, '');
      var dial = current.dial.replace('+', '');
      if (digits.indexOf(dial) === 0) digits = digits.slice(dial.length);
      input.value = formatPhone(digits.slice(0, maskLength(current.mask)), current.mask);
    }

    paintButton();
    return { value: value, set: set, input: input, root: root, country: function () { return current; } };
  }


  /* ---------- Searchable select ------------------------------------ */

  /* Upgrades a native <select> into a searchable combobox without taking it
     out of the DOM. The select stays the value holder and still emits
     `change`, so existing listeners, form serialisation and the draft
     restore all keep working untouched — only the presentation changes.
     Call it again on a select that has already been upgraded and it is a
     no-op, which makes it safe to run after a list is re-rendered. */
  function combo(select, options) {
    if (!select || select.dataset.comboReady) return null;
    options = options || {};
    select.dataset.comboReady = '1';

    var wrap = document.createElement('div');
    wrap.className = 'relative';
    select.parentNode.insertBefore(wrap, select);
    wrap.appendChild(select);

    // Kept for its value, taken out of the tab order and the a11y tree.
    select.classList.add('sr-only');
    select.setAttribute('tabindex', '-1');
    select.setAttribute('aria-hidden', 'true');

    var btn = document.createElement('button');
    btn.type = 'button';
    btn.id = select.id + '-combo';
    // Carry over any layout classes the select was carrying (fixed widths in
    // the business-hours rows), dropping the ones the button restyles itself.
    var carried = (select.className || '').split(/\s+/).filter(function (c) {
      return c && ['sd-input', 'has-value', 'sr-only'].indexOf(c) === -1;
    }).join(' ');
    btn.className = ('sd-input sd-combo-btn ' + carried).trim();
    btn.setAttribute('role', 'combobox');
    btn.setAttribute('aria-haspopup', 'listbox');
    btn.setAttribute('aria-expanded', 'false');
    btn.innerHTML =
      '<span class="sd-combo-btn__label"></span>' +
      '<svg class="sd-combo-btn__caret" width="13" height="13" viewBox="0 0 24 24" fill="none" aria-hidden="true">' +
        '<path d="M6 9l6 6 6-6" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>';
    wrap.insertBefore(btn, select);

    // Point the existing <label for> at the button, which is labelable.
    // The select keeps an aria-label too: it is aria-hidden so assistive tech
    // never reaches it, but this stops it reading as an unlabelled control to
    // tooling and makes the markup self-describing.
    var label = document.querySelector('label[for="' + select.id + '"]');
    if (label) {
      label.setAttribute('for', btn.id);
      select.setAttribute('aria-label', label.textContent.replace(/\s+/g, ' ').trim());
    } else if (select.getAttribute('aria-label')) {
      btn.setAttribute('aria-label', select.getAttribute('aria-label'));
    }

    var pop = document.createElement('div');
    pop.className = 'sd-pop';
    pop.hidden = true;
    pop.innerHTML =
      '<input type="text" class="sd-pop__search" placeholder="' +
        (options.searchPlaceholder || 'Search…') + '" autocomplete="off" aria-label="' +
        (options.searchLabel || 'Search options') + '" />' +
      '<div class="sd-pop__list" role="listbox"></div>';
    wrap.appendChild(pop);
    if (options.width) pop.style.width = options.width;

    var search = pop.querySelector('.sd-pop__search');
    var list   = pop.querySelector('.sd-pop__list');
    var shown  = [];
    var active = -1;

    function items() {
      return Array.prototype.map.call(select.options, function (o, i) {
        return { i: i, value: o.value, text: o.textContent, placeholder: o.value === '' };
      });
    }

    function paintButton() {
      var opt = select.options[select.selectedIndex];
      var isPlaceholder = !opt || opt.value === '';
      btn.querySelector('.sd-combo-btn__label').textContent =
        opt ? opt.textContent : (options.placeholder || 'Select…');
      btn.classList.toggle('is-placeholder', isPlaceholder);
    }

    function paintList(query) {
      var q = (query || '').trim().toLowerCase();
      shown = items().filter(function (o) {
        return !q || o.text.toLowerCase().indexOf(q) !== -1;
      });
      var selectedValue = select.value;
      active = 0;
      shown.forEach(function (o, i) { if (o.value === selectedValue) active = i; });

      list.innerHTML = shown.length
        ? shown.map(function (o, i) {
            return '<button type="button" role="option" data-index="' + o.i + '" aria-selected="' +
                   (o.value === selectedValue) + '" class="sd-pop__opt' +
                   (i === active ? ' is-active' : '') + '">' +
                     '<span class="truncate">' + escapeHtml(o.text) + '</span>' +
                     (o.value === selectedValue
                       ? '<span class="sd-pop__dial"><svg width="13" height="13" viewBox="0 0 24 24" fill="none">' +
                         '<path d="M5 12l5 5 9-11" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"/></svg></span>'
                       : '') +
                   '</button>';
          }).join('')
        : '<p class="sd-pop__empty">No match.</p>';
    }

    function scrollActiveIntoView() {
      var opts = list.querySelectorAll('.sd-pop__opt');
      if (opts[active]) opts[active].scrollIntoView({ block: 'nearest' });
    }

    function open() {
      paintList('');
      search.value = '';
      pop.hidden = false;
      btn.setAttribute('aria-expanded', 'true');
      search.focus();
      scrollActiveIntoView();
    }

    function close(focusBtn) {
      pop.hidden = true;
      btn.setAttribute('aria-expanded', 'false');
      if (focusBtn) btn.focus();
    }

    function choose(index) {
      select.selectedIndex = index;
      paintButton();
      close(true);
      select.dispatchEvent(new Event('change', { bubbles: true }));
    }

    btn.addEventListener('click', function () { pop.hidden ? open() : close(true); });

    btn.addEventListener('keydown', function (e) {
      if (e.key === 'ArrowDown' || e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        if (pop.hidden) open();
      }
    });

    search.addEventListener('input', function () { paintList(search.value); });

    search.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') { e.preventDefault(); return close(true); }
      if (e.key === 'Enter') {
        e.preventDefault();
        if (shown[active]) choose(shown[active].i);
        return;
      }
      if (e.key !== 'ArrowDown' && e.key !== 'ArrowUp') return;
      e.preventDefault();
      if (!shown.length) return;
      active = e.key === 'ArrowDown'
        ? (active + 1) % shown.length
        : (active - 1 + shown.length) % shown.length;
      list.querySelectorAll('.sd-pop__opt').forEach(function (o, i) {
        o.classList.toggle('is-active', i === active);
      });
      scrollActiveIntoView();
    });

    list.addEventListener('click', function (e) {
      var opt = e.target.closest('[data-index]');
      if (opt) choose(Number(opt.getAttribute('data-index')));
    });

    document.addEventListener('click', function (e) {
      if (!pop.hidden && !wrap.contains(e.target)) close(false);
    });

    // Keep the button in step with programmatic changes (draft restore,
    // timezone inferred from the address, copy-hours-to-weekdays).
    select.addEventListener('change', paintButton);

    paintButton();
    var api = { refresh: paintButton, button: btn, select: select };
    select.sdCombo = api;
    return api;
  }

  /* Assigning select.value in code does not fire `change`, so the button
     would keep showing the old label. Call this after any programmatic set. */
  function comboRefresh(select) {
    if (select && select.sdCombo) select.sdCombo.refresh();
  }

  /* Upgrades every select inside `root` that is not already a combo. */
  function comboAll(root, options) {
    (root || document).querySelectorAll('select').forEach(function (el) {
      combo(el, options);
    });
  }

  /* ---------- Field error plumbing --------------------------------- */

  /* Marks a control invalid and writes the message into its
     [data-error-for] element, which is aria-live so screen readers
     announce it (§27). */
  function setError(input, message) {
    if (!input) return;
    var box = document.querySelector('[data-error-for="' + input.id + '"]');
    // Composite controls draw their outline on the wrapper, so the class goes
    // there while aria-invalid stays on the control the user focuses.
    var host = (input.dataset && input.dataset.comboReady && document.getElementById(input.id + '-combo'))
            || (input.closest && (input.closest('.sd-phone') || input.closest('.sd-group')))
            || input;
    if (message) {
      host.classList.add('is-error');
      input.setAttribute('aria-invalid', 'true');
      if (box) { box.textContent = message; box.hidden = false; }
    } else {
      host.classList.remove('is-error');
      input.removeAttribute('aria-invalid');
      if (box) { box.textContent = ''; box.hidden = true; }
    }
    return !message;
  }

  function clearErrors(form) {
    if (!form) return;
    form.querySelectorAll('.is-error').forEach(function (el) {
      el.classList.remove('is-error');
      el.removeAttribute('aria-invalid');
    });
    form.querySelectorAll('[aria-invalid]').forEach(function (el) {
      el.removeAttribute('aria-invalid');
    });
    form.querySelectorAll('[data-error-for]').forEach(function (el) {
      el.textContent = '';
      el.hidden = true;
    });
  }

  /* Moves focus to the first invalid control so keyboard and screen-reader
     users land on the problem instead of hunting for it. */
  function focusFirstError(form) {
    var first = form && form.querySelector('.is-error');
    if (first && typeof first.focus === 'function') first.focus();
  }

  /* ---------- Progress indicator ----------------------------------- */

  /* Fills [data-step-label] with "Step N of 7" and sets the width of the
     [data-progress] bar. The label is the accessible signal; the bar only
     reinforces it, so progress is never conveyed by the bar alone. */
  function renderProgress(currentId) {
    var current = stepIndex(currentId);
    var pct = Math.round(((current + 1) / STEPS.length) * 100);

    var label = document.querySelector('[data-step-label]');
    if (label) label.textContent = 'Step ' + (current + 1) + ' of ' + STEPS.length;

    var bar = document.querySelector('[data-progress]');
    if (bar) {
      bar.style.width = pct + '%';
      var track = bar.parentNode;
      track.setAttribute('role', 'progressbar');
      track.setAttribute('aria-valuenow', String(current + 1));
      track.setAttribute('aria-valuemin', '1');
      track.setAttribute('aria-valuemax', String(STEPS.length));
      track.setAttribute('aria-label', 'Setup progress');
    }
  }

  /* ---------- Resume guard (§22) ----------------------------------- */

  /* Called on each onboarding page. If the user has jumped ahead of their
     real progress, send them to the first step that still needs work.
     Required steps are enforced; optional ones are not, so a user can move
     forward past Services/Team/Booking. */
  function guard(currentId) {
    var target = nextIncomplete();
    var here = stepIndex(currentId);
    var allowed = stepIndex(target.id);

    // Only bounce backwards, and only past a step that is genuinely required.
    if (here > allowed && REQUIRED.indexOf(target.id) !== -1) {
      window.location.replace(target.url);
      return false;
    }
    patch({ current_step: currentId });
    return true;
  }

  /* ---------- Trial (§18) ------------------------------------------ */

  var TRIAL_DAYS = 14;

  function trialRemaining() {
    var s = read();
    if (!s.tenant || !s.tenant.trialStart) return TRIAL_DAYS;
    var elapsed = (Date.now() - s.tenant.trialStart) / 86400000;
    return Math.max(0, Math.ceil(TRIAL_DAYS - elapsed));
  }

  /* ---------- Misc -------------------------------------------------- */

  function escapeHtml(value) {
    return String(value == null ? '' : value)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
  }

  /* Guards against the §25 double-submit: disables the button and restores
     it if the caller reports failure. */
  function lockSubmit(button, busyLabel) {
    if (!button || button.disabled) return null;
    var original = button.innerHTML;
    button.disabled = true;
    button.classList.add('opacity-60', 'pointer-events-none');
    if (busyLabel) button.textContent = busyLabel;
    return function release() {
      button.disabled = false;
      button.classList.remove('opacity-60', 'pointer-events-none');
      button.innerHTML = original;
    };
  }

  return {
    STEPS: STEPS,
    PASSWORD_RULES: PASSWORD_RULES,
    TRIAL_DAYS: TRIAL_DAYS,
    read: read, write: write, patch: patch, reset: reset,
    isDone: isDone, isSkipped: isSkipped, markSkipped: markSkipped,
    nextIncomplete: nextIncomplete, stepIndex: stepIndex,
    slugify: slugify, uniqueSlug: uniqueSlug,
    normalizeEmail: normalizeEmail, isEmail: isEmail, emailTaken: emailTaken,
    checkPassword: checkPassword,
    COUNTRIES: COUNTRIES, phoneField: phoneField, formatPhone: formatPhone,
    combo: combo, comboAll: comboAll, comboRefresh: comboRefresh,
    setError: setError, clearErrors: clearErrors, focusFirstError: focusFirstError,
    renderProgress: renderProgress, guard: guard, trialRemaining: trialRemaining,
    escapeHtml: escapeHtml, lockSubmit: lockSubmit
  };
}());
