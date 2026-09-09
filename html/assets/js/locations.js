/* ==================================================================
   StyleDesk — Locations store  (SDL)
   ------------------------------------------------------------------
   The sites the business operates from: a salon, a spa, a clinic
   inside an airport terminal.

   This module loads before the others because everything downstream
   is anchored to a location — a resource sits at one, a service is
   offered at some, a booking happens at exactly one. `SDR.LOCATIONS`
   used to be three hardcoded names; it now reads from here, so every
   existing call site keeps working while the records behind them grow
   real hours, addresses and time zones.

   One rule worth stating up front: a location is **disabled**, never
   deleted. Appointments, resources and revenue all point at it, and a
   site that closes does not thereby stop having had a history.
   ================================================================== */

var SDL = (function () {
  'use strict';

  var KEY = 'styledesk.locations';

  var DAYS = [
    { id: 'mon', label: 'Monday',    short: 'Mon' },
    { id: 'tue', label: 'Tuesday',   short: 'Tue' },
    { id: 'wed', label: 'Wednesday', short: 'Wed' },
    { id: 'thu', label: 'Thursday',  short: 'Thu' },
    { id: 'fri', label: 'Friday',    short: 'Fri' },
    { id: 'sat', label: 'Saturday',  short: 'Sat' },
    { id: 'sun', label: 'Sunday',    short: 'Sun' }
  ];

  /* A short list rather than the full tz database: these are the zones
     a US-based salon group actually operates in, and a 400-entry
     dropdown is worse than a short one plus a request. */
  var TIMEZONES = [
    { id: 'America/New_York',    label: 'Eastern — New York' },
    { id: 'America/Chicago',     label: 'Central — Chicago' },
    { id: 'America/Denver',      label: 'Mountain — Denver' },
    { id: 'America/Phoenix',     label: 'Mountain, no DST — Phoenix' },
    { id: 'America/Los_Angeles', label: 'Pacific — Los Angeles' },
    { id: 'America/Anchorage',   label: 'Alaska — Anchorage' },
    { id: 'Pacific/Honolulu',    label: 'Hawaii — Honolulu' },
    { id: 'Europe/London',       label: 'United Kingdom — London' },
    { id: 'Europe/Dublin',       label: 'Ireland — Dublin' }
  ];

  var COUNTRIES = [
    { id: 'US', label: 'United States' },
    { id: 'CA', label: 'Canada' },
    { id: 'GB', label: 'United Kingdom' },
    { id: 'IE', label: 'Ireland' },
    { id: 'AU', label: 'Australia' }
  ];

  var STATUSES = [
    { id: 'active',   label: 'Open',     badge: 'sd-badge--active',
      note: 'Taking bookings.' },
    { id: 'disabled', label: 'Closed',   badge: 'sd-badge--inactive',
      note: 'Takes no new bookings. Its history and resources are kept.' }
  ];

  /* ---------- Seed --------------------------------------------------
     The three sites the rest of the prototype already assumes, filled
     out. Their hours match the resource hours seeded in SDR, because a
     resource open later than its building is a contradiction nobody
     would notice until a booking failed. */

  function hours(spec) {
    var out = {};
    DAYS.forEach(function (d) { out[d.id] = spec[d.id] ? spec[d.id].slice() : []; });
    return out;
  }

  function seed() {
    return [
      {
        id: 'loc1',
        name: 'Downtown Salon',
        code: 'DT',
        description: 'The original site. Cutting, colour and barbering over two floors.',
        phone: '+1 (202) 555-0110',
        email: 'downtown@bellabeauty.example.com',
        street: '184 Cedar Street',
        street2: 'Ground and first floor',
        city: 'Washington',
        region: 'DC',
        postcode: '20001',
        country: 'US',
        timezone: 'America/New_York',
        status: 'active',
        isPrimary: true,
        hours: hours({
          mon: [['09:00', '19:00']], tue: [['09:00', '19:00']], wed: [['09:00', '19:00']],
          thu: [['09:00', '20:00']], fri: [['09:00', '20:00']], sat: [['09:00', '18:00']]
        }),
        booking: { online: true, intervalMin: 30, minNoticeHours: 2, windowDays: 60,
                   allowWalkIn: true, allowGuest: true },
        notes: ''
      },
      {
        id: 'loc2',
        name: 'Westside Spa',
        code: 'WS',
        description: 'Treatment rooms, massage and med-spa. Quieter, by appointment only.',
        phone: '+1 (202) 555-0140',
        email: 'westside@bellabeauty.example.com',
        street: '9 Rosewood Avenue',
        street2: '',
        city: 'Washington',
        region: 'DC',
        postcode: '20016',
        country: 'US',
        timezone: 'America/New_York',
        status: 'active',
        isPrimary: false,
        hours: hours({
          mon: [['09:00', '19:00']], tue: [['09:00', '19:00']], wed: [['09:00', '19:00']],
          thu: [['09:00', '19:00']], fri: [['09:00', '19:00']],
          sat: [['10:00', '17:00']], sun: [['10:00', '16:00']]
        }),
        booking: { online: true, intervalMin: 30, minNoticeHours: 24, windowDays: 90,
                   allowWalkIn: false, allowGuest: false },
        notes: 'No walk-ins — every treatment needs a room, and the rooms are booked out.'
      },
      {
        id: 'loc3',
        name: 'Airport Location',
        code: 'AP',
        description: 'Express cuts and blow-dries, Terminal B departures.',
        phone: '+1 (202) 555-0175',
        email: 'airport@bellabeauty.example.com',
        street: 'Terminal B, Unit 14',
        street2: 'Airside, past security',
        city: 'Arlington',
        region: 'VA',
        postcode: '20001',
        country: 'US',
        timezone: 'America/New_York',
        status: 'active',
        isPrimary: false,
        hours: hours({
          mon: [['06:00', '20:00']], tue: [['06:00', '20:00']], wed: [['06:00', '20:00']],
          thu: [['06:00', '20:00']], fri: [['06:00', '20:00']],
          sat: [['07:00', '18:00']], sun: [['07:00', '18:00']]
        }),
        booking: { online: true, intervalMin: 15, minNoticeHours: 0, windowDays: 14,
                   allowWalkIn: true, allowGuest: true },
        notes: 'Most business is walk-in. Short notice is the point.'
      }
    ];
  }

  function blank() {
    return {
      id: '', name: '', code: '', description: '',
      phone: '', email: '',
      street: '', street2: '', city: '', region: '', postcode: '', country: 'US',
      timezone: 'America/New_York',
      status: 'active', isPrimary: false,
      hours: hours({
        mon: [['09:00', '18:00']], tue: [['09:00', '18:00']], wed: [['09:00', '18:00']],
        thu: [['09:00', '18:00']], fri: [['09:00', '18:00']], sat: [['10:00', '16:00']]
      }),
      booking: { online: true, intervalMin: 30, minNoticeHours: 2, windowDays: 60,
                 allowWalkIn: true, allowGuest: true },
      notes: ''
    };
  }

  /* ---------- Store --------------------------------------------------- */

  var memory = null;

  function read() {
    if (memory) return memory;
    try {
      var raw = window.localStorage.getItem(KEY);
      memory = raw ? JSON.parse(raw) : seed();
    } catch (e) {
      memory = seed();
    }
    /* Merge onto a current blank so a field added later reads as its
       default rather than undefined. */
    memory = memory.map(function (l) {
      var row = Object.assign(blank(), l);
      row.hours = Object.assign(blank().hours, l.hours || {});
      row.booking = Object.assign(blank().booking, l.booking || {});
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
    try { window.localStorage.removeItem(KEY); } catch (e) { /* ignore */ }
    return read();
  }

  function all() { return read().slice(); }

  /* Everything that offers a choice of where to book uses this. A
     closed site keeps its records but is never offered. */
  function live() {
    return read().filter(function (l) { return l.status === 'active'; });
  }

  function byId(id) {
    var hit = read().filter(function (l) { return l.id === id; });
    return hit.length ? hit[0] : null;
  }

  /* Resolves a *disabled* location too. History has to keep naming the
     site it happened at, so this must never fall back to a placeholder
     just because the site has since closed. */
  function nameOf(id) {
    var l = byId(id);
    return l ? l.name : '';
  }

  function primary() {
    return read().filter(function (l) { return l.isPrimary; })[0] || live()[0] || read()[0] || null;
  }

  function nextId() {
    var max = 0;
    read().forEach(function (l) {
      var n = parseInt(String(l.id).replace(/\D/g, ''), 10);
      if (!isNaN(n) && n > max) max = n;
    });
    return 'loc' + (max + 1);
  }

  function withoutBlankId(data) {
    var out = {}, k;
    for (k in data) {
      if (!Object.prototype.hasOwnProperty.call(data, k)) continue;
      if (k === 'id' && (data.id === undefined || data.id === null || data.id === '')) continue;
      out[k] = data[k];
    }
    return out;
  }

  function save(data) {
    var rows = read();

    if (data.id && byId(data.id)) {
      rows = rows.map(function (l) { return l.id === data.id ? Object.assign({}, l, data) : l; });
    } else {
      rows = rows.concat([Object.assign(blank(), withoutBlankId(data), { id: nextId() })]);
    }

    /* Exactly one primary, always. It is what a new resource, a new
       service and a walk-in all default to, so "none" is not a state
       the rest of the app can cope with. */
    var wanted = data.isPrimary ? (data.id || rows[rows.length - 1].id) : null;
    if (wanted) {
      rows = rows.map(function (l) { return Object.assign({}, l, { isPrimary: l.id === wanted }); });
    } else if (!rows.some(function (l) { return l.isPrimary; })) {
      var first = rows.filter(function (l) { return l.status === 'active'; })[0] || rows[0];
      if (first) rows = rows.map(function (l) { return Object.assign({}, l, { isPrimary: l.id === first.id }); });
    }

    write(rows);
    return data.id && byId(data.id) ? byId(data.id) : rows[rows.length - 1];
  }

  function setStatus(id, status) {
    var rows = read().map(function (l) {
      return l.id === id ? Object.assign({}, l, { status: status }) : l;
    });

    /* Closing the primary site hands the flag to another open one
       rather than leaving the business with no default. */
    var closed = status !== 'active';
    if (closed) {
      var wasPrimary = rows.filter(function (l) { return l.id === id && l.isPrimary; }).length;
      if (wasPrimary) {
        var next = rows.filter(function (l) { return l.id !== id && l.status === 'active'; })[0];
        rows = rows.map(function (l) {
          return Object.assign({}, l, { isPrimary: !!(next && l.id === next.id) });
        });
      }
    }

    write(rows);
    return byId(id);
  }

  /* ---------- What a location actually holds ---------------------------
     The counts that make disabling one a decision rather than a
     shrug. Each is read live from the module that owns it. */

  function usage(id) {
    var out = { resources: 0, services: 0, staff: 0, upcoming: 0, servicesOnlyHere: [] };

    if (typeof SDR !== 'undefined' && SDR.resources) {
      out.resources = SDR.resources().filter(function (r) { return r.locationId === id; }).length;
    }

    if (typeof SDS !== 'undefined' && SDS.all) {
      SDS.all().forEach(function (s) {
        var ids = SDS.locationIds(s);
        if (ids.indexOf(id) === -1) return;
        out.services++;
        /* A service offered *only* here stops being bookable anywhere
           the moment this site closes. That is the consequence worth
           naming before someone confirms. */
        if (ids.length === 1 && s.status === 'active') out.servicesOnlyHere.push(s.name);
      });
    }

    if (typeof SD !== 'undefined' && SD.STAFF) {
      out.staff = SD.STAFF.filter(function (p) {
        return (p.locations || []).indexOf(id) !== -1;
      }).length;
    }

    if (typeof SDB !== 'undefined' && SDB.all) {
      var today = new Date().toISOString().slice(0, 10);
      out.upcoming = SDB.all().filter(function (a) {
        return a.locationId === id && a.date >= today &&
               (a.status === 'confirmed' || a.status === 'checkedin' || a.status === 'draft');
      }).length;
    }

    return out;
  }

  /* ---------- Formatting ------------------------------------------------ */

  function statusMeta(id) {
    return STATUSES.filter(function (s) { return s.id === id; })[0] || STATUSES[0];
  }

  function timezoneLabel(id) {
    var hit = TIMEZONES.filter(function (t) { return t.id === id; })[0];
    return hit ? hit.label : id;
  }

  function countryLabel(id) {
    var hit = COUNTRIES.filter(function (c) { return c.id === id; })[0];
    return hit ? hit.label : id;
  }

  function addressLines(l) {
    if (!l) return [];
    var city = [l.city, l.region].filter(Boolean).join(', ');
    return [l.street, l.street2, [city, l.postcode].filter(Boolean).join(' '),
            l.country === 'US' ? '' : countryLabel(l.country)]
      .filter(function (v) { return String(v || '').trim(); });
  }

  function addressOneLine(l) {
    return addressLines(l).join(', ');
  }

  function timeLabel(t) {
    var parts = String(t || '').split(':');
    var h = Number(parts[0]), m = Number(parts[1]) || 0;
    if (isNaN(h)) return '';
    if (h === 24) return 'Midnight';
    var suffix = h >= 12 ? 'PM' : 'AM';
    var display = h % 12 === 0 ? 12 : h % 12;
    return display + (m ? ':' + ('0' + m).slice(-2) : '') + ' ' + suffix;
  }

  function dayText(l, dayId) {
    var spans = (l.hours && l.hours[dayId]) || [];
    if (!spans.length) return 'Closed';
    return spans.map(function (s) { return timeLabel(s[0]) + ' – ' + timeLabel(s[1]); }).join(', ');
  }

  /* "Mon–Fri 9 AM – 7 PM · Sat 9 AM – 6 PM · Sun closed" rather than
     seven lines. Consecutive days with identical hours are collapsed,
     because that is how anyone would say it out loud. */
  function hoursSummary(l) {
    var runs = [];
    DAYS.forEach(function (d) {
      var text = dayText(l, d.id);
      var last = runs[runs.length - 1];
      if (last && last.text === text) last.days.push(d);
      else runs.push({ text: text, days: [d] });
    });

    return runs.map(function (run) {
      var span = run.days.length === 1
        ? run.days[0].short
        : run.days[0].short + '–' + run.days[run.days.length - 1].short;
      return run.text === 'Closed' ? span + ' closed' : span + ' ' + run.text;
    }).join(' · ');
  }

  function openDays(l) {
    return DAYS.filter(function (d) { return ((l.hours || {})[d.id] || []).length; }).length;
  }

  function esc(v) {
    return String(v == null ? '' : v)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
  }

  return {
    DAYS: DAYS, TIMEZONES: TIMEZONES, COUNTRIES: COUNTRIES, STATUSES: STATUSES,

    read: read, write: write, reset: reset, blank: blank,
    all: all, live: live, byId: byId, nameOf: nameOf, primary: primary,
    save: save, setStatus: setStatus, usage: usage,

    statusMeta: statusMeta, timezoneLabel: timezoneLabel, countryLabel: countryLabel,
    addressLines: addressLines, addressOneLine: addressOneLine,
    timeLabel: timeLabel, dayText: dayText, hoursSummary: hoursSummary, openDays: openDays,
    esc: esc
  };
}());
