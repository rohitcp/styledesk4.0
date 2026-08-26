/* StyleDesk — Client management
   ------------------------------------------------------------------
   Shared behaviour for the All Clients directory, the Add/Edit Client
   drawer and the client profile.

   Depends on window.SD (assets/js/styledesk.js) for the combobox, the
   phone field and the field-error plumbing, so the client screens use
   exactly the same controls as onboarding.

   Everything here is front-end only. The record store is a localStorage
   draft seeded with demo clients so the screens can be clicked through;
   in the Laravel build every read/write becomes an API call scoped to
   the current tenant, and the seed goes away.
   ------------------------------------------------------------------ */

window.SDC = (function () {
  'use strict';

  var KEY = 'styledesk.clients';
  var memory = null;

  /* ---------- Reference data --------------------------------------
     Server-owned in the real build: staff comes from the tenant's team,
     sources and tags from tenant settings. */

  /* The roster itself now lives in SD, so Services and Resources can
     use the same one. Clients prepends its own "No preference" entry,
     which is a booking affordance rather than a member of the team. */
  var STAFF = [{ id: 'any', name: 'No preference' }].concat(SD.STAFF);

  var SOURCES = [
    'Walk-in', 'Referral', 'Online booking', 'Instagram', 'Google search',
    'Facebook', 'Existing client', 'Event / pop-up', 'Other'
  ];

  var TAGS = [
    'VIP', 'New client', 'Colour', 'Keratin', 'Sensitive scalp', 'Allergy noted',
    'Prefers morning', 'Prefers evening', 'No-show risk', 'Student discount',
    'Membership', 'Bridal'
  ];

  var STATUSES = [
    { id: 'active',   label: 'Active',   badge: 'sd-badge--active'   },
    { id: 'inactive', label: 'Inactive', badge: 'sd-badge--inactive' },
    { id: 'lead',     label: 'Lead',     badge: 'sd-badge--lead'     },
    { id: 'blocked',  label: 'Blocked',  badge: 'sd-badge--blocked'  }
  ];

  var GENDERS = ['Female', 'Male', 'Non-binary', 'Prefer not to say'];

  /* ---------- Contact methods --------------------------------------
     A client has many emails and many phones. Each record carries its
     own type, its own primary flag and its own communication
     preferences — a client may want texts on their mobile but not on
     the salon line they share with work.

     Type and Primary are different things and must stay different:
     TYPE says what kind of number it is (Mobile / Home / Work), PRIMARY
     says which record StyleDesk reaches for by default. A record can be
     "Primary · Work" or "Home" with no primary at all. */

  var EMAIL_TYPES = ['Personal', 'Home', 'Work', 'Other'];

  /* Mobile leads: for a salon it is the number that carries SMS
     reminders, so it is the sensible default for a new row. */
  var PHONE_TYPES = ['Mobile', 'Home', 'Work', 'Other'];

  var EMAIL_PREFS = [
    { id: 'appointmentEmailEnabled', label: 'Appointment emails', hint: 'Confirmations, reminders and receipts' },
    { id: 'marketingConsent',        label: 'Marketing emails',   hint: 'Campaigns and offers. Opt-in only' }
  ];

  var PHONE_PREFS = [
    { id: 'smsEnabled',            label: 'Can receive SMS',  hint: 'Turn off for a landline' },
    { id: 'appointmentSmsEnabled', label: 'Appointment texts', hint: 'Confirmations and reminders', requires: 'smsEnabled' },
    { id: 'marketingConsent',      label: 'Marketing texts',   hint: 'Campaigns and offers. Opt-in only', requires: 'smsEnabled' }
  ];

  /* ---------- Contact record builders ------------------------------ */

  var seq = 0;
  function contactId(prefix) {
    seq += 1;
    return prefix + seq + '-' + (seq * 7919 % 100000);
  }

  function makeEmail(data, id) {
    return {
      id: id || contactId('e'),
      email: String((data && data.email) || '').trim(),
      type: (data && data.type) || 'Personal',
      isPrimary: !!(data && data.isPrimary),
      isVerified: !!(data && data.isVerified),
      appointmentEmailEnabled: data && data.appointmentEmailEnabled !== undefined ? !!data.appointmentEmailEnabled : true,
      marketingConsent: !!(data && data.marketingConsent),
      consentAt: (data && data.consentAt) || null,
      active: data && data.active !== undefined ? !!data.active : true
    };
  }

  function makePhone(data, id) {
    var smsOn = data && data.smsEnabled !== undefined ? !!data.smsEnabled : true;
    return {
      id: id || contactId('p'),
      iso: (data && data.iso) || 'US',
      dial: (data && data.dial) || '+1',
      national: String((data && data.national) || '').trim(),
      type: (data && data.type) || 'Mobile',
      isPrimary: !!(data && data.isPrimary),
      isVerified: !!(data && data.isVerified),
      smsEnabled: smsOn,
      appointmentSmsEnabled: smsOn && (data && data.appointmentSmsEnabled !== undefined ? !!data.appointmentSmsEnabled : true),
      marketingConsent: smsOn && !!(data && data.marketingConsent),
      consentAt: (data && data.consentAt) || null,
      active: data && data.active !== undefined ? !!data.active : true
    };
  }

  /* ---------- Demo records ---------------------------------------- */

  function seed() {
    var rows = [
      ['Amelia',  'Hart',      'amelia.hart@example.com',    '2025551043', 'active',   ['VIP', 'Colour'],            'u1', 'Referral',       '2026-08-12', 34, 4180, '2023-02-11'],
      ['Noah',    'Kaur',      'noah.kaur@example.com',      '2025558821', 'active',   ['Prefers morning'],          'u3', 'Online booking', '2026-08-19', 12, 960,  '2024-06-02'],
      ['Sofia',   'Marchetti', 'sofia.m@example.com',        '2025554417', 'active',   ['Keratin', 'VIP'],           'u2', 'Instagram',      '2026-08-21', 21, 3240, '2023-09-18'],
      ['Elijah',  'Brooks',    'e.brooks@example.com',       '2025557734', 'inactive', [],                           'u4', 'Walk-in',        '2025-11-04', 3,  185,  '2025-08-30'],
      ['Priya',   'Nandakumar','priya.n@example.com',        '2025552290', 'active',   ['Membership'],               'u2', 'Referral',       '2026-08-20', 47, 5890, '2022-04-07'],
      ['Mateo',   'Alvarez',   'mateo.alvarez@example.com',  '2025556612', 'active',   ['Student discount'],         'u5', 'Google search',  '2026-07-30', 8,  420,  '2025-10-14'],
      ['Grace',   'Okonkwo',   'grace.ok@example.com',       '2025553308', 'active',   ['Bridal', 'VIP'],            'u1', 'Event / pop-up', '2026-08-22', 15, 4620, '2024-01-22'],
      ['Henrik',  'Larsen',    'h.larsen@example.com',       '2025559925', 'lead',     ['New client'],               'any','Facebook',       '',          0,  0,    '2026-08-18'],
      ['Yuki',    'Tanaka',    'yuki.tanaka@example.com',    '2025551176', 'active',   ['Sensitive scalp'],          'u3', 'Online booking', '2026-08-14', 19, 1740, '2024-03-09'],
      ['Isabella','Moreau',    'i.moreau@example.com',       '2025554063', 'active',   ['Colour', 'Prefers evening'],'u2', 'Instagram',      '2026-08-16', 26, 3110, '2023-07-25'],
      ['Daniel',  'Osei',      'daniel.osei@example.com',    '2025557781', 'active',   [],                           'u5', 'Walk-in',        '2026-06-28', 6,  310,  '2025-12-01'],
      ['Chloe',   'Bennett',   'chloe.bennett@example.com',  '2025552847', 'active',   ['Allergy noted'],            'u4', 'Referral',       '2026-08-11', 31, 2980, '2023-05-16'],
      ['Omar',    'Haddad',    'omar.haddad@example.com',    '2025556130', 'inactive', [],                           'any','Google search',  '2025-09-19', 2,  120,  '2025-08-02'],
      ['Freya',   'Lindqvist', 'freya.l@example.com',        '2025553954', 'active',   ['VIP', 'Membership'],        'u1', 'Existing client','2026-08-23', 52, 7240, '2021-11-30'],
      ['Arjun',   'Mehta',     'arjun.mehta@example.com',    '2025558402', 'active',   ['Prefers morning'],          'u3', 'Online booking', '2026-08-09', 11, 690,  '2025-02-13'],
      ['Nina',    'Petrov',    'nina.petrov@example.com',    '2025551598', 'lead',     ['New client'],               'any','Instagram',      '',          0,  0,    '2026-08-21'],
      ['Tomas',   'Novak',     't.novak@example.com',        '2025557246', 'active',   [],                           'u5', 'Walk-in',        '2026-05-17', 9,  545,  '2024-10-08'],
      ['Leila',   'Zadeh',     'leila.zadeh@example.com',    '2025554871', 'active',   ['Keratin'],                  'u2', 'Referral',       '2026-08-18', 23, 2760, '2023-12-04'],
      ['Marcus',  'Whitfield', 'm.whitfield@example.com',    '2025559037', 'blocked',  ['No-show risk'],             'any','Facebook',       '2026-01-22', 4,  0,    '2025-06-11'],
      ['Ava',     'Rodrigues', 'ava.rodrigues@example.com',  '2025552715', 'active',   ['Colour'],                   'u1', 'Instagram',      '2026-08-13', 17, 1980, '2024-08-27'],
      ['Sebastian','Fischer',  's.fischer@example.com',      '2025556489', 'active',   [],                           'u4', 'Online booking', '2026-07-21', 14, 1120, '2024-04-19'],
      ['Rania',   'Aziz',      'rania.aziz@example.com',     '2025553162', 'active',   ['VIP', 'Bridal'],            'u2', 'Referral',       '2026-08-20', 29, 5410, '2022-09-05'],
      ['Felix',   'Andersen',  'felix.a@example.com',        '2025558950', 'inactive', [],                           'u3', 'Walk-in',        '2025-10-30', 5,  275,  '2025-05-23'],
      ['Zoe',     'Kavanagh',  'zoe.kavanagh@example.com',   '2025554326', 'active',   ['Membership', 'Colour'],     'u1', 'Existing client','2026-08-22', 38, 4470, '2022-12-16'],
      ['Ibrahim', 'Diallo',    'i.diallo@example.com',       '2025557508', 'active',   ['Prefers evening'],          'u5', 'Google search',  '2026-08-06', 10, 620,  '2025-03-28'],
      ['Hannah',  'Lieberman', 'hannah.l@example.com',       '2025551884', 'lead',     ['New client'],               'any','Event / pop-up', '',          0,  0,    '2026-08-23']
    ];

    /* A handful of clients get a second or third contact method, so the
       multi-contact screens have something real to show — and so search
       and duplicate detection can be exercised against a NON-primary
       record, which is the case that actually regresses. */
    var extras = {
      0:  { emails: [['amelia.hart@work.example.com', 'Work']], phones: [['2025557001', 'Home', false]] },
      2:  { emails: [['sofia.marchetti@company.example.com', 'Work'], ['marchetti.family@example.com', 'Home']] },
      4:  { phones: [['2025557002', 'Work', false], ['2025557003', 'Home', false]] },
      6:  { emails: [['grace.okonkwo@studio.example.com', 'Work']], phones: [['2025557004', 'Work', true]] },
      13: { emails: [['freya@lindqvist-design.example.com', 'Work']], phones: [['2025557005', 'Home', false]] },
      21: { phones: [['2025557006', 'Work', false]] }
    };

    return rows.map(function (r, i) {
      var ex = extras[i] || {};

      var emails = [makeEmail({
        email: r[2],
        type: 'Personal',
        isPrimary: true,
        isVerified: i % 3 !== 0,
        marketingConsent: i % 3 !== 0
      }, 'c' + (1000 + i) + '-e0')];

      (ex.emails || []).forEach(function (e, n) {
        emails.push(makeEmail({
          email: e[0], type: e[1], isPrimary: false,
          isVerified: false, marketingConsent: false
        }, 'c' + (1000 + i) + '-e' + (n + 1)));
      });

      var phones = [makePhone({
        iso: 'US', dial: '+1', national: r[3],
        type: 'Mobile', isPrimary: true,
        isVerified: i % 4 === 0,
        smsEnabled: true, appointmentSmsEnabled: true,
        marketingConsent: i % 4 === 0
      }, 'c' + (1000 + i) + '-p0')];

      (ex.phones || []).forEach(function (pRow, n) {
        phones.push(makePhone({
          iso: 'US', dial: '+1', national: pRow[0],
          type: pRow[1], isPrimary: false,
          isVerified: false,
          smsEnabled: pRow[2], appointmentSmsEnabled: pRow[2],
          marketingConsent: false
        }, 'c' + (1000 + i) + '-p' + (n + 1)));
      });

      return {
        id: 'c' + (1000 + i),
        firstName: r[0],
        lastName: r[1],
        preferredName: '',
        emails: emails,
        phones: phones,
        status: r[4],
        tags: r[5],
        preferredStaff: r[6],
        source: r[7],
        lastVisit: r[8],
        visits: r[9],
        spend: r[10],
        createdAt: r[11],
        birthday: '',
        gender: '',
        address: null,
        notes: '',
        photo: null,
        deletedAt: null
      };
    });
  }

  /* ---------- Store ------------------------------------------------ */

  /* Clients created before contact methods became their own records
     carry `email` / `mobile` / `altPhone` and a client-level `consents`
     block. Fold those forward on read so a store written by an earlier
     build still opens. Stands in for the Laravel migration that will
     backfill client_emails and client_phone_numbers. */
  function migrate(c) {
    if (!c || (Array.isArray(c.emails) && Array.isArray(c.phones))) return c;

    var old = c.consents || {};

    if (!Array.isArray(c.emails)) {
      c.emails = c.email ? [makeEmail({
        email: c.email,
        type: 'Personal',
        isPrimary: true,
        appointmentEmailEnabled: old.appt_reminders !== false,
        marketingConsent: !!old.email_marketing
      })] : [];
    }

    if (!Array.isArray(c.phones)) {
      c.phones = [];
      if (c.mobile && c.mobile.national) {
        c.phones.push(makePhone({
          iso: c.mobile.iso, dial: c.mobile.dial, national: c.mobile.national,
          type: 'Mobile', isPrimary: true,
          smsEnabled: true,
          appointmentSmsEnabled: old.appt_reminders !== false,
          marketingConsent: !!old.sms_marketing
        }));
      }
      if (c.altPhone && c.altPhone.national) {
        // The old "alternate phone" had no type of its own, and guessing
        // Home or Work would invent information. Other is honest.
        c.phones.push(makePhone({
          iso: c.altPhone.iso, dial: c.altPhone.dial, national: c.altPhone.national,
          type: 'Other', isPrimary: false, smsEnabled: false
        }));
      }
    }

    delete c.email;
    delete c.mobile;
    delete c.altPhone;
    delete c.consents;
    return enforcePrimary(c);
  }

  function readAll() {
    if (memory) return memory;
    try {
      var raw = window.localStorage.getItem(KEY);
      memory = raw ? JSON.parse(raw) : seed();
    } catch (e) {
      memory = seed();                       // private mode / bad JSON
    }
    if (!Array.isArray(memory) || !memory.length) memory = seed();

    // Fold any old-shape records forward and persist the result once,
    // rather than re-deriving it on every page load.
    var changed = memory.some(function (c) {
      return c && !(Array.isArray(c.emails) && Array.isArray(c.phones));
    });
    memory = memory.map(migrate);
    if (changed) writeAll(memory);

    return memory;
  }

  function writeAll(rows) {
    memory = rows;
    try { window.localStorage.setItem(KEY, JSON.stringify(rows)); } catch (e) { /* session only */ }
    return rows;
  }

  /* Soft-deleted clients stay in the store (§30) and are only surfaced
     through the "Deleted" filter, never in the default list. */
  function live() {
    return readAll().filter(function (c) { return !c.deletedAt; });
  }

  function byId(id) {
    var found = readAll().filter(function (c) { return c.id === id; });
    return found.length ? found[0] : null;
  }

  function nextId() {
    var max = 1000;
    readAll().forEach(function (c) {
      var n = parseInt(String(c.id).replace(/\D/g, ''), 10);
      if (!isNaN(n) && n > max) max = n;
    });
    return 'c' + (max + 1);
  }

  function create(record) {
    var rows = readAll();
    var row = Object.assign({
      id: nextId(),
      firstName: '', lastName: '', preferredName: '',
      emails: [], phones: [],
      status: 'active', tags: [], preferredStaff: 'any', source: '',
      lastVisit: '', visits: 0, spend: 0,
      createdAt: today(), birthday: '', gender: '', address: null,
      notes: '', photo: null, deletedAt: null
    }, record || {});
    rows.unshift(enforcePrimary(row));
    writeAll(rows);
    return row;
  }

  function update(id, changes) {
    var rows = readAll();
    for (var i = 0; i < rows.length; i++) {
      if (rows[i].id === id) {
        rows[i] = enforcePrimary(Object.assign({}, rows[i], changes));
        writeAll(rows);
        return rows[i];
      }
    }
    return null;
  }

  /* ---------- The one-primary rule ---------------------------------
     Enforced in one place, on every write, rather than trusted to each
     caller: at most one primary per list, and never zero while an
     active record exists. The database will carry the same constraint
     (a partial unique index on client_id where is_primary). */

  function enforceList(list) {
    if (!Array.isArray(list)) return [];
    var actives = list.filter(function (r) { return r.active !== false && hasValue(r); });
    var primaries = actives.filter(function (r) { return r.isPrimary; });

    if (primaries.length > 1) {
      // Keep the first, demote the rest.
      primaries.slice(1).forEach(function (r) { r.isPrimary = false; });
    } else if (primaries.length === 0 && actives.length) {
      actives[0].isPrimary = true;
    }
    // An inactive or empty record can never hold the flag.
    list.forEach(function (r) {
      if (r.active === false || !hasValue(r)) r.isPrimary = false;
    });
    return list;
  }

  function hasValue(r) {
    return !!(r && (String(r.email || '').trim() || String(r.national || '').trim()));
  }

  function enforcePrimary(client) {
    if (!client) return client;
    client.emails = enforceList(client.emails);
    client.phones = enforceList(client.phones);
    return client;
  }

  /* ---------- Contact accessors + mutations ------------------------- */

  function primaryEmail(c) {
    var list = (c && c.emails) || [];
    return list.filter(function (e) { return e.isPrimary && e.active !== false; })[0]
        || list.filter(function (e) { return e.active !== false; })[0]
        || null;
  }

  function primaryPhone(c) {
    var list = (c && c.phones) || [];
    return list.filter(function (p) { return p.isPrimary && p.active !== false; })[0]
        || list.filter(function (p) { return p.active !== false; })[0]
        || null;
  }

  /* Every address / number on the record, primary or not — this is what
     search and duplicate detection have to look at. */
  function allEmails(c) { return ((c && c.emails) || []).filter(hasValue); }
  function allPhones(c) { return ((c && c.phones) || []).filter(hasValue); }

  function setPrimary(clientId, kind, contactId) {
    var c = byId(clientId);
    if (!c) return null;
    var list = kind === 'email' ? c.emails : c.phones;
    list.forEach(function (r) { r.isPrimary = r.id === contactId && r.active !== false; });
    return update(clientId, kind === 'email' ? { emails: list } : { phones: list });
  }

  function setContactFlag(clientId, kind, contactId, field, value) {
    var c = byId(clientId);
    if (!c) return null;
    var list = kind === 'email' ? c.emails : c.phones;
    list.forEach(function (r) {
      if (r.id !== contactId) return;
      r[field] = value;
      // SMS is the master switch: turning it off takes the two things
      // that depend on it with it, so the record can't claim to send
      // appointment texts to a number that can't receive any.
      if (field === 'smsEnabled' && !value) {
        r.appointmentSmsEnabled = false;
        r.marketingConsent = false;
      }
      if (field === 'active' && !value) r.isPrimary = false;
    });
    return update(clientId, kind === 'email' ? { emails: list } : { phones: list });
  }

  /* §14: removing the primary while other methods exist must not leave
     the client without one. enforcePrimary promotes the next active
     record; the caller is told which so it can say so out loud rather
     than silently reassigning something behind the user's back. */
  function removeContact(clientId, kind, contactId) {
    var c = byId(clientId);
    if (!c) return null;
    var list = (kind === 'email' ? c.emails : c.phones);
    var gone = list.filter(function (r) { return r.id === contactId; })[0];
    var next = list.filter(function (r) { return r.id !== contactId; });
    var updated = update(clientId, kind === 'email' ? { emails: next } : { phones: next });
    var promoted = null;
    if (gone && gone.isPrimary) {
      promoted = kind === 'email' ? primaryEmail(updated) : primaryPhone(updated);
    }
    return { client: updated, removed: gone, promoted: promoted };
  }

  function softDelete(id) { return update(id, { deletedAt: today() }); }
  function restore(id)    { return update(id, { deletedAt: null }); }

  function resetStore() { memory = null; try { window.localStorage.removeItem(KEY); } catch (e) {} return readAll(); }

  /* ---------- Duplicate detection (§19) ----------------------------
     Matches on normalised email and on the last 10 digits of the phone,
     so "+1 (202) 555-1043" and "2025551043" are the same person. The
     check is advisory: the user can always continue with a new record. */

  function digits(v) { return String(v || '').replace(/\D/g, ''); }

  function phoneKey(phone) {
    if (!phone) return '';
    var d = digits(typeof phone === 'string' ? phone : (phone.dial || '') + (phone.national || ''));
    return d.length > 10 ? d.slice(-10) : d;
  }

  function emailKey(v) { return String(v || '').trim().toLowerCase(); }

  /* §11: the check spans EVERY address and number on both sides, not
     just the primary ones. A work email that was never primary is still
     the same person, and missing that is how duplicate records get made.

     `candidate` takes { id, emails: [...], phones: [...] } — the whole
     in-progress form, so a match on the third row is caught too. */
  function duplicates(candidate) {
    candidate = candidate || {};
    var skipId = candidate.id || null;

    var wantEmails = (candidate.emails || []).map(function (e) { return emailKey(e.email); }).filter(Boolean);
    var wantPhones = (candidate.phones || []).map(phoneKey).filter(function (k) { return k.length >= 7; });
    if (!wantEmails.length && !wantPhones.length) return [];

    var out = [];
    live().forEach(function (c) {
      if (skipId && c.id === skipId) return;
      var matches = [];

      allEmails(c).forEach(function (e) {
        if (wantEmails.indexOf(emailKey(e.email)) !== -1) {
          matches.push({ kind: 'email', record: e, label: contactLabel(e) + ' email', value: e.email });
        }
      });
      allPhones(c).forEach(function (p) {
        if (wantPhones.indexOf(phoneKey(p)) !== -1) {
          matches.push({ kind: 'phone', record: p, label: contactLabel(p) + ' number', value: phoneText(p) });
        }
      });

      if (matches.length) {
        out.push({
          client: c,
          matches: matches,
          // Kept for the copy: "already has the same email address and mobile number".
          why: matches.map(function (m) {
            return m.kind === 'email' ? 'email address' : 'phone number';
          }).filter(function (v, i, a) { return a.indexOf(v) === i; })
        });
      }
    });
    return out;
  }

  /* "Primary · Mobile" where it is primary, plain "Work" where it is
     not — §22's distinction, rendered the same way everywhere. */
  function contactLabel(r) {
    if (!r) return '';
    return r.isPrimary ? 'Primary · ' + r.type : r.type;
  }

  /* ---------- Formatting ------------------------------------------- */

  function today() {
    var d = new Date();
    return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate());
  }
  function pad(n) { return (n < 10 ? '0' : '') + n; }

  function fullName(c) {
    if (!c) return '';
    return [c.firstName, c.lastName].filter(Boolean).join(' ').trim();
  }

  function displayName(c) {
    if (!c) return '';
    return c.preferredName ? c.preferredName + ' ' + (c.lastName || '') : fullName(c);
  }

  function initials(c) {
    var a = (c.firstName || '').trim().charAt(0);
    var b = (c.lastName || '').trim().charAt(0);
    return (a + b).toUpperCase() || '?';
  }

  function money(n) {
    var v = Number(n) || 0;
    return '$' + v.toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
  }

  var MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

  function dateShort(iso) {
    if (!iso) return '';
    var p = String(iso).split('-');
    if (p.length < 3) return iso;
    return MONTHS[Number(p[1]) - 1] + ' ' + Number(p[2]) + ', ' + p[0];
  }

  function phoneText(phone) {
    if (!phone) return '';
    if (typeof phone === 'string') return phone;
    var country = null;
    (window.SD && SD.COUNTRIES || []).forEach(function (c) { if (c.iso === phone.iso) country = c; });
    var national = country && window.SD
      ? SD.formatPhone(digits(phone.national), country.mask)
      : phone.national;
    return ((phone.dial || '') + ' ' + (national || '')).trim();
  }

  /* Convenience for the directory and any single-line rendering. */
  function primaryPhoneText(c) { return phoneText(primaryPhone(c)); }
  function primaryEmailText(c) { var e = primaryEmail(c); return e ? e.email : ''; }

  function staffName(id) {
    var hit = STAFF.filter(function (s) { return s.id === id; });
    return hit.length ? hit[0].name : '';
  }

  function statusMeta(id) {
    var hit = STATUSES.filter(function (s) { return s.id === id; });
    return hit.length ? hit[0] : STATUSES[0];
  }

  function esc(v) {
    return String(v == null ? '' : v)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
  }

  /* ---------- Query ------------------------------------------------
     Search spans name, email and phone digits (§13), so typing the last
     four digits of a number finds the client at the desk. */

  /* §21: search spans every email and every phone on the record, not
     just the primary pair — a client whose work email is secondary must
     still come back when you search that address. Plus name, preferred
     name, tags and the client id. */
  function search(rows, q) {
    var needle = String(q || '').trim().toLowerCase();
    if (!needle) return rows;
    var nDigits = digits(needle);
    return rows.filter(function (c) {
      var hay = [
        fullName(c), c.preferredName, c.id,
        allEmails(c).map(function (e) { return e.email; }).join(' '),
        (c.tags || []).join(' ')
      ].join(' ').toLowerCase();
      if (hay.indexOf(needle) !== -1) return true;

      if (nDigits.length >= 3) {
        return allPhones(c).some(function (p) { return phoneKey(p).indexOf(nDigits) !== -1; });
      }
      return false;
    });
  }

  function applyFilters(rows, f) {
    f = f || {};
    return rows.filter(function (c) {
      if (f.status && f.status.length && f.status.indexOf(c.status) === -1) return false;
      if (f.tags && f.tags.length) {
        var has = f.tags.every(function (t) { return (c.tags || []).indexOf(t) !== -1; });
        if (!has) return false;
      }
      if (f.staff && f.staff.length && f.staff.indexOf(c.preferredStaff) === -1) return false;
      if (f.source && f.source.length && f.source.indexOf(c.source) === -1) return false;
      return true;
    });
  }

  var SORTS = {
    name:      function (c) { return fullName(c).toLowerCase(); },
    created:   function (c) { return c.createdAt || ''; },
    lastVisit: function (c) { return c.lastVisit || ''; },
    visits:    function (c) { return Number(c.visits) || 0; },
    spend:     function (c) { return Number(c.spend) || 0; }
  };

  function sortRows(rows, key, dir) {
    var get = SORTS[key] || SORTS.name;
    var sign = dir === 'desc' ? -1 : 1;
    return rows.slice().sort(function (a, b) {
      var x = get(a), y = get(b);
      if (x < y) return -1 * sign;
      if (x > y) return 1 * sign;
      return fullName(a).localeCompare(fullName(b));
    });
  }


  /* ---------- Public API ------------------------------------------- */

  return {
    STAFF: STAFF, SOURCES: SOURCES, TAGS: TAGS, STATUSES: STATUSES,
    GENDERS: GENDERS,
    EMAIL_TYPES: EMAIL_TYPES, PHONE_TYPES: PHONE_TYPES,
    EMAIL_PREFS: EMAIL_PREFS, PHONE_PREFS: PHONE_PREFS,
    makeEmail: makeEmail, makePhone: makePhone,
    primaryEmail: primaryEmail, primaryPhone: primaryPhone,
    primaryEmailText: primaryEmailText, primaryPhoneText: primaryPhoneText,
    allEmails: allEmails, allPhones: allPhones, contactLabel: contactLabel,
    setPrimary: setPrimary, setContactFlag: setContactFlag, removeContact: removeContact,
    emailKey: emailKey,

    readAll: readAll, writeAll: writeAll, live: live, byId: byId,
    create: create, update: update, softDelete: softDelete, restore: restore,
    resetStore: resetStore,

    duplicates: duplicates, phoneKey: phoneKey,
    search: search, applyFilters: applyFilters, sortRows: sortRows,

    fullName: fullName, displayName: displayName, initials: initials,
    money: money, dateShort: dateShort, phoneText: phoneText,
    staffName: staffName, statusMeta: statusMeta, esc: esc, today: today,

    /* The shared UI primitives moved to SD so every module can use
       them; re-exported here so existing call sites keep working. */
    drop: SD.drop, dropAll: SD.dropAll, closeAllDrops: SD.closeAllDrops,
    overlay: SD.overlay, tokens: SD.tokens, toast: SD.toast,
    flash: SD.flash, takeFlash: SD.takeFlash
  };
}());
