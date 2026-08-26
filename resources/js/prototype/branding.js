/* StyleDesk — Branding (SDBR)
   ------------------------------------------------------------------
   The business's identity, everywhere it is seen: the app itself, the
   client-facing booking pages, email, receipts and printed documents.

   Two decisions shape this module.

   **Colour is checked, not just stored.** A settings screen that lets
   someone save pale yellow as their button colour with white text on it
   has not helped them brand anything — it has helped them ship an
   illegible confirmation email to every client they have. So every
   colour that carries text runs through a contrast check, `buttonInk`
   is *computed* rather than chosen, and the hover shade is derived. The
   user picks the one colour they actually have an opinion about.

   **Derived values are never fields.** `brandDark` (hover) and
   `buttonInk` (label colour) are arithmetic, and arithmetic the machine
   does correctly and a person does not. Asking for them would produce a
   form with two more inputs and a lower chance of a legible result.

   The palette reaches the page as CSS custom properties on :root, which
   is why every brand rule in styles.css is written as var(--sd-*). A
   small bootstrap in each page's <head> applies the saved values before
   first paint; this module re-applies them on load and after a save.
   ================================================================== */

var SDBR = (function () {
  'use strict';

  var KEY = 'styledesk.branding.v1';

  /* ---------- Colour maths ---------------------------------------------
     sRGB relative luminance and the WCAG contrast ratio. Small enough to
     carry here rather than pull a library for, and it is the difference
     between a branding screen that guides and one that just obeys. */

  function parseHex(hex) {
    var h = String(hex || '').trim().replace(/^#/, '');
    if (h.length === 3) h = h[0] + h[0] + h[1] + h[1] + h[2] + h[2];
    if (!/^[0-9a-fA-F]{6}$/.test(h)) return null;
    return {
      r: parseInt(h.slice(0, 2), 16),
      g: parseInt(h.slice(2, 4), 16),
      b: parseInt(h.slice(4, 6), 16)
    };
  }

  function toHex(rgb) {
    function p(v) { return ('0' + Math.max(0, Math.min(255, Math.round(v))).toString(16)).slice(-2); }
    return '#' + p(rgb.r) + p(rgb.g) + p(rgb.b);
  }

  function isHex(v) { return parseHex(v) !== null; }

  function normalise(v) {
    var rgb = parseHex(v);
    return rgb ? toHex(rgb) : '';
  }

  function luminance(hex) {
    var c = parseHex(hex);
    if (!c) return 0;
    function ch(v) {
      v = v / 255;
      return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4);
    }
    return 0.2126 * ch(c.r) + 0.7152 * ch(c.g) + 0.0722 * ch(c.b);
  }

  function contrast(a, b) {
    var la = luminance(a), lb = luminance(b);
    var hi = Math.max(la, lb), lo = Math.min(la, lb);
    return (hi + 0.05) / (lo + 0.05);
  }

  /* Black or white, whichever is legible on this fill. This is the
     computation that stops someone shipping white-on-yellow. */
  function inkFor(hex) {
    return contrast(hex, '#ffffff') >= contrast(hex, '#0f0f10') ? '#ffffff' : '#0f0f10';
  }

  function mix(hex, target, amount) {
    var a = parseHex(hex), b = parseHex(target);
    if (!a || !b) return hex;
    return toHex({
      r: a.r + (b.r - a.r) * amount,
      g: a.g + (b.g - a.g) * amount,
      b: a.b + (b.b - a.b) * amount
    });
  }

  /* The pressed/hover shade. Darker is the normal answer; a brand that
     is already near-black cannot get usefully darker, so it lightens
     instead — otherwise "hover" on charcoal is invisible.

     The threshold is deliberately low. An earlier 0.12 caught #3d348b
     (luminance 0.055) and lightened the house purple on hover, which
     contradicted the brand-dark the design system has always used. Only
     genuinely near-black brands should flip. */
  function hoverFor(hex) {
    return luminance(hex) < 0.02 ? mix(hex, '#ffffff', 0.18) : mix(hex, '#000000', 0.20);
  }

  /* The banner strip sits above the app bar and has always been the
     darker of the two. Keep that relationship whatever the brand is —
     and note the same low threshold as hoverFor, for the same reason:
     at 0.06 the house purple came out *lighter* than the bar beneath
     it, inverting the two-tone chrome the design system depends on. */
  function bannerFor(hex) {
    return luminance(hex) < 0.02 ? mix(hex, '#ffffff', 0.22) : mix(hex, '#000000', 0.28);
  }

  function ratioText(n) {
    return (Math.round(n * 10) / 10).toFixed(1) + ':1';
  }

  /* WCAG thresholds, named rather than left as bare numbers at the call
     site. Large text is 18.66px bold or 24px regular; our buttons are
     13px semibold, so they are held to the normal-text bar. */
  function grade(ratio, large) {
    var aa = large ? 3 : 4.5;
    var aaa = large ? 4.5 : 7;
    if (ratio >= aaa) return { id: 'aaa', label: 'AAA', ok: true };
    if (ratio >= aa) return { id: 'aa', label: 'AA', ok: true };
    if (ratio >= 3) return { id: 'low', label: 'Large text only', ok: false };
    return { id: 'fail', label: 'Fails', ok: false };
  }

  /* ---------- Reference data -------------------------------------------- */

  /* Predefined swatches (§ "Provide predefined colour swatches plus a
     custom colour picker"). Every one of these clears AA against white
     text, so a business that picks from the row cannot go wrong; the
     custom picker is where the warnings earn their place. */
  var SWATCHES = [
    { id: 'violet',    label: 'Violet',    hex: '#3d348b' },
    { id: 'indigo',    label: 'Indigo',    hex: '#3730a3' },
    { id: 'blue',      label: 'Blue',      hex: '#1d4ed8' },
    { id: 'teal',      label: 'Teal',      hex: '#0f766e' },
    { id: 'green',     label: 'Green',     hex: '#15803d' },
    { id: 'olive',     label: 'Olive',     hex: '#4d7c0f' },
    { id: 'amber',     label: 'Amber',     hex: '#b45309' },
    { id: 'rust',      label: 'Rust',      hex: '#c2410c' },
    { id: 'rose',      label: 'Rose',      hex: '#be123c' },
    { id: 'plum',      label: 'Plum',      hex: '#86198f' },
    { id: 'slate',     label: 'Slate',     hex: '#334155' },
    { id: 'charcoal',  label: 'Charcoal',  hex: '#1f2937' }
  ];

  /* The colours the user actually sets. `brandDark` and `buttonInk` are
     absent on purpose — see the module header. */
  var FIELDS = [
    { id: 'brand',     label: 'Primary brand colour', prop: '--sd-brand',
      note: 'The app bar, primary buttons, selected states and focus rings.',
      onText: '#ffffff', checks: true },
    { id: 'secondary', label: 'Secondary colour',     prop: '--sd-secondary',
      note: 'Supporting fills and secondary charts. Rarely carries text.',
      onText: '', checks: false },
    { id: 'accent',    label: 'Accent colour',        prop: '--sd-accent',
      note: 'Highlights and attention states — a closure, an overdue balance.',
      onText: '', checks: false },
    { id: 'button',    label: 'Button colour',        prop: '--sd-btn',
      note: 'Defaults to your primary. Set it only if buttons should differ.',
      onText: 'auto', checks: true },
    { id: 'link',      label: 'Link colour',          prop: '--sd-link',
      note: 'Hyperlinks in the app and in email.',
      onText: '', checks: true, onSurface: '#ffffff' }
  ];

  /* §"Where Branding Is Applied" — a checklist rather than prose, so it
     is obvious at a glance which surfaces are and are not covered. In
     the prototype the first group is live and the rest are declarations
     of intent, which the page says plainly rather than implying. */
  var SURFACES = [
    { id: 'app',      label: 'Main application',            live: true },
    { id: 'booking',  label: 'Client-facing online booking', live: true },
    { id: 'portal',   label: 'Client portal',                live: false },
    { id: 'kiosk',    label: 'Kiosk',                        live: false },
    { id: 'auth',     label: 'Login and signup screens',     live: true },
    { id: 'email',    label: 'Email notifications',          live: true },
    { id: 'confirm',  label: 'Appointment confirmations',    live: true },
    { id: 'remind',   label: 'Reminders',                    live: true },
    { id: 'cancel',   label: 'Cancellation and reschedule emails', live: true },
    { id: 'gift',     label: 'Gift cards',                   live: false },
    { id: 'invoice',  label: 'Invoices',                     live: true },
    { id: 'receipt',  label: 'Receipts',                     live: true },
    { id: 'print',    label: 'Printable documents',          live: false }
  ];

  var LOGO_SLOTS = [
    { id: 'primary', label: 'Primary logo', required: true,
      note: 'The main lockup. Used in the app, on booking pages and at the top of email.',
      ratio: 'Wide. Around 3:1 works best.', bg: 'light' },
    { id: 'square',  label: 'Square logo / app icon', required: false,
      note: 'For small square spaces — the account chip, kiosk tiles, mobile shortcuts.',
      ratio: 'Square. 512×512 or larger.', bg: 'light' },
    { id: 'light',   label: 'Light-background logo', required: false,
      note: 'Used wherever the surface is white. Falls back to the primary logo.',
      ratio: 'Wide.', bg: 'light' },
    { id: 'dark',    label: 'Dark-background logo', required: false,
      note: 'Used on the app bar and dark email headers. Without it, the primary logo is placed on a white tile so it stays visible.',
      ratio: 'Wide.', bg: 'dark' }
  ];

  var ACCEPTED = ['image/png', 'image/jpeg', 'image/svg+xml', 'image/webp'];
  var MAX_BYTES = 512 * 1024;      /* logos are chrome, not photography */

  /* ---------- Defaults ---------------------------------------------------
     The StyleDesk look, which is also what Reset returns to. Kept as a
     function so a reset cannot hand back a mutated shared object. */

  function defaults() {
    return {
      identity: {
        displayName: 'Bella Beauty',
        legalName: 'Bella Beauty Group LLC',
        shortName: 'Bella'
      },
      logos: { primary: null, square: null, light: null, dark: null },
      favicon: null,
      colors: {
        brand: '#3d348b',
        secondary: '#0d9488',
        accent: '#b45309',
        button: '#3d348b',
        link: '#2563eb'
      },
      surfaces: SURFACES.reduce(function (acc, s) { acc[s.id] = true; return acc; }, {}),
      email: {
        useLogo: true,
        headerStyle: 'brand',        /* brand | light | dark */
        footerName: 'Bella Beauty',
        contact: '(202) 555-0110 · hello@bellabeauty.example.com',
        website: 'bellabeauty.example.com',
        social: 'instagram.com/bellabeauty',
        poweredBy: true              /* plan-dependent; see canHidePoweredBy() */
      },
      receipt: {
        useLogo: true,
        businessName: 'Bella Beauty',
        address: '184 Cedar Street, Washington, DC 20001',
        phone: '+1 (202) 555-0110',
        email: 'hello@bellabeauty.example.com',
        website: 'bellabeauty.example.com',
        footer: 'Thank you for visiting. We hope to see you again soon.',
        taxLine: 'Tax ID 47-2938471'
      },
      plan: 'pro'                    /* free | pro — gates the Powered by toggle */
    };
  }

  /* ---------- Store ------------------------------------------------------ */

  var memory = null;

  function read() {
    if (memory) return memory;
    var stored = null;
    try {
      var raw = window.localStorage.getItem(KEY);
      stored = raw ? JSON.parse(raw) : null;
    } catch (e) { stored = null; }
    memory = merge(defaults(), stored || {});
    return memory;
  }

  /* One level of nesting is all this record has, so a full deep merge
     would be more machinery than the shape justifies. What matters is
     that a field added in a later version reads as its default rather
     than undefined for anyone with a saved record. */
  function merge(base, saved) {
    var out = {};
    Object.keys(base).forEach(function (k) {
      var b = base[k], s = saved[k];
      if (b && typeof b === 'object' && !Array.isArray(b)) {
        out[k] = Object.assign({}, b, s && typeof s === 'object' ? s : {});
      } else {
        out[k] = s === undefined ? b : s;
      }
    });
    return out;
  }

  function write(next) {
    memory = merge(defaults(), next || {});
    try { window.localStorage.setItem(KEY, JSON.stringify(memory)); }
    catch (e) { return { ok: false, error: storageError(e) }; }
    apply();
    return { ok: true };
  }

  /* Logos are data URLs, so a large upload can genuinely exhaust the
     quota. Saying which limit was hit beats a silent failure. */
  function storageError(e) {
    var quota = e && (e.name === 'QuotaExceededError' || e.code === 22);
    return quota
      ? 'The logos are too large to store together. Try smaller files, or an SVG.'
      : 'Could not save. Your browser is blocking local storage.';
  }

  function reset() {
    memory = null;
    try { window.localStorage.removeItem(KEY); } catch (e) { /* ignore */ }
    read();
    apply();
    return memory;
  }

  function save(next) { return write(next); }

  /* ---------- Derived colours -------------------------------------------- */

  /* Everything the page needs to paint, including the values nobody
     picks. One function so the live preview and the real app cannot
     drift apart. */
  function palette(record) {
    var c = (record || read()).colors;
    var button = c.button || c.brand;
    return {
      brand: c.brand,
      brandDark: hoverFor(c.brand),
      banner: bannerFor(c.brand),
      secondary: c.secondary,
      accent: c.accent,
      button: button,
      buttonInk: inkFor(button),
      brandInk: inkFor(c.brand),
      link: c.link
    };
  }

  var PROPS = {
    brand: '--sd-brand', brandDark: '--sd-brand-dark', banner: '--sd-banner',
    link: '--sd-link', accent: '--sd-accent', button: '--sd-btn',
    buttonInk: '--sd-btn-ink', secondary: '--sd-secondary'
  };

  /* Paint a palette onto an element — :root for the real app, a preview
     container for the panel on the settings page. Same function, so what
     the preview shows is what the app will do. */
  function paint(target, record) {
    var el = target || document.documentElement;
    var p = palette(record);
    Object.keys(PROPS).forEach(function (k) {
      if (p[k]) el.style.setProperty(PROPS[k], p[k]);
    });
    return p;
  }

  function apply(record) {
    var r = record || read();
    paint(document.documentElement, r);
    applyFavicon(r.favicon);
    return r;
  }

  function applyFavicon(dataUrl) {
    if (!dataUrl) return;
    var links = document.querySelectorAll('link[rel="icon"]');
    Array.prototype.forEach.call(links, function (l) { l.parentNode.removeChild(l); });
    var el = document.createElement('link');
    el.rel = 'icon';
    el.href = dataUrl;
    document.head.appendChild(el);
  }

  /* ---------- Contrast report -------------------------------------------
     What the page shows beside each colour. Returns findings rather than
     a boolean, because "this fails" is not actionable and "white text on
     this is 2.4:1, which is unreadable — try a darker shade" is. */

  function report(record) {
    var r = record || read();
    var c = r.colors;
    var p = palette(r);
    var out = [];

    function add(id, label, fg, bg, large) {
      var ratio = contrast(fg, bg);
      var g = grade(ratio, large);
      out.push({ id: id, label: label, ratio: ratio, text: ratioText(ratio),
                 grade: g, ok: g.ok, fg: fg, bg: bg });
    }

    add('bar', 'White text on the app bar', '#ffffff', c.brand, false);
    add('button', 'Button label on the button fill', p.buttonInk, p.button, false);
    add('link', 'Link colour on white', c.link, '#ffffff', false);
    add('accent', 'Accent on white', c.accent, '#ffffff', false);

    return out;
  }

  /* The one-line verdict above the palette. Counting is friendlier than
     listing when everything passes, and specific when it does not. */
  function verdict(record) {
    var rows = report(record);
    var bad = rows.filter(function (r) { return !r.ok; });
    if (!bad.length) {
      return { ok: true, text: 'Every combination clears WCAG AA.' };
    }
    return {
      ok: false,
      text: bad.length === 1
        ? bad[0].label + ' is only ' + bad[0].text + '. AA needs 4.5:1.'
        : bad.length + ' colour combinations fall below AA.'
    };
  }

  /* ---------- Validation -------------------------------------------------- */

  function validate(record) {
    var errors = {};
    var r = record || read();

    if (!String(r.identity.displayName || '').trim()) {
      errors.displayName = 'The business needs a display name — it appears on every email and receipt.';
    }
    Object.keys(r.colors).forEach(function (k) {
      if (!isHex(r.colors[k])) errors[k] = 'Enter a six-digit hex colour, like #3d348b.';
    });

    /* A palette that fails contrast is a warning, not an error: it is
       the business's brand and they may have a reason. It must be
       impossible to save it *unknowingly*, not impossible to save. */
    return errors;
  }

  /* ---------- Uploads ------------------------------------------------------ */

  function checkFile(file) {
    if (!file) return 'No file chosen.';
    if (ACCEPTED.indexOf(file.type) === -1) {
      return 'That has to be a PNG, JPG, SVG or WebP.';
    }
    if (file.size > MAX_BYTES) {
      return 'That file is ' + Math.round(file.size / 1024) + ' KB. Keep logos under ' +
             Math.round(MAX_BYTES / 1024) + ' KB — an SVG is usually a fraction of the size.';
    }
    return null;
  }

  /* Files become data URLs because there is no server here. In the real
     build these are uploads and the record holds a URL, which is why
     every consumer reads `logo(slot)` rather than the field. */
  function readFile(file, done) {
    var problem = checkFile(file);
    if (problem) { done(problem, null); return; }
    var fr = new window.FileReader();
    fr.onload = function () { done(null, String(fr.result)); };
    fr.onerror = function () { done('That file could not be read.', null); };
    fr.readAsDataURL(file);
  }

  /* Which image to use where, with the fallbacks the spec implies: an
     unset light logo falls back to the primary, and an unset dark logo
     means the primary has to be given a light tile to sit on rather
     than disappearing into the app bar. */
  function logo(slot, record) {
    var l = (record || read()).logos;
    if (slot === 'dark') return l.dark || null;
    if (slot === 'light') return l.light || l.primary || null;
    if (slot === 'square') return l.square || null;
    return l.primary || null;
  }

  function darkNeedsTile(record) {
    var l = (record || read()).logos;
    return !l.dark && !!l.primary;
  }

  /* ---------- Names --------------------------------------------------------- */

  function displayName(record) {
    var i = (record || read()).identity;
    return String(i.displayName || '').trim() || 'StyleDesk';
  }

  function shortName(record) {
    var i = (record || read()).identity;
    return String(i.shortName || '').trim() || displayName(record);
  }

  function legalName(record) {
    var i = (record || read()).identity;
    return String(i.legalName || '').trim() || displayName(record);
  }

  function initials(record) {
    return displayName(record).split(/\s+/).slice(0, 2)
      .map(function (w) { return w.charAt(0); }).join('').toUpperCase();
  }

  /* §"Optional Powered by StyleDesk depending on subscription plan" —
     the toggle exists on every plan, but on the free plan it is locked
     on and says why, rather than being hidden and quietly ignored. */
  function canHidePoweredBy(record) {
    return (record || read()).plan !== 'free';
  }

  function esc(v) {
    return String(v == null ? '' : v)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
  }

  var api = {
    SWATCHES: SWATCHES, FIELDS: FIELDS, SURFACES: SURFACES,
    LOGO_SLOTS: LOGO_SLOTS, ACCEPTED: ACCEPTED, MAX_BYTES: MAX_BYTES,

    read: read, write: write, save: save, reset: reset, defaults: defaults, merge: merge,

    palette: palette, paint: paint, apply: apply, applyFavicon: applyFavicon,
    report: report, verdict: verdict, validate: validate,

    checkFile: checkFile, readFile: readFile, logo: logo, darkNeedsTile: darkNeedsTile,

    displayName: displayName, shortName: shortName, legalName: legalName,
    initials: initials, canHidePoweredBy: canHidePoweredBy,

    isHex: isHex, normalise: normalise, contrast: contrast, ratioText: ratioText,
    grade: grade, inkFor: inkFor, hoverFor: hoverFor, bannerFor: bannerFor,
    luminance: luminance, mix: mix, esc: esc
  };

  /* Apply on load. The <head> bootstrap has already painted the colours
     to avoid a flash; this re-applies from the merged record so a field
     the bootstrap does not know about still lands. */
  if (typeof document !== 'undefined') {
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', function () { apply(); });
    } else {
      apply();
    }
  }

  return api;
}());
