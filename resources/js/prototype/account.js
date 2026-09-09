/* StyleDesk — Account Settings
   ------------------------------------------------------------------
   The logged-in user's own settings: profile, preferences, password,
   notifications. Deliberately separate from Business Settings (§38) —
   nothing here touches tenant configuration, and a stylist changing
   their own preferences gains no business permissions by doing so.

   Front-end only. The store is a localStorage draft so the screens can
   be clicked through; in the Laravel build every read/write becomes a
   call scoped to the authenticated user.
   ------------------------------------------------------------------ */

window.SDA = (function () {
  'use strict';

  var KEY = 'styledesk.account';
  var memory = null;

  /* ---------- Sections (§7) ---------------------------------------- */

  var SECTIONS = [
    { id: 'profile',       label: 'My Profile',      url: 'account-profile.html' },
    { id: 'preferences',   label: 'Preferences',     url: 'account-preferences.html' },
    { id: 'password',      label: 'Change Password', url: 'account-password.html' },
    { id: 'notifications', label: 'Notifications',   url: 'account-notifications.html' }
  ];

  /* ---------- Reference data ---------------------------------------
     Tenant-owned in the real build: locations come from the business,
     currency from Business Settings (§18), roles from Staff Management. */

  var LOCATIONS = [
    { id: 'loc1', name: 'Downtown Salon' },
    { id: 'loc2', name: 'Westside Spa' },
    { id: 'loc3', name: 'Airport Location' }
  ];

  var LANDING_PAGES = [
    { id: 'dashboard',    label: 'Dashboard',    url: 'dashboard.html' },
    { id: 'calendar',     label: 'Calendar',     url: '#' },
    { id: 'appointments', label: 'Appointments', url: '#' },
    { id: 'clients',      label: 'Clients',      url: 'clients.html' }
  ];

  var TIMEZONES = [
    'America/New_York', 'America/Chicago', 'America/Denver', 'America/Los_Angeles',
    'America/Toronto', 'Europe/London', 'Europe/Dublin', 'Europe/Paris',
    'Europe/Berlin', 'Europe/Madrid', 'Australia/Sydney', 'Asia/Dubai', 'Asia/Kolkata'
  ];

  var DATE_FORMATS = [
    { id: 'mdy', label: 'MM/DD/YYYY', sample: '08/24/2026' },
    { id: 'dmy', label: 'DD/MM/YYYY', sample: '24/08/2026' },
    { id: 'iso', label: 'YYYY-MM-DD', sample: '2026-08-24' }
  ];

  var APPT_CARD_FIELDS = [
    { id: 'client',   label: 'Client name' },
    { id: 'service',  label: 'Service name' },
    { id: 'staff',    label: 'Staff member' },
    { id: 'duration', label: 'Appointment duration' },
    { id: 'status',   label: 'Appointment status' },
    { id: 'room',     label: 'Room / resource' },
    { id: 'price',    label: 'Price' }
  ];

  var PRONOUNS = ['She / her', 'He / him', 'They / them', 'Prefer not to say'];

  /* ---------- Notifications (§26–§34) ------------------------------
     In-App and Email ship now; SMS and Push are designed for but not
     enabled, so their columns render as "Later" rather than as controls
     that do nothing. */

  var CHANNELS = [
    { id: 'inapp', label: 'In-App', live: true },
    { id: 'email', label: 'Email',  live: true },
    { id: 'sms',   label: 'SMS',    live: false },
    { id: 'push',  label: 'Push',   live: false }
  ];

  var NOTIFICATION_GROUPS = [
    {
      id: 'appointments',
      title: 'Appointments',
      note: 'What happens to bookings you can see.',
      items: [
        { id: 'appt_new',        label: 'New appointment booked' },
        { id: 'appt_assigned',   label: 'Appointment assigned to me' },
        { id: 'appt_unassigned', label: 'Appointment unassigned from me' },
        { id: 'appt_moved',      label: 'Appointment rescheduled' },
        { id: 'appt_cancelled',  label: 'Appointment cancelled' },
        { id: 'appt_checkin',    label: 'Appointment checked in' },
        { id: 'appt_noshow',     label: 'Appointment marked no-show' },
        { id: 'appt_done',       label: 'Appointment completed' }
      ]
    },
    {
      id: 'clients',
      title: 'Clients',
      note: 'Only clients your permissions let you see.',
      items: [
        { id: 'client_new',      label: 'New client created' },
        { id: 'client_assigned', label: 'Client assigned to me' },
        { id: 'client_message',  label: 'Client sends a message' },
        { id: 'client_form',     label: 'Client submits a form' },
        { id: 'client_review',   label: 'Client leaves a review' }
      ]
    },
    {
      id: 'schedule',
      title: 'Schedule',
      note: 'Your own rota and time off.',
      items: [
        { id: 'sched_changed',  label: 'My schedule changed' },
        { id: 'shift_new',      label: 'New shift assigned' },
        { id: 'shift_changed',  label: 'Shift changed' },
        { id: 'timeoff_yes',    label: 'Time-off approved' },
        { id: 'timeoff_no',     label: 'Time-off rejected' }
      ]
    },
    {
      id: 'payments',
      title: 'Payments',
      note: 'Shown because your role includes financial permissions.',
      requiresFinance: true,
      items: [
        { id: 'pay_received', label: 'Payment received' },
        { id: 'pay_failed',   label: 'Payment failed', channels: ['inapp', 'email'] },
        { id: 'pay_refund',   label: 'Refund processed' },
        { id: 'pay_dispute',  label: 'Payment dispute / chargeback' }
      ]
    },
    {
      id: 'security',
      title: 'Account & security',
      note: 'Security alerts cannot be switched off. They also ignore digests and quiet hours.',
      items: [
        { id: 'sec_login',    label: 'New login or device', required: true },
        { id: 'sec_password', label: 'Password changed',    required: true },
        { id: 'sec_activity', label: 'Important security activity', required: true },
        { id: 'sys_notices',  label: 'Important account notices' },
        { id: 'sys_product',  label: 'Product updates' },
        { id: 'sys_business', label: 'Business announcements' }
      ]
    }
  ];

  var SCOPES = [
    { id: 'all',       label: 'Everything I have access to', hint: 'Every location and every client your role can see.' },
    { id: 'mine',      label: 'Only items assigned to me',   hint: 'Your own appointments, clients and shifts.' },
    { id: 'locations', label: 'Selected locations',          hint: 'Pick the locations you want to hear about.' }
  ];

  var FREQUENCIES = [
    { id: 'immediate', label: 'Immediately',   hint: 'Each event as it happens.' },
    { id: 'daily',     label: 'Daily digest',  hint: 'One email each morning.' },
    { id: 'weekly',    label: 'Weekly digest', hint: 'One email on Monday.' }
  ];

  /* ---------- Defaults --------------------------------------------- */

  function blank() {
    var notifications = {};
    NOTIFICATION_GROUPS.forEach(function (g) {
      g.items.forEach(function (item) {
        notifications[item.id] = {
          // Required rows are on and stay on (§32).
          inapp: item.required ? true : defaultOn(g.id, item.id, 'inapp'),
          email: item.required ? true : defaultOn(g.id, item.id, 'email'),
          sms: false,
          push: false
        };
      });
    });

    return {
      user: {
        firstName: 'Rohit',
        lastName: 'Philip',
        displayName: '',
        email: 'rohit@example.com',
        pendingEmail: '',
        mobile: { iso: 'US', dial: '+1', national: '2025550188' },
        jobTitle: 'Owner',
        pronouns: '',
        bio: '',
        preferredLocation: 'loc1',
        photo: null,
        // Read-only, owned by Staff Management (§9).
        staff: {
          role: 'Owner',
          locations: ['Downtown Salon', 'Westside Spa'],
          services: ['Cut & finish', 'Balayage', 'Keratin smoothing'],
          financePermission: true
        }
      },
      preferences: {
        language: 'en',
        timezoneMode: 'business',
        timezone: 'America/New_York',
        dateFormat: 'mdy',
        timeFormat: '12',
        weekStart: 'sun',
        defaultLocation: 'loc1',
        landingPage: 'dashboard',
        theme: 'system',
        calendarView: 'week',
        calendarDensity: 'comfortable',
        workingHours: 'business',
        calendarColor: 'service',
        apptCard: { client: true, service: true, staff: false, duration: true, status: true, room: false, price: false },
        clientNameOrder: 'firstLast'
      },
      notifications: notifications,
      delivery: {
        scope: 'all',
        scopeLocations: ['loc1', 'loc2'],
        frequency: 'immediate',
        quietHours: false,
        quietStart: '22:00',
        quietEnd: '07:00',
        quietUrgent: true
      },
      passwordChangedAt: '2026-05-02'
    };
  }

  /* Sensible starting point: the things you act on are on, the things
     that are merely nice to know are not. */
  function defaultOn(groupId, itemId, channel) {
    if (groupId === 'security') return true;
    if (itemId === 'sys_product' || itemId === 'sys_business') return channel === 'inapp';
    if (groupId === 'payments') return true;
    if (itemId === 'appt_checkin' || itemId === 'appt_done') return channel === 'inapp';
    if (itemId === 'client_review' || itemId === 'client_new') return channel === 'inapp';
    return true;
  }

  /* ---------- Store ------------------------------------------------ */

  function read() {
    if (memory) return memory;
    try {
      var raw = window.localStorage.getItem(KEY);
      memory = raw ? merge(blank(), JSON.parse(raw)) : blank();
    } catch (e) {
      memory = blank();
    }
    return memory;
  }

  /* Shallow-merges one level deep so a stored draft written before a
     new field existed still opens with that field at its default. */
  function merge(base, saved) {
    Object.keys(base).forEach(function (k) {
      if (saved[k] && typeof base[k] === 'object' && !Array.isArray(base[k])) {
        base[k] = Object.assign({}, base[k], saved[k]);
      } else if (saved[k] !== undefined) {
        base[k] = saved[k];
      }
    });
    return base;
  }

  function write(state) {
    memory = state;
    try { window.localStorage.setItem(KEY, JSON.stringify(state)); } catch (e) { /* session only */ }
    return state;
  }

  function patch(section, changes) {
    var s = read();
    s[section] = Object.assign({}, s[section], changes);
    return write(s);
  }

  function reset() {
    memory = null;
    try { window.localStorage.removeItem(KEY); } catch (e) {}
    return read();
  }

  /* ---------- Derived ---------------------------------------------- */

  function fullName() {
    var u = read().user;
    return [u.firstName, u.lastName].filter(Boolean).join(' ').trim();
  }

  function initials() {
    var u = read().user;
    return ((u.firstName || '').charAt(0) + (u.lastName || '').charAt(0)).toUpperCase() || '?';
  }

  function locationName(id) {
    var hit = LOCATIONS.filter(function (l) { return l.id === id; });
    return hit.length ? hit[0].name : '';
  }

  function hasFinance() {
    var s = read();
    return !!(s.user.staff && s.user.staff.financePermission);
  }

  /* Groups the current user should not see at all (§31). */
  function visibleGroups() {
    return NOTIFICATION_GROUPS.filter(function (g) {
      return !g.requiresFinance || hasFinance();
    });
  }

  /* ---------- Return-to (§5) ---------------------------------------
     Account Settings is opened from somewhere, and both Back and Close
     have to land back there. The opener is carried in the URL so a
     refresh keeps it; the referrer is the fallback, and the dashboard
     is the last resort. */

  var RETURN_KEY = 'styledesk.account.returnTo';
  /* A bare same-origin filename only: no scheme, no host, no path
     traversal. That keeps ?from= from being turned into an open
     redirect while still accepting every real page name. */
  var SAFE = /^[A-Za-z0-9_-]+\.html(\?[^#]*)?$/;

  function rememberReturn(url) {
    if (!url || !SAFE.test(url)) return;
    try { window.sessionStorage.setItem(RETURN_KEY, url); } catch (e) {}
  }

  function returnTo() {
    var fromParam = new URLSearchParams(window.location.search).get('from');
    if (fromParam && SAFE.test(fromParam)) return fromParam;

    try {
      var stored = window.sessionStorage.getItem(RETURN_KEY);
      if (stored && SAFE.test(stored)) return stored;
    } catch (e) {}

    var ref = document.referrer ? document.referrer.split('/').pop() : '';
    // Coming from another account section is not "where I came from".
    if (ref && SAFE.test(ref) && ref.indexOf('account-') !== 0) return ref;

    return 'dashboard.html';
  }

  function sectionUrl(id, from) {
    var hit = SECTIONS.filter(function (s) { return s.id === id; });
    var url = hit.length ? hit[0].url : SECTIONS[0].url;
    return from ? url + '?from=' + encodeURIComponent(from) : url;
  }

  /* ---------- Unsaved changes (§6) ---------------------------------
     One guard shared by every section: snapshot on load, compare on the
     way out, and offer the three choices the spec asks for. */

  function dirtyGuard(options) {
    options = options || {};
    var snapshot = options.snapshot || function () { return ''; };
    // Deliberately not taken here: the guard is constructed by the shell
    // before the section script has populated its fields, so the baseline
    // is captured by the first markClean() instead. Until then nothing
    // counts as dirty.
    var pristine = null;
    var saving = false;

    function isDirty() { return pristine !== null && !saving && snapshot() !== pristine; }
    function markClean() { pristine = snapshot(); saving = false; }

    function onBeforeUnload(e) {
      if (!isDirty()) return;
      e.preventDefault();
      e.returnValue = '';
    }
    window.addEventListener('beforeunload', onBeforeUnload);

    return {
      isDirty: isDirty,
      markClean: markClean,
      // Called just before a deliberate navigation so beforeunload stays quiet.
      release: function () { saving = true; },
      /* Runs `go` if clean; otherwise hands the decision to the caller's
         dialog. `onSave` is passed through so the dialog can offer
         "Save & continue" where the section supports it. */
      attempt: function (go) {
        if (!isDirty()) { saving = true; go(); return true; }
        if (options.onBlocked) options.onBlocked(go);
        return false;
      }
    };
  }

  function esc(v) {
    return String(v == null ? '' : v)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
  }

  return {
    SECTIONS: SECTIONS, LOCATIONS: LOCATIONS, LANDING_PAGES: LANDING_PAGES,
    TIMEZONES: TIMEZONES, DATE_FORMATS: DATE_FORMATS,
    APPT_CARD_FIELDS: APPT_CARD_FIELDS, PRONOUNS: PRONOUNS,
    CHANNELS: CHANNELS, NOTIFICATION_GROUPS: NOTIFICATION_GROUPS,
    SCOPES: SCOPES, FREQUENCIES: FREQUENCIES,

    read: read, write: write, patch: patch, reset: reset,
    fullName: fullName, initials: initials, locationName: locationName,
    hasFinance: hasFinance, visibleGroups: visibleGroups,

    rememberReturn: rememberReturn, returnTo: returnTo, sectionUrl: sectionUrl,
    /* One toast lives in SD; re-exported so call sites here need no change. */
    dirtyGuard: dirtyGuard, toast: SD.toast, esc: esc
  };
}());
