/* StyleDesk — shared front-end module
   Used by signup, the onboarding wizard, settings and the dashboard.
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

  /* `options.minLength` raises the length rule for callers with a
     stricter policy — the account password change asks for 10 (§20)
     while sign-up asks for 8. The rule list is rebuilt rather than
     mutated so the two policies cannot leak into each other. */
  function checkPassword(value, options) {
    var v = String(value || '');
    var min = (options && options.minLength) || 8;

    var rules = PASSWORD_RULES.map(function (rule) {
      if (rule.id !== 'length') return rule;
      return {
        id: 'length',
        label: 'At least ' + min + ' characters',
        test: function (x) { return x.length >= min; }
      };
    });

    var results = rules.map(function (rule) {
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
      if (passed === rules.length && v.length >= min + 4) score = 4;
    }

    return {
      rules: results,
      valid: passed === rules.length,
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
  var comboSeq = 0;

  function combo(select, options) {
    if (!select || select.dataset.comboReady) return null;
    options = options || {};
    select.dataset.comboReady = '1';

    /* The button's id is derived from the select's, and two buttons sharing
       an id break every `label[for]` and `aria-controls` on the page. Rows
       added by a repeater have no id of their own, so one is minted here. */
    if (!select.id) {
      comboSeq += 1;
      select.id = 'sd-combo-' + comboSeq;
    }

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

    /* A search box over four options is furniture. It stays in the DOM and
       keeps focus, so typing still filters and the keyboard handling below
       is unchanged — it is simply not drawn. */
    if (options.search === false) search.classList.add('sr-only');

    function items() {
      /* A disabled or hidden option is one this field is not offering — the
         membership form marks a service already added on another line that
         way. The one currently chosen is always kept, or a row would stop
         showing its own answer. */
      return Array.prototype.map.call(select.options, function (o, i) {
        return {
          i: i, value: o.value, text: o.textContent, placeholder: o.value === '',
          offered: !(o.disabled || o.hidden) || o.value === select.value,
        };
      }).filter(function (o) { return o.offered; });
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

  /* ---------- Date picker ------------------------------------------
     Calendar popover on a readonly field. Lives here rather than in a
     page so designsystem.html and the client screens share one
     implementation.

     Two deliberate changes from the original demo version:
     - month and year are native <select>s dressed to look like the mini
       buttons, so keyboard and screen-reader support come for free
       instead of being reimplemented on <li> elements;
     - the grid is keyboard-navigable (arrows, PageUp/Down, Home/End),
       which the click-only version was not.

     The visible field shows MM/DD/YYYY; the ISO value lives on the
     input's `data-value` so form code never has to reparse the display
     string. Selecting a day fires `change` on the input.

     Markup:
       <div class="relative" data-datepicker>
         <input class="sd-input is-picker has-suffix" readonly data-dp-input />
         <span …calendar icon…></span>
         <div class="sd-cal" data-dp-cal hidden></div>
       </div>                                                          */

  var DP_MONTHS = ['January', 'February', 'March', 'April', 'May', 'June',
                   'July', 'August', 'September', 'October', 'November', 'December'];
  var DP_DOW = ['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa'];

  function dpPad(n) { return (n < 10 ? '0' : '') + n; }
  function dpIso(d) { return d.getFullYear() + '-' + dpPad(d.getMonth() + 1) + '-' + dpPad(d.getDate()); }
  function dpDisplay(d, order) {
    var day = dpPad(d.getDate()), month = dpPad(d.getMonth() + 1), year = d.getFullYear();
    // Day-first for everyone who writes dates that way; the ISO value on
    // data-value is unaffected either way, so only the reading changes.
    return order === 'dmy' ? day + '/' + month + '/' + year : month + '/' + day + '/' + year;
  }
  function dpParse(iso) {
    var m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(String(iso || ''));
    if (!m) return null;
    var d = new Date(Number(m[1]), Number(m[2]) - 1, Number(m[3]));
    return isNaN(d.getTime()) ? null : d;
  }
  function dpSame(a, b) {
    return !!a && !!b && a.getFullYear() === b.getFullYear() &&
           a.getMonth() === b.getMonth() && a.getDate() === b.getDate();
  }
  function dpMidnight(d) { return new Date(d.getFullYear(), d.getMonth(), d.getDate()); }

  function datePicker(root, options) {
    if (!root || root.dataset.dpReady) return null;
    root.dataset.dpReady = '1';
    options = options || {};

    var input = root.querySelector('[data-dp-input]');
    var cal = root.querySelector('[data-dp-cal]');
    if (!input || !cal) return null;

    /* Wording is passed in rather than baked in: the app runs in more than
       one language, and a Spanish form with an English calendar inside it is
       the kind of seam a business notices immediately. The English defaults
       stay so the prototype pages, which pass nothing, are unaffected. */
    var L = options.labels || {};
    var MONTHS = L.months && L.months.length === 12 ? L.months : DP_MONTHS;
    var DOW = L.dow && L.dow.length === 7 ? L.dow : DP_DOW;

    var today = dpMidnight(new Date());
    var min = options.min ? dpParse(options.min) : null;
    var max = options.max ? dpParse(options.max) : null;
    var maxYear = options.maxYear || (max ? max.getFullYear() : today.getFullYear() + 10);
    var minYear = options.minYear || (min ? min.getFullYear() : today.getFullYear() - 10);

    var selected = dpParse(input.dataset.value || options.value || '');
    // What the grid opens on when nothing is chosen. For a date of birth
    // that should not be this month — you would page back decades.
    var fallback = dpParse(options.openTo || '') || today;
    var view = new Date((selected || fallback).getFullYear(), (selected || fallback).getMonth(), 1);
    var focusDay = selected ? new Date(selected) : new Date(view);

    input.setAttribute('readonly', 'readonly');
    input.setAttribute('autocomplete', 'off');
    input.setAttribute('aria-haspopup', 'dialog');
    input.setAttribute('aria-expanded', 'false');
    cal.setAttribute('role', 'dialog');
    cal.setAttribute('aria-label', options.dialogLabel || 'Choose a date');

    function outOfRange(d) {
      if (min && d < min) return true;
      if (max && d > max) return true;
      return false;
    }

    function paint() {
      var y = view.getFullYear(), m = view.getMonth();

      var months = MONTHS.map(function (name, i) {
        return '<option value="' + i + '"' + (i === m ? ' selected' : '') + '>' + name + '</option>';
      }).join('');
      var years = '';
      for (var yr = maxYear; yr >= minYear; yr--) {
        years += '<option value="' + yr + '"' + (yr === y ? ' selected' : '') + '>' + yr + '</option>';
      }

      var start = new Date(y, m, 1);
      start.setDate(1 - start.getDay());
      var cells = '';
      for (var i = 0; i < 42; i++) {
        var d = new Date(start.getFullYear(), start.getMonth(), start.getDate() + i);
        var cls = 'sd-cal__day';
        if (d.getMonth() !== m) cls += ' is-out';
        if (dpSame(d, today)) cls += ' is-today';
        if (dpSame(d, selected)) cls += ' is-selected';
        var disabled = outOfRange(d);
        // One tab stop for the whole grid; arrows move within it.
        var tab = dpSame(d, focusDay) ? '0' : '-1';
        cells += '<button type="button" class="' + cls + '" data-dp-day="' + dpIso(d) + '"' +
          ' tabindex="' + tab + '"' + (disabled ? ' disabled' : '') +
          ' aria-label="' + MONTHS[d.getMonth()] + ' ' + d.getDate() + ', ' + d.getFullYear() +
          (dpSame(d, today) ? ' (' + (L.today || 'Today') + ')' : '') + '"' +
          (dpSame(d, selected) ? ' aria-current="date"' : '') + '>' + d.getDate() + '</button>';
      }

      var prevOff = min && new Date(y, m, 0) < min;
      var nextOff = max && new Date(y, m + 1, 1) > max;

      cal.innerHTML =
        '<div class="flex items-center gap-1.5 mb-3">' +
          '<button type="button" class="sd-cal__nav grid" data-dp-nav="-1" aria-label="' + escapeHtml(L.previousMonth || 'Previous month') + '"' + (prevOff ? ' disabled' : '') + '>' +
            '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M15 6l-6 6 6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>' +
          '</button>' +
          '<div class="flex-1 min-w-0">' +
            '<label class="sr-only" for="' + input.id + '-month">' + escapeHtml(L.month || 'Month') + '</label>' +
            '<select id="' + input.id + '-month" class="sd-cal__mini" data-dp-month>' + months + '</select>' +
          '</div>' +
          '<div class="w-[92px] shrink-0">' +
            '<label class="sr-only" for="' + input.id + '-year">' + escapeHtml(L.year || 'Year') + '</label>' +
            '<select id="' + input.id + '-year" class="sd-cal__mini" data-dp-year>' + years + '</select>' +
          '</div>' +
          '<button type="button" class="sd-cal__nav grid" data-dp-nav="1" aria-label="' + escapeHtml(L.nextMonth || 'Next month') + '"' + (nextOff ? ' disabled' : '') + '>' +
            '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M9 6l6 6-6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>' +
          '</button>' +
        '</div>' +
        '<div class="grid grid-cols-7 gap-0.5 mb-1" aria-hidden="true">' +
          DOW.map(function (x) { return '<span class="sd-cal__dow">' + escapeHtml(x) + '</span>'; }).join('') +
        '</div>' +
        '<div class="grid grid-cols-7 gap-0.5" role="group" aria-label="' + MONTHS[m] + ' ' + y + '">' + cells + '</div>' +
        (options.clearable === false ? '' :
          '<div class="flex items-center gap-2 mt-3 pt-3 border-t border-line">' +
            '<button type="button" class="h-8 px-3 rounded-md text-[12px] font-semibold text-sub hover:text-ink hover:bg-hover transition-colors" data-dp-clear>' + escapeHtml(L.clear || 'Clear') + '</button>' +
            (outOfRange(today) ? '' :
              '<button type="button" class="ml-auto h-8 px-3 rounded-md text-[12px] font-semibold text-brand hover:bg-hover transition-colors" data-dp-today>' + escapeHtml(L.today || 'Today') + '</button>') +
          '</div>');

      comboHeader();
    }

    /* Month and year as combos, matching every other dropdown in the app.
       Re-applied after each paint because the header is rebuilt with the
       grid; the month list is short enough not to want a search box, while
       a hundred years is exactly what one is for. */
    function comboHeader() {
      var month = cal.querySelector('[data-dp-month]');
      var year = cal.querySelector('[data-dp-year]');

      if (month) combo(month, { search: false, width: '190px' });
      if (year) {
        combo(year, {
          width: '130px',
          searchPlaceholder: L.year || 'Year',
          searchLabel: L.year || 'Year'
        });
      }
    }

    function moveFocus(days) {
      var next = new Date(focusDay.getFullYear(), focusDay.getMonth(), focusDay.getDate() + days);
      if (outOfRange(next)) return;
      focusDay = next;
      if (focusDay.getMonth() !== view.getMonth() || focusDay.getFullYear() !== view.getFullYear()) {
        view = new Date(focusDay.getFullYear(), focusDay.getMonth(), 1);
      }
      paint();
      var el = cal.querySelector('[data-dp-day="' + dpIso(focusDay) + '"]');
      if (el) el.focus();
    }

    function moveMonth(delta) {
      view = new Date(view.getFullYear(), view.getMonth() + delta, 1);
      paint();
    }

    function commit(d) {
      selected = d;
      if (d) {
        input.value = dpDisplay(d, options.order);
        input.dataset.value = dpIso(d);
      } else {
        input.value = '';
        delete input.dataset.value;
      }
      // Real event, so page code can listen the same way it would to a
      // native <input type="date">.
      input.dispatchEvent(new Event('change', { bubbles: true }));
      if (options.onChange) options.onChange(d ? dpIso(d) : '');
    }

    function isOpen() { return !cal.hidden; }

    function open() {
      if (isOpen()) return;
      var base = selected || fallback;
      view = new Date(base.getFullYear(), base.getMonth(), 1);
      focusDay = selected ? new Date(selected) : new Date(base);
      paint();
      cal.hidden = false;
      input.setAttribute('aria-expanded', 'true');
      place();
    }

    function close(focusInput) {
      if (!isOpen()) return;
      cal.hidden = true;
      cal.classList.remove('sd-cal--up', 'sd-cal--right');
      input.setAttribute('aria-expanded', 'false');
      if (focusInput) input.focus();
    }

    /* Flip up / right rather than hang off the viewport. */
    function place() {
      cal.classList.remove('sd-cal--up', 'sd-cal--right');
      var r = cal.getBoundingClientRect();
      if (r.bottom > window.innerHeight - 8 && input.getBoundingClientRect().top > r.height + 8) {
        cal.classList.add('sd-cal--up');
      }
      if (cal.getBoundingClientRect().right > window.innerWidth - 8) {
        cal.classList.add('sd-cal--right');
      }
    }

    input.addEventListener('click', function (e) {
      e.stopPropagation();
      isOpen() ? close() : open();
    });
    input.addEventListener('keydown', function (e) {
      if (e.key === 'Enter' || e.key === ' ' || e.key === 'ArrowDown') {
        e.preventDefault();
        open();
        var el = cal.querySelector('[tabindex="0"]');
        if (el) el.focus();
      }
    });

    cal.addEventListener('click', function (e) {
      e.stopPropagation();
      var nav = e.target.closest('[data-dp-nav]');
      if (nav) { moveMonth(Number(nav.dataset.dpNav)); return; }

      if (e.target.closest('[data-dp-clear]')) { commit(null); close(true); return; }
      if (e.target.closest('[data-dp-today]')) { commit(today); close(true); return; }

      var day = e.target.closest('[data-dp-day]');
      if (day) {
        commit(dpParse(day.dataset.dpDay));
        close(true);
      }
    });

    cal.addEventListener('change', function (e) {
      /* Focus goes back to the control the reader used. Once the select is
         a combo it is the button that is visible and focusable — focusing
         the hidden select would leave the ring nowhere. */
      var refocus = function (selector) {
        var el = cal.querySelector(selector);
        if (!el) return;
        (el.sdCombo ? el.sdCombo.button : el).focus();
      };

      if (e.target.matches('[data-dp-month]')) {
        view = new Date(view.getFullYear(), Number(e.target.value), 1);
        paint();
        refocus('[data-dp-month]');
      } else if (e.target.matches('[data-dp-year]')) {
        view = new Date(Number(e.target.value), view.getMonth(), 1);
        paint();
        refocus('[data-dp-year]');
      }
    });

    cal.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') { e.stopPropagation(); close(true); return; }
      if (!e.target.closest('[data-dp-day]')) return;
      var map = { ArrowLeft: -1, ArrowRight: 1, ArrowUp: -7, ArrowDown: 7 };
      if (e.key in map) { e.preventDefault(); moveFocus(map[e.key]); return; }
      if (e.key === 'PageUp')   { e.preventDefault(); moveFocus(-28); return; }
      if (e.key === 'PageDown') { e.preventDefault(); moveFocus(28); return; }
      if (e.key === 'Home')     { e.preventDefault(); moveFocus(-focusDay.getDay()); return; }
      if (e.key === 'End')      { e.preventDefault(); moveFocus(6 - focusDay.getDay()); return; }
    });

    document.addEventListener('click', function (e) {
      if (isOpen() && !root.contains(e.target)) close();
    });
    // Focus leaving the whole control closes it, so tabbing past the
    // calendar does not leave it hanging open. Checked on the next tick
    // against activeElement rather than relatedTarget: repainting the
    // grid detaches whatever had focus, which fires focusout with a null
    // relatedTarget and would otherwise close the calendar out from
    // under someone changing the month.
    root.addEventListener('focusout', function () {
      window.setTimeout(function () {
        if (isOpen() && !root.contains(document.activeElement)) close();
      }, 0);
    });

    if (selected) commit(selected);

    var api = {
      value: function () { return selected ? dpIso(selected) : ''; },
      set: function (iso) { commit(dpParse(iso)); },
      clear: function () { commit(null); },
      open: open,
      close: close,
      input: input
    };
    input.sdDatePicker = api;
    return api;
  }

  /* Upgrades every `[data-datepicker]` inside `root`, reading each field's
     own options from data-dp-options. Lets a Blade view add a date field by
     writing markup, with no per-page init script to forget. */
  function datePickerAll(root) {
    (root || document).querySelectorAll('[data-datepicker]').forEach(function (el) {
      var options = {};

      if (el.dataset.dpOptions) {
        try {
          options = JSON.parse(el.dataset.dpOptions);
        } catch (e) {
          if (window.console) console.error('[styledesk] Invalid data-dp-options JSON.', el, e);
        }
      }

      var api = datePicker(el, options);
      if (!api) return;

      /* The visible field is a display string; the form posts ISO. Keeping
         them in two inputs means no server code has to parse MM/DD/YYYY, and
         no screen has to remember which format it drew. */
      var target = api.input.dataset.dpFor && document.getElementById(api.input.dataset.dpFor);
      if (!target) return;

      var sync = function () { target.value = api.value(); };
      api.input.addEventListener('change', sync);
      sync();
    });
  }

  /* ---------- Account menu (avatar dropdown) ------------------------
     Lives in the app bar on every screen. Built here rather than in a
     page so all five headers stay identical, and so it works whether or
     not assets/js/account.js happens to be loaded — the labels are
     static, only the name/email/photo come from the account store.

     Behaviour per spec: opens below the avatar, right-aligned, closes on
     a second click, on an outside click and on Escape, whole row is the
     target, and arrow keys walk the rows.                              */

  var ACCOUNT_ICONS = {
    settings: '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="3.1" stroke="currentColor" stroke-width="1.7"/><path d="M19.4 15a1.7 1.7 0 00.3 1.9 1.9 1.9 0 11-2.8 2.8 1.7 1.7 0 00-1.9-.3 1.7 1.7 0 00-1 1.6 1.9 1.9 0 11-3.9 0 1.7 1.7 0 00-1-1.6 1.7 1.7 0 00-1.9.3 1.9 1.9 0 11-2.8-2.8 1.7 1.7 0 00.3-1.9 1.7 1.7 0 00-1.6-1 1.9 1.9 0 110-3.9 1.7 1.7 0 001.6-1 1.7 1.7 0 00-.3-1.9 1.9 1.9 0 112.8-2.8 1.7 1.7 0 001.9.3 1.7 1.7 0 001-1.6 1.9 1.9 0 113.9 0 1.7 1.7 0 001 1.6 1.7 1.7 0 001.9-.3 1.9 1.9 0 112.8 2.8 1.7 1.7 0 00-.3 1.9 1.7 1.7 0 001.6 1 1.9 1.9 0 110 3.9 1.7 1.7 0 00-1.6 1z" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>',
    profile: '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="8" r="3.4" stroke="currentColor" stroke-width="1.7"/><path d="M5.5 19.5a6.5 6.5 0 0113 0" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>',
    preferences: '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M5 7h14M5 12h14M5 17h14" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/><circle cx="9" cy="7" r="2" fill="#fff" stroke="currentColor" stroke-width="1.7"/><circle cx="15" cy="12" r="2" fill="#fff" stroke="currentColor" stroke-width="1.7"/><circle cx="9" cy="17" r="2" fill="#fff" stroke="currentColor" stroke-width="1.7"/></svg>',
    password: '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" aria-hidden="true"><rect x="4.5" y="10.5" width="15" height="9.5" rx="2" stroke="currentColor" stroke-width="1.7"/><path d="M8 10.5V7.5a4 4 0 018 0v3" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>',
    notifications: '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M6.5 10a5.5 5.5 0 0111 0v4l1.5 2.5H5L6.5 14v-4z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/><path d="M10 19.5a2.2 2.2 0 004 0" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>',
    signout: '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M14 5.5H6.5A1.5 1.5 0 005 7v10a1.5 1.5 0 001.5 1.5H14" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/><path d="M16 8.5l3.5 3.5L16 15.5M19 12h-9" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>'
  };

  var ACCOUNT_ROWS = [
    { id: 'profile',       label: 'My Profile',      url: 'account-profile.html' },
    { id: 'preferences',   label: 'Preferences',     url: 'account-preferences.html' },
    { id: 'password',      label: 'Change Password', url: 'account-password.html' },
    { id: 'notifications', label: 'Notifications',   url: 'account-notifications.html' }
  ];

  function accountMenu(root, options) {
    if (!root || root.dataset.acctReady) return null;
    root.dataset.acctReady = '1';
    options = options || {};

    var account = window.SDA;
    var name  = options.name  || (account ? account.fullName() : 'Bertrand Bruant');
    var email = options.email || (account ? account.read().user.email : 'bertrand@nicelydone.com');
    var initials = options.initials || (account ? account.initials() : 'BB');
    var photo = options.photo || (account ? account.read().user.photo : null);
    /* The workspace line under the user's name is the business, not the
       product, so it comes from Branding once that has been set. */
    var brand = window.SDBR;
    var org = options.org || (brand ? brand.displayName() : 'Nicelydone');

    // Where Account Settings should send the user back to (§5).
    var here = window.location.pathname.split('/').pop() || 'dashboard.html';
    var from = encodeURIComponent(here.indexOf('account-') === 0 ? 'dashboard.html' : here);

    /* The rows, and where App Settings lives.
       Passed in by the Laravel shell, which knows the real routes and the
       reader's language; the prototype's own .html list is the fallback so
       this file still works when opened straight from html/. Rows whose id
       has no icon are skipped rather than drawn blank. */
    var rows = (options.rows && options.rows.length ? options.rows : ACCOUNT_ROWS);
    var settingsUrl = options.settingsUrl || 'app-settings.html';
    var settingsLabel = options.settingsLabel || 'App Settings';
    var showSettings = options.showSettings !== false;
    var linked = !!options.rows;

    var avatar = photo
      ? '<img src="' + photo + '" alt="" class="h-full w-full object-cover" />'
      : escapeHtml(initials);


    var btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'sd-acct__btn';
    btn.setAttribute('aria-haspopup', 'menu');
    btn.setAttribute('aria-expanded', 'false');
    btn.setAttribute('aria-label', 'Account menu for ' + name);
    btn.innerHTML =
      '<span class="h-8 w-8 rounded-full bg-white text-head grid place-items-center text-[11px] font-bold shrink-0 overflow-hidden">' + avatar + '</span>' +
      '<span class="hidden xl:block leading-tight min-w-0 text-left">' +
        '<span class="block text-white text-[13px] font-semibold truncate max-w-[130px]">' + escapeHtml(name) + '</span>' +
        '<span class="block text-white/70 text-[11px] truncate max-w-[130px]">' + escapeHtml(org) + '</span>' +
      '</span>' +
      '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" class="text-white/70 shrink-0" aria-hidden="true"><path d="M6 9l6 6 6-6" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>';

    var pop = document.createElement('div');
    pop.className = 'sd-acct__pop';
    pop.setAttribute('role', 'menu');
    pop.setAttribute('aria-label', 'Account');
    pop.hidden = true;
    pop.innerHTML =
      '<div class="sd-acct__head">' +
        '<span class="sd-avatar sd-avatar--md" aria-hidden="true">' + avatar + '</span>' +
        '<span class="min-w-0">' +
          '<span class="block text-[13px] font-semibold text-head truncate">' + escapeHtml(name) + '</span>' +
          '<span class="block text-[12px] text-sub truncate">' + escapeHtml(email) + '</span>' +
        '</span>' +
      '</div>' +
      rows.map(function (r) {
        /* Real routes carry no ?from=: the app has a back button and a nav,
           and an opener remembered in a query string would be a second,
           worse one. */
        var href = linked ? r.url : r.url + '?from=' + from;
        return '<a class="sd-acct__item" role="menuitem" tabindex="-1" href="' + href + '">' +
          (ACCOUNT_ICONS[r.id] || '') + escapeHtml(r.label) + '</a>';
      }).join('') +
      (showSettings
        ? '<div class="sd-acct__sep"></div>' +
          '<a class="sd-acct__item" role="menuitem" tabindex="-1" href="' + settingsUrl + '">' +
          ACCOUNT_ICONS.settings + escapeHtml(settingsLabel) + '</a>'
        : '') +
      '<div class="sd-acct__sep"></div>' +
      '<button type="button" class="sd-acct__item sd-acct__item--danger" role="menuitem" tabindex="-1" data-signout>' +
        ACCOUNT_ICONS.signout + 'Sign Out</button>';

    root.className = (root.className + ' sd-acct').trim();
    root.innerHTML = '';
    root.appendChild(btn);
    root.appendChild(pop);

    function items() {
      return Array.prototype.slice.call(pop.querySelectorAll('.sd-acct__item'));
    }
    function isOpen() { return !pop.hidden; }

    function open(focusFirst) {
      pop.hidden = false;
      btn.setAttribute('aria-expanded', 'true');
      if (focusFirst) { var f = items()[0]; if (f) f.focus(); }
    }
    function close(focusBtn) {
      if (!isOpen()) return;
      pop.hidden = true;
      btn.setAttribute('aria-expanded', 'false');
      if (focusBtn) btn.focus();
    }

    btn.addEventListener('click', function (e) {
      e.stopPropagation();
      isOpen() ? close() : open(false);
    });
    btn.addEventListener('keydown', function (e) {
      if (e.key === 'ArrowDown' || e.key === 'ArrowUp') { e.preventDefault(); open(true); }
    });

    pop.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') { e.stopPropagation(); close(true); return; }
      var list = items();
      var at = list.indexOf(document.activeElement);
      if (e.key === 'ArrowDown') { e.preventDefault(); (list[at + 1] || list[0]).focus(); }
      else if (e.key === 'ArrowUp') { e.preventDefault(); (list[at - 1] || list[list.length - 1]).focus(); }
      else if (e.key === 'Home') { e.preventDefault(); list[0].focus(); }
      else if (e.key === 'End') { e.preventDefault(); list[list.length - 1].focus(); }
      else if (e.key === 'Tab') { close(); }
    });

    document.addEventListener('click', function (e) {
      if (isOpen() && !root.contains(e.target)) close();
    });

    pop.querySelector('[data-signout]').addEventListener('click', function () {
      close();
      if (options.onSignOut) { options.onSignOut(); return; }
      // No login screen in the prototype; say what would happen (§39).
      if (window.SDA) SDA.toast('Signing out — this ends the session and returns to the login screen.');
    });

    return { open: open, close: close, root: root, button: btn };
  }

  /* Upgrades every [data-account-menu] container on the page. */
  function accountMenuAll(options) {
    document.querySelectorAll('[data-account-menu]').forEach(function (el) {
      accountMenu(el, options);
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

  /* ---------- Staff roster -----------------------------------------
     The tenant's team. It lives here rather than in any one feature's
     file because three modules need it independently: Clients (preferred
     staff), Resources (who a booking holds) and Services (who is
     eligible, at what price, for how long). `locations` is which sites a
     person actually works at — a service can only be staffed at a
     location where someone eligible is based. */

  /* The roster now lives in SDT, which holds their hours, services,
     locations, roles and access. This stays a plain array of
     {id,name,role,locations,skill} so every existing call site — the
     service staff matrix, the booking staff list, the client's
     preferred-stylist dropdown — keeps working untouched.

     `bookable()` rather than `all()`: someone who has left, or who is a
     receptionist rather than a provider, must never be offered as
     somebody to book. `staffName()` below still resolves them, because
     last year's appointment has to keep saying who performed it. */
  function staffList() {
    if (typeof SDT === 'undefined') return [];
    return SDT.bookable().map(function (s) {
      return {
        id: s.id,
        name: SDT.fullName(s),
        role: s.jobTitle,
        locations: (s.locationIds || []).slice(),
        skill: s.skill || 'Standard'
      };
    });
  }

  var SKILL_LEVELS = ['Trainee', 'Standard', 'Senior', 'Master'];

  /* Resolves anyone on the roster, including someone who has left.
     History has to keep naming who did the work. */
  function staffById(id) {
    if (typeof SDT === 'undefined') return null;
    var s = SDT.byId(id);
    if (!s) return null;
    return {
      id: s.id,
      name: SDT.fullName(s),
      role: s.jobTitle,
      locations: (s.locationIds || []).slice(),
      skill: s.skill || 'Standard'
    };
  }

  function staffName(id) {
    var s = staffById(id);
    return s ? s.name : '';
  }

  function staffAt(locationId) {
    var list = staffList();
    if (!locationId) return list;
    return list.filter(function (s) { return s.locations.indexOf(locationId) !== -1; });
  }

  /* ---------- Shared UI primitives ---------------------------------
     Dropdowns, overlays, tag inputs, flash and toast. Generic enough
     that every module needs them, so they live here rather than in
     any one feature's file. */

  var esc = escapeHtml;

  /* ---------- Generic dropdown -------------------------------------
     Wires [data-drop] containers: a trigger, a panel, outside-click and
     Escape. Kept deliberately small — the panel content is whatever the
     page puts inside it. */

  var openDrops = [];

  /* A panel inside a scrolling container gets clipped by it — and
     `overflow-x: auto` on the table wrapper computes `overflow-y` to auto
     too, so a row menu would be cut off in both directions. Marking the
     container [data-drop-portal] moves the panel to <body> while it is
     open and positions it against the trigger, then puts it back so a
     re-render of the rows disposes of it with everything else. */
  function positionPortal(btn, pop) {
    var r = btn.getBoundingClientRect();
    var w = pop.offsetWidth;
    var h = pop.offsetHeight;
    var pad = 8;

    var left = r.right - w;                       // right-aligned to the trigger
    left = Math.max(pad, Math.min(left, window.innerWidth - w - pad));

    var top = r.bottom + 4;
    if (top + h > window.innerHeight - pad) top = r.top - 4 - h;   // flip above
    top = Math.max(pad, top);

    pop.style.position = 'fixed';
    pop.style.top = top + 'px';
    pop.style.left = left + 'px';
    pop.style.right = 'auto';
    pop.style.bottom = 'auto';
  }

  function drop(root) {
    if (!root || root.dataset.dropReady) return null;
    root.dataset.dropReady = '1';
    var btn = root.querySelector('[data-drop-btn]');
    var pop = root.querySelector('[data-drop-pop]');
    if (!btn || !pop) return null;
    var portal = root.hasAttribute('data-drop-portal');

    var api = {
      isOpen: function () { return !pop.hidden; },
      open: function () {
        closeAll(api);
        if (portal) {
          pop.classList.add('sd-drop--portal');
          document.body.appendChild(pop);
        }
        pop.hidden = false;
        if (portal) positionPortal(btn, pop);
        btn.setAttribute('aria-expanded', 'true');
      },
      close: function (focusBtn) {
        if (pop.hidden) return;
        pop.hidden = true;
        btn.setAttribute('aria-expanded', 'false');
        if (portal) {
          pop.removeAttribute('style');
          pop.classList.remove('sd-drop--portal');
          root.appendChild(pop);
        }
        if (focusBtn) btn.focus();
      },
      toggle: function () { api.isOpen() ? api.close() : api.open(); },
      root: root, btn: btn, pop: pop
    };

    btn.setAttribute('aria-expanded', 'false');
    btn.setAttribute('aria-haspopup', 'true');
    btn.addEventListener('click', function (e) { e.preventDefault(); e.stopPropagation(); api.toggle(); });

    function onKey(e) {
      if (e.key === 'Escape' && api.isOpen()) { e.stopPropagation(); api.close(true); }
    }
    root.addEventListener('keydown', onKey);
    pop.addEventListener('keydown', onKey);      // the panel leaves `root` when portalled

    openDrops.push(api);
    return api;
  }

  function closeAll(except) {
    openDrops.forEach(function (d) { if (d !== except) d.close(); });
  }

  document.addEventListener('click', function (e) {
    openDrops.forEach(function (d) {
      if (d.isOpen() && !d.root.contains(e.target) && !d.pop.contains(e.target)) d.close();
    });
  });

  /* A portalled panel is positioned once, so anything that moves the
     trigger under it has to dismiss it rather than leave it floating. */
  window.addEventListener('scroll', function () {
    openDrops.forEach(function (d) { if (d.isOpen() && d.pop.classList.contains('sd-drop--portal')) d.close(); });
  }, true);
  window.addEventListener('resize', function () {
    openDrops.forEach(function (d) { if (d.isOpen() && d.pop.classList.contains('sd-drop--portal')) d.close(); });
  });

  function dropAll(root) {
    // Rows are re-rendered wholesale, so drop the controllers whose element
    // is no longer in the document before registering the new ones.
    openDrops = openDrops.filter(function (d) { return document.body.contains(d.root); });
    (root || document).querySelectorAll('[data-drop]').forEach(drop);
  }

  /* ---------- Overlay: focus trap + Escape -------------------------
     Shared by the drawer and the confirmation dialogs so both behave the
     same way for keyboard users. */

  var FOCUSABLE = 'a[href],button:not([disabled]),input:not([disabled]):not([type="hidden"]),' +
                  'select:not([disabled]),textarea:not([disabled]),[tabindex]:not([tabindex="-1"])';

  function overlay(panel, opts) {
    opts = opts || {};
    var lastFocus = null;

    function visible(el) {
      return !!(el.offsetWidth || el.offsetHeight || el.getClientRects().length);
    }
    function tabbables() {
      return Array.prototype.filter.call(panel.querySelectorAll(FOCUSABLE), visible);
    }

    function onKey(e) {
      if (e.key === 'Escape') {
        // Let an open dropdown inside the panel take Escape first.
        var swallowed = openDrops.some(function (d) { return d.isOpen() && panel.contains(d.root); });
        if (swallowed) return;
        e.preventDefault();
        if (opts.onEscape) opts.onEscape(); else api.close();
        return;
      }
      if (e.key !== 'Tab') return;
      var list = tabbables();
      if (!list.length) { e.preventDefault(); return; }
      var first = list[0], last = list[list.length - 1];
      if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
      else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
    }

    var api = {
      open: function () {
        lastFocus = document.activeElement;
        document.body.classList.add('sd-locked');
        document.addEventListener('keydown', onKey, true);
        // Next frame, so the transform transition has a start value.
        window.requestAnimationFrame(function () {
          panel.classList.add('is-open');
          var target = opts.initialFocus && panel.querySelector(opts.initialFocus);
          if (!target) target = tabbables()[0];
          if (target) target.focus({ preventScroll: true });
        });
      },
      close: function () {
        closeAll();
        document.removeEventListener('keydown', onKey, true);
        document.body.classList.remove('sd-locked');
        panel.classList.remove('is-open');
        if (opts.onClose) opts.onClose();
        if (lastFocus && typeof lastFocus.focus === 'function') lastFocus.focus({ preventScroll: true });
      }
    };
    return api;
  }

  /* ---------- Token (tag) input ------------------------------------ */

  function tokens(root, options) {
    if (!root) return null;
    options = options || {};
    var input = root.querySelector('[data-tokens-input]');
    var listId = input.id + '-suggest';
    var values = (options.value || []).slice();
    var suggestions = options.suggestions || [];

    // Native datalist: free typing plus the tenant's existing tags, with
    // no custom listbox to keep in sync.
    if (suggestions.length && !document.getElementById(listId)) {
      var dl = document.createElement('datalist');
      dl.id = listId;
      dl.innerHTML = suggestions.map(function (t) { return '<option value="' + esc(t) + '"></option>'; }).join('');
      root.appendChild(dl);
      input.setAttribute('list', listId);
    }

    var live = document.createElement('span');
    live.className = 'sr-only';
    live.setAttribute('aria-live', 'polite');
    root.appendChild(live);

    function paint() {
      root.querySelectorAll('[data-token]').forEach(function (el) { el.remove(); });
      values.forEach(function (v, i) {
        var chip = document.createElement('span');
        chip.className = 'sd-tag';
        chip.setAttribute('data-token', v);
        chip.innerHTML =
          '<span class="sd-tag__text">' + esc(v) + '</span>' +
          '<button type="button" class="sd-tag__x" aria-label="Remove tag ' + esc(v) + '">' +
            '<svg width="9" height="9" viewBox="0 0 24 24" fill="none" aria-hidden="true">' +
            '<path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="3" stroke-linecap="round"/></svg>' +
          '</button>';
        chip.querySelector('button').addEventListener('click', function () { removeAt(i); input.focus(); });
        root.insertBefore(chip, input);
      });
      input.placeholder = values.length ? '' : (options.placeholder || 'Add a tag…');
    }

    function add(raw) {
      var v = String(raw || '').trim().replace(/,+$/, '');
      if (!v) return;
      if (values.some(function (x) { return x.toLowerCase() === v.toLowerCase(); })) { input.value = ''; return; }
      values.push(v);
      input.value = '';
      paint();
      live.textContent = v + ' added. ' + values.length + ' tags.';
      if (options.onChange) options.onChange(values.slice());
    }

    function removeAt(i) {
      var gone = values.splice(i, 1)[0];
      paint();
      live.textContent = gone + ' removed. ' + values.length + ' tags.';
      if (options.onChange) options.onChange(values.slice());
    }

    input.addEventListener('keydown', function (e) {
      if (e.key === 'Enter' || e.key === ',') { e.preventDefault(); add(input.value); }
      else if (e.key === 'Backspace' && !input.value && values.length) { removeAt(values.length - 1); }
    });
    // Committing on blur means a typed tag is never silently lost on save.
    input.addEventListener('blur', function () { if (input.value.trim()) add(input.value); });
    input.addEventListener('change', function () { if (input.value.trim()) add(input.value); });

    root.addEventListener('click', function (e) { if (e.target === root) input.focus(); });

    paint();
    return {
      value: function () { return values.slice(); },
      set: function (next) { values = (next || []).slice(); paint(); },
      clear: function () { values = []; paint(); }
    };
  }

  /* ---------- Flash ------------------------------------------------
     Carries one confirmation across a navigation, so saving on
     add-client.html can be announced on the page you land on. Session
     storage, not local: it should not survive a new tab. */

  var FLASH = 'styledesk.flash';

  function flash(message) {
    try { window.sessionStorage.setItem(FLASH, message); } catch (e) { /* ignore */ }
  }

  function takeFlash() {
    try {
      var v = window.sessionStorage.getItem(FLASH);
      window.sessionStorage.removeItem(FLASH);
      return v;
    } catch (e) { return null; }
  }

  /* ---------- Toast ------------------------------------------------ */

  function toast(html, ms) {
    var el = document.getElementById('sdToast');
    if (!el) {
      el = document.createElement('div');
      el.id = 'sdToast';
      el.className = 'sd-toast';
      el.setAttribute('role', 'status');
      el.hidden = true;
      document.body.appendChild(el);
    }
    window.clearTimeout(el.sdTimer);
    el.innerHTML =
      '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" class="shrink-0" aria-hidden="true">' +
      '<path d="M5 12.5l4.5 4.5L19 7.5" stroke="#4ade80" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/></svg>' +
      '<span class="min-w-0">' + html + '</span>';
    el.hidden = false;
    window.requestAnimationFrame(function () { el.classList.add('is-open'); });
    el.sdTimer = window.setTimeout(function () {
      el.classList.remove('is-open');
      window.setTimeout(function () { el.hidden = true; }, 200);
    }, ms || 4200);
  }

  var api = {
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
    datePicker: datePicker, datePickerAll: datePickerAll,
    accountMenu: accountMenu, accountMenuAll: accountMenuAll,
    setError: setError, clearErrors: clearErrors, focusFirstError: focusFirstError,
    renderProgress: renderProgress, guard: guard, trialRemaining: trialRemaining,
    escapeHtml: escapeHtml, lockSubmit: lockSubmit,

    SKILL_LEVELS: SKILL_LEVELS,
    staffById: staffById, staffName: staffName, staffAt: staffAt,

    drop: drop, dropAll: dropAll, closeAllDrops: closeAll,
    overlay: overlay, tokens: tokens, toast: toast,
    flash: flash, takeFlash: takeFlash
  };

  /* A getter, not a snapshot: hiring, deactivating or making someone
     non-bookable has to be visible to code that captured `SD` at load. */
  Object.defineProperty(api, 'STAFF', {
    enumerable: true,
    get: function () { return staffList(); }
  });

  return api;
}());
