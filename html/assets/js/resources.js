/* StyleDesk — Resource inventory
   ------------------------------------------------------------------
   Physical assets a service needs in order to happen: chairs, rooms,
   tables, machines. Managed independently of staff, because the core
   scheduling rule is that a free stylist is not a bookable slot if
   every chair is taken (§37).

   The availability engine lives here rather than in a page so the
   Services screens can ask it the same question the booking engine
   will:  is this slot actually deliverable?

   Front-end only. In the Laravel build these become tenant-scoped
   tables — resource_types, resources, resource_availability,
   resource_maintenance — and appointment_resources on the booking.
   ------------------------------------------------------------------ */

window.SDR = (function () {
  'use strict';

  var KEY = 'styledesk.resources';
  var memory = null;

  /* ---------- Reference ------------------------------------------- */

  /* Locations are tenant-owned; mirrored from the account module so the
     two never disagree about what a location is called. */
  /* The sites the business runs from now live in SDL, which holds their
     addresses, hours and time zones. This stays a plain array of
     {id,name} so that every existing call site — `SDR.LOCATIONS.map`,
     `.filter`, `.length` — keeps working untouched; it is only where the
     names come from that changed.

     `live()` rather than `all()`: a closed site must not be offered as
     somewhere to book. `locationName()` below still resolves a closed
     one, because history has to keep naming where it happened. */
  function locationList() {
    if (typeof SDL === 'undefined') {
      return [{ id: 'loc1', name: 'Downtown Salon' },
              { id: 'loc2', name: 'Westside Spa' },
              { id: 'loc3', name: 'Airport Location' }];
    }
    return SDL.live().map(function (l) { return { id: l.id, name: l.name }; });
  }

  var DAYS = [
    { id: 'mon', label: 'Monday',    short: 'Mon' },
    { id: 'tue', label: 'Tuesday',   short: 'Tue' },
    { id: 'wed', label: 'Wednesday', short: 'Wed' },
    { id: 'thu', label: 'Thursday',  short: 'Thu' },
    { id: 'fri', label: 'Friday',    short: 'Fri' },
    { id: 'sat', label: 'Saturday',  short: 'Sat' },
    { id: 'sun', label: 'Sunday',    short: 'Sun' }
  ];

  var MAINTENANCE_TYPES = [
    'Routine service', 'Deep clean', 'Repair', 'Calibration',
    'Replacement', 'Inspection', 'Other'
  ];

  var MAINTENANCE_STATUSES = [
    { id: 'scheduled',  label: 'Scheduled',   badge: 'sd-badge--lead' },
    { id: 'inprogress', label: 'In progress', badge: 'sd-badge--primary' },
    { id: 'completed',  label: 'Completed',   badge: 'sd-badge--active' },
    { id: 'cancelled',  label: 'Cancelled',   badge: 'sd-badge--inactive' }
  ];

  var RECURRENCE = [
    { id: 'none',    label: 'Does not repeat' },
    { id: 'weekly',  label: 'Every week' },
    { id: 'monthly', label: 'Every month' }
  ];

  /* ---------- Seed -------------------------------------------------
     Deliberately mixed: a salon floor of interchangeable chairs, a spa
     with single and couples rooms, and one piece of equipment that a
     service will have to name specifically. Between them they exercise
     any-of-type, minimum-capacity and specific-resource assignment. */

  function seed() {
    var types = [
      { id: 't1', name: 'Styling Chair',        code: 'CHR-STY', defaultCapacity: 1, description: 'Standard cutting and finishing chair.', active: true },
      { id: 't2', name: 'Colour Station',       code: 'CHR-COL', defaultCapacity: 1, description: 'Chair with colour bar access and a backwash nearby.', active: true },
      { id: 't3', name: 'Barber Chair',         code: 'CHR-BRB', defaultCapacity: 1, description: 'Reclining barber chair with a wet shave setup.', active: true },
      { id: 't4', name: 'Nail Station',         code: 'STN-NAI', defaultCapacity: 1, description: 'Manicure desk with extraction.', active: true },
      { id: 't5', name: 'Single Massage Room',  code: 'RM-MAS1', defaultCapacity: 1, description: 'One table, one therapist.', active: true },
      { id: 't6', name: 'Couples Massage Room', code: 'RM-MAS2', defaultCapacity: 2, description: 'Two tables side by side. Needs two therapists.', active: true },
      { id: 't7', name: 'Treatment Room',       code: 'RM-TRT',  defaultCapacity: 1, description: 'Enclosed room for facials and med-spa work.', active: true },
      { id: 't8', name: 'Laser Machine',        code: 'EQ-LSR',  defaultCapacity: 1, description: 'Moves between treatment rooms. Booked separately.', active: true },
      { id: 't9', name: 'HydraFacial Machine',  code: 'EQ-HYD',  defaultCapacity: 1, description: 'Single unit, shared across the floor.', active: true }
    ];

    var full = { mon: [['09:00', '19:00']], tue: [['09:00', '19:00']], wed: [['09:00', '19:00']],
                 thu: [['09:00', '20:00']], fri: [['09:00', '20:00']], sat: [['09:00', '18:00']], sun: [] };
    var spa  = { mon: [['09:00', '19:00']], tue: [['09:00', '19:00']], wed: [['09:00', '19:00']],
                 thu: [['09:00', '19:00']], fri: [['09:00', '19:00']], sat: [['10:00', '17:00']], sun: [['10:00', '16:00']] };

    function res(id, name, typeId, locationId, capacity, extra) {
      return Object.assign({
        id: id, name: name, typeId: typeId, locationId: locationId,
        capacity: capacity,
        code: '', description: '',
        active: true, bookable: true, onlineEligible: true,
        floor: '', roomNumber: '', serial: '', purchaseDate: '', maintenanceIntervalDays: '',
        notes: '',
        availability: JSON.parse(JSON.stringify(full))
      }, extra || {});
    }

    var resources = [
      res('r1', 'Chair 01', 't1', 'loc1', 1, { code: 'DT-C01', floor: 'Main floor' }),
      res('r2', 'Chair 02', 't1', 'loc1', 1, { code: 'DT-C02', floor: 'Main floor' }),
      res('r3', 'Chair 03', 't1', 'loc1', 1, { code: 'DT-C03', floor: 'Main floor' }),
      res('r4', 'Chair 04', 't1', 'loc1', 1, { code: 'DT-C04', floor: 'Main floor', active: false,
            notes: 'Hydraulic base sinking. Out until the part arrives.' }),
      res('r5', 'Colour Station 01', 't2', 'loc1', 1, { code: 'DT-K01', floor: 'Colour area' }),
      res('r6', 'Colour Station 02', 't2', 'loc1', 1, { code: 'DT-K02', floor: 'Colour area' }),
      res('r7', 'Barber Chair 01', 't3', 'loc1', 1, { code: 'DT-B01', floor: 'Main floor' }),
      res('r8', 'Nail Station 01', 't4', 'loc1', 1, { code: 'DT-N01', floor: 'Main floor' }),
      res('r9', 'Nail Station 02', 't4', 'loc1', 1, { code: 'DT-N02', floor: 'Main floor' }),

      res('r10', 'Massage Room 1', 't5', 'loc2', 1, { code: 'WS-M01', floor: 'Spa floor', roomNumber: '1',
            availability: JSON.parse(JSON.stringify(spa)) }),
      res('r11', 'Massage Room 2', 't5', 'loc2', 1, { code: 'WS-M02', floor: 'Spa floor', roomNumber: '2',
            availability: JSON.parse(JSON.stringify(spa)) }),
      res('r12', 'Couples Suite 1', 't6', 'loc2', 2, { code: 'WS-M03', floor: 'Spa floor', roomNumber: '3',
            description: 'Two tables. Requires two therapists.',
            availability: JSON.parse(JSON.stringify(spa)) }),
      res('r13', 'Facial Room 1', 't7', 'loc2', 1, { code: 'WS-T01', floor: 'Spa floor', roomNumber: '4',
            availability: JSON.parse(JSON.stringify(spa)) }),
      res('r14', 'Treatment Room 2', 't7', 'loc2', 1, { code: 'WS-T02', floor: 'Spa floor', roomNumber: '5',
            availability: JSON.parse(JSON.stringify(spa)) }),
      res('r15', 'Laser Machine 01', 't8', 'loc2', 1, { code: 'WS-L01', serial: 'LX-99421',
            purchaseDate: '2025-03-14', maintenanceIntervalDays: '90',
            onlineEligible: false,
            description: 'Wheeled between treatment rooms.',
            availability: JSON.parse(JSON.stringify(spa)) }),
      res('r16', 'HydraFacial 01', 't9', 'loc2', 1, { code: 'WS-H01', serial: 'HF-20871',
            purchaseDate: '2024-11-02', maintenanceIntervalDays: '60',
            availability: JSON.parse(JSON.stringify(spa)) }),

      res('r17', 'Chair 01', 't1', 'loc3', 1, { code: 'AP-C01' }),
      res('r18', 'Chair 02', 't1', 'loc3', 1, { code: 'AP-C02' })
    ];

    return {
      types: types,
      resources: resources,
      maintenance: [
        { id: 'm1', resourceId: 'r12', type: 'Deep clean', start: today(3) + 'T12:00', end: today(3) + 'T14:00',
          recurrence: 'weekly', reason: 'Weekly deep clean', notes: '', status: 'scheduled' },
        { id: 'm2', resourceId: 'r15', type: 'Calibration', start: today(1) + 'T09:00', end: today(1) + 'T11:30',
          recurrence: 'none', reason: 'Quarterly calibration', notes: 'Engineer booked.', status: 'scheduled' },
        { id: 'm3', resourceId: 'r4', type: 'Repair', start: today(-6) + 'T09:00', end: today(9) + 'T18:00',
          recurrence: 'none', reason: 'Hydraulic base replacement', notes: 'Awaiting part.', status: 'inprogress' },
        { id: 'm4', resourceId: 'r16', type: 'Routine service', start: today(-14) + 'T13:00', end: today(-14) + 'T15:00',
          recurrence: 'none', reason: '60-day service', notes: '', status: 'completed' }
      ],
      /* Stand-ins for real appointments, so conflict checks have
         something to collide with. */
      bookings: [
        { id: 'b1', resourceIds: ['r10'], staffIds: ['u1'], start: today(1) + 'T10:00', end: today(1) + 'T11:00', label: '60-minute massage' },
        { id: 'b2', resourceIds: ['r11'], staffIds: ['u3'], start: today(1) + 'T10:30', end: today(1) + 'T11:30', label: '60-minute massage' },
        { id: 'b3', resourceIds: ['r12'], staffIds: ['u1', 'u2'], start: today(1) + 'T14:00', end: today(1) + 'T15:30', label: 'Couples massage' },
        { id: 'b4', resourceIds: ['r1'], staffIds: ['u1'], start: today(1) + 'T09:30', end: today(1) + 'T10:15', label: 'Cut & finish' },
        { id: 'b5', resourceIds: ['r2'], staffIds: ['u2'], start: today(1) + 'T09:30', end: today(1) + 'T10:30', label: 'Cut & finish' },
        { id: 'b6', resourceIds: ['r3'], staffIds: ['u4'], start: today(1) + 'T09:00', end: today(1) + 'T11:00', label: 'Full head colour' }
      ]
    };
  }

  /* ---------- Dates ------------------------------------------------ */

  function pad(n) { return (n < 10 ? '0' : '') + n; }

  function today(offsetDays) {
    var d = new Date();
    d.setDate(d.getDate() + (offsetDays || 0));
    return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate());
  }

  function dayIdOf(dateStr) {
    var d = new Date(dateStr + 'T12:00:00');
    return ['sun', 'mon', 'tue', 'wed', 'thu', 'fri', 'sat'][d.getDay()];
  }

  /* Minutes since midnight — the unit every overlap test works in, so
     nothing has to reason about date objects mid-comparison. */
  function toMin(hhmm) {
    var m = /^(\d{1,2}):(\d{2})$/.exec(String(hhmm || ''));
    return m ? Number(m[1]) * 60 + Number(m[2]) : null;
  }

  function fromMin(mins) {
    return pad(Math.floor(mins / 60)) + ':' + pad(mins % 60);
  }

  function timeLabel(hhmm) {
    var m = toMin(hhmm);
    if (m === null) return '';
    var h = Math.floor(m / 60), min = m % 60;
    var ampm = h >= 12 ? 'PM' : 'AM';
    var h12 = h % 12 === 0 ? 12 : h % 12;
    return h12 + (min ? ':' + pad(min) : '') + ' ' + ampm;
  }

  function splitStamp(stamp) {
    var parts = String(stamp || '').split('T');
    return { date: parts[0] || '', time: parts[1] || '' };
  }

  function overlaps(aStart, aEnd, bStart, bEnd) {
    // Touching ranges do not overlap: 10:00–11:00 and 11:00–12:00 are fine.
    return aStart < bEnd && bStart < aEnd;
  }

  function dateShort(iso) {
    if (!iso) return '';
    var MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    var p = String(iso).split('-');
    if (p.length < 3) return iso;
    return MONTHS[Number(p[1]) - 1] + ' ' + Number(p[2]) + ', ' + p[0];
  }

  /* ---------- Store ------------------------------------------------ */

  function read() {
    if (memory) return memory;
    try {
      var raw = window.localStorage.getItem(KEY);
      memory = raw ? JSON.parse(raw) : seed();
    } catch (e) {
      memory = seed();
    }
    if (!memory || !Array.isArray(memory.resources)) memory = seed();
    return memory;
  }

  function write(state) {
    memory = state;
    try { window.localStorage.setItem(KEY, JSON.stringify(state)); } catch (e) {}
    return state;
  }

  function reset() {
    memory = null;
    try { window.localStorage.removeItem(KEY); } catch (e) {}
    return read();
  }

  /* A caller building one payload for both create and update passes
     `id: undefined` on the create path. Object.assign happily copies that
     over a freshly minted id, so the record lands with no id at all —
     strip the key before it can. */
  function withoutBlankId(data) {
    var out = {}, k;
    for (k in data) {
      if (!Object.prototype.hasOwnProperty.call(data, k)) continue;
      if (k === 'id' && (data.id === undefined || data.id === null || data.id === '')) continue;
      out[k] = data[k];
    }
    return out;
  }

  function nextId(list, prefix) {
    var max = 0;
    list.forEach(function (row) {
      var n = parseInt(String(row.id).replace(/\D/g, ''), 10);
      if (!isNaN(n) && n > max) max = n;
    });
    return prefix + (max + 1);
  }

  /* ---------- Types ------------------------------------------------ */

  function types() { return read().types; }
  function typeById(id) { return types().filter(function (t) { return t.id === id; })[0] || null; }
  function typeName(id) { var t = typeById(id); return t ? t.name : ''; }

  function saveType(data) {
    var s = read();
    if (data.id) {
      s.types = s.types.map(function (t) { return t.id === data.id ? Object.assign({}, t, data) : t; });
    } else {
      s.types.push(Object.assign({
        id: nextId(s.types, 't'), name: '', code: '', defaultCapacity: 1, description: '', active: true
      }, withoutBlankId(data)));
    }
    write(s);
    return s.types;
  }

  /* A type in use cannot be deleted — the resources pointing at it
     would be orphaned, and silently reassigning them would be worse. */
  function typeUsage(typeId) {
    return read().resources.filter(function (r) { return r.typeId === typeId; }).length;
  }

  function deleteType(typeId) {
    if (typeUsage(typeId)) return { ok: false, reason: 'in-use', count: typeUsage(typeId) };
    var s = read();
    s.types = s.types.filter(function (t) { return t.id !== typeId; });
    write(s);
    return { ok: true };
  }

  /* ---------- Resources -------------------------------------------- */

  function resources() { return read().resources; }
  function resourceById(id) { return resources().filter(function (r) { return r.id === id; })[0] || null; }

  function saveResource(data) {
    var s = read();
    if (data.id) {
      s.resources = s.resources.map(function (r) { return r.id === data.id ? Object.assign({}, r, data) : r; });
      write(s);
      return resourceById(data.id);
    }
    var row = Object.assign({
      id: nextId(s.resources, 'r'),
      name: '', typeId: '', locationId: '', capacity: 1,
      code: '', description: '',
      active: true, bookable: true, onlineEligible: true,
      floor: '', roomNumber: '', serial: '', purchaseDate: '', maintenanceIntervalDays: '',
      notes: '', availability: { mon: [], tue: [], wed: [], thu: [], fri: [], sat: [], sun: [] }
    }, withoutBlankId(data));
    s.resources.unshift(row);
    write(s);
    return row;
  }

  function deleteResource(id) {
    var s = read();
    s.resources = s.resources.filter(function (r) { return r.id !== id; });
    s.maintenance = s.maintenance.filter(function (m) { return m.resourceId !== id; });
    write(s);
  }

  function staffName(id) {
    if (typeof SDT !== 'undefined') {
      var n = SDT.nameOf(id);
      if (n) return n;
    }
    return id;
  }

  function locationName(id) {
    /* Resolves a closed site as well as an open one. A booking made at
       a location that has since shut still has to say where it was. */
    if (typeof SDL !== 'undefined') return SDL.nameOf(id);
    var hit = locationList().filter(function (l) { return l.id === id; });
    return hit.length ? hit[0].name : '';
  }

  /* ---------- Maintenance ------------------------------------------ */

  function maintenance() { return read().maintenance; }

  function saveMaintenance(data) {
    var s = read();
    if (data.id) {
      s.maintenance = s.maintenance.map(function (m) { return m.id === data.id ? Object.assign({}, m, data) : m; });
    } else {
      s.maintenance.unshift(Object.assign({
        id: nextId(s.maintenance, 'm'), resourceId: '', type: MAINTENANCE_TYPES[0],
        start: '', end: '', recurrence: 'none', reason: '', notes: '', status: 'scheduled'
      }, withoutBlankId(data)));
    }
    write(s);
    return s.maintenance;
  }

  function deleteMaintenance(id) {
    var s = read();
    s.maintenance = s.maintenance.filter(function (m) { return m.id !== id; });
    write(s);
  }

  function maintenanceStatus(id) {
    return MAINTENANCE_STATUSES.filter(function (m) { return m.id === id; })[0] || MAINTENANCE_STATUSES[0];
  }

  /* Blocks that actually stop a booking. Cancelled and completed ones
     are history and must not keep a room out of service. */
  function blockingMaintenance(resourceId) {
    return maintenance().filter(function (m) {
      return m.resourceId === resourceId &&
             (m.status === 'scheduled' || m.status === 'inprogress');
    });
  }

  /* Does a maintenance block cover this window? Weekly and monthly
     recurrence repeat the time-of-day window on matching days. */
  function maintenanceHit(block, dateStr, startMin, endMin) {
    var s = splitStamp(block.start), e = splitStamp(block.end);
    if (!s.date || !e.date) return false;

    var bStart = toMin(s.time), bEnd = toMin(e.time);
    if (bStart === null || bEnd === null) return false;

    if (block.recurrence === 'none') {
      // A multi-day block covers whole days in between.
      if (dateStr < s.date || dateStr > e.date) return false;
      var from = dateStr === s.date ? bStart : 0;
      var to = dateStr === e.date ? bEnd : 24 * 60;
      return overlaps(startMin, endMin, from, to);
    }

    if (dateStr < s.date) return false;
    if (block.recurrence === 'weekly' && dayIdOf(dateStr) !== dayIdOf(s.date)) return false;
    if (block.recurrence === 'monthly' && dateStr.slice(-2) !== s.date.slice(-2)) return false;
    return overlaps(startMin, endMin, bStart, bEnd);
  }

  /* ---------- Bookings --------------------------------------------- */

  function bookings() { return read().bookings; }

  function addBooking(row) {
    var s = read();
    s.bookings.push(Object.assign({ id: nextId(s.bookings, 'b') }, row));
    write(s);
    return s.bookings;
  }

  function bookingsFor(resourceId, dateStr) {
    return bookings().filter(function (b) {
      return b.resourceIds.indexOf(resourceId) !== -1 && splitStamp(b.start).date === dateStr;
    });
  }

  /* ---------- Availability -----------------------------------------
     One resource, one window. Returns why it failed rather than a bare
     false, because "no chair free" and "chair is being repaired" are
     different problems for whoever is looking at the calendar. */

  function checkResource(resourceId, dateStr, startMin, endMin) {
    var r = resourceById(resourceId);
    if (!r) return { ok: false, reason: 'Resource not found' };
    if (!r.active) return { ok: false, reason: 'Inactive' };
    if (!r.bookable) return { ok: false, reason: 'Not bookable' };

    var windows = (r.availability && r.availability[dayIdOf(dateStr)]) || [];
    var inHours = windows.some(function (w) {
      return toMin(w[0]) <= startMin && toMin(w[1]) >= endMin;
    });
    if (!inHours) return { ok: false, reason: 'Outside its hours' };

    var block = blockingMaintenance(resourceId).filter(function (m) {
      return maintenanceHit(m, dateStr, startMin, endMin);
    })[0];
    if (block) return { ok: false, reason: 'Maintenance — ' + (block.reason || block.type) };

    var clash = bookingsFor(resourceId, dateStr).filter(function (b) {
      return overlaps(startMin, endMin, toMin(splitStamp(b.start).time), toMin(splitStamp(b.end).time));
    })[0];
    if (clash) return { ok: false, reason: 'Booked — ' + clash.label };

    return { ok: true };
  }

  /* Every resource of a type at a location that is free for the window
     and big enough. This is what "any available chair" means (§16). */
  function candidates(requirement, dateStr, startMin, endMin) {
    var minCapacity = Number(requirement.minCapacity) || 1;

    return resources().filter(function (r) {
      if (requirement.locationId && r.locationId !== requirement.locationId) return false;
      if (requirement.resourceId) return r.id === requirement.resourceId;
      if (requirement.typeId && r.typeId !== requirement.typeId) return false;
      return r.capacity >= minCapacity;
    }).map(function (r) {
      return { resource: r, check: checkResource(r.id, dateStr, startMin, endMin) };
    });
  }

  /* The whole rule in one call (§30, §37):
       slot = staff availability + resource availability + location + service rules
     `requirement` is one line of a service's Resource Requirements:
       { typeId | resourceId, quantity, minCapacity, locationId }
     Returns what was assigned, or precisely what was missing. */
  function checkSlot(options) {
    var dateStr = options.date;
    var startMin = toMin(options.start);
    var endMin = startMin + (Number(options.durationMin) || 0);
    var needs = options.requirements || [];
    var staffIds = options.staffIds || [];
    var staffNeeded = options.staffNeeded || staffIds.length || 1;

    var failures = [];
    var assigned = [];
    var taken = {};       // one physical resource cannot fill two lines

    /* §50 of the Business Hours spec: the location's own hours are the
       outermost term of the intersection, so they are asked first. A
       free stylist and a free chair are no use inside a locked building,
       and answering "no staff available" when the real answer is
       "closed for Thanksgiving" sends the receptionist hunting through
       rotas for a problem that is not there.

       `allowOutsideHours` is how §23's Override & Book gets through —
       the engine still reports the truth, the caller decides. */
    var where = options.locationId || (needs.filter(function (n) { return n.locationId; })[0] || {}).locationId;

    if (where && !options.allowOutsideHours && typeof SDH !== 'undefined') {
      var closedWhy = SDH.why(where, dateStr, startMin, endMin);
      if (closedWhy) {
        var state = SDH.spansOn(where, dateStr);
        failures.push({
          kind: 'hours',
          detail: closedWhy,
          because: state.exception && state.exception.note ? [state.exception.note] : []
        });
      }
    }

    /* Staff next: the cheapest check and the usual reason a slot is
       gone. Four separate ways a person can be unavailable, and all
       four have to hold — an empty diary is not the same as being at
       work, and a free chair at 6 PM is no use if the stylist finished
       at five. */
    function staffWhy(id) {
      if (typeof SDT !== 'undefined') {
        var person = SDT.byId(id);
        if (person) {
          if (SDT.isOff(person, dateStr)) return 'Time off';

          /* Outside their shift. Someone on a split shift is genuinely
             free between them, which is the point of split shifts. */
          var shifts = SDT.shiftsOn(person, dateStr);
          if (!shifts.length) return 'Not working that day';
          var inShift = shifts.some(function (sp) {
            return startMin >= toMin(sp[0]) && endMin <= toMin(sp[1]);
          });
          if (!inShift) {
            return 'Outside their hours (' +
              shifts.map(function (sp) { return timeLabel(sp[0]) + '–' + timeLabel(sp[1]); }).join(', ') + ')';
          }

          var day = dayIdOf(dateStr);
          var br = (person.breaks || []).filter(function (x) {
            return (x.days || []).indexOf(day) !== -1 &&
                   overlaps(startMin, endMin, toMin(x.from), toMin(x.to));
          })[0];
          if (br) return 'On a break — ' + (br.label || 'break');
        }
      }

      var clash = bookings().filter(function (b) {
        return (b.staffIds || []).indexOf(id) !== -1 &&
               splitStamp(b.start).date === dateStr &&
               overlaps(startMin, endMin, toMin(splitStamp(b.start).time), toMin(splitStamp(b.end).time));
      })[0];
      if (clash) return 'Booked — ' + clash.label;

      return null;
    }

    /* Four different reasons a person is unavailable, and they call for
       four different responses: a break moves the slot by half an hour,
       a holiday moves it by a week. Reporting all of them as "no staff
       free" makes the receptionist go and look. */
    var staffBlocked = [];
    var freeStaff = staffIds.filter(function (id) {
      var why = staffWhy(id);
      if (why) staffBlocked.push({ id: id, name: staffName(id), reason: why });
      return !why;
    });

    if (freeStaff.length < staffNeeded) {
      failures.push({
        kind: 'staff',
        detail: staffNeeded === 1
          ? (staffBlocked.length === 1
              ? staffBlocked[0].name + ': ' + staffBlocked[0].reason
              : 'No eligible staff free')
          : 'Needs ' + staffNeeded + ' staff, only ' + freeStaff.length + ' free',
        because: staffBlocked.map(function (b) { return b.name + ': ' + b.reason; })
      });
    }

    needs.forEach(function (need) {
      var quantity = Number(need.quantity) || 1;
      var options_ = candidates(need, dateStr, startMin, endMin);
      var free = options_.filter(function (c) { return c.check.ok && !taken[c.resource.id]; });

      if (free.length < quantity) {
        var label = (need.resourceId
          ? (resourceById(need.resourceId) || {}).name
          : typeName(need.typeId)) || 'Resource';
        var capacityNote = Number(need.minCapacity) > 1 ? ' (capacity ' + need.minCapacity + '+)' : '';

        /* Two different problems, and they need different answers.
           Nothing matching the requirement exists — wrong capacity, no
           such type at this location — is a setup mistake to go and fix.
           Everything matching is busy is a scheduling problem. Reporting
           both as "unavailable" sends people looking in the wrong place. */
        if (!options_.length) {
          failures.push({
            kind: 'resource',
            detail: 'No ' + label + capacityNote + ' exists' +
                    (need.locationId ? ' at ' + locationName(need.locationId) : ''),
            because: []
          });
          return;
        }

        failures.push({
          kind: 'resource',
          detail: (quantity > 1 ? quantity + '× ' : '') + label + capacityNote +
                  ' unavailable' +
                  (quantity > 1 ? ' — ' + free.length + ' of ' + quantity + ' free' : ''),
          because: options_
            .filter(function (c) { return !c.check.ok; })
            .map(function (c) { return c.resource.name + ': ' + c.check.reason; })
        });
        return;
      }

      free.slice(0, quantity).forEach(function (c) {
        taken[c.resource.id] = true;
        assigned.push(c.resource);
      });
    });

    /* §23 — "outside business hours" is the one failure a permitted
       user can knowingly book through, so it is flagged separately
       rather than left for the caller to string-match a reason. */
    var hoursOnly = failures.length === 1 && failures[0].kind === 'hours';

    return {
      ok: failures.length === 0,
      assigned: assigned,
      staff: freeStaff.slice(0, staffNeeded),
      failures: failures,
      outsideHours: failures.some(function (f) { return f.kind === 'hours'; }),
      overridable: hoursOnly
    };
  }

  /* Slots across a day at a fixed step, each with its verdict. Drives
     the checker on the service screens. */
  function daySlots(options) {
    var step = options.stepMin || 30;
    var from = toMin(options.from || '09:00');
    var to = toMin(options.to || '19:00');
    var duration = Number(options.durationMin) || 60;
    var out = [];

    for (var m = from; m + duration <= to; m += step) {
      var result = checkSlot(Object.assign({}, options, { start: fromMin(m) }));
      out.push({ start: fromMin(m), end: fromMin(m + duration), label: timeLabel(fromMin(m)), result: result });
    }
    return out;
  }

  function esc(v) {
    return String(v == null ? '' : v)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
  }

  var api = {
    DAYS: DAYS,
    MAINTENANCE_TYPES: MAINTENANCE_TYPES, MAINTENANCE_STATUSES: MAINTENANCE_STATUSES,
    RECURRENCE: RECURRENCE,

    read: read, write: write, reset: reset,

    types: types, typeById: typeById, typeName: typeName,
    saveType: saveType, deleteType: deleteType, typeUsage: typeUsage,

    resources: resources, resourceById: resourceById,
    saveResource: saveResource, deleteResource: deleteResource, locationName: locationName,

    maintenance: maintenance, saveMaintenance: saveMaintenance, deleteMaintenance: deleteMaintenance,
    maintenanceStatus: maintenanceStatus, blockingMaintenance: blockingMaintenance, maintenanceHit: maintenanceHit,

    bookings: bookings, addBooking: addBooking, bookingsFor: bookingsFor,

    checkResource: checkResource, candidates: candidates, checkSlot: checkSlot, daySlots: daySlots,

    today: today, dayIdOf: dayIdOf, toMin: toMin, fromMin: fromMin,
    timeLabel: timeLabel, splitStamp: splitStamp, overlaps: overlaps, dateShort: dateShort, esc: esc
  };

  /* A getter, not a snapshot: adding or closing a location has to be
     visible to code that captured `SDR` at load time. */
  Object.defineProperty(api, 'LOCATIONS', {
    enumerable: true,
    get: function () { return locationList(); }
  });

  return api;
}());
