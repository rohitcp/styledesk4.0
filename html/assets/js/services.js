/* ==================================================================
   StyleDesk — Services store  (SDS)
   ------------------------------------------------------------------
   Categories, add-ons and services, plus the rules that decide whether
   a service is bookable at all.

   The centre of this file is §24:

       bookable slot = eligible staff
                     + the location
                     + every required resource
                     + the service's own booking rules

   None of those four is special-cased. A service names what it needs;
   `requirementsFor()` turns that into the shape SDR.checkSlot already
   understands, and the resource engine answers. That is why a couples
   massage and a laser facial need no bespoke code — one asks for a
   room with capacity 2 and two therapists, the other for a room and a
   named machine, and both are the same question.

   Depends on SD (staff roster, escapeHtml) and SDR (resources,
   locations, availability engine).
   ================================================================== */

var SDS = (function () {
  'use strict';

  var KEY = 'styledesk.services';

  /* ---------- Reference data --------------------------------------- */

  /* §20. Draft and Archived both mean "not on the booking page", but for
     opposite reasons — one is not finished, the other is retired — and
     they are listed differently, so they stay distinct statuses rather
     than one "hidden" flag. */
  var STATUSES = [
    { id: 'draft',    label: 'Draft',    badge: 'sd-badge--lead',
      note: 'Not finished. Cannot be booked.' },
    { id: 'active',   label: 'Active',   badge: 'sd-badge--active',
      note: 'Bookable wherever staff and resources allow.' },
    { id: 'inactive', label: 'Inactive', badge: 'sd-badge--inactive',
      note: 'Kept in history. Takes no new bookings.' },
    { id: 'archived', label: 'Archived', badge: 'sd-badge--inactive',
      note: 'Hidden from the normal list. Past appointments keep it.' }
  ];

  var PRICING_TYPES = [
    { id: 'fixed',    label: 'Fixed price',        needsPrice: true,
      note: 'One price, shown to the client up front.' },
    { id: 'from',     label: 'Starting at',        needsPrice: true,
      note: 'Shown as "from $X". The final price is set at checkout.' },
    { id: 'variable', label: 'Variable',           needsPrice: false,
      note: 'No price shown. Quoted after consultation.' },
    { id: 'free',     label: 'Free',               needsPrice: false,
      note: 'No charge — consultations, patch tests, redos.' },
    { id: 'staff',    label: 'Set by staff member', needsPrice: false,
      note: 'Each eligible staff member sets their own price.' }
  ];

  var TAX_CATEGORIES = ['Standard rate', 'Reduced rate', 'Services — exempt', 'Goods'];

  var COMMISSION_TYPES = [
    { id: 'percent', label: 'Percentage of price' },
    { id: 'fixed',   label: 'Fixed amount per service' }
  ];

  var DEPOSIT_TYPES = [
    { id: 'percent', label: 'Percentage of price' },
    { id: 'fixed',   label: 'Fixed amount' }
  ];

  /* §16. Each is a gate on the appointment, not a note on the service,
     so each carries its own "required" and "before the appointment"
     answers rather than one shared flag. */
  var CLIENT_REQUIREMENTS = [
    { id: 'intake',       label: 'Intake form',
      note: 'Health history and contraindications.' },
    { id: 'consent',      label: 'Consent form',
      note: 'Informed consent for the treatment itself.' },
    { id: 'waiver',       label: 'Waiver',
      note: 'Liability waiver.' },
    { id: 'consultation', label: 'Consultation',
      note: 'A separate appointment before this one can be booked.' },
    { id: 'patchtest',    label: 'Patch test',
      note: 'Colour and lash services. Usually 48 hours ahead.' },
    { id: 'card',         label: 'Card on file',
      note: 'Held against late cancellation.' }
  ];

  var INSTRUCTION_FIELDS = [
    { id: 'confirmation', label: 'Confirmation',       channel: 'Sent when the appointment is booked.' },
    { id: 'preparation',  label: 'How to prepare',     channel: 'Sent with the reminder.' },
    { id: 'arrival',      label: 'On arrival',         channel: 'Sent with the reminder.' },
    { id: 'aftercare',    label: 'Aftercare',          channel: 'Sent after the appointment.' }
  ];

  var COLORS = [
    { id: 'violet', label: 'Violet', hex: '#3d348b' },
    { id: 'teal',   label: 'Teal',   hex: '#0d9488' },
    { id: 'amber',  label: 'Amber',  hex: '#d97706' },
    { id: 'rose',   label: 'Rose',   hex: '#e11d48' },
    { id: 'blue',   label: 'Blue',   hex: '#2563eb' },
    { id: 'green',  label: 'Green',  hex: '#16a34a' },
    { id: 'slate',  label: 'Slate',  hex: '#475569' }
  ];

  /* ---------- Seed --------------------------------------------------
     Six services chosen to exercise every branch of the availability
     rule: one plain (staff + chair), one with processing time, one that
     needs two staff and a capacity-2 room, one that needs a room AND a
     named machine, one that needs nothing physical, and one draft. */

  function seed() {
    var categories = [
      { id: 'c1', name: 'Hair',      description: 'Cutting, colour and finishing.',  color: 'violet', order: 1, active: true },
      { id: 'c2', name: 'Barbering', description: 'Cuts, beards and wet shaves.',    color: 'slate',  order: 2, active: true },
      { id: 'c3', name: 'Massage',   description: 'Bodywork and couples treatments.', color: 'teal',  order: 3, active: true },
      { id: 'c4', name: 'Skin',      description: 'Facials and med-spa treatments.',  color: 'rose',  order: 4, active: true },
      { id: 'c5', name: 'Nails',     description: 'Manicures and pedicures.',        color: 'amber',  order: 5, active: true },
      { id: 'c6', name: 'Wellness',  description: 'Consultations and assessments.',  color: 'green',  order: 6, active: true }
    ];

    var addons = [
      { id: 'a1', name: 'Aromatherapy',      description: 'Essential oil blend chosen on arrival.',
        price: 15, durationMin: 0,  requiresResource: false, typeId: '', staffIds: [], locationIds: [],
        onlineEligible: true, active: true },
      { id: 'a2', name: 'Hot stones',        description: 'Heated basalt stones through the back work.',
        price: 20, durationMin: 15, requiresResource: false, typeId: '', staffIds: ['u3'], locationIds: ['loc2'],
        onlineEligible: true, active: true },
      { id: 'a3', name: 'CBD balm',          description: 'Topical CBD on request.',
        price: 25, durationMin: 0,  requiresResource: false, typeId: '', staffIds: [], locationIds: ['loc2'],
        onlineEligible: true, active: true },
      { id: 'a4', name: 'Extra 30 minutes',  description: 'Extends the treatment. Holds the room longer.',
        price: 40, durationMin: 30, requiresResource: false, typeId: '', staffIds: [], locationIds: [],
        onlineEligible: true, active: true },
      { id: 'a5', name: 'Deep conditioning', description: 'Mask and steam before the blow-dry.',
        price: 18, durationMin: 15, requiresResource: false, typeId: '', staffIds: [], locationIds: [],
        onlineEligible: true, active: true },
      { id: 'a6', name: 'LED light therapy', description: 'Ten minutes under the LED panel.',
        price: 30, durationMin: 10, requiresResource: true, typeId: 't7', staffIds: [], locationIds: ['loc2'],
        onlineEligible: false, active: true }
    ];

    return { categories: categories, addons: addons, services: seedServices(), audit: [] };
  }

  function blank() {
    return {
      id: '', name: '', categoryId: '', code: '', shortDescription: '', description: '',
      color: 'violet', status: 'draft',

      pricingType: 'fixed', price: 0, taxable: true, taxCategory: TAX_CATEGORIES[0],
      allowStaffPrice: false, allowLocationPrice: false,

      durationMin: 60, processingMin: 0, finishMin: 0, cleanupMin: 0,
      bufferBeforeMin: 0, bufferAfterMin: 0,

      staffNeeded: 1,
      staff: [],          /* {staffId, eligible, price, durationMin, commission, skill, online} */

      allLocations: true,
      locations: [],      /* {locationId, enabled, price, durationMin} */

      requiresResource: false,
      requirements: [],   /* {id, mode:'type'|'specific', typeId, resourceId, quantity,
                             minCapacity, autoAssign, allowOverride,
                             bufferBeforeMin, bufferAfterMin} */

      online: {
        enabled: false, name: '', description: '', displayOrder: 0,
        allowNew: true, allowExisting: true,
        minAdvanceHours: 2, maxWindowDays: 60,
        requireDeposit: false, depositType: 'percent', depositAmount: 0,
        requireCard: false, cancellationPolicy: ''
      },

      addonIds: [],

      clientRequirements: {},   /* id -> {required, before} */
      minAge: '',
      customQuestions: [],

      commission: { enabled: false, type: 'percent', value: 0, staffOverride: false, addons: false },

      cost: { product: 0, labor: 0, consumables: 0, other: 0 },

      instructions: {}          /* id -> text */
    };
  }

  function make(over) {
    return Object.assign(blank(), over);
  }

  function req(over) {
    return Object.assign({
      id: 'q1', mode: 'type', typeId: '', resourceId: '', quantity: 1, minCapacity: 1,
      autoAssign: true, allowOverride: true, bufferBeforeMin: 0, bufferAfterMin: 0
    }, over);
  }

  function staffRow(id, over) {
    var s = SD.staffById(id) || {};
    return Object.assign({
      staffId: id, eligible: true, price: '', durationMin: '', commission: '',
      skill: s.skill || 'Standard', online: true
    }, over);
  }

  function seedServices() {
    return [
      /* Staff + chair. The everyday case. */
      make({
        id: 's1', name: "Women's Haircut", categoryId: 'c1', code: 'HAIR-WC',
        shortDescription: 'Consultation, cut and finish.',
        description: 'A full consultation, wash, cut and blow-dry finish.',
        color: 'violet', status: 'active',
        pricingType: 'fixed', price: 65, allowStaffPrice: true, allowLocationPrice: true,
        durationMin: 45, cleanupMin: 10, bufferAfterMin: 5,
        /* §22 — the same service is not the same price or length in
           every pair of hands, and the override beats the default. */
        staff: [staffRow('u1', { price: 75, durationMin: 45 }),
                staffRow('u2', { price: 85, durationMin: 60 }),
                staffRow('u5', { eligible: false })],
        allLocations: false,
        locations: [{ locationId: 'loc1', enabled: true, price: '', durationMin: '' },
                    { locationId: 'loc3', enabled: true, price: 55, durationMin: '' }],
        requiresResource: true,
        requirements: [req({ id: 'q1', mode: 'type', typeId: 't1', quantity: 1 })],
        online: Object.assign(blank().online, {
          enabled: true, name: "Women's Haircut", displayOrder: 1,
          minAdvanceHours: 2, maxWindowDays: 60,
          cancellationPolicy: 'Free up to 24 hours before. After that the deposit is kept.'
        }),
        addonIds: ['a5'],
        clientRequirements: { consultation: { required: false, before: false } },
        commission: { enabled: true, type: 'percent', value: 35, staffOverride: true, addons: true },
        cost: { product: 4, labor: 22, consumables: 2, other: 0 },
        instructions: {
          confirmation: 'Please arrive with dry, brushed hair.',
          aftercare: 'Wait 48 hours before the first wash to let the shape settle.'
        }
      }),

      /* Processing time — the chair is held throughout, the colourist is
         not. That distinction is the whole reason the field exists. */
      make({
        id: 's2', name: 'Full Head Colour', categoryId: 'c1', code: 'HAIR-FC',
        shortDescription: 'Single-process colour, roots to ends.',
        description: 'Application, development, then a cut-in finish and blow-dry.',
        color: 'violet', status: 'active',
        pricingType: 'from', price: 120,
        durationMin: 30, processingMin: 45, finishMin: 30, cleanupMin: 15, bufferAfterMin: 5,
        staff: [staffRow('u2', { price: 140 }), staffRow('u1')],
        allLocations: false,
        locations: [{ locationId: 'loc1', enabled: true, price: '', durationMin: '' }],
        requiresResource: true,
        requirements: [req({ id: 'q1', mode: 'type', typeId: 't2', quantity: 1 })],
        online: Object.assign(blank().online, {
          enabled: true, name: 'Full Head Colour', displayOrder: 2,
          minAdvanceHours: 48, maxWindowDays: 90,
          requireDeposit: true, depositType: 'percent', depositAmount: 25,
          cancellationPolicy: 'A patch test at least 48 hours before is required for new clients.'
        }),
        addonIds: ['a5'],
        clientRequirements: {
          patchtest: { required: true, before: true },
          consent: { required: true, before: true }
        },
        commission: { enabled: true, type: 'percent', value: 30, staffOverride: false, addons: true },
        cost: { product: 26, labor: 48, consumables: 4, other: 0 },
        instructions: {
          preparation: 'Come with unwashed hair — a day or two of natural oil protects the scalp.',
          aftercare: 'Colour-safe shampoo only for the first two weeks.'
        }
      }),

      /* Two therapists and one room that holds two. */
      make({
        id: 's3', name: 'Couples Massage', categoryId: 'c3', code: 'MASS-CPL',
        shortDescription: '60 minutes, side by side.',
        description: 'Two therapists, two tables, one room. Booked as a single appointment.',
        color: 'teal', status: 'active',
        pricingType: 'fixed', price: 220,
        durationMin: 60, cleanupMin: 20, bufferBeforeMin: 10,
        staffNeeded: 2,
        staff: [staffRow('u3'), staffRow('u6'), staffRow('u4', { eligible: false })],
        allLocations: false,
        locations: [{ locationId: 'loc2', enabled: true, price: '', durationMin: '' }],
        requiresResource: true,
        requirements: [req({ id: 'q1', mode: 'type', typeId: 't6', quantity: 1, minCapacity: 2 })],
        online: Object.assign(blank().online, {
          enabled: true, name: 'Couples Massage', displayOrder: 1,
          minAdvanceHours: 24, maxWindowDays: 90,
          requireDeposit: true, depositType: 'fixed', depositAmount: 50, requireCard: true,
          cancellationPolicy: 'Two therapists are held for this booking. 48 hours notice, please.'
        }),
        addonIds: ['a1', 'a2', 'a3', 'a4'],
        clientRequirements: {
          intake: { required: true, before: true },
          waiver: { required: true, before: false }
        },
        commission: { enabled: true, type: 'percent', value: 25, staffOverride: false, addons: true },
        cost: { product: 8, labor: 90, consumables: 6, other: 0 },
        instructions: {
          arrival: 'Arrive 15 minutes early to change and settle.',
          aftercare: 'Drink water and take the rest of the day gently.'
        }
      }),

      /* A room AND a named machine — §12's "every required resource". */
      make({
        id: 's4', name: 'Laser Facial', categoryId: 'c4', code: 'SKIN-LSR',
        shortDescription: 'Fractional resurfacing, 45 minutes.',
        description: 'Numbing, treatment and cool-down. The laser moves between rooms, so it is booked in its own right.',
        color: 'rose', status: 'active',
        pricingType: 'fixed', price: 340, taxable: true,
        durationMin: 45, cleanupMin: 20, bufferBeforeMin: 15, bufferAfterMin: 10,
        staff: [staffRow('u4', { skill: 'Master' })],
        allLocations: false,
        locations: [{ locationId: 'loc2', enabled: true, price: '', durationMin: '' }],
        requiresResource: true,
        requirements: [
          req({ id: 'q1', mode: 'type', typeId: 't7', quantity: 1 }),
          /* §13 — one specific machine, not any of its type. */
          req({ id: 'q2', mode: 'specific', resourceId: 'r15', quantity: 1, autoAssign: false })
        ],
        online: Object.assign(blank().online, {
          enabled: false,
          cancellationPolicy: 'Consultation required before the first treatment.'
        }),
        addonIds: ['a6'],
        clientRequirements: {
          consultation: { required: true, before: true },
          consent: { required: true, before: true },
          intake: { required: true, before: true },
          card: { required: true, before: true }
        },
        minAge: 18,
        commission: { enabled: true, type: 'fixed', value: 40, staffOverride: false, addons: false },
        cost: { product: 32, labor: 70, consumables: 18, other: 12 },
        instructions: {
          preparation: 'No sun exposure, retinoids or waxing for two weeks beforehand.',
          aftercare: 'SPF 50 daily for four weeks. Expect redness for 24–48 hours.'
        }
      }),

      /* Needs no room at all — proves the engine is not assuming one. */
      make({
        id: 's5', name: 'Skin Consultation', categoryId: 'c6', code: 'WELL-CON',
        shortDescription: 'Twenty minutes, no charge.',
        description: 'A short sit-down to plan a course of treatment.',
        color: 'green', status: 'active',
        pricingType: 'free', taxable: false,
        durationMin: 20,
        staff: [staffRow('u4'), staffRow('u6')],
        allLocations: false,
        locations: [{ locationId: 'loc2', enabled: true, price: '', durationMin: '' }],
        requiresResource: false,
        online: Object.assign(blank().online, {
          enabled: true, name: 'Free Skin Consultation', displayOrder: 3,
          minAdvanceHours: 1, maxWindowDays: 30
        }),
        commission: { enabled: false, type: 'percent', value: 0, staffOverride: false, addons: false }
      }),

      /* §20 — a draft is not bookable however complete it looks. */
      make({
        id: 's6', name: 'Hot Stone Massage', categoryId: 'c3', code: 'MASS-HS',
        shortDescription: '75 minutes with heated stones.',
        color: 'teal', status: 'draft',
        pricingType: 'fixed', price: 145,
        durationMin: 75, cleanupMin: 15,
        staff: [staffRow('u3')],
        allLocations: false,
        locations: [{ locationId: 'loc2', enabled: true, price: '', durationMin: '' }],
        requiresResource: true,
        requirements: [req({ id: 'q1', mode: 'type', typeId: 't5', quantity: 1 })],
        addonIds: ['a1']
      }),

      /* §20 — archived stays out of the normal list. */
      make({
        id: 's7', name: 'Paraffin Hand Treatment', categoryId: 'c5', code: 'NAIL-PAR',
        shortDescription: 'Retired March 2026.',
        color: 'amber', status: 'archived',
        pricingType: 'fixed', price: 28,
        durationMin: 25,
        staff: [staffRow('u7')],
        allLocations: false,
        locations: [{ locationId: 'loc1', enabled: true, price: '', durationMin: '' }],
        requiresResource: true,
        requirements: [req({ id: 'q1', mode: 'type', typeId: 't4', quantity: 1 })]
      })
    ];
  }

  /* ---------- Store ------------------------------------------------- */

  var memory = null;

  function read() {
    if (memory) return memory;
    try {
      var raw = window.localStorage.getItem(KEY);
      memory = raw ? JSON.parse(raw) : seed();
    } catch (e) {
      memory = seed();
    }
    /* An older shape is upgraded rather than thrown away — every service
       is merged onto a current blank so a field added later reads as its
       default instead of undefined. */
    memory.services = (memory.services || []).map(function (s) { return Object.assign(blank(), s); });
    memory.categories = memory.categories || [];
    memory.addons = memory.addons || [];
    memory.audit = memory.audit || [];
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

  function nextId(list, prefix) {
    var max = 0;
    list.forEach(function (row) {
      var n = parseInt(String(row.id).replace(/\D/g, ''), 10);
      if (!isNaN(n) && n > max) max = n;
    });
    return prefix + (max + 1);
  }

  /* Same trap as the resource store: a caller sharing one payload
     between create and update passes `id: undefined`, and Object.assign
     would copy that straight over a freshly minted id. */
  function withoutBlankId(data) {
    var out = {}, k;
    for (k in data) {
      if (!Object.prototype.hasOwnProperty.call(data, k)) continue;
      if (k === 'id' && (data.id === undefined || data.id === null || data.id === '')) continue;
      out[k] = data[k];
    }
    return out;
  }

  /* ---------- Categories -------------------------------------------- */

  function categories() {
    return read().categories.slice().sort(function (a, b) {
      return (a.order || 0) - (b.order || 0) || a.name.localeCompare(b.name);
    });
  }

  function categoryById(id) {
    var hit = read().categories.filter(function (c) { return c.id === id; });
    return hit.length ? hit[0] : null;
  }

  function categoryName(id) {
    var c = categoryById(id);
    return c ? c.name : '';
  }

  function categoryUsage(id) {
    return read().services.filter(function (s) { return s.categoryId === id; }).length;
  }

  function saveCategory(data) {
    var s = read();
    if (data.id && categoryById(data.id)) {
      s.categories = s.categories.map(function (c) {
        return c.id === data.id ? Object.assign({}, c, data) : c;
      });
    } else {
      s.categories.push(Object.assign({
        id: nextId(s.categories, 'c'), name: '', description: '',
        color: 'violet', order: s.categories.length + 1, active: true
      }, withoutBlankId(data)));
    }
    write(s);
    return categoryById(data.id) || s.categories[s.categories.length - 1];
  }

  function deleteCategory(id) {
    if (categoryUsage(id) > 0) return false;
    var s = read();
    s.categories = s.categories.filter(function (c) { return c.id !== id; });
    write(s);
    return true;
  }

  /* ---------- Add-ons ------------------------------------------------ */

  function addons() { return read().addons.slice(); }

  function addonById(id) {
    var hit = read().addons.filter(function (a) { return a.id === id; });
    return hit.length ? hit[0] : null;
  }

  function addonUsage(id) {
    return read().services.filter(function (s) { return (s.addonIds || []).indexOf(id) !== -1; });
  }

  function saveAddon(data) {
    var s = read();
    if (data.id && addonById(data.id)) {
      s.addons = s.addons.map(function (a) { return a.id === data.id ? Object.assign({}, a, data) : a; });
    } else {
      s.addons.push(Object.assign({
        id: nextId(s.addons, 'a'), name: '', description: '', price: 0, durationMin: 0,
        requiresResource: false, typeId: '', staffIds: [], locationIds: [],
        onlineEligible: true, active: true
      }, withoutBlankId(data)));
    }
    write(s);
    return addonById(data.id) || s.addons[s.addons.length - 1];
  }

  function deleteAddon(id) {
    var s = read();
    s.addons = s.addons.filter(function (a) { return a.id !== id; });
    /* An add-on removed from the library is removed from the services
       that offered it, rather than left as a dangling id. */
    s.services = s.services.map(function (svc) {
      svc.addonIds = (svc.addonIds || []).filter(function (x) { return x !== id; });
      return svc;
    });
    write(s);
  }

  /* ---------- Services ----------------------------------------------- */

  function all() { return read().services.slice(); }

  /* §20 — archived is out of the normal list. Everything that wants the
     everyday view calls this; the directory opts back in with a filter. */
  function live() {
    return all().filter(function (s) { return s.status !== 'archived'; });
  }

  function byId(id) {
    var hit = read().services.filter(function (s) { return s.id === id; });
    return hit.length ? hit[0] : null;
  }

  function save(data, actor) {
    var s = read();
    var existing = data.id ? byId(data.id) : null;

    if (existing) {
      var changes = diff(existing, data);
      s.services = s.services.map(function (x) {
        return x.id === data.id ? Object.assign({}, x, data) : x;
      });
      write(s);
      changes.forEach(function (c) { log(c.event, data.id, c.from, c.to, actor); });
      return byId(data.id);
    }

    var row = Object.assign(blank(), withoutBlankId(data), { id: nextId(s.services, 's') });
    s.services.unshift(row);
    write(s);
    log('Service created', row.id, '', row.name, actor);
    return row;
  }

  function setStatus(id, status, actor) {
    var svc = byId(id);
    if (!svc) return null;
    var from = svc.status;
    if (from === status) return svc;
    var s = read();
    s.services = s.services.map(function (x) {
      return x.id === id ? Object.assign({}, x, { status: status }) : x;
    });
    write(s);
    log(status === 'archived' ? 'Service archived' : 'Status changed', id, statusLabel(from), statusLabel(status), actor);
    return byId(id);
  }

  function remove(id) {
    var s = read();
    s.services = s.services.filter(function (x) { return x.id !== id; });
    s.audit = s.audit.filter(function (a) { return a.serviceId !== id; });
    write(s);
  }

  /* §21 — a duplicate copies everything and lands as a Draft, because a
     copy is never finished: at minimum the name is wrong. */
  function duplicate(id, actor) {
    var src = byId(id);
    if (!src) return null;
    var s = read();
    var copy = JSON.parse(JSON.stringify(src));
    copy.id = nextId(s.services, 's');
    copy.name = uniqueName(src.name + ' (copy)');
    copy.code = src.code ? src.code + '-2' : '';
    copy.status = 'draft';
    copy.online = Object.assign({}, copy.online, { enabled: false, displayOrder: 0 });
    s.services.unshift(copy);
    write(s);
    log('Service created', copy.id, '', 'Duplicated from ' + src.name, actor);
    return copy;
  }

  function uniqueName(base) {
    var taken = {};
    all().forEach(function (s) { taken[s.name.toLowerCase()] = true; });
    if (!taken[base.toLowerCase()]) return base;
    var n = 2;
    while (taken[(base + ' ' + n).toLowerCase()]) n++;
    return base + ' ' + n;
  }

  /* ---------- Audit (§28) -------------------------------------------- */

  /* Only fields whose change someone would later have to account for.
     Logging every keystroke would bury the price change that matters. */
  var TRACKED = [
    { key: 'name',           event: 'Service updated',           show: function (v) { return v; } },
    { key: 'price',          event: 'Price changed',             show: money },
    { key: 'pricingType',    event: 'Price changed',             show: function (v) { return pricingLabel(v); } },
    { key: 'durationMin',    event: 'Duration changed',          show: function (v) { return v + ' min'; } },
    { key: 'categoryId',     event: 'Service updated',           show: categoryName },
    { key: 'status',         event: 'Status changed',            show: statusLabel }
  ];

  function diff(before, after) {
    var out = [];

    TRACKED.forEach(function (f) {
      if (!(f.key in after)) return;
      if (String(before[f.key]) === String(after[f.key])) return;
      out.push({ event: f.event, from: f.show(before[f.key]), to: f.show(after[f.key]) });
    });

    if ('staff' in after) {
      var was = eligibleIds(before), now = eligibleIds(after);
      added(was, now).forEach(function (id) {
        out.push({ event: 'Staff added', from: '', to: SD.staffName(id) });
      });
      added(now, was).forEach(function (id) {
        out.push({ event: 'Staff removed', from: SD.staffName(id), to: '' });
      });
    }

    if ('locations' in after || 'allLocations' in after) {
      var lWas = locationIds(before), lNow = locationIds(after);
      added(lWas, lNow).forEach(function (id) {
        out.push({ event: 'Location added', from: '', to: SDR.locationName(id) });
      });
      added(lNow, lWas).forEach(function (id) {
        out.push({ event: 'Location removed', from: SDR.locationName(id), to: '' });
      });
    }

    if ('requirements' in after || 'requiresResource' in after) {
      var rWas = requirementText(before), rNow = requirementText(after);
      if (rWas !== rNow) {
        out.push({ event: 'Resource requirement changed', from: rWas || 'None', to: rNow || 'None' });
      }
    }

    if ('online' in after && before.online.enabled !== after.online.enabled) {
      out.push({
        event: after.online.enabled ? 'Online booking enabled' : 'Online booking disabled',
        from: before.online.enabled ? 'On' : 'Off',
        to: after.online.enabled ? 'On' : 'Off'
      });
    }

    return out;
  }

  function added(from, to) {
    return to.filter(function (x) { return from.indexOf(x) === -1; });
  }

  function log(event, serviceId, from, to, actor) {
    var s = read();
    s.audit.unshift({
      id: 'log' + (s.audit.length + 1),
      serviceId: serviceId,
      event: event,
      from: from == null ? '' : String(from),
      to: to == null ? '' : String(to),
      actor: actor || 'You',
      at: new Date().toISOString()
    });
    s.audit = s.audit.slice(0, 400);
    write(s);
  }

  function auditFor(serviceId) {
    return read().audit.filter(function (a) { return a.serviceId === serviceId; });
  }

  /* ---------- Derived views ------------------------------------------ */

  function eligibleIds(svc) {
    return (svc.staff || []).filter(function (r) { return r.eligible; })
      .map(function (r) { return r.staffId; });
  }

  /* Where this service can actually be booked. A site the business has
     closed offers nothing, however the service is configured — so the
     answer is filtered against the open list rather than read straight
     off the record. The record itself is left alone: reopening the site
     should bring the service back without anyone re-ticking it. */
  function locationIds(svc) {
    var open = SDR.LOCATIONS.map(function (l) { return l.id; });
    if (svc.allLocations) return open;
    return (svc.locations || [])
      .filter(function (l) { return l.enabled && open.indexOf(l.locationId) !== -1; })
      .map(function (l) { return l.locationId; });
  }

  /* What the service is configured for, closed sites included. The
     edit form needs this — otherwise saving a service while one site is
     shut would quietly drop it from that site for good. */
  function configuredLocationIds(svc) {
    if (svc.allLocations) return SDL.all().map(function (l) { return l.id; });
    return (svc.locations || []).filter(function (l) { return l.enabled; })
      .map(function (l) { return l.locationId; });
  }

  /* Staff eligible for the service AND actually based at that location.
     A stylist who works downtown cannot staff a booking at the airport,
     however eligible for the service she is. */
  /* Eligible for the service, based at that location, AND still on the
     bookable roster. `SD.staffById` resolves someone who has left —
     it has to, so history keeps their name — so eligibility has to be
     checked against the current roster rather than against whether the
     person can be looked up at all. */
  function staffAt(svc, locationId) {
    var roster = {};
    SD.STAFF.forEach(function (p) { roster[p.id] = p; });
    return eligibleIds(svc).filter(function (id) {
      var s = roster[id];
      return s && (!locationId || s.locations.indexOf(locationId) !== -1);
    });
  }

  /* §22/§23 — a staff override beats a location override beats the
     service default. Staff is most specific, so it wins. */
  function priceFor(svc, options) {
    options = options || {};
    if (svc.pricingType === 'free') return 0;

    if (options.staffId && svc.allowStaffPrice) {
      var row = (svc.staff || []).filter(function (r) { return r.staffId === options.staffId; })[0];
      if (row && row.price !== '' && row.price != null) return Number(row.price);
    }
    if (options.locationId && svc.allowLocationPrice) {
      var loc = (svc.locations || []).filter(function (l) { return l.locationId === options.locationId; })[0];
      if (loc && loc.price !== '' && loc.price != null) return Number(loc.price);
    }
    return Number(svc.price) || 0;
  }

  function durationFor(svc, options) {
    options = options || {};
    if (options.staffId) {
      var row = (svc.staff || []).filter(function (r) { return r.staffId === options.staffId; })[0];
      if (row && row.durationMin !== '' && row.durationMin != null) return Number(row.durationMin);
    }
    if (options.locationId) {
      var loc = (svc.locations || []).filter(function (l) { return l.locationId === options.locationId; })[0];
      if (loc && loc.durationMin !== '' && loc.durationMin != null) return Number(loc.durationMin);
    }
    return Number(svc.durationMin) || 0;
  }

  /* §7 — what the calendar actually loses. Processing time is inside the
     appointment, not added to it: the client sits there developing while
     the stylist starts someone else. Buffers sit outside it. */
  function serviceMinutes(svc, options) {
    return durationFor(svc, options) +
           (Number(svc.processingMin) || 0) +
           (Number(svc.finishMin) || 0);
  }

  function blockingMinutes(svc, options) {
    return (Number(svc.bufferBeforeMin) || 0) +
           serviceMinutes(svc, options) +
           (Number(svc.cleanupMin) || 0) +
           (Number(svc.bufferAfterMin) || 0);
  }

  function addonsFor(svc) {
    return (svc.addonIds || []).map(addonById).filter(Boolean);
  }

  function requirementText(svc) {
    if (!svc.requiresResource || !(svc.requirements || []).length) return '';
    return svc.requirements.map(function (r) {
      var name = r.mode === 'specific'
        ? ((SDR.resourceById(r.resourceId) || {}).name || 'a specific resource')
        : (SDR.typeName(r.typeId) || 'a resource');
      var q = Number(r.quantity) > 1 ? Number(r.quantity) + '× ' : '';
      var cap = Number(r.minCapacity) > 1 ? ' (holds ' + r.minCapacity + '+)' : '';
      return q + name + cap;
    }).join(' + ');
  }

  /* The one-line answer to "what has to line up for this to be
     bookable" — §24 read back as a sentence. */
  function ruleText(svc, locationId) {
    var parts = [];
    var n = Number(svc.staffNeeded) || 1;
    parts.push(n > 1 ? n + ' eligible staff' : '1 eligible staff');
    if (locationId) parts.push('at ' + SDR.locationName(locationId));
    var r = requirementText(svc);
    if (r) parts.push(r);
    return parts.join(' + ');
  }

  /* ---------- The availability bridge (§24) --------------------------
     Turns a service's own configuration into the requirement shape the
     resource engine already speaks, then hands it over. Nothing about
     couples rooms or laser machines is special-cased — the difference
     lives entirely in the data. */

  function requirementsFor(svc, locationId) {
    if (!svc.requiresResource) return [];
    return (svc.requirements || []).map(function (r) {
      return {
        typeId: r.mode === 'type' ? r.typeId : '',
        resourceId: r.mode === 'specific' ? r.resourceId : '',
        quantity: Number(r.quantity) || 1,
        minCapacity: Number(r.minCapacity) || 1,
        locationId: locationId || ''
      };
    });
  }

  function slotOptions(svc, options) {
    options = options || {};
    var locationId = options.locationId || locationIds(svc)[0] || '';
    var staff = options.staffId ? [options.staffId] : staffAt(svc, locationId);

    return {
      date: options.date || SDR.today(1),
      /* `start` has to travel with the rest: SDR.checkSlot reads it
         directly, and without it every window becomes NaN and every
         resource reports itself as outside its hours. daySlots overwrites
         this per slot, which is why the bug only showed on single checks. */
      start: options.start || '09:00',
      locationId: locationId,
      durationMin: serviceMinutes(svc, { locationId: locationId, staffId: options.staffId }),
      requirements: requirementsFor(svc, locationId),
      staffIds: staff,
      staffNeeded: Number(svc.staffNeeded) || 1
    };
  }

  /* A status check the resource engine cannot make: a draft, inactive or
     archived service has no slots however free the room is. */
  function bookableStatus(svc) {
    var meta = statusMeta(svc.status);
    if (svc.status === 'active') return { ok: true };
    return { ok: false, reason: meta.label + ' — ' + meta.note };
  }

  function checkSlot(svc, options) {
    var gate = bookableStatus(svc);
    if (!gate.ok) {
      return { ok: false, assigned: [], staff: [], failures: [{ kind: 'status', detail: gate.reason }] };
    }
    return SDR.checkSlot(slotOptions(svc, options));
  }

  function daySlots(svc, options) {
    options = options || {};
    var base = slotOptions(svc, options);
    var gate = bookableStatus(svc);

    var rows = SDR.daySlots(Object.assign({}, base, {
      from: options.from || '09:00',
      to: options.to || '19:00',
      stepMin: options.stepMin || 30
    }));

    if (gate.ok) return rows;
    return rows.map(function (row) {
      return Object.assign({}, row, {
        result: { ok: false, assigned: [], staff: [], failures: [{ kind: 'status', detail: gate.reason }] }
      });
    });
  }

  /* ---------- Validation (§26) ---------------------------------------
     Two tiers, deliberately. A draft is a place to leave unfinished
     work, so only what the record cannot exist without is enforced.
     Activating is the promise that it is bookable, and that is where the
     rest applies — a service with no eligible staff would otherwise go
     live and simply never show a slot. */

  function validate(svc, options) {
    options = options || {};
    var forStatus = options.status || svc.status;
    var errors = [];

    function fail(field, message) { errors.push({ field: field, message: message }); }

    if (!String(svc.name || '').trim()) fail('name', 'Give the service a name.');
    if (!svc.categoryId) fail('categoryId', 'Choose a category.');

    var dur = Number(svc.durationMin);
    if (!(dur > 0)) fail('durationMin', 'Duration has to be more than zero.');

    var pricing = pricingMeta(svc.pricingType);
    if (pricing.needsPrice && !(Number(svc.price) > 0)) {
      fail('price', pricing.label + ' needs a price above zero.');
    }

    var dupe = all().filter(function (x) {
      return x.id !== svc.id && x.name.trim().toLowerCase() === String(svc.name || '').trim().toLowerCase();
    });
    if (dupe.length) fail('name', 'Another service already has this name.');

    if (svc.requiresResource) {
      (svc.requirements || []).forEach(function (r, i) {
        if (r.mode === 'type' && !r.typeId) fail('requirement-' + i, 'Choose a resource type, or remove this requirement.');
        if (r.mode === 'specific' && !r.resourceId) fail('requirement-' + i, 'Choose the specific resource, or remove this requirement.');
        if (!(Number(r.quantity) > 0)) fail('requirement-' + i, 'Quantity has to be at least 1.');
      });
      if (!(svc.requirements || []).length) {
        fail('requirements', 'Resources are required but none are listed. Add one, or turn the requirement off.');
      }
    }

    if (svc.online.requireDeposit && !(Number(svc.online.depositAmount) > 0)) {
      fail('depositAmount', 'A deposit is required but no amount is set.');
    }
    if (svc.online.requireDeposit && svc.online.depositType === 'percent' && Number(svc.online.depositAmount) > 100) {
      fail('depositAmount', 'A percentage deposit cannot be over 100%.');
    }
    if (svc.commission.enabled && !(Number(svc.commission.value) > 0)) {
      fail('commissionValue', 'Commission is on but the value is zero.');
    }
    if (svc.commission.enabled && svc.commission.type === 'percent' && Number(svc.commission.value) > 100) {
      fail('commissionValue', 'A percentage commission cannot be over 100%.');
    }

    /* §26 — online booking on a service that is not active. Checked
       against the status being saved, not the one on disk, and before the
       draft tier returns: a draft saved with it on would start taking
       bookings the moment someone flipped the status. */
    if (svc.online.enabled && forStatus !== 'active') {
      fail('onlineEnabled', 'Online booking cannot be on for a ' + statusLabel(forStatus).toLowerCase() + ' service.');
    }

    /* Draft stops here. */
    if (forStatus === 'draft') return errors;

    if (!locationIds(svc).length) {
      fail('locations', 'Choose at least one location before activating.');
    }

    var eligible = eligibleIds(svc);
    if (!eligible.length) {
      fail('staff', 'No one is eligible yet. A service with no staff can never offer a slot.');
    }

    /* §26 — a two-therapist service with one eligible therapist is the
       failure that looks fine on the form and produces an empty booking
       page. It is caught here, with the numbers named. */
    var need = Number(svc.staffNeeded) || 1;
    if (need > 1 && eligible.length < need) {
      fail('staffNeeded', 'This needs ' + need + ' staff at once but only ' +
        eligible.length + ' ' + (eligible.length === 1 ? 'person is' : 'people are') + ' eligible.');
    }

    /* And the same check per location — enough eligible people in total
       means nothing if they are all at the other site. Skipped when
       nobody is eligible anywhere: that error is already above, and
       repeating it once per site buries everything else. */
    if (eligible.length) locationIds(svc).forEach(function (locId) {
      var here = staffAt(svc, locId);
      if (!here.length) {
        fail('locations', 'No eligible staff work at ' + SDR.locationName(locId) + '.');
      } else if (need > 1 && here.length < need) {
        fail('locations', SDR.locationName(locId) + ' has only ' + here.length +
          ' of the ' + need + ' eligible staff this needs.');
      }
    });

    /* A requirement that no resource anywhere can satisfy is a dead
       service, and the form is the last place to notice cheaply. */
    if (svc.requiresResource) {
      (svc.requirements || []).forEach(function (r, i) {
        /* A requirement that names nothing yet already has its own error.
           Asking whether the empty type exists anywhere produces
           " does not exist at Downtown Salon", which is worse than
           silence. */
        var named = r.mode === 'specific' ? r.resourceId : r.typeId;
        if (!named) return;

        locationIds(svc).forEach(function (locId) {
          var pool = SDR.resources().filter(function (res) {
            if (res.locationId !== locId) return false;
            if (r.mode === 'specific') return res.id === r.resourceId;
            return res.typeId === r.typeId && (res.capacity || 1) >= (Number(r.minCapacity) || 1);
          });
          if (!pool.length) {
            var what = r.mode === 'specific'
              ? ((SDR.resourceById(r.resourceId) || {}).name || 'That resource')
              : SDR.typeName(r.typeId) + (Number(r.minCapacity) > 1 ? ' holding ' + r.minCapacity + '+' : '');
            fail('requirement-' + i, what + ' does not exist at ' + SDR.locationName(locId) + '.');
          } else if (pool.length < (Number(r.quantity) || 1)) {
            fail('requirement-' + i, SDR.locationName(locId) + ' has ' + pool.length + ' of the ' +
              r.quantity + ' ' + SDR.typeName(r.typeId) + ' this needs.');
          }
        });
      });
    }

    return errors;
  }

  /* ---------- Query --------------------------------------------------- */

  function search(list, term) {
    var q = String(term || '').trim().toLowerCase();
    if (!q) return list;
    return list.filter(function (s) {
      return [s.name, s.code, s.shortDescription, categoryName(s.categoryId), s.online.name]
        .some(function (v) { return String(v || '').toLowerCase().indexOf(q) !== -1; });
    });
  }

  function applyFilters(list, f) {
    f = f || {};
    return list.filter(function (s) {
      if (f.category && f.category.length && f.category.indexOf(s.categoryId) === -1) return false;
      if (f.status && f.status.length && f.status.indexOf(s.status) === -1) return false;
      if (f.location && f.location.length) {
        var ids = locationIds(s);
        if (!f.location.some(function (l) { return ids.indexOf(l) !== -1; })) return false;
      }
      if (f.online && f.online.length) {
        var on = s.online.enabled ? 'on' : 'off';
        if (f.online.indexOf(on) === -1) return false;
      }
      if (f.staff && f.staff.length) {
        var e = eligibleIds(s);
        if (!f.staff.some(function (id) { return e.indexOf(id) !== -1; })) return false;
      }
      return true;
    });
  }

  function sortRows(list, key, dir) {
    var sign = dir === 'desc' ? -1 : 1;
    return list.slice().sort(function (a, b) {
      if (key === 'price') return (Number(a.price) - Number(b.price)) * sign;
      if (key === 'duration') return (Number(a.durationMin) - Number(b.durationMin)) * sign;
      if (key === 'category') {
        var c = categoryName(a.categoryId).localeCompare(categoryName(b.categoryId));
        return (c || a.name.localeCompare(b.name)) * sign;
      }
      return a.name.localeCompare(b.name) * sign;
    });
  }

  /* ---------- Formatting ---------------------------------------------- */

  function statusMeta(id) {
    return STATUSES.filter(function (s) { return s.id === id; })[0] || STATUSES[0];
  }
  function statusLabel(id) { return statusMeta(id).label; }

  function pricingMeta(id) {
    return PRICING_TYPES.filter(function (p) { return p.id === id; })[0] || PRICING_TYPES[0];
  }
  function pricingLabel(id) { return pricingMeta(id).label; }

  function money(v) {
    var n = Number(v) || 0;
    return '$' + n.toFixed(n % 1 === 0 ? 0 : 2);
  }

  /* What the client sees where a price would go. "Free" and "Price on
     consultation" are prices too — an empty cell is not. */
  function priceText(svc) {
    if (svc.pricingType === 'free') return 'Free';
    if (svc.pricingType === 'variable') return 'On consultation';
    if (svc.pricingType === 'staff') return 'Set by staff';
    if (svc.pricingType === 'from') return 'From ' + money(svc.price);
    return money(svc.price);
  }

  function durationText(mins) {
    var n = Number(mins) || 0;
    if (n < 60) return n + ' min';
    var h = Math.floor(n / 60), m = n % 60;
    return h + 'h' + (m ? ' ' + m + 'm' : '');
  }

  function colorHex(id) {
    var hit = COLORS.filter(function (c) { return c.id === id; });
    return hit.length ? hit[0].hex : COLORS[0].hex;
  }

  function esc(v) {
    return String(v == null ? '' : v)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
  }

  return {
    STATUSES: STATUSES, PRICING_TYPES: PRICING_TYPES, TAX_CATEGORIES: TAX_CATEGORIES,
    COMMISSION_TYPES: COMMISSION_TYPES, DEPOSIT_TYPES: DEPOSIT_TYPES,
    CLIENT_REQUIREMENTS: CLIENT_REQUIREMENTS, INSTRUCTION_FIELDS: INSTRUCTION_FIELDS,
    COLORS: COLORS,

    read: read, write: write, reset: reset, blank: blank,

    categories: categories, categoryById: categoryById, categoryName: categoryName,
    categoryUsage: categoryUsage, saveCategory: saveCategory, deleteCategory: deleteCategory,

    addons: addons, addonById: addonById, addonUsage: addonUsage,
    saveAddon: saveAddon, deleteAddon: deleteAddon,

    all: all, live: live, byId: byId, save: save, setStatus: setStatus,
    remove: remove, duplicate: duplicate,

    auditFor: auditFor, log: log,

    eligibleIds: eligibleIds, locationIds: locationIds,
    configuredLocationIds: configuredLocationIds, staffAt: staffAt,
    priceFor: priceFor, durationFor: durationFor,
    serviceMinutes: serviceMinutes, blockingMinutes: blockingMinutes,
    addonsFor: addonsFor, requirementText: requirementText, ruleText: ruleText,

    requirementsFor: requirementsFor, slotOptions: slotOptions,
    bookableStatus: bookableStatus, checkSlot: checkSlot, daySlots: daySlots,

    validate: validate,
    search: search, applyFilters: applyFilters, sortRows: sortRows,

    statusMeta: statusMeta, statusLabel: statusLabel,
    pricingMeta: pricingMeta, pricingLabel: pricingLabel,
    money: money, priceText: priceText, durationText: durationText,
    colorHex: colorHex, esc: esc
  };
}());
