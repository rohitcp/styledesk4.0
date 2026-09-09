/* StyleDesk — Business Hours (SDH)
   ------------------------------------------------------------------
   Regular opening hours already live on the location record in SDL,
   because "when is this building open" is a property of the building.
   What this module owns is everything that *departs* from them.

   §36 asks for one unified concept internally, and it is worth taking
   literally. A holiday, a temporary closure, reduced hours, a staff
   meeting and Christmas Eve's shortened day are not five features —
   they are one record, a **schedule exception**, wearing different
   labels:

     closed   the location does not open that day at all
     special  it opens, but to different hours than usual
     partial  it keeps its usual hours minus one or more blocked windows

   Every screen in the spec (§11 Add Special Hours, §15 Add Holiday,
   §17 Temporary Closure) is the same form with different defaults.
   Building three of them would mean three sets of validation, three
   overlap rules and three ways for the calendar to disagree with the
   booking engine.

   The other job here is §50: the final bookable time is

     business hours ∩ staff hours ∩ service ∩ resources ∩ rules

   and business hours are the outermost term. `spansOn()` answers
   "when is this site open on this date, after everything", and the
   availability engine in SDR asks it before it asks anything else. */

var SDH = (function () {
  'use strict';

  var KEY = 'styledesk.hours.v1';

  /* Why a closure happened. §33 — these matter for reporting, and
     "Closed" on its own tells a manager nothing in six months. */
  var CATEGORIES = [
    { id: 'holiday',     label: 'Holiday',       tone: 'violet' },
    { id: 'meeting',     label: 'Staff meeting', tone: 'slate' },
    { id: 'training',    label: 'Training',      tone: 'slate' },
    { id: 'maintenance', label: 'Maintenance',   tone: 'amber' },
    { id: 'event',       label: 'Private event', tone: 'violet' },
    { id: 'weather',     label: 'Weather',       tone: 'amber' },
    { id: 'emergency',   label: 'Emergency',     tone: 'rose' },
    { id: 'other',       label: 'Other',         tone: 'slate' }
  ];

  /* §36 — the three shapes an exception can take. */
  var KINDS = [
    { id: 'closed',  label: 'Closed all day',
      note: 'The site does not open. Nothing can be booked.' },
    { id: 'special', label: 'Special hours',
      note: 'It opens, but to different hours than usual.' },
    { id: 'partial', label: 'Partial closure',
      note: 'Usual hours, minus a blocked window. Good for a staff meeting.' }
  ];

  var MAX_SHIFTS = 3;          /* §6 — MVP ceiling on split shifts */

  /* ---------- Time helpers ---------------------------------------------
     Minutes-from-midnight throughout. 24:00 is a real value: it is how
     a day that runs to midnight ends, and how "open 24 hours" closes. */

  function toMin(t) {
    var p = String(t || '').split(':');
    var h = Number(p[0]), m = Number(p[1]) || 0;
    return isNaN(h) ? 0 : h * 60 + m;
  }

  function fromMin(m) {
    m = Math.max(0, Math.min(1440, Math.round(m)));
    var h = Math.floor(m / 60), r = m % 60;
    return ('0' + h).slice(-2) + ':' + ('0' + r).slice(-2);
  }

  function timeLabel(t) {
    var m = toMin(t);
    if (m === 0) return 'Midnight';
    if (m === 1440) return 'Midnight';
    if (m === 720) return 'Noon';
    var h = Math.floor(m / 60), r = m % 60;
    var suffix = h >= 12 ? 'PM' : 'AM';
    var display = h % 12 === 0 ? 12 : h % 12;
    return display + (r ? ':' + ('0' + r).slice(-2) : '') + ' ' + suffix;
  }

  function overlaps(aStart, aEnd, bStart, bEnd) {
    return aStart < bEnd && bStart < aEnd;
  }

  function today() {
    var d = new Date();
    return d.getFullYear() + '-' + ('0' + (d.getMonth() + 1)).slice(-2) +
           '-' + ('0' + d.getDate()).slice(-2);
  }

  function dayIdOf(dateStr) {
    var ids = ['sun', 'mon', 'tue', 'wed', 'thu', 'fri', 'sat'];
    var p = String(dateStr || '').split('-');
    var d = new Date(Number(p[0]), Number(p[1]) - 1, Number(p[2]));
    return ids[d.getDay()] || 'mon';
  }

  function dateLong(dateStr) {
    var months = ['January', 'February', 'March', 'April', 'May', 'June', 'July',
                  'August', 'September', 'October', 'November', 'December'];
    var p = String(dateStr || '').split('-');
    if (p.length !== 3) return '';
    return months[Number(p[1]) - 1] + ' ' + Number(p[2]) + ', ' + p[0];
  }

  function dateShort(dateStr) {
    var months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun',
                  'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    var p = String(dateStr || '').split('-');
    if (p.length !== 3) return '';
    return months[Number(p[1]) - 1] + ' ' + Number(p[2]);
  }

  /* ---------- Seed ------------------------------------------------------
     A year that looks like a real one: two closed public holidays, a
     shortened Christmas Eve with a split shift, one all-sites closure,
     a partial closure for a staff meeting, and a past holiday so the
     Past filter (§38) has something to show. */

  function seed() {
    var year = new Date().getFullYear();

    return [
      { id: 'ex1', locationIds: 'all', date: year + '-09-07', endDate: '',
        kind: 'closed', category: 'holiday', name: 'Labor Day',
        shifts: [], blocks: [],
        note: '', message: 'We are closed for the Labor Day holiday.',
        showClients: true },

      { id: 'ex2', locationIds: 'all', date: year + '-11-26', endDate: '',
        kind: 'closed', category: 'holiday', name: 'Thanksgiving',
        shifts: [], blocks: [],
        note: 'Every site dark. Payroll cut-off moves to the Wednesday.',
        message: 'Closed for Thanksgiving. Back on the 27th.',
        showClients: true },

      /* §13's worked example: a short day, in two blocks. */
      { id: 'ex3', locationIds: ['loc1'], date: year + '-12-24', endDate: '',
        kind: 'special', category: 'holiday', name: 'Christmas Eve',
        shifts: [['09:00', '13:00'], ['14:00', '16:00']], blocks: [],
        note: 'Skeleton team. Colour appointments not offered.',
        message: 'Holiday hours: 9 AM – 1 PM and 2 PM – 4 PM.',
        showClients: true },

      { id: 'ex4', locationIds: 'all', date: year + '-12-25', endDate: '',
        kind: 'closed', category: 'holiday', name: 'Christmas Day',
        shifts: [], blocks: [],
        note: '', message: 'Closed for Christmas Day.',
        showClients: true },

      /* §32 — the location stays bookable either side of the block. */
      { id: 'ex5', locationIds: ['loc2'], date: nextWeekday(3), endDate: '',
        kind: 'partial', category: 'meeting', name: 'Team meeting',
        shifts: [], blocks: [['12:00', '14:00']],
        note: 'Monthly floor meeting. Front desk covers the phone.',
        message: '', showClients: false },

      { id: 'ex6', locationIds: ['loc3'], date: nextWeekday(5), endDate: '',
        kind: 'special', category: 'maintenance', name: 'Terminal works',
        shifts: [['10:00', '18:00']], blocks: [],
        note: 'Airside contractors on site before 10. No early access.',
        message: 'Opening late at 10 AM while the terminal works finish.',
        showClients: true },

      /* Deliberately in the past so the Past filter is not empty. */
      { id: 'ex7', locationIds: 'all', date: year + '-01-01', endDate: '',
        kind: 'closed', category: 'holiday', name: "New Year's Day",
        shifts: [], blocks: [],
        note: '', message: '', showClients: true }
    ];
  }

  /* The next date whose weekday is `dow` (1 = Monday), at least three
     days out, so the seeded exceptions are always genuinely upcoming
     however long this prototype sits open. */
  function nextWeekday(dow) {
    var d = new Date();
    d.setDate(d.getDate() + 3);
    while (d.getDay() !== dow) d.setDate(d.getDate() + 1);
    return d.getFullYear() + '-' + ('0' + (d.getMonth() + 1)).slice(-2) +
           '-' + ('0' + d.getDate()).slice(-2);
  }

  function blank() {
    return {
      id: '', locationIds: [], date: today(), endDate: '',
      kind: 'closed', category: 'holiday', name: '',
      shifts: [['10:00', '15:00']], blocks: [['12:00', '14:00']],
      note: '', message: '', showClients: true
    };
  }

  /* ---------- Store ------------------------------------------------------ */

  var memory = null;

  function read() {
    if (memory) return memory;
    try {
      var raw = window.localStorage.getItem(KEY);
      memory = raw ? JSON.parse(raw) : seed();
    } catch (e) {
      memory = seed();
    }
    memory = memory.map(function (x) {
      var row = Object.assign(blank(), x);
      row.shifts = (x.shifts || []).map(function (s) { return s.slice(); });
      row.blocks = (x.blocks || []).map(function (s) { return s.slice(); });
      return row;
    });
    return memory;
  }

  function write(rows) {
    memory = rows;
    try { window.localStorage.setItem(KEY, JSON.stringify(rows)); } catch (e) { /* ignore */ }
  }

  function reset() {
    memory = null;
    try { window.localStorage.removeItem(KEY); window.localStorage.removeItem(KEY + '.audit'); }
    catch (e) { /* ignore */ }
    return read();
  }

  function all() {
    return read().slice().sort(function (a, b) {
      return a.date < b.date ? -1 : a.date > b.date ? 1 : 0;
    });
  }

  function byId(id) {
    return read().filter(function (x) { return x.id === id; })[0] || null;
  }

  /* ---------- Which sites an exception touches --------------------------
     §19 — one location's closure must not close the others unless it
     was explicitly asked for. `'all'` is stored as a literal rather
     than an expanded id list, so a site added next month inherits the
     public holidays without anyone having to remember. */

  function appliesTo(ex, locationId) {
    if (!ex) return false;
    if (ex.locationIds === 'all') return true;
    return (ex.locationIds || []).indexOf(locationId) !== -1;
  }

  function scopeText(ex) {
    if (ex.locationIds === 'all') return 'All locations';
    var ids = ex.locationIds || [];
    if (!ids.length) return 'No locations';
    if (ids.length === 1) {
      return typeof SDL !== 'undefined' ? SDL.nameOf(ids[0]) : ids[0];
    }
    return ids.length + ' locations';
  }

  /* An exception can span several days (§17 gives a temporary closure a
     start and an end). One record, a date range, rather than one record
     per day — otherwise deleting a week-long closure is seven deletes. */
  function coversDate(ex, dateStr) {
    if (!ex || !ex.date) return false;
    var end = ex.endDate && ex.endDate > ex.date ? ex.endDate : ex.date;
    return dateStr >= ex.date && dateStr <= end;
  }

  function exceptionOn(locationId, dateStr) {
    var hits = read().filter(function (ex) {
      return appliesTo(ex, locationId) && coversDate(ex, dateStr);
    });
    if (!hits.length) return null;

    /* Two exceptions on one date is a data problem, not a UI one, but
       it should not produce an undefined day. The most restrictive wins
       — a closure beats reduced hours beats a blocked window — because
       the safe failure is refusing a booking, not taking one for a day
       the doors are locked. */
    var rank = { closed: 0, special: 1, partial: 2 };
    return hits.sort(function (a, b) { return rank[a.kind] - rank[b.kind]; })[0];
  }

  function conflictsOn(locationId, dateStr, ignoreId) {
    return read().filter(function (ex) {
      return ex.id !== ignoreId && appliesTo(ex, locationId) && coversDate(ex, dateStr);
    });
  }

  /* ---------- Regular hours --------------------------------------------- */

  function locationOf(locationId) {
    if (typeof SDL === 'undefined') return null;
    return SDL.byId(locationId);
  }

  function regularSpans(locationId, dayId) {
    var loc = locationOf(locationId);
    if (!loc) return [];
    return ((loc.hours || {})[dayId] || []).map(function (s) { return s.slice(); });
  }

  /* §30 — a day that runs midnight to midnight is "Open 24 hours"
     rather than "12 AM – 12 AM", which reads like a mistake. */
  function isAllDay(spans) {
    return spans.length === 1 && toMin(spans[0][0]) === 0 && toMin(spans[0][1]) >= 1440;
  }

  function spansText(spans) {
    if (!spans.length) return 'Closed';
    if (isAllDay(spans)) return 'Open 24 hours';
    return spans.map(function (s) {
      return timeLabel(s[0]) + ' – ' + timeLabel(s[1]);
    }).join(', ');
  }

  /* ---------- Effective hours for a date --------------------------------
     The one function the rest of the app should ask. Returns the spans
     the site is actually open, plus what changed them, so a screen can
     say *why* a day looks different instead of just showing odd hours. */

  function spansOn(locationId, dateStr) {
    var dayId = dayIdOf(dateStr);
    var base = regularSpans(locationId, dayId);
    var ex = exceptionOn(locationId, dateStr);

    if (!ex) return { spans: base, exception: null, source: 'regular' };

    if (ex.kind === 'closed') {
      return { spans: [], exception: ex, source: 'exception' };
    }

    if (ex.kind === 'special') {
      return {
        spans: (ex.shifts || []).map(function (s) { return s.slice(); }),
        exception: ex, source: 'exception'
      };
    }

    /* Partial: keep the regular day and cut the blocked windows out of
       it (§32). A block in the middle of a shift splits that shift in
       two, which is exactly what the calendar should show. */
    var out = base;
    (ex.blocks || []).forEach(function (b) {
      var bs = toMin(b[0]), be = toMin(b[1]);
      var next = [];
      out.forEach(function (s) {
        var ss = toMin(s[0]), se = toMin(s[1]);
        if (!overlaps(ss, se, bs, be)) { next.push(s); return; }
        if (ss < bs) next.push([fromMin(ss), fromMin(Math.min(se, bs))]);
        if (se > be) next.push([fromMin(Math.max(ss, be)), fromMin(se)]);
      });
      out = next;
    });

    return { spans: out, exception: ex, source: 'exception' };
  }

  function isOpenOn(locationId, dateStr) {
    return spansOn(locationId, dateStr).spans.length > 0;
  }

  /* Does a booking window fit inside the open hours? §21's worked
     example — the answer has to be the intersection, not "the site is
     open at some point today". */
  function covers(locationId, dateStr, startMin, endMin) {
    return spansOn(locationId, dateStr).spans.some(function (s) {
      return startMin >= toMin(s[0]) && endMin <= toMin(s[1]);
    });
  }

  /* The reason a window does not fit, phrased for a human. Returns null
     when the site is open — this is what SDR.checkSlot calls, and it is
     deliberately the same shape as the staff and resource reasons so
     the failure list reads consistently. */
  function why(locationId, dateStr, startMin, endMin) {
    var loc = locationOf(locationId);
    if (!loc) return null;                    /* unknown site: not our refusal to make */

    var state = spansOn(locationId, dateStr);
    var ex = state.exception;

    if (!state.spans.length) {
      if (ex) {
        return (ex.name || KINDS.filter(function (k) { return k.id === ex.kind; })[0].label) +
               ' — ' + loc.name + ' is closed';
      }
      return loc.name + ' is closed that day';
    }

    if (covers(locationId, dateStr, startMin, endMin)) return null;

    var hoursText = state.spans.map(function (s) {
      return timeLabel(s[0]) + '–' + timeLabel(s[1]);
    }).join(', ');

    if (ex && ex.kind === 'partial') {
      return 'Closed for ' + (ex.name || 'a blocked period') + ' — open ' + hoursText;
    }
    if (ex) {
      return (ex.name ? ex.name + ' — ' : '') + 'open ' + hoursText + ' that day';
    }
    return 'Outside ' + loc.name + "'s hours (" + hoursText + ')';
  }

  /* ---------- Validation -------------------------------------------------
     §43 in one place, so the regular-hours editor and the exception
     form cannot disagree about what a valid day looks like. */

  function validateSpans(spans) {
    var errors = [];
    var clean = (spans || []).filter(function (s) { return s && s[0] && s[1]; });

    clean.forEach(function (s, i) {
      if (toMin(s[1]) <= toMin(s[0])) {
        errors.push('A shift has to end after it starts (' +
                    timeLabel(s[0]) + ' – ' + timeLabel(s[1]) + ').');
      }
      for (var j = i + 1; j < clean.length; j++) {
        if (overlaps(toMin(s[0]), toMin(s[1]), toMin(clean[j][0]), toMin(clean[j][1]))) {
          errors.push('Business-hour shifts cannot overlap.');
        }
      }
    });

    if (clean.length > MAX_SHIFTS) {
      errors.push('A day can have at most ' + MAX_SHIFTS + ' shifts.');
    }

    /* §29 — closing after midnight is a Phase 2 feature, and silently
       accepting 6 PM – 2 AM as a twenty-minute day would be worse than
       saying so. */
    return errors.filter(function (v, i, a) { return a.indexOf(v) === i; });
  }

  function validateDay(spans) {
    return validateSpans(spans);
  }

  function validateException(ex) {
    var errors = {};

    if (!ex.date) errors.date = 'Pick a date.';
    if (ex.endDate && ex.endDate < ex.date) errors.endDate = 'The closure has to end after it starts.';
    if (ex.locationIds !== 'all' && !(ex.locationIds || []).length) {
      errors.locationIds = 'Choose at least one location.';
    }

    if (ex.kind === 'special') {
      if (!(ex.shifts || []).filter(function (s) { return s && s[0] && s[1]; }).length) {
        errors.shifts = 'Special hours need at least one open period.';
      } else {
        var se = validateSpans(ex.shifts);
        if (se.length) errors.shifts = se[0];
      }
    }

    if (ex.kind === 'partial') {
      if (!(ex.blocks || []).filter(function (s) { return s && s[0] && s[1]; }).length) {
        errors.blocks = 'A partial closure needs a blocked period.';
      } else {
        var be = validateSpans(ex.blocks);
        if (be.length) errors.blocks = be[0];
      }
    }

    return errors;
  }

  /* ---------- Saving hours ----------------------------------------------
     Regular hours belong to the location record, so this writes through
     SDL — but the audit entry (§41) is written here, because "Monday
     9 AM–5 PM → 9 AM–7 PM" is a business-hours fact, not an address
     change, and nobody looking for it would think to open Locations. */

  function saveHours(locationId, hours, who) {
    if (typeof SDL === 'undefined') return { ok: false, errors: ['Locations are unavailable.'] };
    var loc = SDL.byId(locationId);
    if (!loc) return { ok: false, errors: ['That location no longer exists.'] };

    var errors = [];
    var days = SDL.DAYS;
    days.forEach(function (d) {
      validateDay(hours[d.id] || []).forEach(function (msg) {
        errors.push(d.label + ': ' + msg);
      });
    });
    if (errors.length) return { ok: false, errors: errors };

    var changes = [];
    days.forEach(function (d) {
      var before = spansText(loc.hours[d.id] || []);
      var after = spansText(hours[d.id] || []);
      if (before !== after) changes.push(d.label + ': ' + before + ' → ' + after);
    });

    SDL.save(Object.assign({}, loc, { hours: hours }));

    if (changes.length) {
      log(locationId, 'Business hours changed', changes, who);
    }
    return { ok: true, changes: changes };
  }

  /* ---------- Saving exceptions ------------------------------------------ */

  function saveException(ex, who) {
    var rows = read().slice();
    var errors = validateException(ex);
    if (Object.keys(errors).length) return { ok: false, errors: errors };

    var record = Object.assign(blank(), ex);
    /* Keep only the shape this kind actually uses, so a record cannot
       carry stale hours from a type it is no longer. */
    if (record.kind === 'closed') { record.shifts = []; record.blocks = []; }
    if (record.kind === 'special') { record.blocks = []; }
    if (record.kind === 'partial') { record.shifts = []; }

    var existing = record.id ? rows.filter(function (x) { return x.id === record.id; })[0] : null;

    if (existing) {
      rows = rows.map(function (x) { return x.id === record.id ? record : x; });
      log(scopeIdFor(record), 'Closure updated', [record.name || dateShort(record.date)], who);
    } else {
      record.id = 'ex' + (Date.now().toString(36)) + Math.floor(Math.random() * 1000);
      rows.push(record);
      log(scopeIdFor(record), 'Closure created', [
        (record.name || KINDS.filter(function (k) { return k.id === record.kind; })[0].label) +
        ' · ' + dateShort(record.date) + ' · ' + scopeText(record)
      ], who);
    }

    write(rows);
    return { ok: true, exception: record };
  }

  function deleteException(id, who) {
    var row = byId(id);
    write(read().filter(function (x) { return x.id !== id; }));
    if (row) {
      log(scopeIdFor(row), 'Closure deleted',
          [(row.name || dateShort(row.date)) + ' · ' + dateShort(row.date)], who);
    }
    return true;
  }

  function duplicateException(id) {
    var row = byId(id);
    if (!row) return null;
    var copy = Object.assign({}, row, {
      id: '', name: row.name ? row.name + ' (copy)' : ''
    });
    copy.shifts = (row.shifts || []).map(function (s) { return s.slice(); });
    copy.blocks = (row.blocks || []).map(function (s) { return s.slice(); });
    return copy;
  }

  function scopeIdFor(ex) {
    return ex.locationIds === 'all' ? 'all' : (ex.locationIds || [])[0] || 'all';
  }

  /* ---------- Filtering the list ---------------------------------------- */

  /* §39 — status is calculated, never stored. A field a user can set to
     "Completed" on a date that has not happened is a field that will
     eventually disagree with the calendar. */
  function statusOf(ex) {
    var now = today();
    var end = ex.endDate && ex.endDate > ex.date ? ex.endDate : ex.date;
    if (end < now) return { id: 'done', label: 'Completed', badge: 'sd-badge--inactive' };
    if (ex.date <= now && end >= now) return { id: 'active', label: 'Active', badge: 'sd-badge--warn' };
    return { id: 'upcoming', label: 'Upcoming', badge: 'sd-badge--active' };
  }

  function forLocation(locationId, range) {
    var now = today();
    return all().filter(function (ex) {
      if (locationId && !appliesTo(ex, locationId)) return false;
      var end = ex.endDate && ex.endDate > ex.date ? ex.endDate : ex.date;
      if (range === 'past') return end < now;
      if (range === 'upcoming') return end >= now;
      return true;
    });
  }

  function upcoming(locationId, limit) {
    var rows = forLocation(locationId, 'upcoming');
    return limit ? rows.slice(0, limit) : rows;
  }

  function categoryMeta(id) {
    return CATEGORIES.filter(function (c) { return c.id === id; })[0] || CATEGORIES[CATEGORIES.length - 1];
  }

  function kindMeta(id) {
    return KINDS.filter(function (k) { return k.id === id; })[0] || KINDS[0];
  }

  /* What a client would be told (§18, §35). Internal notes never make
     it into this function's output — that is the whole reason it is a
     separate function rather than a field the template reads. */
  function clientMessage(ex, locationName) {
    if (!ex || !ex.showClients) return '';
    if (ex.message) return ex.message;
    if (ex.kind === 'closed') {
      return (locationName || 'We') + ' will be closed on ' + dateLong(ex.date) + '.';
    }
    if (ex.kind === 'special') {
      return 'Holiday hours: ' + spansText(ex.shifts) + '.';
    }
    return '';
  }

  /* ---------- Copying ---------------------------------------------------- */

  /* §8 / §45 — copy one day onto others, and §46 — copy a whole week to
     another site. Both return the new hours rather than writing, so the
     editor can show the result and let it be cancelled. */

  function copyDay(hours, fromDay, toDays) {
    var next = {};
    Object.keys(hours).forEach(function (k) {
      next[k] = (hours[k] || []).map(function (s) { return s.slice(); });
    });
    toDays.forEach(function (d) {
      if (d === fromDay) return;
      next[d] = (hours[fromDay] || []).map(function (s) { return s.slice(); });
    });
    return next;
  }

  function applyToDays(hours, days, span) {
    var next = {};
    Object.keys(hours).forEach(function (k) {
      next[k] = (hours[k] || []).map(function (s) { return s.slice(); });
    });
    days.forEach(function (d) { next[d] = [[span[0], span[1]]]; });
    return next;
  }

  function copyToLocation(fromId, toId, options, who) {
    if (typeof SDL === 'undefined') return { ok: false, error: 'Locations are unavailable.' };
    var from = SDL.byId(fromId), to = SDL.byId(toId);
    if (!from || !to) return { ok: false, error: 'Pick two different locations.' };
    if (fromId === toId) return { ok: false, error: 'Pick two different locations.' };

    var done = [];

    if (options.regular) {
      var hours = {};
      Object.keys(from.hours).forEach(function (k) {
        hours[k] = (from.hours[k] || []).map(function (s) { return s.slice(); });
      });
      SDL.save(Object.assign({}, to, { hours: hours }));
      done.push('regular hours');
    }

    if (options.exceptions) {
      var rows = read().slice();
      var copied = 0;
      forLocation(fromId, 'upcoming').forEach(function (ex) {
        if (ex.locationIds === 'all') return;     /* already applies everywhere */
        if (appliesTo(ex, toId)) return;          /* already there */
        rows.push(Object.assign({}, ex, {
          id: 'ex' + Date.now().toString(36) + (copied++),
          locationIds: [toId],
          shifts: (ex.shifts || []).map(function (s) { return s.slice(); }),
          blocks: (ex.blocks || []).map(function (s) { return s.slice(); })
        }));
      });
      write(rows);
      if (copied) done.push(copied + (copied === 1 ? ' closure' : ' closures'));
    }

    if (!done.length) return { ok: false, error: 'Choose what to copy.' };

    log(toId, 'Hours copied', ['From ' + from.name + ': ' + done.join(' and ')], who);
    return { ok: true, summary: done.join(' and ') + ' copied from ' + from.name + '.' };
  }

  /* ---------- Affected bookings ------------------------------------------
     §24 / §25 — a closure must never silently cancel anything. This
     counts what a proposed closure would sit on top of, before it is
     saved, so the warning can be shown while it is still a choice. */

  function affectedBookings(ex) {
    if (typeof SDR === 'undefined') return [];
    var end = ex.endDate && ex.endDate > ex.date ? ex.endDate : ex.date;

    /* A booking stores a stamp and a set of resources, not a date and a
       location — so both have to be derived. Where the resources are is
       where the appointment is. */
    function placeOf(b) {
      var ids = b.resourceIds || [];
      for (var i = 0; i < ids.length; i++) {
        var r = SDR.resourceById(ids[i]);
        if (r && r.locationId) return r.locationId;
      }
      return '';
    }

    return SDR.bookings().filter(function (b) {
      var from = String(b.start || '').split('T');
      var to = String(b.end || '').split('T');
      var date = from[0];
      if (!date || date < ex.date || date > end) return false;

      var where = placeOf(b);
      if (where && !appliesTo(ex, where)) return false;

      if (ex.kind === 'closed') return true;

      var bs = toMin(from[1]), be = toMin(to[1]);

      if (ex.kind === 'special') {
        return !(ex.shifts || []).some(function (s) {
          return bs >= toMin(s[0]) && be <= toMin(s[1]);
        });
      }

      return (ex.blocks || []).some(function (blk) {
        return overlaps(bs, be, toMin(blk[0]), toMin(blk[1]));
      });
    }).map(function (b) {
      var from = String(b.start || '').split('T');
      var to = String(b.end || '').split('T');
      return {
        id: b.id, label: b.label, date: from[0],
        start: from[1], end: to[1],
        locationId: placeOf(b),
        when: dateShort(from[0]) + ' · ' + timeLabel(from[1]) + ' – ' + timeLabel(to[1])
      };
    });
  }

  /* ---------- Audit (§41) ------------------------------------------------ */

  var AUDIT_KEY = KEY + '.audit';
  var auditMemory = null;

  function auditRead() {
    if (auditMemory) return auditMemory;
    try {
      var raw = window.localStorage.getItem(AUDIT_KEY);
      auditMemory = raw ? JSON.parse(raw) : seedAudit();
    } catch (e) {
      auditMemory = seedAudit();
    }
    return auditMemory;
  }

  function seedAudit() {
    return [
      { id: 'a1', locationId: 'loc1', action: 'Business hours changed',
        detail: ['Thursday: 9 AM – 7 PM → 9 AM – 8 PM', 'Friday: 9 AM – 7 PM → 9 AM – 8 PM'],
        who: 'Rohit Philip', at: stampDaysAgo(9) },
      { id: 'a2', locationId: 'all', action: 'Closure created',
        detail: ['Thanksgiving · Nov 26 · All locations'],
        who: 'Rohit Philip', at: stampDaysAgo(6) },
      { id: 'a3', locationId: 'loc3', action: 'Business hours changed',
        detail: ['Sunday: Closed → 7 AM – 6 PM'],
        who: 'Lena Fitzgerald', at: stampDaysAgo(2) }
    ];
  }

  function stampDaysAgo(n) {
    var d = new Date();
    d.setDate(d.getDate() - n);
    return d.toISOString();
  }

  function log(locationId, action, detail, who) {
    var rows = auditRead().slice();
    rows.push({
      id: 'a' + Date.now().toString(36),
      locationId: locationId || 'all',
      action: action,
      detail: detail || [],
      who: who || 'Rohit Philip',
      at: new Date().toISOString()
    });
    auditMemory = rows;
    try { window.localStorage.setItem(AUDIT_KEY, JSON.stringify(rows)); } catch (e) { /* ignore */ }
  }

  function auditFor(locationId, limit) {
    var rows = auditRead().filter(function (r) {
      return !locationId || r.locationId === locationId || r.locationId === 'all';
    }).slice().sort(function (a, b) { return a.at < b.at ? 1 : -1; });
    return limit ? rows.slice(0, limit) : rows;
  }

  function stampText(iso) {
    var d = new Date(iso);
    if (isNaN(d.getTime())) return '';
    var months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun',
                  'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    var h = d.getHours(), m = d.getMinutes();
    var suffix = h >= 12 ? 'PM' : 'AM';
    var display = h % 12 === 0 ? 12 : h % 12;
    return months[d.getMonth()] + ' ' + d.getDate() + ', ' + d.getFullYear() +
           ' · ' + display + ':' + ('0' + m).slice(-2) + ' ' + suffix;
  }

  /* ---------- Week summaries --------------------------------------------- */

  /* §27 — a week at a glance, as proportions of the day, so the shape
     of the week is readable without reading seven times. */
  function weekBars(locationId) {
    if (typeof SDL === 'undefined') return [];
    var loc = SDL.byId(locationId);
    if (!loc) return [];

    var spread = { from: 24 * 60, to: 0 };
    SDL.DAYS.forEach(function (d) {
      (loc.hours[d.id] || []).forEach(function (s) {
        spread.from = Math.min(spread.from, toMin(s[0]));
        spread.to = Math.max(spread.to, toMin(s[1]));
      });
    });
    if (spread.to <= spread.from) { spread.from = 8 * 60; spread.to = 20 * 60; }

    /* Round out to the hour so the axis labels are whole numbers. */
    spread.from = Math.floor(spread.from / 60) * 60;
    spread.to = Math.ceil(spread.to / 60) * 60;
    var width = spread.to - spread.from;

    return SDL.DAYS.map(function (d) {
      var spans = (loc.hours[d.id] || []);
      return {
        day: d,
        text: spansText(spans),
        closed: !spans.length,
        bars: spans.map(function (s) {
          return {
            left: ((toMin(s[0]) - spread.from) / width) * 100,
            width: ((toMin(s[1]) - toMin(s[0])) / width) * 100,
            label: timeLabel(s[0]) + ' – ' + timeLabel(s[1])
          };
        }),
        from: spread.from, to: spread.to
      };
    });
  }

  function esc(v) {
    return String(v == null ? '' : v)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
  }

  return {
    CATEGORIES: CATEGORIES, KINDS: KINDS, MAX_SHIFTS: MAX_SHIFTS,

    read: read, write: write, reset: reset, blank: blank,
    all: all, byId: byId,

    appliesTo: appliesTo, scopeText: scopeText, coversDate: coversDate,
    exceptionOn: exceptionOn, conflictsOn: conflictsOn,

    regularSpans: regularSpans, spansOn: spansOn, isOpenOn: isOpenOn,
    covers: covers, why: why, isAllDay: isAllDay, spansText: spansText,

    validateSpans: validateSpans, validateDay: validateDay,
    validateException: validateException,

    saveHours: saveHours,
    saveException: saveException, deleteException: deleteException,
    duplicateException: duplicateException,

    statusOf: statusOf, forLocation: forLocation, upcoming: upcoming,
    categoryMeta: categoryMeta, kindMeta: kindMeta, clientMessage: clientMessage,

    copyDay: copyDay, applyToDays: applyToDays, copyToLocation: copyToLocation,
    affectedBookings: affectedBookings,

    log: log, auditFor: auditFor, stampText: stampText, weekBars: weekBars,

    toMin: toMin, fromMin: fromMin, timeLabel: timeLabel, overlaps: overlaps,
    today: today, dayIdOf: dayIdOf, dateLong: dateLong, dateShort: dateShort, esc: esc
  };
}());
