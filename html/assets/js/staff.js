/* ==================================================================
   StyleDesk — Staff, Roles & Permissions  (SDT)
   ------------------------------------------------------------------
   The team, and what each of them may do.

   Two ideas kept deliberately apart, because conflating them is the
   mistake this module exists to prevent:

     · a **staff member** is someone the business schedules — they have
       hours, services, a chair, a client history;
     · a **user** is someone who signs in to StyleDesk.

   A massage therapist can be the first without ever being the second.
   A bookkeeper can be the second without ever being the first. So
   `bookable` and `access.enabled` are separate switches, and turning
   one off never touches the other.

   The same separation runs through status: employment status (Active,
   Inactive, On leave) is not access status (Active, Invited, Disabled,
   Never invited). A therapist on maternity leave keeps her login; a
   stylist who has left keeps her appointment history but loses both.

   Depends on SDL (locations) and, where present, SDS/SDB for counts.
   ================================================================== */

var SDT = (function () {
  'use strict';

  var KEY = 'styledesk.staff';

  /* ---------- Reference data --------------------------------------- */

  var DAYS = [
    { id: 'mon', label: 'Monday',    short: 'Mon' },
    { id: 'tue', label: 'Tuesday',   short: 'Tue' },
    { id: 'wed', label: 'Wednesday', short: 'Wed' },
    { id: 'thu', label: 'Thursday',  short: 'Thu' },
    { id: 'fri', label: 'Friday',    short: 'Fri' },
    { id: 'sat', label: 'Saturday',  short: 'Sat' },
    { id: 'sun', label: 'Sunday',    short: 'Sun' }
  ];

  /* §10 — employment. "On leave" is its own state rather than Inactive
     because the person is coming back: their services, hours and chair
     should all still be there when they do. */
  var STATUSES = [
    { id: 'active',   label: 'Active',   badge: 'sd-badge--active',
      note: 'Working and schedulable.' },
    { id: 'leave',    label: 'On leave', badge: 'sd-badge--lead',
      note: 'Away for a while. Takes no new bookings; everything else is kept.' },
    { id: 'inactive', label: 'Inactive', badge: 'sd-badge--inactive',
      note: 'No longer working here. History, revenue and notes are kept.' }
  ];

  /* §38 — access. Separate from the above, and named from the user's
     point of view rather than the admin's. */
  var ACCESS_STATUSES = [
    { id: 'none',     label: 'No login',    badge: 'sd-badge--inactive' },
    { id: 'invited',  label: 'Invited',     badge: 'sd-badge--lead' },
    { id: 'active',   label: 'Can sign in', badge: 'sd-badge--active' },
    { id: 'disabled', label: 'Login off',   badge: 'sd-badge--inactive' }
  ];

  var EMPLOYMENT_TYPES = [
    'Employee', 'Contractor', 'Booth renter', 'Commission', 'Part time', 'Temporary', 'Other'
  ];

  var JOB_TITLES = [
    'Senior Stylist', 'Hair Stylist', 'Colourist', 'Barber',
    'Massage Therapist', 'Aesthetician', 'Nail Technician',
    'Receptionist', 'Salon Manager', 'Owner'
  ];

  var TIME_OFF_TYPES = ['Vacation', 'Sick', 'Personal', 'Training', 'Other'];

  /* §25 — how a client may end up with this person. */
  var SELECTION_MODES = [
    { id: 'client',   label: 'Clients can choose them',
      note: 'They appear by name on the booking page.' },
    { id: 'auto',     label: 'Assigned automatically only',
      note: 'Never offered by name; the engine may still allocate them.' },
    { id: 'internal', label: 'Internal booking only',
      note: 'Staff can book them. Clients cannot, by any route.' }
  ];

  var PRIORITIES = [
    { id: 'preferred', label: 'Preferred', note: 'Allocated first when the client has no preference.' },
    { id: 'normal',    label: 'Normal',    note: '' },
    { id: 'low',       label: 'Last resort', note: 'Allocated only when nobody else is free.' }
  ];

  var COLORS = [
    { id: 'violet', hex: '#3d348b' }, { id: 'teal', hex: '#0d9488' },
    { id: 'amber',  hex: '#d97706' }, { id: 'rose', hex: '#e11d48' },
    { id: 'blue',   hex: '#2563eb' }, { id: 'green', hex: '#16a34a' },
    { id: 'slate',  hex: '#475569' }, { id: 'plum', hex: '#9333ea' }
  ];

  /* ---------- The permission catalogue (§49–§60) --------------------
     Flat ids inside named groups. `sensitive` marks the ones that hand
     over the business rather than a feature — they are called out in
     the UI and never swept up by a Select all. */

  var PERMISSIONS = [
    { id: 'dashboard', label: 'Dashboard', items: [
      ['dashboard.view',      'View dashboard'],
      ['dashboard.analytics', 'View business analytics'],
      ['dashboard.financial', 'View financial summary'],
      ['dashboard.staffperf', 'View staff performance']
    ]},
    { id: 'calendar', label: 'Calendar', items: [
      ['calendar.view',      'View calendar'],
      ['calendar.create',    'Create appointment'],
      ['calendar.edit',      'Edit appointment'],
      ['calendar.cancel',    'Cancel appointment'],
      ['calendar.delete',    'Delete appointment'],
      ['calendar.override',  'Override availability'],
      ['calendar.rules',     'Override booking rules'],
      ['calendar.block',     'Block calendar time'],
      ['calendar.move',      'Move appointments between staff']
    ]},
    { id: 'clients', label: 'Clients', items: [
      ['clients.view',      'View clients'],
      ['clients.create',    'Create clients'],
      ['clients.edit',      'Edit clients'],
      ['clients.delete',    'Delete clients'],
      ['clients.contact',   'View contact information'],
      ['clients.prefs',     'View and edit preferences'],
      ['clients.notes',     'View client notes'],
      ['clients.notesedit', 'Create and edit notes'],
      ['clients.notesdel',  'Delete notes'],
      ['clients.history',   'View client history'],
      ['clients.spend',     'View client spending']
    ]},
    { id: 'services', label: 'Services', items: [
      ['services.view',       'View services'],
      ['services.create',     'Create services'],
      ['services.edit',       'Edit services'],
      ['services.delete',     'Delete services'],
      ['services.pricing',    'Edit pricing'],
      ['services.duration',   'Edit duration'],
      ['services.assign',     'Assign services to staff'],
      ['services.categories', 'Manage categories'],
      ['services.resources',  'Manage resources']
    ]},
    { id: 'staff', label: 'Staff', items: [
      ['staff.view',      'View staff'],
      ['staff.create',    'Add staff'],
      ['staff.edit',      'Edit staff'],
      ['staff.disable',   'Deactivate staff'],
      ['staff.delete',    'Delete staff'],
      ['staff.hours',     'Edit working hours'],
      ['staff.services',  'Edit assigned services'],
      ['staff.locations', 'Manage staff locations'],
      ['staff.timeoff',   'Manage time off'],
      ['staff.access',    'Manage app access', true],
      ['staff.roles',     'Assign roles', true]
    ]},
    /* §40 of the Business Hours spec. `hours.override` is the one that
       matters: it is what lets a front-desk booking sit outside the
       hours the site advertises, so it is a permission rather than a
       button everyone gets. */
    { id: 'hours', label: 'Business hours', items: [
      ['hours.view',        'View business hours'],
      ['hours.edit',        'Edit business hours'],
      ['hours.closures',    'Create closures'],
      ['hours.closuresedit','Edit closures'],
      ['hours.closuresdel', 'Delete closures'],
      ['hours.holidays',    'Manage holiday hours'],
      ['hours.override',    'Override business hours']
    ]},
    { id: 'booking', label: 'Online booking', items: [
      ['booking.view',       'View online booking settings'],
      ['booking.edit',       'Edit online booking settings'],
      ['booking.rules',      'Manage booking rules'],
      ['booking.policy',     'Manage cancellation policy'],
      ['booking.questions',  'Manage booking questions'],
      ['booking.visibility', 'Manage staff visibility']
    ]},
    { id: 'payments', label: 'Payments', items: [
      ['payments.view',      'View payments'],
      ['payments.collect',   'Collect payment'],
      ['payments.refund',    'Refund payment', true],
      ['payments.void',      'Void transaction', true],
      ['payments.history',   'View transaction history'],
      ['payments.revenue',   'View revenue'],
      ['payments.tips',      'View tips'],
      ['payments.tipsedit',  'Edit tips'],
      ['payments.discount',  'Apply discounts']
    ]},
    { id: 'reports', label: 'Reports', items: [
      ['reports.view',      'View reports'],
      ['reports.sales',     'View sales'],
      ['reports.staffperf', 'View staff performance'],
      ['reports.services',  'View service performance'],
      ['reports.clients',   'View client reports'],
      ['reports.export',    'Export reports'],
      ['reports.payroll',   'View payroll and commission', true]
    ]},
    { id: 'inventory', label: 'Inventory', items: [
      ['inventory.view',    'View inventory'],
      ['inventory.create',  'Create products'],
      ['inventory.edit',    'Edit products'],
      ['inventory.stock',   'Adjust stock'],
      ['inventory.cost',    'View cost'],
      ['inventory.costedit','Edit cost'],
      ['inventory.po',      'Create purchase orders'],
      ['inventory.vendors', 'Manage vendors']
    ]},
    { id: 'marketing', label: 'Marketing', items: [
      ['marketing.view',      'View campaigns'],
      ['marketing.create',    'Create campaign'],
      ['marketing.send',      'Send campaign'],
      ['marketing.templates', 'Manage templates'],
      ['marketing.analytics', 'View marketing analytics']
    ]},
    { id: 'settings', label: 'App settings', items: [
      ['settings.view',          'View business settings'],
      ['settings.edit',          'Edit business settings'],
      ['settings.locations',     'Manage locations'],
      ['settings.staff',         'Manage staff'],
      ['settings.roles',         'Manage roles', true],
      ['settings.services',      'Manage services'],
      ['settings.resources',     'Manage resources'],
      ['settings.branding',      'Manage branding'],
      ['settings.notifications', 'Manage notifications'],
      ['settings.integrations',  'Manage integrations', true],
      ['settings.billing',       'Manage billing', true]
    ]},
    { id: 'security', label: 'Security', items: [
      ['security.roles',      'Manage roles and permissions', true],
      ['security.users',      'Manage user access', true],
      ['security.logs',       'View security logs'],
      ['security.api',        'Manage API keys', true],
      ['security.transfer',   'Transfer ownership', true],
      ['security.deletebiz',  'Delete the business', true]
    ]}
  ];

  /* Ownership-grade permissions. §72: these cannot be handed to a custom
     role at all — not "with a warning", not at all. A custom role that
     could grant itself ownership transfer is not a permission system. */
  var OWNER_ONLY = ['security.transfer', 'security.deletebiz'];

  function allPermissionIds() {
    var out = [];
    PERMISSIONS.forEach(function (g) {
      g.items.forEach(function (it) { out.push(it[0]); });
    });
    return out;
  }

  function permissionMeta(id) {
    var found = null;
    PERMISSIONS.forEach(function (g) {
      g.items.forEach(function (it) {
        if (it[0] === id) found = { id: it[0], label: it[1], sensitive: !!it[2], group: g.label, groupId: g.id };
      });
    });
    return found;
  }

  function isSensitive(id) {
    var m = permissionMeta(id);
    return !!(m && m.sensitive);
  }

  /* ---------- Scopes (§61–§63) --------------------------------------
     A permission answers "may they?"; a scope answers "over whom?".
     Both are needed: `View staff` at Downtown is a different power from
     `View staff` everywhere, and a system that only models the first
     leaks the whole roster to a single-site manager. */

  var CALENDAR_SCOPES = [
    { id: 'own',      label: 'Their own calendar only' },
    { id: 'location', label: 'Everyone at their locations' },
    { id: 'all',      label: 'All staff, everywhere' }
  ];

  var CLIENT_SCOPES = [
    { id: 'served',   label: 'Clients they have served' },
    { id: 'location', label: 'Clients at their locations' },
    { id: 'all',      label: 'All clients' }
  ];

  /* ---------- Roles ---------------------------------------------------- */

  function grant(list) {
    var out = {};
    list.forEach(function (id) { out[id] = true; });
    return out;
  }

  function seedRoles() {
    var everything = grant(allPermissionIds());

    /* §73 — Admin is everything except the two that end the business. */
    var admin = grant(allPermissionIds().filter(function (id) {
      return OWNER_ONLY.indexOf(id) === -1;
    }));

    var manager = grant([
      'dashboard.view', 'dashboard.analytics', 'dashboard.staffperf',
      'calendar.view', 'calendar.create', 'calendar.edit', 'calendar.cancel',
      'calendar.block', 'calendar.move', 'calendar.override',
      'clients.view', 'clients.create', 'clients.edit', 'clients.contact',
      'clients.prefs', 'clients.notes', 'clients.notesedit', 'clients.history',
      'services.view', 'services.edit', 'services.duration', 'services.assign',
      'services.categories', 'services.resources',
      'staff.view', 'staff.create', 'staff.edit', 'staff.hours', 'staff.services',
      'staff.locations', 'staff.timeoff',
      /* A manager can shorten a day or close for a training morning,
         but cannot book straight through the closure they just made. */
      'hours.view', 'hours.edit', 'hours.closures', 'hours.closuresedit',
      'booking.view', 'booking.rules', 'booking.visibility',
      'payments.view', 'payments.collect', 'payments.history', 'payments.tips',
      'payments.discount',
      'reports.view', 'reports.sales', 'reports.staffperf', 'reports.services',
      'reports.clients', 'reports.export',
      'settings.view', 'settings.notifications'
    ]);

    var receptionist = grant([
      'calendar.view', 'calendar.create', 'calendar.edit', 'calendar.cancel', 'calendar.block',
      'clients.view', 'clients.create', 'clients.edit', 'clients.contact',
      'clients.prefs', 'clients.notes', 'clients.notesedit', 'clients.history',
      'services.view',
      'staff.view',
      'hours.view',
      'payments.view', 'payments.collect', 'payments.history', 'payments.tips'
    ]);

    var provider = grant([
      'calendar.view',
      'clients.view', 'clients.contact', 'clients.prefs', 'clients.notes',
      'clients.notesedit', 'clients.history',
      'services.view',
      'staff.timeoff'
    ]);

    return [
      { id: 'owner', name: 'Owner', system: true, kind: 'Full',
        description: 'Complete control of the business, including ownership and billing.',
        permissions: everything, calendarScope: 'all', clientScope: 'all', allLocations: true, locationIds: [] },
      { id: 'admin', name: 'Admin', system: true, kind: 'Administrative',
        description: 'Everything an owner can do, except transferring ownership or deleting the business.',
        permissions: admin, calendarScope: 'all', clientScope: 'all', allLocations: true, locationIds: [] },
      { id: 'manager', name: 'Manager', system: true, kind: 'Operational',
        description: 'Runs the floor: bookings, staff, services and reporting. No billing or security.',
        permissions: manager, calendarScope: 'location', clientScope: 'location', allLocations: true, locationIds: [] },
      { id: 'reception', name: 'Receptionist', system: true, kind: 'Front desk',
        description: 'Books, checks in and takes payment. Cannot see reports or change the business.',
        permissions: receptionist, calendarScope: 'location', clientScope: 'location', allLocations: true, locationIds: [] },
      { id: 'provider', name: 'Service Provider', system: true, kind: 'Limited',
        description: 'Their own diary and their own clients. No business settings, no other schedules.',
        permissions: provider, calendarScope: 'own', clientScope: 'served', allLocations: true, locationIds: [] }
    ];
  }

  /* ---------- Staff ------------------------------------------------------ */

  function hours(spec) {
    var out = {};
    DAYS.forEach(function (d) { out[d.id] = spec[d.id] ? spec[d.id].slice() : []; });
    return out;
  }

  function blank() {
    return {
      id: '', firstName: '', lastName: '', displayName: '',
      email: '', phone: '', jobTitle: '', employeeId: '',
      photo: null, color: 'violet',
      bio: '', specialties: [],

      employmentType: 'Employee', hireDate: '', endDate: '', status: 'active',

      locationIds: [], primaryLocationId: '',
      serviceIds: [],
      /* {serviceId, durationMin, price} — blank means the service default. */
      serviceOverrides: [],

      hours: hours({
        mon: [['09:00', '17:00']], tue: [['09:00', '17:00']], wed: [['09:00', '17:00']],
        thu: [['09:00', '17:00']], fri: [['09:00', '17:00']]
      }),
      breaks: [],     /* {id, label, days:[], from, to} */
      timeOff: [],    /* {id, type, start, end, allDay, repeat, note} */
      specialHours: [], /* {id, date, spans:[[from,to]], note} */

      bookable: true,
      onlineBooking: true,
      onlineName: '',
      selection: 'client',
      anyAvailable: true,
      priority: 'normal',
      noticeHours: '',      /* blank = business default */
      windowDays: '',
      bufferBefore: '',
      bufferAfter: '',
      capacity: 1,
      resourceIds: [],

      access: {
        enabled: false, roleId: '', loginEmail: '',
        status: 'none', invitedAt: '', lastSentAt: '', acceptedAt: '',
        allLocations: true, locationIds: []
      },

      notifications: { email: true, sms: false, inapp: true },
      notes: ''
    };
  }

  function make(over) {
    var row = Object.assign(blank(), over);
    row.hours = Object.assign(blank().hours, over.hours || {});
    row.access = Object.assign(blank().access, over.access || {});
    row.notifications = Object.assign(blank().notifications, over.notifications || {});
    return row;
  }

  /* The seven the rest of the prototype already knows, filled out. Their
     locations and roles match what services, resources and bookings
     already assume, so nothing downstream shifts under them. */
  function seedStaff() {
    var full = hours({
      mon: [['09:00', '17:00']], tue: [['09:00', '17:00']], wed: [['09:00', '17:00']],
      thu: [['11:00', '19:00']], fri: [['11:00', '19:00']], sat: [['09:00', '16:00']]
    });
    var spa = hours({
      tue: [['10:00', '18:00']], wed: [['10:00', '18:00']], thu: [['10:00', '18:00']],
      fri: [['10:00', '18:00']], sat: [['10:00', '17:00']]
    });

    return [
      make({
        id: 'u1', firstName: 'Amara', lastName: 'Osei', displayName: 'Amara',
        email: 'amara@bellabeauty.example.com', phone: '+1 (202) 555-0198',
        jobTitle: 'Senior Stylist', employeeId: 'STF-00124', color: 'violet',
        bio: 'Amara specialises in layered cuts, balayage and dimensional colour, with over ten years behind the chair.',
        specialties: ['Balayage', "Women's cuts", 'Curly hair'],
        employmentType: 'Employee', hireDate: '2019-04-08', status: 'active',
        locationIds: ['loc1', 'loc3'], primaryLocationId: 'loc1',
        serviceIds: ['s1', 's2'],
        serviceOverrides: [{ serviceId: 's1', durationMin: 45, price: 75 }],
        hours: full, priority: 'preferred',
        breaks: [{ id: 'b1', label: 'Lunch', days: ['mon', 'tue', 'wed', 'thu', 'fri'], from: '13:00', to: '13:45' }],
        resourceIds: ['r1', 'r5'],
        access: { enabled: true, roleId: 'provider', loginEmail: 'amara@bellabeauty.example.com',
                  status: 'active', invitedAt: '2019-04-08', acceptedAt: '2019-04-09',
                  allLocations: false, locationIds: ['loc1', 'loc3'] }
      }),
      make({
        id: 'u2', firstName: 'Priya', lastName: 'Raman', displayName: 'Priya',
        email: 'priya@bellabeauty.example.com', phone: '+1 (202) 555-0211',
        jobTitle: 'Colourist', employeeId: 'STF-00131', color: 'plum',
        bio: 'Colour specialist. Corrective work, vivids and grey blending.',
        specialties: ['Colour correction', 'Vivids', 'Balayage'],
        employmentType: 'Commission', hireDate: '2020-09-14', status: 'active',
        locationIds: ['loc1'], primaryLocationId: 'loc1',
        serviceIds: ['s1', 's2'],
        serviceOverrides: [{ serviceId: 's1', durationMin: 60, price: 85 },
                           { serviceId: 's2', durationMin: '', price: 140 }],
        hours: full, priority: 'normal',
        resourceIds: ['r5', 'r6'],
        access: { enabled: true, roleId: 'provider', loginEmail: 'priya@bellabeauty.example.com',
                  status: 'active', invitedAt: '2020-09-14', acceptedAt: '2020-09-15',
                  allLocations: false, locationIds: ['loc1'] }
      }),
      make({
        id: 'u3', firstName: 'Jonas', lastName: 'Weber', displayName: 'Jonas',
        email: 'jonas@bellabeauty.example.com', phone: '+1 (202) 555-0233',
        jobTitle: 'Massage Therapist', employeeId: 'STF-00140', color: 'teal',
        bio: 'Deep tissue and sports massage. Trained in myofascial release.',
        specialties: ['Deep tissue', 'Sports massage'],
        employmentType: 'Employee', hireDate: '2021-02-01', status: 'active',
        locationIds: ['loc2'], primaryLocationId: 'loc2',
        serviceIds: ['s3', 's6'],
        hours: spa, priority: 'preferred',
        breaks: [{ id: 'b2', label: 'Lunch', days: ['tue', 'wed', 'thu', 'fri'], from: '13:30', to: '14:15' }],
        resourceIds: ['r10', 'r11', 'r12'],
        /* Bookable without a login. The case this module exists for. */
        access: { enabled: false, roleId: '', loginEmail: '', status: 'none',
                  allLocations: true, locationIds: [] }
      }),
      make({
        id: 'u4', firstName: 'Lena', lastName: 'Fitzgerald', displayName: 'Lena',
        email: 'lena@bellabeauty.example.com', phone: '+1 (202) 555-0247',
        jobTitle: 'Aesthetician', employeeId: 'STF-00146', color: 'rose',
        bio: 'Advanced facials, laser and skin consultations.',
        specialties: ['Laser', 'Facials', 'Skin consultation'],
        employmentType: 'Employee', hireDate: '2022-06-20', status: 'active',
        locationIds: ['loc2'], primaryLocationId: 'loc2',
        serviceIds: ['s4', 's5'],
        hours: spa, priority: 'normal',
        resourceIds: ['r13', 'r14', 'r15'],
        access: { enabled: true, roleId: 'manager', loginEmail: 'lena@bellabeauty.example.com',
                  status: 'active', invitedAt: '2022-06-20', acceptedAt: '2022-06-21',
                  /* §61 — a manager restricted to one site. */
                  allLocations: false, locationIds: ['loc2'] }
      }),
      make({
        id: 'u5', firstName: 'Marco', lastName: 'Silva', displayName: 'Marco',
        email: 'marco@bellabeauty.example.com', phone: '+1 (202) 555-0259',
        jobTitle: 'Barber', employeeId: 'STF-00152', color: 'slate',
        specialties: ['Fades', 'Wet shave', 'Beard work'],
        employmentType: 'Booth renter', hireDate: '2023-01-16', status: 'active',
        locationIds: ['loc1', 'loc3'], primaryLocationId: 'loc3',
        serviceIds: [],
        hours: hours({
          mon: [['10:00', '18:00']], tue: [['10:00', '18:00']],
          thu: [['10:00', '18:00']], fri: [['10:00', '18:00']], sat: [['09:00', '15:00']]
        }),
        priority: 'normal', resourceIds: ['r7'],
        access: { enabled: false, roleId: '', loginEmail: '', status: 'none',
                  allLocations: true, locationIds: [] }
      }),
      make({
        id: 'u6', firstName: 'Nadia', lastName: 'Haddad', displayName: 'Nadia',
        email: 'nadia@bellabeauty.example.com', phone: '+1 (202) 555-0264',
        jobTitle: 'Massage Therapist', employeeId: 'STF-00158', color: 'green',
        specialties: ['Swedish', 'Prenatal'],
        employmentType: 'Part time', hireDate: '2024-03-04', status: 'active',
        locationIds: ['loc2'], primaryLocationId: 'loc2',
        serviceIds: ['s3', 's5'],
        hours: hours({ thu: [['12:00', '20:00']], fri: [['12:00', '20:00']], sat: [['10:00', '17:00']] }),
        priority: 'normal',
        /* §23 — bookable internally, not yet shown publicly. */
        onlineBooking: false, selection: 'internal',
        resourceIds: ['r10', 'r11', 'r12'],
        access: { enabled: true, roleId: 'provider', loginEmail: 'nadia@bellabeauty.example.com',
                  status: 'invited', invitedAt: '2024-03-04', lastSentAt: '2024-03-11',
                  allLocations: false, locationIds: ['loc2'] }
      }),
      make({
        id: 'u7', firstName: 'Tomas', lastName: 'Lindqvist', displayName: 'Tomas',
        email: 'tomas@bellabeauty.example.com', phone: '+1 (202) 555-0270',
        jobTitle: 'Nail Technician', employeeId: 'STF-00163', color: 'amber',
        specialties: ['Gel', 'Nail art'],
        employmentType: 'Employee', hireDate: '2023-08-21', status: 'active',
        locationIds: ['loc1'], primaryLocationId: 'loc1',
        serviceIds: [],
        hours: full, priority: 'normal', resourceIds: ['r8', 'r9'],
        access: { enabled: false, roleId: '', loginEmail: '', status: 'none',
                  allLocations: true, locationIds: [] }
      }),
      /* Front desk: signs in, is never booked. The mirror image of Jonas. */
      make({
        id: 'u8', firstName: 'Rohit', lastName: 'Philip', displayName: 'Rohit',
        email: 'rohitcphilip@gmail.com', phone: '+1 (202) 555-0100',
        jobTitle: 'Owner', employeeId: 'STF-00001', color: 'blue',
        employmentType: 'Employee', hireDate: '2018-01-02', status: 'active',
        locationIds: ['loc1', 'loc2', 'loc3'], primaryLocationId: 'loc1',
        serviceIds: [], hours: full,
        bookable: false, onlineBooking: false, selection: 'internal', anyAvailable: false,
        /* The address the account module signs in with. These two seeds
           have to agree: the app works out what the current user may do
           by matching this against their login. */
        access: { enabled: true, roleId: 'owner', loginEmail: 'rohit@example.com',
                  status: 'active', invitedAt: '2018-01-02', acceptedAt: '2018-01-02',
                  allLocations: true, locationIds: [] }
      }),
      make({
        id: 'u9', firstName: 'Dana', lastName: 'Whitmore', displayName: 'Dana',
        email: 'dana@bellabeauty.example.com', phone: '+1 (202) 555-0288',
        jobTitle: 'Receptionist', employeeId: 'STF-00171', color: 'blue',
        employmentType: 'Employee', hireDate: '2024-11-11', status: 'active',
        locationIds: ['loc1'], primaryLocationId: 'loc1',
        serviceIds: [], hours: full,
        bookable: false, onlineBooking: false, selection: 'internal', anyAvailable: false,
        access: { enabled: true, roleId: 'reception', loginEmail: 'dana@bellabeauty.example.com',
                  status: 'active', invitedAt: '2024-11-11', acceptedAt: '2024-11-12',
                  allLocations: false, locationIds: ['loc1'] }
      }),
      /* Left in March. Still resolvable, so her past appointments and
         revenue keep her name on them. */
      make({
        id: 'u10', firstName: 'Elise', lastName: 'Moreau', displayName: 'Elise',
        email: 'elise@bellabeauty.example.com', phone: '+1 (202) 555-0295',
        jobTitle: 'Hair Stylist', employeeId: 'STF-00119', color: 'amber',
        employmentType: 'Employee', hireDate: '2020-02-17', endDate: '2026-03-31',
        status: 'inactive',
        locationIds: ['loc1'], primaryLocationId: 'loc1',
        serviceIds: ['s1'], hours: full,
        bookable: false, onlineBooking: false,
        access: { enabled: true, roleId: 'provider', loginEmail: 'elise@bellabeauty.example.com',
                  status: 'disabled', invitedAt: '2020-02-17', acceptedAt: '2020-02-18',
                  allLocations: false, locationIds: ['loc1'] }
      })
    ];
  }

  function seed() {
    return { staff: seedStaff(), roles: seedRoles(), audit: [] };
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
    memory.staff = (memory.staff || []).map(make);
    memory.roles = memory.roles || seedRoles();
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

  function all() { return read().staff.slice(); }

  /* Currently employed. What the staff directory shows by default and
     what any "who works here" question means. */
  function active() {
    return all().filter(function (s) { return s.status === 'active'; });
  }

  /* Who can take a new appointment. Active, bookable, and not away —
     three separate reasons someone might not be, and all three have to
     hold. This is what SD.STAFF resolves to. */
  function bookable() {
    return all().filter(function (s) {
      return s.status === 'active' && s.bookable;
    });
  }

  function byId(id) {
    var hit = read().staff.filter(function (s) { return s.id === id; });
    return hit.length ? hit[0] : null;
  }

  function fullName(s) {
    if (!s) return '';
    return [s.firstName, s.lastName].filter(Boolean).join(' ').trim();
  }

  /* Resolves an inactive person too. A booking from last year still has
     to say who performed it. */
  function nameOf(id) {
    return fullName(byId(id));
  }

  function initials(s) {
    if (!s) return '';
    return ((s.firstName || '')[0] || '' + (s.lastName || '')[0] || '').toUpperCase().slice(0, 1) +
           ((s.lastName || '')[0] || '').toUpperCase();
  }

  function nextId() {
    var max = 0;
    read().staff.forEach(function (s) {
      var n = parseInt(String(s.id).replace(/\D/g, ''), 10);
      if (!isNaN(n) && n > max) max = n;
    });
    return 'u' + (max + 1);
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

  function save(data, actor) {
    var s = read();
    var existing = data.id ? byId(data.id) : null;

    if (existing) {
      var changes = diff(existing, data);
      s.staff = s.staff.map(function (x) { return x.id === data.id ? make(Object.assign({}, x, data)) : x; });
      write(s);
      changes.forEach(function (c) { log(c.event, data.id, c.from, c.to, actor); });
      return byId(data.id);
    }

    var row = make(Object.assign({}, withoutBlankId(data), { id: nextId() }));
    s.staff.push(row);
    write(s);
    log('Staff created', row.id, '', fullName(row), actor);
    return row;
  }

  function setStatus(id, status, actor) {
    var before = byId(id);
    if (!before || before.status === status) return before;
    var s = read();
    s.staff = s.staff.map(function (x) {
      /* §75 — deactivating takes them out of new bookings, but the
         `bookable` flag is left exactly as it was: `bookable()` already
         requires an active status, so clearing the flag too would
         destroy the setting and leave them unbookable on the day they
         came back. Their login is untouched for the same reason —
         employment and access are separate decisions. */
      return x.id === id ? Object.assign({}, x, { status: status }) : x;
    });
    write(s);
    log(status === 'active' ? 'Staff reactivated' : 'Staff deactivated', id,
        statusLabel(before.status), statusLabel(status), actor);
    return byId(id);
  }

  function remove(id) {
    var s = read();
    s.staff = s.staff.filter(function (x) { return x.id !== id; });
    s.audit = s.audit.filter(function (a) { return a.staffId !== id; });
    write(s);
  }

  /* ---------- App access (§34–§38) --------------------------------------- */

  function setAccess(id, patch, actor) {
    var before = byId(id);
    if (!before) return null;
    var s = read();
    s.staff = s.staff.map(function (x) {
      return x.id === id ? Object.assign({}, x, { access: Object.assign({}, x.access, patch) }) : x;
    });
    write(s);

    if ('enabled' in patch && patch.enabled !== before.access.enabled) {
      log(patch.enabled ? 'Login access granted' : 'Login access disabled', id,
          before.access.enabled ? 'On' : 'Off', patch.enabled ? 'On' : 'Off', actor);
    }
    if ('roleId' in patch && patch.roleId !== before.access.roleId) {
      log('Role changed', id, roleName(before.access.roleId), roleName(patch.roleId), actor);
    }
    return byId(id);
  }

  function invite(id, actor) {
    var now = new Date().toISOString();
    var s = byId(id);
    if (!s) return null;
    var first = !s.access.invitedAt;
    setAccess(id, {
      status: 'invited',
      invitedAt: s.access.invitedAt || now,
      lastSentAt: now
    });
    log(first ? 'Invitation sent' : 'Invitation resent', id, '', s.access.loginEmail || s.email, actor);
    return byId(id);
  }

  function accessMeta(s) {
    if (!s || !s.access.enabled) return ACCESS_STATUSES[0];
    var hit = ACCESS_STATUSES.filter(function (a) { return a.id === s.access.status; })[0];
    return hit || ACCESS_STATUSES[0];
  }

  /* Login emails have to be unique — two people cannot be the same
     account. Checked against every record, including inactive ones,
     because those logins still exist. */
  function loginTaken(email, exceptId) {
    var want = String(email || '').trim().toLowerCase();
    if (!want) return null;
    return all().filter(function (s) {
      return s.id !== exceptId && s.access.enabled &&
             String(s.access.loginEmail || '').trim().toLowerCase() === want;
    })[0] || null;
  }

  /* ---------- Roles ------------------------------------------------------- */

  function roles() { return read().roles.slice(); }

  function roleById(id) {
    var hit = read().roles.filter(function (r) { return r.id === id; });
    return hit.length ? hit[0] : null;
  }

  function roleName(id) {
    var r = roleById(id);
    return r ? r.name : '';
  }

  function roleUsage(id) {
    return all().filter(function (s) { return s.access.enabled && s.access.roleId === id; });
  }

  function nextRoleId() {
    var n = 1;
    while (roleById('role' + n)) n++;
    return 'role' + n;
  }

  function saveRole(data, actor) {
    var s = read();
    var existing = data.id ? roleById(data.id) : null;

    if (existing) {
      /* §72 — no route, however indirect, hands ownership-grade rights
         to anything but the Owner role. */
      var perms = Object.assign({}, data.permissions || existing.permissions);
      if (existing.id !== 'owner') {
        OWNER_ONLY.forEach(function (p) { delete perms[p]; });
      }
      s.roles = s.roles.map(function (r) {
        return r.id === data.id ? Object.assign({}, r, data, { permissions: perms, system: r.system }) : r;
      });
      write(s);
      log('Permissions changed', '', existing.name, describeDiff(existing.permissions, perms), actor);
      return roleById(data.id);
    }

    var clean = Object.assign({}, data.permissions || {});
    OWNER_ONLY.forEach(function (p) { delete clean[p]; });

    var row = Object.assign({
      id: nextRoleId(), name: '', description: '', system: false, kind: 'Custom',
      permissions: clean, calendarScope: 'own', clientScope: 'served',
      allLocations: true, locationIds: []
    }, withoutBlankId(data), { permissions: clean, system: false, kind: 'Custom' });

    s.roles.push(row);
    write(s);
    log('Custom role created', '', '', row.name, actor);
    return row;
  }

  function duplicateRole(id, actor) {
    var src = roleById(id);
    if (!src) return null;
    var name = src.name + ' (copy)';
    var n = 2;
    while (roles().some(function (r) { return r.name.toLowerCase() === name.toLowerCase(); })) {
      name = src.name + ' (copy ' + n + ')'; n++;
    }
    return saveRole({
      name: name,
      description: src.description,
      permissions: Object.assign({}, src.permissions),
      calendarScope: src.calendarScope, clientScope: src.clientScope,
      allLocations: src.allLocations, locationIds: (src.locationIds || []).slice()
    }, actor);
  }

  function deleteRole(id, moveToId, actor) {
    var role = roleById(id);
    if (!role || role.system) return false;

    var s = read();
    if (moveToId) {
      s.staff = s.staff.map(function (x) {
        return x.access.roleId === id
          ? Object.assign({}, x, { access: Object.assign({}, x.access, { roleId: moveToId }) })
          : x;
      });
    }
    s.roles = s.roles.filter(function (r) { return r.id !== id; });
    write(s);
    log('Custom role deleted', '', role.name, moveToId ? 'Staff moved to ' + roleName(moveToId) : '', actor);
    return true;
  }

  function can(roleId, permissionId) {
    var r = roleById(roleId);
    return !!(r && r.permissions[permissionId]);
  }

  function grantedCount(role) {
    return Object.keys(role.permissions || {}).filter(function (k) { return role.permissions[k]; }).length;
  }

  function describeDiff(before, after) {
    var added = [], removed = [];
    allPermissionIds().forEach(function (id) {
      if (!before[id] && after[id]) added.push(id);
      if (before[id] && !after[id]) removed.push(id);
    });
    var bits = [];
    if (added.length) bits.push('+' + added.length);
    if (removed.length) bits.push('−' + removed.length);
    return bits.join(' ') || 'no change';
  }

  /* ---------- Schedule ---------------------------------------------------- */

  function toMin(t) {
    var p = String(t || '').split(':');
    var h = Number(p[0]), m = Number(p[1]);
    if (isNaN(h) || isNaN(m)) return null;
    return h * 60 + m;
  }

  function fromMin(m) {
    var h = Math.floor(m / 60), r = m % 60;
    return ('0' + h).slice(-2) + ':' + ('0' + r).slice(-2);
  }

  function timeLabel(t) {
    var m = toMin(t);
    if (m === null) return '';
    if (m === 1440) return 'Midnight';
    var h = Math.floor(m / 60), r = m % 60;
    var suffix = h >= 12 ? 'PM' : 'AM';
    var d = h % 12 === 0 ? 12 : h % 12;
    return d + (r ? ':' + ('0' + r).slice(-2) : '') + ' ' + suffix;
  }

  function dayIdOf(dateStr) {
    var d = new Date(dateStr + 'T12:00:00');
    return ['sun', 'mon', 'tue', 'wed', 'thu', 'fri', 'sat'][d.getDay()];
  }

  /* What they are working on a given date, special hours taken into
     account. Returns [] for a day off. */
  function shiftsOn(s, dateStr) {
    if (!s) return [];
    var special = (s.specialHours || []).filter(function (x) { return x.date === dateStr; })[0];
    if (special) return (special.spans || []).slice();
    return ((s.hours || {})[dayIdOf(dateStr)] || []).slice();
  }

  function worksOn(s, dateStr) {
    return shiftsOn(s, dateStr).length > 0;
  }

  function todayText(s) {
    var today = new Date().toISOString().slice(0, 10);
    if (s.status !== 'active') return statusLabel(s.status);
    if (isOff(s, today)) return 'Time off';
    var shifts = shiftsOn(s, today);
    if (!shifts.length) return 'Not working';
    return shifts.map(function (sp) { return timeLabel(sp[0]) + ' – ' + timeLabel(sp[1]); }).join(', ');
  }

  function isOff(s, dateStr) {
    return (s.timeOff || []).some(function (t) {
      var from = String(t.start || '').slice(0, 10);
      var to = String(t.end || '').slice(0, 10);
      if (!from) return false;
      return dateStr >= from && dateStr <= (to || from);
    });
  }

  function hoursSummary(s) {
    var runs = [];
    DAYS.forEach(function (d) {
      var spans = (s.hours || {})[d.id] || [];
      var text = spans.length
        ? spans.map(function (sp) { return timeLabel(sp[0]) + ' – ' + timeLabel(sp[1]); }).join(', ')
        : 'Off';
      var last = runs[runs.length - 1];
      if (last && last.text === text) last.days.push(d);
      else runs.push({ text: text, days: [d] });
    });
    return runs.map(function (run) {
      var span = run.days.length === 1
        ? run.days[0].short
        : run.days[0].short + '–' + run.days[run.days.length - 1].short;
      return run.text === 'Off' ? span + ' off' : span + ' ' + run.text;
    }).join(' · ');
  }

  function weeklyMinutes(s) {
    var total = 0;
    DAYS.forEach(function (d) {
      ((s.hours || {})[d.id] || []).forEach(function (sp) {
        var a = toMin(sp[0]), b = toMin(sp[1]);
        if (a !== null && b !== null && b > a) total += b - a;
      });
    });
    (s.breaks || []).forEach(function (br) {
      var a = toMin(br.from), b = toMin(br.to);
      if (a !== null && b !== null && b > a) total -= (b - a) * (br.days || []).length;
    });
    return Math.max(0, total);
  }

  /* ---------- Audit (§79) --------------------------------------------------- */

  var TRACKED = [
    { key: 'firstName',  event: 'Staff edited',        show: function (v) { return v; } },
    { key: 'lastName',   event: 'Staff edited',        show: function (v) { return v; } },
    { key: 'jobTitle',   event: 'Staff edited',        show: function (v) { return v; } },
    { key: 'status',     event: 'Status changed',      show: function (v) { return statusLabel(v); } },
    { key: 'bookable',   event: 'Bookable changed',    show: function (v) { return v ? 'On' : 'Off'; } },
    { key: 'onlineBooking', event: 'Online booking changed', show: function (v) { return v ? 'On' : 'Off'; } }
  ];

  function diff(before, after) {
    var out = [];

    TRACKED.forEach(function (f) {
      if (!(f.key in after)) return;
      if (String(before[f.key]) === String(after[f.key])) return;
      out.push({ event: f.event, from: f.show(before[f.key]), to: f.show(after[f.key]) });
    });

    if ('locationIds' in after) {
      added(before.locationIds, after.locationIds).forEach(function (id) {
        out.push({ event: 'Location added', from: '', to: SDL.nameOf(id) });
      });
      added(after.locationIds, before.locationIds).forEach(function (id) {
        out.push({ event: 'Location removed', from: SDL.nameOf(id), to: '' });
      });
    }

    if ('serviceIds' in after) {
      var a = added(before.serviceIds, after.serviceIds).length;
      var r = added(after.serviceIds, before.serviceIds).length;
      if (a || r) {
        out.push({ event: 'Services changed',
          from: before.serviceIds.length + ' services',
          to: after.serviceIds.length + ' services' });
      }
    }

    if ('hours' in after && JSON.stringify(before.hours) !== JSON.stringify(after.hours)) {
      out.push({ event: 'Working hours updated', from: hoursSummary(before), to: hoursSummary(after) });
    }

    return out;
  }

  function added(from, to) {
    from = from || []; to = to || [];
    return to.filter(function (x) { return from.indexOf(x) === -1; });
  }

  function log(event, staffId, from, to, actor) {
    var s = read();
    s.audit.unshift({
      id: 'log' + (s.audit.length + 1),
      staffId: staffId || '',
      event: event,
      from: from == null ? '' : String(from),
      to: to == null ? '' : String(to),
      actor: actor || 'You',
      at: new Date().toISOString()
    });
    s.audit = s.audit.slice(0, 500);
    write(s);
  }

  function auditFor(staffId) {
    return read().audit.filter(function (a) { return a.staffId === staffId; });
  }

  function auditAll() { return read().audit.slice(); }

  /* ---------- Query --------------------------------------------------------- */

  function search(list, term) {
    var q = String(term || '').trim().toLowerCase();
    if (!q) return list;
    return list.filter(function (s) {
      return [s.firstName, s.lastName, fullName(s), s.displayName, s.email, s.phone,
              s.jobTitle, s.employeeId]
        .some(function (v) { return String(v || '').toLowerCase().indexOf(q) !== -1; });
    });
  }

  function applyFilters(list, f) {
    f = f || {};
    return list.filter(function (s) {
      if (f.location && f.location.length &&
          !f.location.some(function (l) { return (s.locationIds || []).indexOf(l) !== -1; })) return false;
      if (f.role && f.role.length) {
        var r = s.access.enabled ? s.access.roleId : 'none';
        if (f.role.indexOf(r) === -1) return false;
      }
      if (f.status && f.status.length && f.status.indexOf(s.status) === -1) return false;
      if (f.booking && f.booking.length) {
        var tags = [];
        tags.push(s.bookable ? 'bookable' : 'nonbookable');
        tags.push(s.bookable && s.onlineBooking ? 'online' : 'offline');
        if (!f.booking.some(function (t) { return tags.indexOf(t) !== -1; })) return false;
      }
      return true;
    });
  }

  /* ---------- Formatting ------------------------------------------------------ */

  function statusMeta(id) {
    return STATUSES.filter(function (s) { return s.id === id; })[0] || STATUSES[0];
  }
  function statusLabel(id) { return statusMeta(id).label; }

  function colorHex(id) {
    var hit = COLORS.filter(function (c) { return c.id === id; })[0];
    return hit ? hit.hex : COLORS[0].hex;
  }

  function locationText(s) {
    var ids = s.locationIds || [];
    if (!ids.length) return '';
    var first = SDL.nameOf(ids[0]);
    return ids.length > 1 ? first + ' +' + (ids.length - 1) : first;
  }

  function serviceCount(s) {
    if (typeof SDS === 'undefined') return (s.serviceIds || []).length;
    return (s.serviceIds || []).filter(function (id) { return SDS.byId(id); }).length;
  }

  function esc(v) {
    return String(v == null ? '' : v)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
  }

  return {
    DAYS: DAYS, STATUSES: STATUSES, ACCESS_STATUSES: ACCESS_STATUSES,
    EMPLOYMENT_TYPES: EMPLOYMENT_TYPES, JOB_TITLES: JOB_TITLES,
    TIME_OFF_TYPES: TIME_OFF_TYPES, SELECTION_MODES: SELECTION_MODES,
    PRIORITIES: PRIORITIES, COLORS: COLORS,
    PERMISSIONS: PERMISSIONS, OWNER_ONLY: OWNER_ONLY,
    CALENDAR_SCOPES: CALENDAR_SCOPES, CLIENT_SCOPES: CLIENT_SCOPES,

    read: read, write: write, reset: reset, blank: blank,
    all: all, active: active, bookable: bookable, byId: byId,
    fullName: fullName, nameOf: nameOf, initials: initials,
    save: save, setStatus: setStatus, remove: remove,

    setAccess: setAccess, invite: invite, accessMeta: accessMeta, loginTaken: loginTaken,

    roles: roles, roleById: roleById, roleName: roleName, roleUsage: roleUsage,
    saveRole: saveRole, duplicateRole: duplicateRole, deleteRole: deleteRole,
    can: can, grantedCount: grantedCount,
    allPermissionIds: allPermissionIds, permissionMeta: permissionMeta, isSensitive: isSensitive,

    shiftsOn: shiftsOn, worksOn: worksOn, isOff: isOff, todayText: todayText,
    hoursSummary: hoursSummary, weeklyMinutes: weeklyMinutes,
    toMin: toMin, fromMin: fromMin, timeLabel: timeLabel, dayIdOf: dayIdOf,

    auditFor: auditFor, auditAll: auditAll, log: log,
    search: search, applyFilters: applyFilters,

    statusMeta: statusMeta, statusLabel: statusLabel, colorHex: colorHex,
    locationText: locationText, serviceCount: serviceCount, esc: esc
  };
}());
