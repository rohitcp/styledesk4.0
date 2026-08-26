/* ==================================================================
   StyleDesk — Bookings store  (SDB)
   ------------------------------------------------------------------
   Appointments, and the reading of a client's history that makes the
   front desk fast.

   The premise of this module is that a receptionist booking a repeat
   client should not be asked anything the history already answers. So
   most of the code here is not about storing appointments — it is about
   deriving, from the ones already stored:

     · who this client actually sees, and why we think so
     · what they actually book
     · how often they come back
     · when in the day they turn up

   Three of those are separate signals about staff and the spec is right
   to insist they stay separate: the person a client *asked* for, the
   person they see *most*, and the person they saw *last* are three
   different facts. Collapsing them into one "preferred stylist" is how a
   system confidently books someone with a stylist they were trying to
   get away from.

   Depends on SD (staff), SDC (clients), SDS (services), SDR (resources).
   ================================================================== */

var SDB = (function () {
  'use strict';

  var KEY = 'styledesk.bookings';

  /* ---------- Reference data ---------------------------------------- */

  /* Where the appointment is in its life. Deliberately separate from
     what has been paid: a completed appointment can be unpaid, and a
     cancelled one can be non-refundably paid for. One status cannot
     carry both without lying about one of them. */
  var STATUSES = [
    { id: 'draft',     label: 'Draft',      badge: 'sd-badge--lead',     note: 'Held, not confirmed. Nothing has been sent.' },
    { id: 'confirmed', label: 'Confirmed',  badge: 'sd-badge--primary',  note: 'On the calendar. The client has been told.' },
    { id: 'checkedin', label: 'Checked in', badge: 'sd-badge--active',   note: 'The client has arrived.' },
    { id: 'inservice', label: 'In service', badge: 'sd-badge--active',   note: 'Happening now.' },
    { id: 'completed', label: 'Completed',  badge: 'sd-badge--active',   note: 'Done.' },
    { id: 'cancelled', label: 'Cancelled',  badge: 'sd-badge--inactive', note: 'Called off in advance.' },
    { id: 'noshow',    label: 'No show',    badge: 'sd-badge--inactive', note: 'Did not arrive and did not cancel.' }
  ];

  var PAYMENT_STATUSES = [
    { id: 'unpaid',    label: 'Unpaid',       badge: 'sd-badge--inactive' },
    { id: 'deposit',   label: 'Deposit paid', badge: 'sd-badge--lead' },
    { id: 'partial',   label: 'Part paid',    badge: 'sd-badge--lead' },
    { id: 'paid',      label: 'Paid',         badge: 'sd-badge--active' },
    { id: 'refunded',  label: 'Refunded',     badge: 'sd-badge--inactive' }
  ];

  /* Front desk leads because it is the default for anything typed in
     here — a list whose default sits fourth invites mis-selection. */
  var SOURCES = [
    'Front desk', 'Phone', 'Walk-in', 'Online', 'Instagram', 'Facebook',
    'Google', 'Referral', 'Other'
  ];

  var DEPOSIT_ACTIONS = [
    { id: 'now',   label: 'Pay now',            short: 'Paid now',
      note: 'Card present, or a card on file.' },
    { id: 'link',  label: 'Send payment link',  short: 'Link sent',
      note: 'Texted or emailed. The booking holds until it is paid.' },
    { id: 'later', label: 'Pay at appointment', short: 'Pay at appointment',
      note: 'Nothing is taken now. Recorded as deposit not collected.' },
    { id: 'waive', label: 'Waive deposit',      short: 'Waived',
      note: 'Needs a manager. Logged with a reason against the booking.', restricted: true }
  ];

  /* ---------- Seed ---------------------------------------------------
     History is the whole point of this module, so the seed is a set of
     real-looking careers rather than a scatter of appointments: one
     client loyal to one stylist, one who has drifted between two, one
     with a single visit, one with none at all. Those are the four cases
     the suggestion code has to get right. */

  function daysAgo(n) {
    var d = new Date();
    d.setDate(d.getDate() - n);
    return d.getFullYear() + '-' + ('0' + (d.getMonth() + 1)).slice(-2) + '-' + ('0' + d.getDate()).slice(-2);
  }

  function visit(clientId, ago, serviceIds, staffId, time, extra) {
    return Object.assign({
      id: '',
      clientId: clientId,
      serviceIds: serviceIds,
      staffIds: [staffId],
      resourceIds: [],
      date: daysAgo(ago),
      start: time,
      end: time,
      locationId: 'loc1',
      status: 'completed',
      paymentStatus: 'paid',
      price: 0,
      source: 'Front desk',
      notes: '',
      rating: 5,
      createdAt: daysAgo(ago + 14)
    }, extra || {});
  }

  function seed() {
    var rows = [
      /* Amelia Hart — loyal to Priya for colour, but her cuts are with
         Amara. Two staff, both legitimate, and the "usual" depends on
         which service you are asking about. */
      visit('c1000', 13,  ['s1'], 'u1', '14:00', { price: 75, rating: 5 }),
      visit('c1000', 42,  ['s2'], 'u2', '10:00', { price: 140, rating: 5 }),
      visit('c1000', 71,  ['s1'], 'u1', '14:30', { price: 75, rating: 5 }),
      visit('c1000', 99,  ['s2', 's1'], 'u2', '09:30', { price: 205, rating: 4 }),
      visit('c1000', 128, ['s1'], 'u1', '15:00', { price: 65, rating: 5 }),
      visit('c1000', 157, ['s1'], 'u1', '14:00', { price: 65, rating: 5 }),

      /* Yuki Tanaka — spa only, and has drifted from Jonas to Nadia.
         Most-frequent and most-recent disagree, which is exactly the
         case the three signals exist to keep apart. */
      visit('c1008', 11, ['s3'], 'u6', '16:00', { price: 220, locationId: 'loc2', rating: 5 }),
      visit('c1008', 39, ['s3'], 'u6', '16:00', { price: 220, locationId: 'loc2', rating: 4 }),
      visit('c1008', 67, ['s3'], 'u3', '11:00', { price: 220, locationId: 'loc2', rating: 5 }),
      visit('c1008', 95, ['s3'], 'u3', '11:00', { price: 200, locationId: 'loc2', rating: 5 }),
      visit('c1008', 124, ['s3'], 'u3', '10:30', { price: 200, locationId: 'loc2', rating: 3 }),

      /* Freya Lindqvist — the metronome. Every four weeks, same service,
         same stylist, same time of day. The one-click case. */
      visit('c1013', 3,  ['s1'], 'u1', '17:00', { price: 75, rating: 5 }),
      visit('c1013', 31, ['s1'], 'u1', '17:30', { price: 75, rating: 5 }),
      visit('c1013', 59, ['s1'], 'u1', '17:00', { price: 75, rating: 5 }),
      visit('c1013', 87, ['s1'], 'u1', '17:30', { price: 65, rating: 5 }),
      visit('c1013', 115, ['s1'], 'u1', '17:00', { price: 65, rating: 4 }),

      /* Sofia Marchetti — colour client, long gaps, high value. */
      visit('c1002', 4,  ['s2'], 'u2', '09:30', { price: 140, rating: 5 }),
      visit('c1002', 55, ['s2'], 'u2', '09:00', { price: 140, rating: 5 }),
      visit('c1002', 111, ['s2'], 'u2', '10:00', { price: 120, rating: 4 }),

      /* Grace Okonkwo — a med-spa regular, so her history exercises the
         two-resource service. */
      visit('c1006', 6,  ['s4'], 'u4', '13:00', { price: 340, locationId: 'loc2', rating: 5 }),
      visit('c1006', 48, ['s4'], 'u4', '13:00', { price: 340, locationId: 'loc2', rating: 5 }),
      visit('c1006', 90, ['s5'], 'u4', '12:00', { price: 0, locationId: 'loc2', rating: 5 }),

      /* Mateo Alvarez — exactly one visit. No cadence to infer, and the
         code must not pretend otherwise. */
      visit('c1005', 26, ['s1'], 'u5', '18:00', { price: 55, locationId: 'loc3', rating: 4 }),

      /* Chloe Bennett — a no-show and a cancellation in the history, so
         neither is allowed to count as a visit. */
      visit('c1011', 14, ['s1'], 'u1', '11:00', { price: 0, status: 'noshow', paymentStatus: 'unpaid', rating: 0 }),
      visit('c1011', 30, ['s1'], 'u1', '11:00', { price: 0, status: 'cancelled', paymentStatus: 'refunded', rating: 0 }),
      visit('c1011', 58, ['s1'], 'u1', '11:30', { price: 65, rating: 4 }),
      visit('c1011', 86, ['s1'], 'u1', '11:00', { price: 65, rating: 5 })
    ];

    rows.forEach(function (r, i) {
      r.id = 'apt' + (100 + i);
      var svcMin = r.serviceIds.reduce(function (t, sid) {
        var s = SDS.byId(sid);
        return t + (s ? SDS.serviceMinutes(s) : 0);
      }, 0);
      r.end = SDR.fromMin(SDR.toMin(r.start) + svcMin);
    });

    return { appointments: rows, prefs: seedPrefs() };
  }

  /* Standing preferences a client has told the desk. These are stated
     facts, not inferences, which is why they live apart from the
     history and always outrank it. */
  function seedPrefs() {
    return {
      c1000: {
        staffId: 'u1',
        notes: ['Sensitive scalp — no heat on the roots', 'Likes plenty of layers'],
        likes: ['Afternoon appointments', 'No fragranced product']
      },
      c1008: {
        staffId: '',
        notes: ['Sensitive scalp', 'Pressure: firm on shoulders, light everywhere else'],
        likes: ['Late morning', 'Quiet room — no conversation']
      },
      c1013: {
        staffId: 'u1',
        notes: ['Books the last slot of the day and always has'],
        likes: ['Evening appointments']
      },
      c1006: { staffId: 'u4', notes: ['Patch tested Mar 2026'], likes: ['Early afternoon'] },
      c1002: { staffId: 'u2', notes: [], likes: ['Morning appointments'] }
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
    memory.appointments = memory.appointments || [];
    memory.prefs = memory.prefs || {};
    return memory;
  }

  function write(state) {
    memory = state;
    try { window.localStorage.setItem(KEY, JSON.stringify(state)); } catch (e) { /* ignore */ }
  }

  function reset() {
    memory = null;
    try { window.localStorage.removeItem(KEY); } catch (e) { /* ignore */ }
    return read();
  }

  function all() { return read().appointments.slice(); }

  function byId(id) {
    var hit = read().appointments.filter(function (a) { return a.id === id; });
    return hit.length ? hit[0] : null;
  }

  function nextId() {
    var max = 100;
    read().appointments.forEach(function (a) {
      var n = parseInt(String(a.id).replace(/\D/g, ''), 10);
      if (!isNaN(n) && n > max) max = n;
    });
    return 'apt' + (max + 1);
  }

  function save(data) {
    var s = read();
    if (data.id && byId(data.id)) {
      s.appointments = s.appointments.map(function (a) {
        return a.id === data.id ? Object.assign({}, a, data) : a;
      });
      write(s);
      return byId(data.id);
    }
    var row = Object.assign({
      id: nextId(), clientId: '', serviceIds: [], staffIds: [], resourceIds: [],
      date: '', start: '', end: '', locationId: 'loc1',
      status: 'confirmed', paymentStatus: 'unpaid', price: 0,
      source: 'Front desk', notes: '', rating: 0,
      createdAt: new Date().toISOString().slice(0, 10)
    }, data, { id: nextId() });
    s.appointments.unshift(row);
    write(s);

    /* The client record carries the headline counters, and a booking
       that never updates them leaves the directory quietly wrong. */
    bumpClient(row);
    return row;
  }

  function bumpClient(row) {
    if (!row.clientId) return;
    var c = SDC.byId(row.clientId);
    if (!c) return;
    if (row.status === 'cancelled' || row.status === 'noshow' || row.status === 'draft') return;
    SDC.update(row.clientId, {
      lastVisit: row.date > (c.lastVisit || '') ? row.date : c.lastVisit,
      visits: (Number(c.visits) || 0) + 1,
      spend: (Number(c.spend) || 0) + (Number(row.price) || 0)
    });
  }

  function setStatus(id, status) {
    var a = byId(id);
    if (!a) return null;
    var s = read();
    s.appointments = s.appointments.map(function (x) {
      return x.id === id ? Object.assign({}, x, { status: status }) : x;
    });
    write(s);
    return byId(id);
  }

  /* ---------- History ------------------------------------------------- */

  /* Only appointments that actually happened. A no-show is not a visit,
     and letting one count would tell the desk a client "usually books"
     something they have never turned up for. */
  function visitsFor(clientId) {
    return all()
      .filter(function (a) { return a.clientId === clientId && a.status === 'completed'; })
      .sort(function (a, b) { return String(b.date).localeCompare(String(a.date)); });
  }

  function lastVisit(clientId) {
    return visitsFor(clientId)[0] || null;
  }

  function recentVisits(clientId, n) {
    return visitsFor(clientId).slice(0, n || 3);
  }

  function prefs(clientId) {
    return read().prefs[clientId] || { staffId: '', notes: [], likes: [] };
  }

  function savePrefs(clientId, data) {
    var s = read();
    s.prefs[clientId] = Object.assign({ staffId: '', notes: [], likes: [] }, s.prefs[clientId], data);
    write(s);
  }

  /* ---------- Staff signals (kept separate on purpose) -----------------

     Three different questions, three different answers, each labelled
     with where it came from. The UI shows the reason, not just the
     name, because "who does Sarah see?" and "who did Sarah see last
     time?" have different right answers and a receptionist can only
     judge which one matters if they can see which they are looking at. */

  function staffSignals(clientId) {
    var out = [];
    var seen = {};

    function push(kind, staffId, why) {
      if (!staffId || staffId === 'any' || seen[staffId]) return;
      var person = SD.staffById(staffId);
      if (!person) return;
      seen[staffId] = true;
      out.push({ kind: kind, staffId: staffId, name: person.name, role: person.role, why: why });
    }

    var client = SDC.byId(clientId);
    var stated = prefs(clientId).staffId || (client && client.preferredStaff);

    var visits = visitsFor(clientId);
    var counts = {};
    visits.forEach(function (v) {
      (v.staffIds || []).forEach(function (id) { counts[id] = (counts[id] || 0) + 1; });
    });
    var ranked = Object.keys(counts).sort(function (a, b) { return counts[b] - counts[a]; });

    /* Stated beats inferred, always. A client who asked for someone gets
       that person even if the diary says they have seen someone else
       more often — the diary does not know why. */
    if (stated) {
      push('preferred', stated,
        counts[stated]
          ? 'Asked for by name · ' + counts[stated] + ' previous ' + (counts[stated] === 1 ? 'visit' : 'visits')
          : 'Asked for by name');
    }

    if (ranked.length && counts[ranked[0]] > 1) {
      push('frequent', ranked[0], counts[ranked[0]] + ' of the last ' + visits.length + ' visits');
    }

    var last = visits[0];
    if (last && (last.staffIds || [])[0]) {
      push('recent', last.staffIds[0], 'Last visit, ' + SDR.dateShort(last.date));
    }

    ranked.slice(1).forEach(function (id) {
      push('also', id, counts[id] + ' previous ' + (counts[id] === 1 ? 'visit' : 'visits'));
    });

    return out;
  }

  var SIGNAL_LABELS = {
    preferred: 'Preferred',
    frequent:  'Books most often with',
    recent:    'Saw last time',
    also:      'Also seen'
  };

  /* ---------- Service signals ------------------------------------------ */

  /* What they book, what they booked last, and what is due. Three
     suggestions rather than one, because they are frequently different
     and the receptionist is the one who knows which applies. */
  function serviceSignals(clientId) {
    var visits = visitsFor(clientId);
    if (!visits.length) return [];

    var out = [];

    /* "Usually books" is a combination, not a service. Someone who has
       had a cut and a blow-dry together five times books that pair, and
       offering the two separately misses the point. */
    var combos = {};
    visits.forEach(function (v) {
      var key = (v.serviceIds || []).slice().sort().join('+');
      if (!key) return;
      combos[key] = combos[key] || { ids: v.serviceIds.slice(), n: 0 };
      combos[key].n++;
    });
    var top = Object.keys(combos).sort(function (a, b) { return combos[b].n - combos[a].n; })[0];

    if (top && combos[top].n > 1) {
      out.push({
        kind: 'usual', serviceIds: combos[top].ids,
        why: combos[top].n + ' of the last ' + visits.length + ' visits'
      });
    }

    var last = visits[0];
    var lastKey = (last.serviceIds || []).slice().sort().join('+');
    if (lastKey && lastKey !== top) {
      out.push({ kind: 'last', serviceIds: last.serviceIds.slice(), why: SDR.dateShort(last.date) });
    }

    /* Due for a rebooking: the cadence has elapsed. Only offered when
       there is a cadence to speak of. */
    var c = cadence(clientId);
    if (c && c.overdueBy >= 0 && lastKey) {
      out.push({
        kind: 'due', serviceIds: last.serviceIds.slice(),
        why: c.overdueBy === 0
          ? 'Due now — books about every ' + c.weeks + ' weeks'
          : c.overdueBy + ' ' + (c.overdueBy === 1 ? 'week' : 'weeks') + ' overdue'
      });
    }

    return out;
  }

  /* ---------- Cadence and time of day ---------------------------------- */

  function toMinutes(t) { return SDR.toMin(t); }

  function dayNumber(dateStr) {
    return Math.floor(new Date(dateStr + 'T12:00:00').getTime() / 86400000);
  }

  /* How often they come back. Needs three visits, not two: two visits
     give one gap, and one gap is an anecdote rather than a rhythm. The
     median is used rather than the mean so a single six-month gap after
     a house move does not stretch the whole estimate. */
  function cadence(clientId) {
    var visits = visitsFor(clientId);
    if (visits.length < 3) return null;

    var gaps = [];
    for (var i = 0; i < visits.length - 1; i++) {
      gaps.push(dayNumber(visits[i].date) - dayNumber(visits[i + 1].date));
    }
    gaps.sort(function (a, b) { return a - b; });
    var mid = Math.floor(gaps.length / 2);
    var median = gaps.length % 2 ? gaps[mid] : Math.round((gaps[mid - 1] + gaps[mid]) / 2);
    if (median <= 0) return null;

    var since = dayNumber(SDR.today(0)) - dayNumber(visits[0].date);

    return {
      days: median,
      weeks: Math.max(1, Math.round(median / 7)),
      sinceDays: since,
      /* Negative means not due yet; the caller decides whether to say so. */
      overdueBy: Math.round((since - median) / 7)
    };
  }

  /* When in the day they actually come. Reported as a window rather than
     an average, because "between 2 and 5" is actionable and "3:40pm" is
     spurious precision on five data points. */
  function timeWindow(clientId) {
    var visits = visitsFor(clientId);
    if (visits.length < 3) return null;
    var mins = visits.map(function (v) { return SDR.toMin(v.start); })
      .filter(function (m) { return m !== null; });
    if (mins.length < 3) return null;

    /* Trim the single earliest and latest once there are enough points:
       one 9am appointment squeezed in before work should not widen an
       otherwise clear afternoon habit into "all day". */
    mins.sort(function (a, b) { return a - b; });
    var trimmed = mins.length >= 5 ? mins.slice(1, -1) : mins;

    var lo = trimmed[0];
    var hi = trimmed[trimmed.length - 1];
    /* Four hours is the widest span still worth calling a preference.
       Wider than that and the honest answer is that they have none. */
    if (hi - lo > 240) return null;
    return {
      from: SDR.fromMin(lo), to: SDR.fromMin(hi),
      label: SDR.timeLabel(SDR.fromMin(lo)) + ' – ' + SDR.timeLabel(SDR.fromMin(hi))
    };
  }

  function averageRating(clientId) {
    var rated = visitsFor(clientId).filter(function (v) { return Number(v.rating) > 0; });
    if (!rated.length) return null;
    var sum = rated.reduce(function (t, v) { return t + Number(v.rating); }, 0);
    return Math.round((sum / rated.length) * 10) / 10;
  }

  /* VIP / New / Returning. A label the desk can act on, derived rather
     than typed in — three visits is the point where someone stops being
     new, and the VIP tag is the business's own judgement so it wins. */
  function standing(clientId) {
    var c = SDC.byId(clientId);
    if (!c) return null;
    var n = visitsFor(clientId).length || Number(c.visits) || 0;
    if ((c.tags || []).indexOf('VIP') !== -1) {
      return { id: 'vip', label: 'VIP', badge: 'sd-badge--primary' };
    }
    if (n === 0) return { id: 'new', label: 'New client', badge: 'sd-badge--lead' };
    if (n < 3) return { id: 'returning', label: 'Returning', badge: 'sd-badge--lead' };
    return { id: 'regular', label: 'Regular', badge: 'sd-badge--active' };
  }

  /* ---------- The one-click summary (the "book the usual" case) --------- */

  /* Everything the desk needs to book a repeat client without asking a
     question. Returns null when the history cannot support it, which is
     the honest answer for a first-timer — the UI then falls back to the
     ordinary flow rather than guessing. */
  function usual(clientId) {
    var visits = visitsFor(clientId);
    if (visits.length < 2) return null;

    var svc = serviceSignals(clientId).filter(function (s) { return s.kind === 'usual'; })[0]
           || serviceSignals(clientId)[0];
    if (!svc) return null;

    var services = svc.serviceIds.map(SDS.byId).filter(Boolean);
    if (!services.length) return null;

    /* The strongest signal that can actually perform it. A client may
       have asked for someone who has since changed role, or who was
       never eligible for this particular service — that preference is
       still worth showing on the profile, but building a one-click
       booking around it would produce an appointment nobody can take. */
    var locationId = SDS.locationIds(services[0])[0] || '';
    var canDo = eligibleStaff(svc.serviceIds, locationId);
    var staff = staffSignals(clientId).filter(function (sig) {
      return canDo.indexOf(sig.staffId) !== -1;
    })[0];
    if (!staff) return null;

    return {
      serviceIds: svc.serviceIds,
      services: services,
      staffId: staff.staffId,
      staffKind: staff.kind,
      staffName: staff.name,
      minutes: services.reduce(function (t, s) { return t + SDS.serviceMinutes(s); }, 0),
      price: services.reduce(function (t, s) {
        return t + SDS.priceFor(s, { staffId: staff.staffId });
      }, 0),
      cadence: cadence(clientId),
      window: timeWindow(clientId),
      rating: averageRating(clientId),
      lastVisit: visits[0]
    };
  }

  /* ---------- Availability --------------------------------------------
     The engine already exists; this only has to ask it the right
     question. A booking of several services is one appointment of their
     combined length against the resources all of them need. */

  function requirementsFor(serviceIds, locationId) {
    var out = [];
    serviceIds.forEach(function (sid) {
      var s = SDS.byId(sid);
      if (!s) return;
      SDS.requirementsFor(s, locationId).forEach(function (r) { out.push(r); });
    });
    return out;
  }

  function minutesFor(serviceIds, staffId) {
    return serviceIds.reduce(function (t, sid) {
      var s = SDS.byId(sid);
      return t + (s ? SDS.serviceMinutes(s, { staffId: staffId }) : 0);
    }, 0);
  }

  function priceFor(serviceIds, options) {
    return serviceIds.reduce(function (t, sid) {
      var s = SDS.byId(sid);
      return t + (s ? SDS.priceFor(s, options || {}) : 0);
    }, 0);
  }

  /* Staff who can perform every service in the booking. Someone eligible
     for the cut but not the colour cannot take an appointment that is
     both — a check that is easy to forget and produces a booking nobody
     can honour. */
  function eligibleStaff(serviceIds, locationId) {
    if (!serviceIds.length) return [];
    var sets = serviceIds.map(function (sid) {
      var s = SDS.byId(sid);
      return s ? SDS.staffAt(s, locationId) : [];
    });
    return sets[0].filter(function (id) {
      return sets.every(function (set) { return set.indexOf(id) !== -1; });
    });
  }

  function staffNeeded(serviceIds) {
    return serviceIds.reduce(function (n, sid) {
      var s = SDS.byId(sid);
      return Math.max(n, s ? (Number(s.staffNeeded) || 1) : 1);
    }, 1);
  }

  function slotOptions(booking) {
    var locationId = booking.locationId || 'loc1';
    var ids = booking.serviceIds || [];
    var pool = eligibleStaff(ids, locationId);
    var need = staffNeeded(ids);

    /* Naming a staff member means "this person", but on a service that
       takes two it means "this person plus whoever else is free" — not
       "only this person", which would make every slot fail on a count
       that can never be met by one name. The named person is put first
       so the engine takes them before anyone else. */
    var staffIds = pool;
    if (booking.staffId) {
      staffIds = need > 1
        ? [booking.staffId].concat(pool.filter(function (id) { return id !== booking.staffId; }))
        : [booking.staffId];
    }

    return {
      date: booking.date || SDR.today(0),
      start: booking.start || '09:00',
      locationId: locationId,
      durationMin: minutesFor(ids, booking.staffId),
      requirements: requirementsFor(ids, locationId),
      staffIds: staffIds,
      staffNeeded: need,
      /* §23 — the front desk can knowingly book outside the site's
         advertised hours. The flag travels with the booking rather
         than being a mode on the engine, so a slot that was overridden
         is still reported honestly by every other caller. */
      allowOutsideHours: !!booking.overrideHours
    };
  }

  function checkSlot(booking) {
    if (!(booking.serviceIds || []).length) {
      return { ok: false, assigned: [], staff: [], failures: [{ kind: 'service', detail: 'No service chosen yet' }] };
    }
    return SDR.checkSlot(slotOptions(booking));
  }

  function daySlots(booking, options) {
    options = options || {};
    if (!(booking.serviceIds || []).length) return [];
    return SDR.daySlots(Object.assign({}, slotOptions(booking), {
      from: options.from || '09:00',
      to: options.to || '19:00',
      stepMin: options.stepMin || 30
    }));
  }

  /* The earliest workable start for one staff member, used to answer
     "when can they see me?" without making anyone read a grid. */
  function nextFreeFor(booking, staffId, options) {
    options = options || {};
    var days = options.days || 7;
    var from = options.date || SDR.today(0);

    /* A slot that has already gone is not free, however empty the diary
       says it is. Offering "next available: 9 AM" at half past two is
       the kind of answer that gets a system distrusted. */
    var now = new Date();
    var nowMin = now.getHours() * 60 + now.getMinutes();
    var todayStr = SDR.today(0);

    for (var d = 0; d < days; d++) {
      var date = SDR.today(dayNumber(from) - dayNumber(todayStr) + d);
      var rows = daySlots(Object.assign({}, booking, { date: date, staffId: staffId }), options);
      var hit = rows.filter(function (r) {
        if (!r.result.ok) return false;
        if (date === todayStr && toMinutes(r.start) < nowMin) return false;
        return true;
      })[0];
      if (hit) return { date: date, start: hit.start, label: hit.label, result: hit.result };
    }
    return null;
  }

  /* Every plausible staff member, each with their next free slot and the
     reason we are suggesting them. This is the ordering the front desk
     reads top to bottom. */
  function staffOptions(booking, clientId) {
    var ids = booking.serviceIds || [];
    var eligible = eligibleStaff(ids, booking.locationId);
    if (!eligible.length) return [];

    var signals = clientId ? staffSignals(clientId) : [];
    var byId_ = {};
    signals.forEach(function (s) { byId_[s.staffId] = s; });

    var ordered = [];
    signals.forEach(function (s) { if (eligible.indexOf(s.staffId) !== -1) ordered.push(s.staffId); });
    eligible.forEach(function (id) { if (ordered.indexOf(id) === -1) ordered.push(id); });

    return ordered.map(function (id) {
      var sig = byId_[id];
      var next = nextFreeFor(booking, id, { date: booking.date });
      return {
        staffId: id,
        name: SD.staffName(id),
        role: (SD.staffById(id) || {}).role || '',
        kind: sig ? sig.kind : 'other',
        why: sig ? sig.why : '',
        next: next
      };
    });
  }

  /* ---------- Formatting ------------------------------------------------ */

  function statusMeta(id) {
    return STATUSES.filter(function (s) { return s.id === id; })[0] || STATUSES[0];
  }
  function paymentMeta(id) {
    return PAYMENT_STATUSES.filter(function (s) { return s.id === id; })[0] || PAYMENT_STATUSES[0];
  }

  function serviceNames(ids) {
    return (ids || []).map(function (sid) {
      var s = SDS.byId(sid);
      return s ? s.name : '';
    }).filter(Boolean).join(' + ');
  }

  function timeRange(a) {
    if (!a.start) return '';
    return SDR.timeLabel(a.start) + ' – ' + SDR.timeLabel(a.end);
  }

  function esc(v) {
    return String(v == null ? '' : v)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
  }

  return {
    STATUSES: STATUSES, PAYMENT_STATUSES: PAYMENT_STATUSES,
    SOURCES: SOURCES, DEPOSIT_ACTIONS: DEPOSIT_ACTIONS,
    SIGNAL_LABELS: SIGNAL_LABELS,

    read: read, write: write, reset: reset,
    all: all, byId: byId, save: save, setStatus: setStatus,

    visitsFor: visitsFor, lastVisit: lastVisit, recentVisits: recentVisits,
    prefs: prefs, savePrefs: savePrefs,

    staffSignals: staffSignals, serviceSignals: serviceSignals,
    cadence: cadence, timeWindow: timeWindow, averageRating: averageRating,
    standing: standing, usual: usual,

    requirementsFor: requirementsFor, minutesFor: minutesFor, priceFor: priceFor,
    eligibleStaff: eligibleStaff, staffNeeded: staffNeeded,
    checkSlot: checkSlot, daySlots: daySlots,
    nextFreeFor: nextFreeFor, staffOptions: staffOptions,

    statusMeta: statusMeta, paymentMeta: paymentMeta,
    serviceNames: serviceNames, timeRange: timeRange, esc: esc
  };
}());
