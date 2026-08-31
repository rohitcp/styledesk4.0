<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import MultiSelect from './MultiSelect.vue';

/**
 * The New Booking screen.
 *
 * One page on a 3/6/3 grid rather than a wizard: the client, the five
 * decisions, and a summary that ends in the commit. A receptionist with a
 * client on the phone answers those decisions in whatever order the client
 * says them — "actually, make it Thursday, and it's for a colour" — and a
 * wizard makes that impossible.
 *
 * Everything the page offers arrives with it. Only the client search goes to
 * the server, because a business has as many clients as it has and that is
 * the one list here that does not fit in a page.
 */
const props = defineProps({
    action: { type: String, required: true },
    csrf: { type: String, required: true },
    cancelUrl: { type: String, required: true },
    clientSearchUrl: { type: String, required: true },
    /** Where the history panel for one client is read from; :id is replaced. */
    clientContextUrl: { type: String, required: true },
    newClientUrl: { type: String, required: true },
    /** Where four fields' worth of client is posted. */
    createClientUrl: { type: String, required: true },
    /** Where a started-but-unfinished booking is noted down. */
    leadUrl: { type: String, default: '' },
    /** The lead this screen was opened from, when it was opened from one. */
    lead: { type: Object, default: null },
    /** The client it is being taken for, where the screen knows already. */
    client: { type: Object, default: null },
    /** Walk-in mode, when the screen was opened from the walk-in entry. */
    walkIn: { type: Boolean, default: false },
    services: { type: Array, default: () => [] },
    /** The categories something is actually offered in, id => name. */
    categories: { type: Object, default: () => ({}) },
    staff: { type: Array, default: () => [] },
    locations: { type: Array, default: () => [] },
    sources: { type: Object, default: () => ({}) },
    times: { type: Array, default: () => [] },
    today: { type: String, required: true },
    /** Whether this business reads its clock as 1:30 PM or 13:30. */
    use12Hours: { type: Boolean, default: true },
    currencySymbol: { type: String, default: '' },
    /** The ways this business can be paid, and whether each is set up. */
    methods: { type: Array, default: () => [] },
    /** The business's tax settings, for the bill shown before one exists. */
    tax: { type: Object, default: () => ({ rate: 0, behavior: 'none' }) },
    /** Where an edit to a held booking goes; :id is replaced. */
    updateUrlPattern: { type: String, default: '' },
    labels: { type: Object, default: () => ({}) },
});

/* One booking under construction. Every column reads it and writes to it, so
   nothing can be true in one place and false in another. */
const mode = ref(props.walkIn ? 'walkin' : 'booking');
const client = ref(null);
const guest = ref({ name: '', phone: '', email: '' });
const chosen = ref([]);
const staffId = ref('');
const locationId = ref(props.locations[0]?.id ?? '');
const date = ref(props.today);
const start = ref('');
const source = ref(props.walkIn ? 'walk-in' : 'front-desk');
const notes = ref('');
const clientNote = ref('');
const payType = ref('none');
const deposit = ref('');
const depositAction = ref('later');
const confirmation = ref('both');
const sending = ref(false);

/* Which section is open. One at a time: five accordions all open is the long
   form this screen exists to avoid. */
const open = ref('service');

function toggle(section) {
    open.value = open.value === section ? '' : section;
}

/* ------------------------------------------------------------- the steps ---

   Five cards worked through in order, each with its own way on.

   They are still accordions rather than a wizard — the whole point of this
   screen is that a client says "actually, make it Thursday" while you are on
   the payment step, and every card stays one click away. What Save & Continue
   adds is the default path: finish here, and the next question opens itself
   instead of being hunted for. */
const STEPS = ['service', 'when', 'details', 'payment', 'comms'];

const stepsDone = ref({});

/** What is missing before this step can be left, in the reader's words. */
function stepBlocker(step) {
    if (step === 'service') {
        return chosen.value.length ? null : props.labels.blockers?.service;
    }

    if (step === 'when') {
        return start.value ? null : props.labels.blockers?.time;
    }

    /* The last three carry defaults that are real answers — front desk,
       no deposit, tell them both ways — so there is nothing to insist on. */
    return null;
}

function saveStep(step) {
    if (stepBlocker(step)) {
        return;
    }

    stepsDone.value = { ...stepsDone.value, [step]: true };
    open.value = STEPS[STEPS.indexOf(step) + 1] ?? '';

    /* Every step reports in, not just the first: the lead is how the desk
       sees where a call ended, and "they stopped at payment" is a different
       call to return from "they stopped at service". */
    noteLead(STEPS[STEPS.indexOf(step) + 1] ?? 'completed');
}

/* The reference for a booking that has been started.
   Kept so saving the service step twice edits one lead rather than leaving
   two references for one conversation. */
const lead = ref(null);

/**
 * Write the lead down, in the background.
 *
 * Deliberately not awaited by the step it belongs to: the next card is
 * already open by the time this returns, and a receptionist mid-call must
 * never be held up — or stopped — by a note being filed. A failure here is
 * silent for the same reason; the booking itself is what matters, and it is
 * still perfectly takeable without a lead behind it.
 */
async function noteLead(step) {
    /* Nothing to note until there is something to note it about. */
    if (! props.leadUrl || ! chosen.value.length) {
        return;
    }

    try {
        const { ok, json } = await send(props.leadUrl, {
            lead_id: lead.value?.id ?? null,
            client_id: client.value?.id ?? null,
            guest_name: client.value ? null : (guest.value.name || null),
            services: chosen.value.map((service) => service.id),
            date: date.value,
            current_step: step,
            deposit: payType.value === 'deposit' ? deposit.value : null,
        });

        if (! ok) {
            return;
        }

        const created = lead.value === null;

        lead.value = json.lead;

        /* Said once, when the reference first exists. Saving the step again
           after an edit is the same conversation, and a second toast about
           it would read as a second booking. */
        if (created) {
            window.styledesk?.toast(
                (props.labels.lead?.created ?? '').replace(':reference', json.lead.reference),
            );
        }
    } catch (error) {
        /* Nothing to say: the note is not the booking. */
    }
}

// ----------------------------------------------------------------- client

const clientQuery = ref('');
const clientResults = ref([]);
const searching = ref(false);
let searchTimer = null;

watch(clientQuery, (term) => {
    window.clearTimeout(searchTimer);

    if (term.trim().length < 2) {
        clientResults.value = [];

        return;
    }

    /* Debounced, because this is the one thing on the screen that asks the
       server, and a request per keystroke is three requests per word. */
    searchTimer = window.setTimeout(async () => {
        searching.value = true;

        try {
            const response = await fetch(`${props.clientSearchUrl}?q=${encodeURIComponent(term)}`, {
                headers: { Accept: 'application/json' },
            });

            clientResults.value = (await response.json()).data ?? [];
        } catch (error) {
            clientResults.value = [];
        }

        searching.value = false;
    }, 250);
});

const context = ref(null);

async function chooseClient(found) {
    client.value = found;
    clientQuery.value = '';
    clientResults.value = [];
    mode.value = 'booking';
    context.value = null;

    /* The history the receptionist needs while they book: who this person
       usually sees, what they had last time, how they like to be booked. */
    try {
        const response = await fetch(props.clientContextUrl.replace(':id', found.id), {
            headers: { Accept: 'application/json' },
        });

        if (response.ok) {
            context.value = await response.json();
        }
    } catch (error) {
        context.value = null;
    }
}

function clearClient() {
    client.value = null;
    context.value = null;
}

// ------------------------------------------------------- new client dialog

/* Four fields, because that is what taking a booking needs. The rest of the
   record can be filled in later from their profile, and a receptionist with
   somebody on the phone will not fill it in now. */
const adding = ref(false);
const saving = ref(false);
const fresh = ref({ first_name: '', last_name: '', email: '', mobile: '' });
const errors = ref({});
const duplicates = ref([]);

function openNewClient() {
    /* Seeded from whatever was typed into the search: somebody who has just
       typed a name and found nothing should not type it again. */
    const typed = clientQuery.value.trim().split(/\s+/);

    fresh.value = {
        first_name: typed[0] ?? '',
        last_name: typed.slice(1).join(' '),
        email: '',
        mobile: '',
    };

    errors.value = {};
    duplicates.value = [];
    adding.value = true;
}

function closeNewClient() {
    adding.value = false;
    saving.value = false;
}

/**
 * @param {boolean} force Add anyway, having read the possible duplicates.
 */
async function saveNewClient(force = false) {
    saving.value = true;
    errors.value = {};

    try {
        const response = await fetch(props.createClientUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': props.csrf,
            },
            body: JSON.stringify({ ...fresh.value, confirm_duplicate: force }),
        });

        /* A warning, never a block: two people can share a phone, and the
           reader decides which of the two this is. */
        if (response.status === 409) {
            duplicates.value = (await response.json()).duplicates ?? [];
            saving.value = false;

            return;
        }

        if (response.status === 422) {
            errors.value = (await response.json()).errors ?? {};
            saving.value = false;

            return;
        }

        const created = (await response.json()).client;

        closeNewClient();
        await chooseClient(created);
    } catch (error) {
        saving.value = false;
    }
}

/* One of the panel's staff rows, chosen. The booking's own staff field and
   the panel are the same answer, so picking here is picking there. */
function pickStaff(id) {
    staffId.value = String(staffId.value) === String(id) ? '' : id;
}

/**
 * The last appointment again: its services, and the person who did it.
 *
 * Only the services that still exist — a discontinued one would prefill a
 * booking nobody can take — and the rest of the screen is left alone, because
 * the receptionist is still choosing the day.
 */
function bookAgain() {
    const again = context.value?.last?.again;

    if (!again) {
        return;
    }

    chosen.value = again.service_ids
        .map((id) => props.services.find((service) => service.id === id))
        .filter(Boolean);

    if (again.staff_id && props.staff.some((member) => member.id === again.staff_id)) {
        staffId.value = again.staff_id;
    }

    open.value = 'when';
}

// ---------------------------------------------------------------- service

const serviceQuery = ref('');

/* Narrowed by category as well as by name: a salon with sixty services is
   scrolled, not read, and "everything in Hair Color" is the way a
   receptionist thinks about it when the client has not named the service. */
const categoryIds = ref([]);

const matches = computed(() => {
    const term = serviceQuery.value.trim().toLowerCase();
    const picked = chosen.value.map((service) => service.id);
    const categories = categoryIds.value.map(String);

    return props.services
        .filter((service) => !picked.includes(service.id))
        .filter((service) => !categories.length || categories.includes(String(service.category_id)))
        .filter((service) => !term || service.name.toLowerCase().includes(term));
});

function addService(service) {
    chosen.value = [...chosen.value, service];
    serviceQuery.value = '';
}

function removeService(service) {
    chosen.value = chosen.value.filter((one) => one.id !== service.id);
}

const minutes = computed(() => chosen.value.reduce((sum, service) => sum + service.minutes, 0));

const totalMinor = computed(() => chosen.value.reduce((sum, service) => sum + service.price_minor, 0));

const money = (minor) => props.currencySymbol + (minor / 100).toFixed(2);

/* --------------------------------------------------------------- when ---

   The day, in the house date picker.

   A bare calendar field is the wrong shape for this screen: nearly every
   booking taken at the desk is today, tomorrow, or "sometime this week", and
   all three of those cost a click plus a date to read and type. So this is
   the design system's range picker turned on a single date — the presets
   answer the common cases outright and tint the days a vaguer one offers,
   and the month grid beside them still books the colour in October. */
const ranges = ['today', 'tomorrow', 'next_3', 'next_7', 'custom'];

/** Days added to an ISO date, read at noon so no daylight change moves it. */
function addDays(iso, days) {
    const at = new Date(`${iso}T12:00:00`);

    at.setDate(at.getDate() + days);

    return isoOf(at);
}

const isoOf = (at) => [
    at.getFullYear(),
    String(at.getMonth() + 1).padStart(2, '0'),
    String(at.getDate()).padStart(2, '0'),
].join('-');

const asDate = (iso) => new Date(`${iso}T12:00:00`);

const dateMode = ref('today');
const pickerOpen = ref(false);
const picker = ref(null);
const view = ref({ year: Number(props.today.slice(0, 4)), month: Number(props.today.slice(5, 7)) - 1 });

/* What a preset offers, counting from today: "next 3 days" that skipped
   today would be a range nobody means. One day for today and tomorrow, which
   is the chosen day itself and so needs no tint behind it. */
const presetDays = computed(() => {
    const span = { today: 1, tomorrow: 1, next_3: 3, next_7: 7 }[dateMode.value] ?? 0;
    const from = dateMode.value === 'tomorrow' ? 1 : 0;

    return Array.from({ length: span }, (_, day) => addDays(props.today, from + day));
});

function chooseRange(range) {
    dateMode.value = range;

    if (range === 'today' || range === 'tomorrow') {
        date.value = addDays(props.today, range === 'tomorrow' ? 1 : 0);
        pickerOpen.value = false;

        return;
    }

    /* Coming back from October with "next 7 days": the old date is not in the
       window, so nothing on the grid would look chosen. */
    if (range !== 'custom' && ! presetDays.value.includes(date.value)) {
        date.value = props.today;
    }

    showMonthOf(date.value);
}

function pickDay(day) {
    date.value = day;

    /* A day picked off the grid that no preset offers is a custom date,
       whatever the sidebar said a moment ago. */
    if (dateMode.value !== 'custom' && ! presetDays.value.includes(day)) {
        dateMode.value = 'custom';
    }

    pickerOpen.value = false;
}

function showMonthOf(iso) {
    view.value = { year: Number(iso.slice(0, 4)), month: Number(iso.slice(5, 7)) - 1 };
}

function shiftMonth(by) {
    const at = new Date(view.value.year, view.value.month + by, 1);

    view.value = { year: at.getFullYear(), month: at.getMonth() };
}



/* Weekday initials in the reader's language, from a known Sunday. */
const weekdays = computed(() => Array.from({ length: 7 }, (_, day) => new Date(2024, 8, 1 + day)
    .toLocaleDateString(undefined, { weekday: 'short' })
    .slice(0, 2)));

/** A month as cells, with blanks for the days before the first. */
function monthOf(year, month) {
    const first = new Date(year, month, 1);
    const count = new Date(year, month + 1, 0).getDate();

    return {
        label: first.toLocaleDateString(undefined, { month: 'long', year: 'numeric' }),
        days: [
            ...Array.from({ length: first.getDay() }, () => null),
            ...Array.from({ length: count }, (_, day) => isoOf(new Date(year, month, day + 1))),
        ],
    };
}

/* The month in view and the one after it. */
const months = computed(() => [0, 1].map((ahead) => {
    const at = new Date(view.value.year, view.value.month + ahead, 1);

    return monthOf(at.getFullYear(), at.getMonth());
}));

const shortDay = (iso) => asDate(iso)
    .toLocaleDateString(undefined, { weekday: 'short', day: 'numeric', month: 'short' });

/* Whichever way the day was chosen, the field says it back in full. A field
   reading "Next 7 days" is not an appointment date. */
const chosenDay = computed(() => asDate(date.value)
    .toLocaleDateString(undefined, { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }));

watch(pickerOpen, (open) => {
    if (open) {
        showMonthOf(date.value);
    }
});

/* Click anywhere else and the picker shuts, the way every other overlay on
   the screen does. */
function closePicker(event) {
    if (pickerOpen.value && picker.value && ! picker.value.contains(event.target)) {
        pickerOpen.value = false;
    }
}

/**
 * Pick up where the call left off.
 *
 * Returning to a lead reopens this screen with what had been chosen when it
 * dropped — the client, the services, the day they wanted — because retyping
 * all of it is exactly the work the lead exists to save. The same lead is
 * carried through, so finishing now converts it rather than filing a second.
 */
function resumeLead(from) {
    lead.value = { id: from.id, reference: from.reference };

    if (from.date) {
        date.value = from.date;
        dateMode.value = 'custom';
    }

    chosen.value = props.services.filter((service) => from.service_ids.includes(service.id));

    if (from.client) {
        chooseClient(from.client);
    } else if (from.guest_name) {
        mode.value = 'walkin';
        guest.value = { ...guest.value, name: from.guest_name };
    }

    /* Straight to the first unanswered question: the services are already
       chosen, so what this call still needs is a time. */
    if (chosen.value.length) {
        stepsDone.value = { ...stepsDone.value, service: true };
        open.value = 'when';
    }
}

onMounted(() => document.addEventListener('click', closePicker));
onBeforeUnmount(() => document.removeEventListener('click', closePicker));

if (props.lead) {
    resumeLead(props.lead);
} else if (props.client) {
    /* Opened from somebody's profile: they are already chosen, and their
       history is already on its way. */
    chooseClient(props.client);
}

const endsAt = computed(() => {
    if (!start.value || !minutes.value) {
        return '';
    }

    const [hours, mins] = start.value.split(':').map(Number);
    const end = new Date(2000, 0, 1, hours, mins + minutes.value);

    return `${String(end.getHours()).padStart(2, '0')}:${String(end.getMinutes()).padStart(2, '0')}`;
});

const chosenStaff = computed(() => props.staff.find((member) => String(member.id) === String(staffId.value)));

// -------------------------------------------------------------- the whole

const who = computed(() => {
    if (client.value) {
        return client.value.name;
    }

    return guest.value.name.trim() || null;
});

/* What is still missing, one thing at a time. A list of every unanswered
   question is a wall; the next one is an instruction. */
const blocker = computed(() => {
    if (!who.value) {
        return mode.value === 'walkin' ? props.labels.blockers?.guest : props.labels.blockers?.client;
    }

    if (!chosen.value.length) {
        return props.labels.blockers?.service;
    }

    if (!start.value) {
        return props.labels.blockers?.time;
    }

    return null;
});

const ready = computed(() => blocker.value === null);

const smsTo = computed(() => client.value?.mobile || guest.value.phone);
const emailTo = computed(() => client.value?.email || guest.value.email);

/**
 * Which of the two buttons was pressed, and nothing else.
 *
 * Deliberately not the place `sending` is set. Vue flushes that change in a
 * microtask, which lands before the browser dispatches the submit event — and
 * a disabled submitter never submits, so the button went grey and nothing was
 * ever posted. The form's own submit handler sets it instead, which runs once
 * the submission is already under way.
 */
function submit(asDraft) {
    draft.value = asDraft;
}

const draft = ref(false);

/* ----------------------------------------------------- the third column ---

   Summary → payment → confirmation, in one panel that never moves.

   The appointment is written down at the end of the first step rather than
   the last: what a receptionist is protecting while they ask "how are you
   paying?" is the slot, and a booking that only exists once the money does is
   a booking somebody else can take in the meantime. So Confirm Booking makes
   it, and payment is recorded against something real.

   Everything the panel shows after that comes from the server's own answer.
   The estimate below is only for the summary, which has to show a total
   before there is a booking to read one from. */
const stage = ref('summary');
const booking = ref(null);
const busy = ref(false);
const failure = ref('');
const method = ref('');
const payment = ref({ amount: '', received: '', reference: '' });
const card = ref({ name: '', number: '', expiry: '', cvv: '', zip: '' });
const lastPayment = ref(null);
const sentTo = ref('');

/* The bill before the booking exists, worked out the way the server works it
   out. Two implementations of one rule is a risk, so this one is used for
   exactly as long as there is nothing better: the moment the booking is
   taken, the panel shows the figures that were actually stored. */
const estimate = computed(() => {
    const subtotal = totalMinor.value;
    const rate = Number(props.tax?.rate ?? 0);
    const behavior = props.tax?.behavior ?? 'none';

    if (rate <= 0 || behavior === 'none') {
        return { subtotal, tax: 0, total: subtotal, included: false };
    }

    if (behavior === 'inclusive') {
        return { subtotal, tax: Math.round(subtotal - subtotal / (1 + rate / 100)), total: subtotal, included: true };
    }

    const tax = Math.round(subtotal * (rate / 100));

    return { subtotal, tax, total: subtotal + tax, included: false };
});

const taxLabel = computed(() => {
    const rate = String(Number(props.tax?.rate ?? 0)).replace(/\.00$/, '');
    const key = estimate.value.included ? 'tax_included' : 'tax';

    return (props.labels.summary?.[key] ?? '').replace(':rate', `${rate}%`);
});

/* The chairs and rooms the chosen services need, named once each. */
const resources = computed(() => [...new Set(chosen.value.flatMap((service) => service.resources ?? []))]);

const chosenMethod = computed(() => props.methods.find((row) => row.key === method.value) ?? null);

/**
 * "2:15 PM" or "14:15", from the stored "14:15".
 *
 * The value never changes — a slot is chosen and posted as 24-hour either
 * way. This is only what the chip says.
 */
function clock(slot) {
    if (! props.use12Hours) {
        return slot;
    }

    const [hours, minutes] = slot.split(':').map(Number);
    const suffix = hours < 12 ? 'AM' : 'PM';

    return `${((hours + 11) % 12) + 1}:${String(minutes).padStart(2, '0')} ${suffix}`;
}

/**
 * The day's start times in three windows.
 *
 * Sixty-odd chips in one run is a wall to read, and a receptionist is nearly
 * always looking inside one part of the day — "have you got anything after
 * lunch?". The boundaries are the ones App\Support\ClientBookingContext reads
 * a client's habits in, so "prefers afternoon appointments" in the panel on
 * the left means the same hours as the Afternoon heading on the right.
 */
const timeGroups = computed(() => {
    const windows = { morning: [], afternoon: [], evening: [] };

    props.times.forEach((slot) => {
        const hour = Number(slot.slice(0, 2));

        windows[hour < 12 ? 'morning' : hour < 17 ? 'afternoon' : 'evening'].push(slot);
    });

    /* An empty window is left out rather than shown as a heading with nothing
       under it: a salon that opens at noon has no morning. */
    return Object.entries(windows)
        .filter(([, slots]) => slots.length)
        .map(([key, slots]) => ({ key, slots }));
});

/**
 * Who this client is likely to want, named before the rest of the team.
 *
 * The client record already knows: somebody they asked for outranks somebody
 * they have merely seen, and both outrank a list in alphabetical order. Only
 * people who are still bookable are offered — a favourite who has left is a
 * suggestion nobody can act on.
 */
const suggestedStaff = computed(() => {
    const rows = [...(context.value?.preferred ?? []), ...(context.value?.also_seen ?? [])];

    return rows
        .filter((row) => props.staff.some((member) => String(member.id) === String(row.id)))
        .slice(0, 3);
});

const chosenLocation = computed(() => props.locations.find((place) => String(place.id) === String(locationId.value)) ?? null);

const dueMinor = computed(() => booking.value?.due_minor ?? estimate.value.total);

/* What is handed over minus what is owed. Shown live, because the number a
   receptionist needs is the one they are counting back into somebody's hand. */
const changeDue = computed(() => {
    const received = Math.round(Number(payment.value.received || 0) * 100);
    const due = Math.round(Number(payment.value.amount || 0) * 100);

    return received > due ? money(received - due) : money(0);
});

/** Everything the booking endpoints are told, in one shape. */
function bookingBody() {
    return {
        client_id: client.value?.id ?? null,
        guest_name: client.value ? null : guest.value.name,
        guest_phone: client.value ? null : guest.value.phone,
        guest_email: client.value ? null : guest.value.email,
        staff_id: staffId.value || null,
        location_id: locationId.value || null,
        date: date.value,
        starts_at: start.value,
        services: chosen.value.map((service) => service.id),
        source: source.value,
        payment_type: payType.value,
        deposit: payType.value === 'deposit' ? deposit.value : null,
        deposit_action: payType.value === 'deposit' ? depositAction.value : null,
        confirmation: confirmation.value,
        notes: notes.value,
        client_note: client.value ? clientNote.value : null,
        lead_id: lead.value?.id ?? null,
    };
}

async function send(url, body, method = 'POST') {
    const response = await fetch(url, {
        method,
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-CSRF-TOKEN': props.csrf,
        },
        body: JSON.stringify(body),
    });

    const json = await response.json().catch(() => ({}));

    return { ok: response.ok, json };
}

/** The first thing the server objected to, in its own words. */
const firstError = (json) => Object.values(json?.errors ?? {}).flat()[0] ?? json?.message ?? null;

/**
 * Take the booking, then ask for the money.
 *
 * Guarded twice against making two: the button is disabled while this runs,
 * and a booking already held is edited rather than taken again — which is
 * what makes "back to booking summary" safe.
 */
async function confirmBooking() {
    if (! ready.value || busy.value) {
        return;
    }

    busy.value = true;
    failure.value = '';

    const held = booking.value;
    const { ok, json } = held
        ? await send(props.updateUrlPattern.replace(':id', held.id), bookingBody(), 'PATCH')
        : await send(props.action, bookingBody());

    busy.value = false;

    if (! ok) {
        failure.value = firstError(json) ?? props.labels.pay?.failed;

        return;
    }

    booking.value = json.booking;
    payment.value.amount = json.booking.due_amount;
    stage.value = 'payment';
}

/**
 * Write down a payment.
 *
 * `manual` is the difference between money StyleDesk took and money somebody
 * says arrived. Everything but a card on a connected provider is the second
 * kind, and the button that records it says so.
 */
async function takePayment(manual) {
    if (busy.value || ! booking.value) {
        return;
    }

    busy.value = true;
    failure.value = '';

    const { ok, json } = await send(booking.value.urls.pay, {
        method: method.value,
        amount: payment.value.amount || booking.value.due_amount,
        received: method.value === 'cash' ? (payment.value.received || null) : null,
        reference: payment.value.reference || null,
        manual,
    });

    busy.value = false;

    if (! ok) {
        failure.value = firstError(json) ?? props.labels.pay?.failed;

        return;
    }

    booking.value = json.booking;
    lastPayment.value = json.payment;
    payment.value = { amount: json.booking.due_amount, received: '', reference: '' };
    card.value = { name: '', number: '', expiry: '', cvv: '', zip: '' };

    /* Part paid stays on this screen with the rest still owing; settled in
       full moves on. */
    if (json.booking.due_minor === 0) {
        method.value = '';
        stage.value = 'done';
    }
}

async function sendConfirmation(channel) {
    if (busy.value || ! booking.value) {
        return;
    }

    busy.value = true;
    failure.value = '';
    sentTo.value = '';

    const { ok, json } = await send(booking.value.urls.confirmation, { channel });

    busy.value = false;

    if (! ok) {
        failure.value = firstError(json) ?? props.labels.pay?.failed;

        return;
    }

    sentTo.value = (props.labels.confirmation?.sent ?? '').replace(':to', json.sent_to);
}

/**
 * Which of the three cards is open.
 *
 * They behave like the accordions in the middle column: heads stay visible so
 * the workflow is readable at a glance, and opening one closes the others.
 * Summary and payment can be reopened while the booking is still being
 * settled — that is what "back to booking summary" is — and both close for
 * good once the money is in, because a paid booking is a receipt and a
 * receipt is not something to edit.
 */
function openPanel(which) {
    if (which === 'payment' && ! booking.value) {
        return;
    }

    if (stage.value === 'done' && which !== 'done') {
        return;
    }

    stage.value = which;
}

/* A fresh screen, without a page load: the desk is usually taking the next
   booking from the same person who is still standing there. */
function startAnother() {
    client.value = null;
    context.value = null;
    guest.value = { name: '', phone: '', email: '' };
    mode.value = props.walkIn ? 'walkin' : 'booking';
    chosen.value = [];
    staffId.value = '';
    date.value = props.today;
    dateMode.value = 'today';
    start.value = '';
    notes.value = '';
    clientNote.value = '';
    payType.value = 'none';
    deposit.value = '';
    confirmation.value = 'both';
    booking.value = null;
    lead.value = null;
    lastPayment.value = null;
    sentTo.value = '';
    failure.value = '';
    method.value = '';
    stage.value = 'summary';
    open.value = 'service';
    window.scrollTo({ top: 0, behavior: 'smooth' });
}


/* The section headers say what has been answered, so a shut accordion is
   still readable. */
const summaryOf = (section) => {
    if (section === 'service') {
        return chosen.value.length
            ? `${chosen.value.length} · ${money(totalMinor.value)}`
            : '';
    }

    if (section === 'when') {
        return start.value ? `${chosenStaff.value?.name ?? props.labels.when?.any} · ${clock(start.value)}` : '';
    }

    if (section === 'payment') {
        return payType.value === 'deposit' && deposit.value ? money(Number(deposit.value) * 100) : '';
    }

    if (section === 'comms') {
        return props.labels.comms?.[confirmation.value] ?? '';
    }

    return '';
};
</script>

<template>
    <form :action="action" method="POST" class="contents" @submit="sending = true">
        <input type="hidden" name="_token" :value="csrf">
        <input type="hidden" name="client_id" :value="client?.id ?? ''">
        <input type="hidden" name="guest_name" :value="client ? '' : guest.name">
        <input type="hidden" name="guest_phone" :value="client ? '' : guest.phone">
        <input type="hidden" name="guest_email" :value="client ? '' : guest.email">
        <input type="hidden" name="staff_id" :value="staffId">
        <input type="hidden" name="location_id" :value="locationId">
        <input type="hidden" name="date" :value="date">
        <input type="hidden" name="starts_at" :value="start">
        <input v-for="service in chosen" :key="service.id" type="hidden" name="services[]" :value="service.id">
        <input type="hidden" name="source" :value="source">
        <input type="hidden" name="payment_type" :value="payType">
        <input type="hidden" name="deposit" :value="payType === 'deposit' ? deposit : ''">
        <input type="hidden" name="deposit_action" :value="payType === 'deposit' ? depositAction : ''">
        <input type="hidden" name="confirmation" :value="confirmation">
        <input type="hidden" name="notes" :value="notes">
        <input type="hidden" name="client_note" :value="client ? clientNote : ''">
        <input type="hidden" name="draft" :value="draft ? 1 : 0">

        <!-- ================================================ column 1 — client -->
        <section class="lg:col-span-3" :aria-label="labels.sections?.client">
            <div class="bg-white border border-line rounded-card overflow-hidden">
                <div class="px-4 pt-4 pb-3 flex items-center justify-between gap-2">
                    <h2 class="text-[12px] font-semibold uppercase tracking-wide text-faint">
                        {{ labels.sections?.client }}
                    </h2>
                    <button v-if="client" type="button" class="text-[12px] font-semibold text-link"
                            @click="clearClient">{{ labels.client?.change }}</button>
                </div>

                <!-- Chosen: the search is put away, because the question it
                     asked has been answered, and the history takes its place.
                     It stays on screen while the service, the person and the
                     time are chosen — which is when it is useful. -->
                <div v-if="client">
                    <div class="px-4 pb-4">
                        <div class="flex items-center gap-2.5">
                            <span class="sd-avatar sd-avatar--md shrink-0" aria-hidden="true">{{ client.initials }}</span>
                            <span class="min-w-0">
                                <span class="block text-[14px] font-semibold text-head truncate">{{ client.name }}</span>
                                <span class="block text-[12px] text-sub truncate">{{ client.mobile || client.email }}</span>
                            </span>
                        </div>
                    </div>

                    <template v-if="context">
                        <!-- Who they are likely to want, and why. The reason
                             is the point: "asked for by name" is something
                             they said, "booked most often" is something the
                             diary noticed. -->
                        <div v-if="context.preferred.length" class="px-4 py-3 border-t border-line">
                            <p class="styledesk_eyebrow">{{ labels.context?.preferred }}</p>

                            <button v-for="person in context.preferred" :key="person.id" type="button"
                                    class="styledesk_signal" :class="{ 'is-on': String(staffId) === String(person.id) }"
                                    @click="pickStaff(person.id)">
                                <span class="block text-[11.5px] font-semibold text-brand">{{ person.why }}</span>
                                <span class="block text-[13px] font-semibold text-head">{{ person.name }}</span>
                                <span class="block text-[11.5px] text-sub">
                                    {{ person.role }}<template v-if="person.visits"> · {{ person.visits_label }}</template>
                                </span>
                            </button>
                        </div>

                        <!-- For the day the usual person is off. -->
                        <div v-if="context.also_seen.length" class="px-4 py-3 border-t border-line">
                            <p class="styledesk_eyebrow">{{ labels.context?.also_seen }}</p>

                            <button v-for="person in context.also_seen" :key="person.id" type="button"
                                    class="styledesk_signal" :class="{ 'is-on': String(staffId) === String(person.id) }"
                                    @click="pickStaff(person.id)">
                                <span class="block text-[13px] font-semibold text-head">{{ person.name }}</span>
                                <span class="block text-[11.5px] text-sub">
                                    {{ person.role }} · {{ person.visits_label }}
                                </span>
                            </button>
                        </div>

                        <div v-if="context.last" class="px-4 py-3 border-t border-line">
                            <p class="styledesk_eyebrow">{{ labels.context?.last }}</p>
                            <p class="text-[13px] font-semibold text-head mt-1">{{ context.last.services }}</p>
                            <p class="text-[12px] text-sub mt-0.5">
                                {{ context.last.date }}<template v-if="context.last.staff"> · {{ context.last.staff }}</template>
                                · {{ context.last.total }}
                            </p>

                            <button type="button" class="styledesk_again" @click="bookAgain">
                                {{ labels.context?.again }}
                            </button>
                        </div>

                        <div v-if="context.recent.length" class="px-4 py-3 border-t border-line">
                            <p class="styledesk_eyebrow">
                                {{ labels.context?.recent }}
                                <template v-if="context.rating">
                                    · {{ (labels.context?.average ?? '').replace(':rating', context.rating) }}
                                </template>
                            </p>

                            <ul class="mt-1.5 space-y-1.5">
                                <li v-for="(visit, index) in context.recent" :key="index" class="text-[12px]">
                                    <span class="flex items-baseline gap-2">
                                        <span class="text-sub shrink-0">{{ visit.date }}</span>
                                        <span class="min-w-0 flex-1 text-ink truncate">{{ visit.services }}</span>
                                        <!-- Only where a review exists: a blank
                                             row of stars reads as a bad review
                                             rather than as no review. -->
                                        <span v-if="visit.rating" class="shrink-0 text-[11px] text-amber-500"
                                              :aria-label="`${visit.rating} / 5`">
                                            {{ '★'.repeat(visit.rating) }}<span class="text-faint">{{ '★'.repeat(5 - visit.rating) }}</span>
                                        </span>
                                    </span>
                                    <span v-if="visit.staff" class="block text-[11.5px] text-sub">{{ visit.staff }}</span>
                                </li>
                            </ul>
                        </div>

                        <div v-if="context.preferences.length" class="px-4 py-3 border-t border-line">
                            <p class="styledesk_eyebrow">{{ labels.context?.preferences }}</p>

                            <ul class="mt-1.5 space-y-1">
                                <li v-for="(preference, index) in context.preferences" :key="index"
                                    class="flex items-start gap-1.5 text-[12px] text-ink">
                                    <span class="styledesk_bullet" aria-hidden="true"></span>
                                    <span class="min-w-0">
                                        {{ preference.label }}
                                        <!-- What the diary noticed, marked as
                                             such: a client who happens to book
                                             afternoons has not asked for them. -->
                                        <span v-if="preference.source === 'system'" class="styledesk_fromdiary">
                                            {{ labels.context?.from_diary }}
                                        </span>
                                    </span>
                                </li>
                            </ul>
                        </div>

                        <p v-if="!context.preferred.length && !context.last"
                           class="px-4 py-3 border-t border-line text-[12px] text-sub">
                            {{ labels.context?.none }}
                        </p>
                    </template>
                </div>

                <div v-else class="px-4 pb-4">
                    <div v-if="mode === 'booking'">
                        <div class="relative">
                            <span class="styledesk_input__prefix pointer-events-none" aria-hidden="true">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none"><circle cx="11" cy="11" r="6.5" stroke="currentColor" stroke-width="1.9"/><path d="M16 16l4.5 4.5" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"/></svg>
                            </span>
                            <input v-model="clientQuery" type="search" class="sd-input styledesk_input--prefixed"
                                   :placeholder="labels.client?.search" :aria-label="labels.client?.search_label"
                                   autocomplete="off">
                        </div>

                        <ul v-if="clientResults.length" class="mt-2 border border-line rounded-lg divide-y divide-line">
                            <li v-for="found in clientResults" :key="found.id">
                                <button type="button" class="w-full flex items-center gap-2.5 p-2.5 text-left hover:bg-hover transition-colors"
                                        @click="chooseClient(found)">
                                    <span class="sd-avatar sd-avatar--sm shrink-0" aria-hidden="true">{{ found.initials }}</span>
                                    <span class="min-w-0">
                                        <span class="block text-[13px] font-semibold text-head truncate">{{ found.name }}</span>
                                        <span class="block text-[12px] text-sub truncate">{{ found.mobile || found.email }}</span>
                                    </span>
                                </button>
                            </li>
                        </ul>

                        <p v-else-if="clientQuery.trim().length >= 2 && !searching"
                           class="mt-2 text-[12px] text-sub">{{ labels.client?.none }}</p>

                        <div class="styledesk_or my-4">{{ labels.client?.or }}</div>

                        <button type="button" class="styledesk_action w-full justify-center"
                                @click="openNewClient">
                            {{ labels.client?.add }}
                        </button>

                        <button type="button" class="w-full mt-2 h-9 rounded-lg text-[12.5px] font-semibold text-sub hover:text-ink hover:bg-hover transition-colors"
                                @click="mode = 'walkin'">{{ labels.client?.guest }}</button>
                    </div>

                    <!-- Walk-in: a name and a way to reach them, and nothing
                         else. The record they do not have is the point. -->
                    <div v-else class="space-y-3">
                        <div>
                            <label for="guestName" class="block text-[13px] font-medium text-ink mb-1.5">
                                {{ labels.client?.guest_name }}
                            </label>
                            <input id="guestName" v-model="guest.name" type="text" class="sd-input" autocomplete="off">
                        </div>

                        <div>
                            <label for="guestPhone" class="block text-[13px] font-medium text-ink mb-1.5">
                                {{ labels.client?.guest_phone }}
                            </label>
                            <input id="guestPhone" v-model="guest.phone" type="tel" class="sd-input" autocomplete="off">
                        </div>

                        <div>
                            <label for="guestEmail" class="block text-[13px] font-medium text-ink mb-1.5">
                                {{ labels.client?.guest_email }}
                            </label>
                            <input id="guestEmail" v-model="guest.email" type="email" class="sd-input" autocomplete="off">
                        </div>

                        <p class="text-[12px] text-faint leading-relaxed">{{ labels.client?.guest_hint }}</p>

                        <button type="button" class="text-[12.5px] font-semibold text-link"
                                @click="mode = 'booking'">{{ labels.client?.search_label }}</button>
                    </div>
                </div>
            </div>
        </section>

        <!-- =============================================== column 2 — booking -->
        <section class="lg:col-span-6 min-w-0 space-y-3" :aria-label="labels.sections?.service">
            <!-- Service -->
            <section class="bg-white border border-line rounded-card overflow-hidden">
                <button type="button" class="styledesk_weekhead" :aria-expanded="open === 'service'"
                        @click="toggle('service')">
                    <span class="styledesk_bookingtick" :class="{ 'is-done': stepsDone.service || chosen.length }" aria-hidden="true">
                        <svg width="11" height="11" viewBox="0 0 24 24" fill="none"><path d="M5 12.5l4.5 4.5L19 7.5" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </span>
                    <span class="min-w-0 flex-1 text-left text-[13px] font-semibold text-head">
                        {{ labels.sections?.service }}
                    </span>
                    <span class="text-[12px] text-sub shrink-0">{{ summaryOf('service') }}</span>
                    <!-- Named rather than left to a chevron: a shut card with
                         a summary on it reads as finished, and the reader
                         needs to know it can still be changed. -->
                    <span v-if="stepsDone.service && open !== 'service'" class="text-[12px] font-semibold text-link shrink-0">
                        {{ labels.steps?.edit }}
                    </span>
                </button>

                <div v-show="open === 'service'" class="p-4 pt-0">
                    <!-- Clear of the header above it: the search is the first
                         thing asked for in this section, not a continuation
                         of the row that names it. -->
                    <div class="relative mt-2.5">
                        <span class="styledesk_input__prefix pointer-events-none" aria-hidden="true">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none"><circle cx="11" cy="11" r="6.5" stroke="currentColor" stroke-width="1.9"/><path d="M16 16l4.5 4.5" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"/></svg>
                        </span>
                        <input v-model="serviceQuery" type="search" class="sd-input styledesk_input--prefixed"
                               :placeholder="labels.service?.search" autocomplete="off">
                    </div>

                    <!-- Under the search rather than beside it: the two
                         narrow the same list, and a filter on the same line
                         as a search box reads as part of the search. -->
                    <div class="mt-2.5">
                        <MultiSelect :options="categories"
                                     :model-value="categoryIds"
                                     name="service_categories"
                                     :placeholder="labels.service?.all_categories"
                                     :search-placeholder="labels.service?.search_categories"
                                     :aria-label="labels.service?.category"
                                     :summary-label="labels.service?.category"
                                     :show-primary="false"
                                     @update:model-value="(values) => categoryIds = values" />
                    </div>

                    <div v-if="chosen.length" class="mt-3">
                        <p class="text-[12px] font-semibold text-sub mb-1.5">{{ labels.service?.chosen }}</p>
                        <ul class="space-y-1.5">
                            <li v-for="service in chosen" :key="service.id"
                                class="flex items-center gap-2 rounded-lg border border-brand/30 bg-brand/5 px-3 py-2">
                                <span class="min-w-0 flex-1">
                                    <span class="block text-[13px] font-semibold text-head truncate">{{ service.name }}</span>
                                    <span class="block text-[12px] text-sub">
                                        {{ (labels.service?.minutes ?? ':count min').replace(':count', service.minutes) }}
                                        <template v-if="service.price"> · {{ service.price }}</template>
                                    </span>
                                </span>
                                <button type="button" class="sd-iconbtn grid place-items-center shrink-0"
                                        :aria-label="(labels.service?.remove ?? '').replace(':name', service.name)"
                                        @click="removeService(service)">
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none"><path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"/></svg>
                                </button>
                            </li>
                        </ul>
                    </div>

                    <ul v-if="matches.length" class="mt-3 border border-line rounded-lg divide-y divide-line max-h-[260px] overflow-y-auto styledesk_scroll">
                        <li v-for="service in matches" :key="service.id">
                            <button type="button" class="w-full flex items-center gap-2 p-2.5 text-left hover:bg-hover transition-colors"
                                    @click="addService(service)">
                                <span class="min-w-0 flex-1">
                                    <span class="block text-[13px] font-medium text-head truncate">{{ service.name }}</span>
                                    <span class="block text-[12px] text-sub">
                                        {{ (labels.service?.minutes ?? ':count min').replace(':count', service.minutes) }}
                                    </span>
                                </span>
                                <span class="text-[13px] font-semibold text-head shrink-0">{{ service.price }}</span>
                            </button>
                        </li>
                    </ul>

                    <p v-else class="mt-3 text-[12px] text-sub">
                        {{ services.length ? labels.service?.none : labels.service?.empty }}
                    </p>

                    <!-- What was chosen adds up to, and what it needs. The
                         resource is worked out from the services rather than
                         asked for: a booking that quietly takes the only
                         colour bar is one somebody has to know about. -->
                    <div v-if="chosen.length" class="mt-3 flex flex-wrap items-baseline gap-x-3 gap-y-1 text-[12.5px]">
                        <span class="text-sub">
                            {{ (labels.service?.minutes ?? ':count min').replace(':count', minutes) }}
                        </span>
                        <span class="font-semibold text-head">{{ money(totalMinor) }}</span>
                        <span v-if="resources.length" class="text-sub">
                            {{ labels.summary?.resource }}: <span class="text-ink">{{ resources.join(', ') }}</span>
                        </span>
                    </div>

                    <!-- The way on. Every step ends with one, so the screen
                         has a default path through it. -->
                    <div class="mt-4 pt-3.5 border-t border-line flex flex-wrap items-center gap-3">
                        <button type="button" class="styledesk_stepcta" :disabled="stepBlocker('service') !== null"
                                @click="saveStep('service')">{{ labels.steps?.save }}</button>
                        <p v-if="stepBlocker('service')" class="text-[12px] text-danger">{{ stepBlocker('service') }}</p>
                    </div>
                </div>
            </section>

            <!-- Who with + when. The card stops clipping while it is open, or
                 it would cut the date picker off at its own bottom edge. -->
            <section class="bg-white border border-line rounded-card"
                     :class="open === 'when' ? 'overflow-visible' : 'overflow-hidden'">
                <button type="button" class="styledesk_weekhead rounded-t-card" :aria-expanded="open === 'when'"
                        @click="toggle('when')">
                    <span class="styledesk_bookingtick" :class="{ 'is-done': stepsDone.when || start }" aria-hidden="true">
                        <svg width="11" height="11" viewBox="0 0 24 24" fill="none"><path d="M5 12.5l4.5 4.5L19 7.5" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </span>
                    <span class="min-w-0 flex-1 text-left text-[13px] font-semibold text-head">
                        {{ labels.sections?.when }}
                    </span>
                    <span class="text-[12px] text-sub shrink-0">{{ summaryOf('when') }}</span>
                    <!-- Named rather than left to a chevron: a shut card with
                         a summary on it reads as finished, and the reader
                         needs to know it can still be changed. -->
                    <span v-if="stepsDone.when && open !== 'when'" class="text-[12px] font-semibold text-link shrink-0">
                        {{ labels.steps?.edit }}
                    </span>
                </button>

                <div v-show="open === 'when'" class="p-4 pt-0 space-y-4">
                    <div class="mt-[5px]">
                        <label class="block text-[13px] font-medium text-ink mb-1.5">{{ labels.when?.staff }}</label>

                        <!-- Who this client is likely to want, named with the
                             reason. "Asked for by name" is something they
                             said; "booked most often" is something the diary
                             noticed, and the two are not the same fact. -->
                        <div v-if="suggestedStaff.length" class="space-y-1.5 mb-2.5">
                            <button v-for="row in suggestedStaff" :key="row.id" type="button"
                                    class="styledesk_paymethod"
                                    :class="{ 'is-on': String(staffId) === String(row.id) }"
                                    @click="staffId = row.id">
                                <span class="min-w-0">
                                    <span class="block text-[13px] font-semibold text-head">{{ row.name }}</span>
                                    <span class="block text-[11.5px] text-sub">{{ row.why }} · {{ row.visits_label }}</span>
                                </span>
                            </button>
                        </div>

                        <div class="flex flex-wrap gap-1.5">
                            <button type="button" class="styledesk_pickchip" :class="{ 'is-on': staffId === '' }"
                                    @click="staffId = ''">{{ labels.when?.any }}</button>
                            <button v-for="member in staff" :key="member.id" type="button"
                                    class="styledesk_pickchip" :class="{ 'is-on': String(staffId) === String(member.id) }"
                                    @click="staffId = member.id">{{ member.name }}</button>
                        </div>
                    </div>

                    <div>
                        <label for="bDate" class="block text-[13px] font-medium text-ink mb-1.5">{{ labels.when?.date }}</label>

                        <div ref="picker" class="relative max-w-[280px]">
                            <input id="bDate" type="text" readonly :value="chosenDay"
                                   class="sd-input is-picker has-suffix" :aria-expanded="pickerOpen"
                                   @click="pickerOpen = ! pickerOpen" @keydown.enter.prevent="pickerOpen = true"
                                   @keydown.esc="pickerOpen = false">
                            <span class="absolute right-3 top-1/2 -translate-y-1/2 text-faint pointer-events-none">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="2" stroke="currentColor" stroke-width="1.7"/><path d="M3 9h18M8 3v4M16 3v4" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>
                            </span>

                            <div v-show="pickerOpen" class="sd-cal styledesk_cal--presets">
                                <div class="flex">
                                    <!-- What the desk actually says, down the side. -->
                                    <div class="w-[110px] shrink-0 border-r border-line pr-2 mr-3 flex flex-col gap-0.5">
                                        <button v-for="range in ranges" :key="range" type="button"
                                                class="text-left h-7 px-2.5 rounded-md text-[12px] whitespace-nowrap"
                                                :class="dateMode === range ? 'bg-brand text-white font-medium' : 'text-ink hover:bg-hover'"
                                                @click="chooseRange(range)">{{ labels.when?.[range] }}</button>
                                    </div>

                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center mb-1.5">
                                            <button type="button" class="h-6 w-6 grid place-items-center rounded-md text-sub hover:bg-hover"
                                                    :aria-label="labels.when?.previous_month" @click="shiftMonth(-1)">
                                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none"><path d="M15 6l-6 6 6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                            </button>
                                            <div class="flex-1"></div>
                                            <button type="button" class="h-6 w-6 grid place-items-center rounded-md text-sub hover:bg-hover"
                                                    :aria-label="labels.when?.next_month" @click="shiftMonth(1)">
                                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none"><path d="M9 6l6 6-6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                            </button>
                                        </div>

                                        <!-- Two months, because "next 7 days" from the 30th is
                                             mostly next month and a window nobody can see is
                                             not a window. -->
                                        <div class="flex gap-4">
                                            <div v-for="month in months" :key="month.label" class="flex-1 min-w-0">
                                                <div class="text-center text-[12px] font-semibold text-head mb-1.5">{{ month.label }}</div>

                                                <div class="grid grid-cols-7 mb-0.5">
                                                    <div v-for="(day, index) in weekdays" :key="index"
                                                         class="h-5 grid place-items-center text-[10px] font-semibold text-faint">{{ day }}</div>
                                                </div>

                                                <div class="grid grid-cols-7 gap-y-0.5">
                                                    <div v-for="(day, index) in month.days" :key="index" class="h-7">
                                                        <button v-if="day" type="button"
                                                                class="h-7 w-full grid place-items-center text-[11px] rounded-md"
                                                                :class="date === day ? 'bg-brand text-white font-semibold'
                                                                    : presetDays.length > 1 && presetDays.includes(day) ? 'bg-sel text-brand'
                                                                    : 'text-ink hover:bg-hover'"
                                                                @click="pickDay(day)">{{ Number(day.slice(8)) }}</button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="flex items-center justify-between mt-2.5 pt-2.5 border-t border-line">
                                    <span class="text-[11px] text-ink font-medium">{{ chosenDay }}</span>
                                    <button type="button" class="h-7 px-3 rounded-md bg-brand hover:bg-brand-dark text-white text-[12px] font-semibold"
                                            @click="pickerOpen = false">{{ labels.when?.done }}</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="grid sm:grid-cols-2 gap-4">
                        <div v-if="locations.length > 1">
                            <label for="bLocation" class="block text-[13px] font-medium text-ink mb-1.5">{{ labels.when?.location }}</label>
                            <select id="bLocation" v-model="locationId" class="sd-input">
                                <option v-for="place in locations" :key="place.id" :value="place.id">{{ place.name }}</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block text-[13px] font-medium text-ink mb-1.5">{{ labels.when?.time }}</label>
                        <div class="max-h-[300px] overflow-y-auto styledesk_scroll space-y-2.5">
                            <!-- A card each, so the part of the day being
                                 looked at has an edge to it. Sixty chips in
                                 one run is a wall, and "anything after lunch?"
                                 is a question about one of these boxes. -->
                            <div v-for="group in timeGroups" :key="group.key"
                                 class="border border-line rounded-lg p-3">
                                <p class="text-[11px] font-semibold uppercase tracking-wide text-faint mb-2">
                                    {{ labels.when?.[group.key] }}
                                </p>
                                <div class="flex flex-wrap gap-1.5">
                                    <button v-for="slot in group.slots" :key="slot" type="button"
                                            class="styledesk_pickchip" :class="{ 'is-on': start === slot }"
                                            @click="start = slot">{{ clock(slot) }}</button>
                                </div>
                            </div>
                        </div>
                        <p v-if="endsAt" class="mt-2 text-[12px] text-sub">
                            {{ (labels.when?.ends ?? '').replace(':time', clock(endsAt)) }}
                        </p>
                    </div>

                    <!-- The way on. Every step ends with one, so the screen
                         has a default path through it. -->
                    <div class="mt-4 pt-3.5 border-t border-line flex flex-wrap items-center gap-3">
                        <button type="button" class="styledesk_stepcta" :disabled="stepBlocker('when') !== null"
                                @click="saveStep('when')">{{ labels.steps?.save }}</button>
                        <p v-if="stepBlocker('when')" class="text-[12px] text-danger">{{ stepBlocker('when') }}</p>
                    </div>
                </div>
            </section>

            <!-- Booking details -->
            <section class="bg-white border border-line rounded-card overflow-hidden">
                <button type="button" class="styledesk_weekhead" :aria-expanded="open === 'details'"
                        @click="toggle('details')">
                    <span class="styledesk_bookingtick" :class="{ 'is-done': stepsDone.details || notes || clientNote }" aria-hidden="true">
                        <svg width="11" height="11" viewBox="0 0 24 24" fill="none"><path d="M5 12.5l4.5 4.5L19 7.5" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </span>
                    <span class="min-w-0 flex-1 text-left text-[13px] font-semibold text-head">
                        {{ labels.sections?.details }}
                    </span>

                    <span v-if="stepsDone.details && open !== 'details'" class="text-[12px] font-semibold text-link shrink-0">
                        {{ labels.steps?.edit }}
                    </span>
                </button>

                <div v-show="open === 'details'" class="p-4 pt-0">
                    <div class="grid sm:grid-cols-2 gap-4 mt-2.5">
                        <div>
                            <span class="block text-[13px] font-medium text-ink mb-1.5">{{ labels.details?.source }}</span>
                            <!-- The app's combo rather than a bare select, so
                                 this reads and behaves like every other choice
                                 on the screen. Single mode: one answer, no
                                 chips. -->
                            <MultiSelect :options="sources"
                                         :model-value="source ? [source] : []"
                                         name="booking_source"
                                         single
                                         :placeholder="labels.details?.choose_source"
                                         :search-placeholder="labels.details?.search_sources"
                                         :aria-label="labels.details?.source"
                                         @update:model-value="(values) => source = values[0] ?? ''" />
                        </div>
                    </div>

                    <div class="mt-4">
                        <label for="bNotes" class="block text-[13px] font-medium text-ink mb-1.5">{{ labels.details?.note }}</label>
                        <textarea id="bNotes" v-model="notes" rows="2" class="sd-input h-auto py-2.5"
                                  :placeholder="labels.details?.note_placeholder"></textarea>
                        <p class="text-[12px] text-faint mt-1.5">{{ labels.details?.note_hint }}</p>
                    </div>

                    <!-- The note about the person, kept apart from the note
                         about the appointment: one is read on the day and the
                         other for as long as they are a client. -->
                    <div class="mt-4 pt-4 border-t border-line">
                        <label for="bClientNote" class="block text-[13px] font-medium text-ink mb-1.5">
                            {{ labels.details?.client_note }}
                            <span class="font-normal text-sub">{{ labels.details?.client_note_aside }}</span>
                        </label>
                        <textarea id="bClientNote" v-model="clientNote" rows="2" class="sd-input h-auto py-2.5"
                                  :disabled="!client" :placeholder="labels.details?.client_note_placeholder"></textarea>
                        <p class="text-[12px] text-faint mt-1.5">
                            {{ client ? labels.details?.client_note_hint : labels.details?.client_note_guest }}
                        </p>
                    </div>

                    <!-- The way on. Every step ends with one, so the screen
                         has a default path through it. -->
                    <div class="mt-4 pt-3.5 border-t border-line flex flex-wrap items-center gap-3">
                        <button type="button" class="styledesk_stepcta" :disabled="stepBlocker('details') !== null"
                                @click="saveStep('details')">{{ labels.steps?.save }}</button>
                        <p v-if="stepBlocker('details')" class="text-[12px] text-danger">{{ stepBlocker('details') }}</p>
                    </div>
                </div>
            </section>

            <!-- Deposit / payment -->
            <section class="bg-white border border-line rounded-card overflow-hidden">
                <button type="button" class="styledesk_weekhead" :aria-expanded="open === 'payment'"
                        @click="toggle('payment')">
                    <span class="styledesk_bookingtick" :class="{ 'is-done': stepsDone.payment || payType === 'deposit' }" aria-hidden="true">
                        <svg width="11" height="11" viewBox="0 0 24 24" fill="none"><path d="M5 12.5l4.5 4.5L19 7.5" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </span>
                    <span class="min-w-0 flex-1 text-left text-[13px] font-semibold text-head">
                        {{ labels.sections?.payment }}
                    </span>
                    <span class="text-[12px] text-sub shrink-0">{{ summaryOf('payment') }}</span>
                    <!-- Named rather than left to a chevron: a shut card with
                         a summary on it reads as finished, and the reader
                         needs to know it can still be changed. -->
                    <span v-if="stepsDone.payment && open !== 'payment'" class="text-[12px] font-semibold text-link shrink-0">
                        {{ labels.steps?.edit }}
                    </span>
                </button>

                <div v-show="open === 'payment'" class="p-4 pt-0">
                    <fieldset class="mt-[5px]">
                        <legend class="block text-[13px] font-medium text-ink mb-2">{{ labels.payment?.type }}</legend>
                        <div class="grid sm:grid-cols-2 gap-2">
                            <button type="button" class="styledesk_optioncard" :class="{ 'is-on': payType === 'none' }"
                                    @click="payType = 'none'">
                                <span class="block text-[13px] font-semibold text-head">{{ labels.payment?.none }}</span>
                                <span class="block text-[12px] text-sub">{{ labels.payment?.none_hint }}</span>
                            </button>
                            <button type="button" class="styledesk_optioncard" :class="{ 'is-on': payType === 'deposit' }"
                                    @click="payType = 'deposit'">
                                <span class="block text-[13px] font-semibold text-head">{{ labels.payment?.deposit }}</span>
                                <span class="block text-[12px] text-sub">{{ labels.payment?.deposit_hint }}</span>
                            </button>
                        </div>
                    </fieldset>

                    <div v-if="payType === 'deposit'" class="mt-4 grid sm:grid-cols-2 gap-4">
                        <div>
                            <label for="bDeposit" class="block text-[13px] font-medium text-ink mb-1.5">{{ labels.payment?.amount }}</label>
                            <input id="bDeposit" v-model="deposit" type="number" min="0" step="0.01" class="sd-input">
                        </div>
                        <div>
                            <label for="bDepositAction" class="block text-[13px] font-medium text-ink mb-1.5">{{ labels.payment?.action }}</label>
                            <select id="bDepositAction" v-model="depositAction" class="sd-input">
                                <option v-for="(name, key) in labels.payment?.actions ?? {}" :key="key" :value="key">{{ name }}</option>
                            </select>
                        </div>
                    </div>

                    <!-- The way on. Every step ends with one, so the screen
                         has a default path through it. -->
                    <div class="mt-4 pt-3.5 border-t border-line flex flex-wrap items-center gap-3">
                        <button type="button" class="styledesk_stepcta" :disabled="stepBlocker('payment') !== null"
                                @click="saveStep('payment')">{{ labels.steps?.save }}</button>
                        <p v-if="stepBlocker('payment')" class="text-[12px] text-danger">{{ stepBlocker('payment') }}</p>
                    </div>
                </div>
            </section>

            <!-- Communication -->
            <section class="bg-white border border-line rounded-card overflow-hidden">
                <button type="button" class="styledesk_weekhead" :aria-expanded="open === 'comms'"
                        @click="toggle('comms')">
                    <span class="styledesk_bookingtick is-done" aria-hidden="true">
                        <svg width="11" height="11" viewBox="0 0 24 24" fill="none"><path d="M5 12.5l4.5 4.5L19 7.5" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </span>
                    <span class="min-w-0 flex-1 text-left text-[13px] font-semibold text-head">
                        {{ labels.sections?.comms }}
                    </span>
                    <span class="text-[12px] text-sub shrink-0">{{ summaryOf('comms') }}</span>
                    <!-- Named rather than left to a chevron: a shut card with
                         a summary on it reads as finished, and the reader
                         needs to know it can still be changed. -->
                    <span v-if="stepsDone.comms && open !== 'comms'" class="text-[12px] font-semibold text-link shrink-0">
                        {{ labels.steps?.edit }}
                    </span>
                </button>

                <div v-show="open === 'comms'" class="p-4 pt-0">
                    <fieldset class="mt-[5px]">
                        <legend class="block text-[13px] font-medium text-ink mb-2">{{ labels.comms?.send }}</legend>
                        <div class="flex flex-wrap gap-1.5">
                            <button v-for="key in ['both', 'sms', 'email', 'none']" :key="key" type="button"
                                    class="styledesk_pickchip" :class="{ 'is-on': confirmation === key }"
                                    @click="confirmation = key">{{ labels.comms?.[key] }}</button>
                        </div>
                    </fieldset>

                    <div v-if="confirmation !== 'none'" class="mt-4 space-y-1.5 text-[12px] text-sub">
                        <p v-if="confirmation !== 'email'">
                            {{ labels.comms?.to_sms }}: <span class="text-ink">{{ smsTo || '—' }}</span>
                        </p>
                        <p v-if="confirmation !== 'sms'">
                            {{ labels.comms?.to_email }}: <span class="text-ink">{{ emailTo || '—' }}</span>
                        </p>
                        <p v-if="(confirmation !== 'email' && !smsTo) || (confirmation !== 'sms' && !emailTo)"
                           class="text-danger">{{ labels.comms?.missing }}</p>
                    </div>

                    <!-- The way on. Every step ends with one, so the screen
                         has a default path through it. -->
                    <div class="mt-4 pt-3.5 border-t border-line flex flex-wrap items-center gap-3">
                        <button type="button" class="styledesk_stepcta" :disabled="stepBlocker('comms') !== null"
                                @click="saveStep('comms')">{{ labels.steps?.save }}</button>
                        <p v-if="stepBlocker('comms')" class="text-[12px] text-danger">{{ stepBlocker('comms') }}</p>
                    </div>
                </div>
            </section>
        </section>

        <!-- Add Client, without leaving the booking that needs them. Its own
             dialog rather than the full client form: that form asks forty
             questions, and the four here are the ones an appointment needs. -->
        <div v-if="adding" class="styledesk_modal" role="dialog" aria-modal="true" aria-labelledby="newClientTitle">
            <div class="styledesk_modal__scrim" @click="closeNewClient"></div>

            <div class="styledesk_modal__panel">
                <div class="styledesk_modal__head">
                    <h2 id="newClientTitle" class="text-[15px] font-semibold text-head">
                        {{ labels.new_client?.title }}
                    </h2>

                    <button type="button" class="styledesk_modal__close" :aria-label="labels.cancel"
                            @click="closeNewClient">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                        </svg>
                    </button>
                </div>

                <div class="styledesk_modal__body">
                    <p class="text-[13px] text-sub leading-relaxed">{{ labels.new_client?.intro }}</p>

                    <div class="mt-4 grid sm:grid-cols-2 gap-3">
                        <div>
                            <label for="ncFirst" class="block text-[13px] font-medium text-ink mb-1.5">
                                {{ labels.new_client?.first_name }} <span class="text-danger">*</span>
                            </label>
                            <input id="ncFirst" v-model="fresh.first_name" type="text" class="sd-input"
                                   autocomplete="off" @keydown.enter.prevent="saveNewClient(false)">
                            <p v-if="errors.first_name" class="text-[12px] text-danger mt-1.5">{{ errors.first_name[0] }}</p>
                        </div>

                        <div>
                            <label for="ncLast" class="block text-[13px] font-medium text-ink mb-1.5">
                                {{ labels.new_client?.last_name }}
                            </label>
                            <input id="ncLast" v-model="fresh.last_name" type="text" class="sd-input"
                                   autocomplete="off" @keydown.enter.prevent="saveNewClient(false)">
                        </div>
                    </div>

                    <div class="mt-3">
                        <label for="ncEmail" class="block text-[13px] font-medium text-ink mb-1.5">
                            {{ labels.new_client?.email }}
                        </label>
                        <input id="ncEmail" v-model="fresh.email" type="email" class="sd-input"
                               placeholder="name@example.com" autocomplete="off"
                               @keydown.enter.prevent="saveNewClient(false)">
                        <p v-if="errors.email" class="text-[12px] text-danger mt-1.5">{{ errors.email[0] }}</p>
                    </div>

                    <div class="mt-3">
                        <label for="ncMobile" class="block text-[13px] font-medium text-ink mb-1.5">
                            {{ labels.new_client?.mobile }}
                        </label>
                        <input id="ncMobile" v-model="fresh.mobile" type="tel" class="sd-input"
                               placeholder="(202) 555-0123" autocomplete="off"
                               @keydown.enter.prevent="saveNewClient(false)">
                        <p v-if="errors.mobile" class="text-[12px] text-danger mt-1.5">{{ errors.mobile[0] }}</p>
                    </div>

                    <p class="text-[12px] text-faint mt-2">{{ labels.new_client?.contact_hint }}</p>

                    <!-- A warning, never a block: two people can share a
                         phone, and a wrongly merged history is not something
                         a receptionist can unpick. The reader decides which
                         of the two this is. -->
                    <div v-if="duplicates.length" class="sd-alert sd-alert--warn mt-3" role="alert">
                        <p class="font-semibold">{{ labels.new_client?.duplicate }}</p>

                        <ul class="mt-1.5 space-y-1">
                            <li v-for="match in duplicates" :key="match.id">
                                <button type="button" class="font-semibold underline"
                                        @click="closeNewClient(); chooseClient(match)">{{ match.name }}</button>
                                <span class="text-[12px]"> · {{ match.mobile || match.email }}</span>
                            </li>
                        </ul>
                    </div>
                </div>

                <div class="styledesk_modalfoot">
                    <button type="button" :disabled="saving"
                            class="h-9 px-4 rounded-lg bg-brand hover:bg-brand-dark text-white text-[13px] font-semibold transition-colors disabled:opacity-60"
                            @click="saveNewClient(duplicates.length > 0)">
                        {{ duplicates.length ? labels.new_client?.add_anyway : labels.new_client?.add }}
                    </button>

                    <button type="button" class="styledesk_action" @click="closeNewClient">{{ labels.cancel }}</button>

                    <a :href="newClientUrl" class="styledesk_modalfoot__note font-semibold text-link">
                        {{ labels.new_client?.full_form }}
                    </a>
                </div>
            </div>
        </div>

        <!-- =================================== column 3 — summary → payment → done
             Three cards in one place, opening in turn. A modal or a second
             page for the money would take the receptionist off the screen
             holding everything they might still be asked about, and the shut
             heads keep the whole workflow readable while one step is open. -->
        <section class="lg:col-span-3 lg:sticky lg:top-[73px] space-y-3" :aria-label="labels.sections?.summary">

            <!-- ---------------------------------------------------- 1 · summary -->
            <!-- Marked apart from the seven cards beside them: these two are
                 where the booking is committed, not where it is described. -->
            <div class="bg-white border rounded-card overflow-hidden styledesk_workcard">
                <button type="button" class="styledesk_weekhead" :aria-expanded="stage === 'summary'"
                        :disabled="stage === 'done'" @click="openPanel('summary')">
                    <span class="styledesk_bookingtick" :class="{ 'is-done': booking !== null }" aria-hidden="true">
                        <svg width="11" height="11" viewBox="0 0 24 24" fill="none"><path d="M5 12.5l4.5 4.5L19 7.5" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </span>
                    <span class="min-w-0 flex-1 text-left text-[13px] font-semibold text-brand">
                        {{ labels.sections?.summary }}
                    </span>
                    <span v-if="chosen.length" class="text-[12px] text-brand/75 shrink-0">{{ money(estimate.total) }}</span>
                </button>

                <div v-show="stage === 'summary'">
                <dl class="p-4 space-y-2.5 text-[13px]">
                    <div class="flex items-baseline justify-between gap-3">
                        <dt class="text-sub">{{ labels.summary?.client }}</dt>
                        <dd class="font-semibold text-head text-right min-w-0 truncate">{{ who ?? '—' }}</dd>
                    </div>

                    <div class="flex items-baseline justify-between gap-3">
                        <dt class="text-sub">{{ labels.summary?.staff }}</dt>
                        <dd class="font-semibold text-head text-right">{{ chosenStaff?.name ?? labels.when?.any }}</dd>
                    </div>

                    <div class="flex items-baseline justify-between gap-3">
                        <dt class="text-sub">{{ labels.summary?.when }}</dt>
                        <dd class="font-semibold text-head text-right">{{ start ? `${shortDay(date)} · ${clock(start)}` : '—' }}</dd>
                    </div>

                    <!-- Where it is and what it needs, but only where either is
                         a real answer: one location and no equipment is a
                         booking nobody has to be told about. -->
                    <div v-if="locations.length > 1 && chosenLocation" class="flex items-baseline justify-between gap-3">
                        <dt class="text-sub">{{ labels.summary?.location }}</dt>
                        <dd class="font-semibold text-head text-right">{{ chosenLocation.name }}</dd>
                    </div>

                    <div v-if="resources.length" class="flex items-baseline justify-between gap-3">
                        <dt class="text-sub">{{ labels.summary?.resource }}</dt>
                        <dd class="font-semibold text-head text-right">{{ resources.join(', ') }}</dd>
                    </div>

                    <div v-if="chosen.length" class="pt-2.5 border-t border-line space-y-1.5">
                        <div v-for="service in chosen" :key="service.id"
                             class="flex items-baseline justify-between gap-3">
                            <dt class="text-sub min-w-0 truncate">{{ service.name }}</dt>
                            <dd class="text-head shrink-0">{{ service.price }}</dd>
                        </div>
                    </div>

                    <p v-else class="text-[12px] text-sub">{{ labels.summary?.nothing }}</p>

                    <div v-if="chosen.length" class="pt-2.5 border-t border-line space-y-1.5">
                        <div class="flex items-baseline justify-between gap-3">
                            <dt class="text-sub">{{ labels.summary?.duration }}</dt>
                            <dd class="text-head">
                                {{ (labels.summary?.minutes_short ?? ':count min').replace(':count', minutes) }}
                            </dd>
                        </div>
                        <div v-if="endsAt" class="flex items-baseline justify-between gap-3">
                            <dt class="text-sub">{{ labels.summary?.ends }}</dt>
                            <dd class="text-head">{{ endsAt }}</dd>
                        </div>
                        <div class="flex items-baseline justify-between gap-3">
                            <dt class="text-sub">{{ labels.summary?.subtotal }}</dt>
                            <dd class="text-head">{{ money(estimate.subtotal) }}</dd>
                        </div>
                        <div v-if="estimate.tax" class="flex items-baseline justify-between gap-3">
                            <dt class="text-sub">{{ taxLabel }}</dt>
                            <dd class="text-head">{{ money(estimate.tax) }}</dd>
                        </div>
                        <div class="flex items-baseline justify-between gap-3">
                            <dt class="font-semibold text-head">{{ labels.summary?.total }}</dt>
                            <dd class="font-semibold text-head">{{ money(estimate.total) }}</dd>
                        </div>
                        <div v-if="payType === 'deposit' && deposit" class="flex items-baseline justify-between gap-3">
                            <dt class="text-sub">{{ labels.summary?.deposit }}</dt>
                            <dd class="text-head">{{ money(Number(deposit) * 100) }}</dd>
                        </div>
                    </div>
                </dl>

                <div class="px-4 py-3.5 border-t border-line">
                    <p v-if="failure" class="sd-alert sd-alert--danger mb-2.5 text-[12.5px]" role="alert">{{ failure }}</p>

                    <p class="text-[12.5px] mb-2.5" :class="ready ? 'text-sub' : 'text-danger'">
                        {{ blocker ?? labels.blockers?.ready }}
                    </p>

                    <button type="button" :disabled="!ready || busy"
                            class="w-full h-11 rounded-lg bg-brand hover:bg-brand-dark text-white text-[13px] font-semibold transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                            @click="confirmBooking">
                        <!-- The booking is already held once the payment card
                             has opened, so this saves the edit rather than
                             taking it again — and says so. -->
                        {{ booking ? labels.pay?.again : labels.confirm }}
                    </button>

                    <div class="flex items-center gap-2 mt-2">
                        <button type="submit" class="flex-1 styledesk_action justify-center" :disabled="sending || busy"
                                @click="submit(true)">{{ labels.draft }}</button>
                        <a :href="cancelUrl"
                           class="flex-1 h-9 rounded-lg text-center leading-9 text-[12.5px] font-semibold text-sub hover:text-ink hover:bg-hover transition-colors">
                            {{ labels.cancel }}
                        </a>
                    </div>
                </div>
                </div>
            </div>

            <!-- ---------------------------------------------------- 2 · payment -->
            <!-- Shown from the start, shut and unreachable until there is a
                 booking to pay for: a step nobody can see coming is a step
                 that reads as a surprise. -->
            <div class="bg-white border rounded-card overflow-hidden styledesk_workcard">
                <button type="button" class="styledesk_weekhead" :aria-expanded="stage === 'payment'"
                        :disabled="!booking || stage === 'done'" @click="openPanel('payment')">
                    <span class="styledesk_bookingtick" :class="{ 'is-done': booking && booking.due_minor === 0 && booking.paid_minor > 0 }" aria-hidden="true">
                        <svg width="11" height="11" viewBox="0 0 24 24" fill="none"><path d="M5 12.5l4.5 4.5L19 7.5" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </span>
                    <span class="min-w-0 flex-1 text-left text-[13px] font-semibold text-brand">
                        {{ labels.pay?.title }}
                    </span>
                    <span v-if="booking" class="text-[12px] text-brand/75 shrink-0">
                        {{ booking.due_minor > 0 ? booking.due : booking.payment_status_label }}
                    </span>
                </button>

                <!-- v-if as well as v-show: the body reads the booking's own
                     figures, and there is no booking until one is taken. -->
                <div v-if="booking" v-show="stage === 'payment'">
                <div class="px-4 pt-4">
                    <p class="text-[12px] text-sub">{{ labels.pay?.due }}</p>
                    <p class="text-[26px] font-bold text-head leading-tight">{{ booking.due }}</p>

                    <p v-if="booking.paid_minor > 0" class="text-[12px] text-sub mt-1">
                        {{ (labels.pay?.partial ?? '')
                            .replace(':paid', booking.paid)
                            .replace(':total', booking.total)
                            .replace(':due', booking.due) }}
                    </p>
                </div>

                <p v-if="failure" class="sd-alert sd-alert--danger mx-4 mt-3 text-[12.5px]" role="alert">{{ failure }}</p>

                <!-- The ways this business can be paid. One that has not been
                     set up is still shown, and says why it cannot be used:
                     hiding it leaves somebody hunting for Venmo. -->
                <div v-if="!method" class="p-4 space-y-1.5">
                    <p class="text-[12px] font-medium text-ink">{{ labels.pay?.method }}</p>

                    <button v-for="row in methods" :key="row.key" type="button"
                            class="styledesk_paymethod" :disabled="!row.ready"
                            @click="method = row.key">
                        <span class="min-w-0">
                            <span class="block text-[13px] font-semibold text-head">{{ row.name }}</span>
                            <span class="block text-[11.5px] text-sub">
                                {{ row.ready ? row.hint : labels.pay?.not_ready }}
                            </span>
                        </span>
                        <svg v-if="row.ready" width="14" height="14" viewBox="0 0 24 24" fill="none" class="text-faint shrink-0"><path d="M9 6l6 6-6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </button>
                </div>

                <div v-else class="p-4 space-y-3">
                    <div class="flex items-center gap-2">
                        <p class="min-w-0 flex-1 text-[13px] font-semibold text-head">{{ chosenMethod?.name }}</p>
                        <button type="button" class="text-[12px] font-semibold text-link hover:underline"
                                @click="method = ''">{{ labels.pay?.change_method }}</button>
                    </div>

                    <!-- Cash: what is owed, what was handed over, what goes back.
                         The third is the number being counted into a hand. -->
                    <template v-if="method === 'cash'">
                        <div>
                            <label for="pAmount" class="block text-[12px] font-medium text-ink mb-1">{{ labels.pay?.amount }}</label>
                            <input id="pAmount" v-model="payment.amount" type="text" inputmode="decimal" class="sd-input">
                        </div>
                        <div>
                            <label for="pGot" class="block text-[12px] font-medium text-ink mb-1">{{ labels.pay?.received }}</label>
                            <input id="pGot" v-model="payment.received" type="text" inputmode="decimal" class="sd-input">
                        </div>
                        <div class="flex items-baseline justify-between gap-3 text-[13px]">
                            <span class="text-sub">{{ labels.pay?.change }}</span>
                            <span class="font-semibold text-head">{{ changeDue }}</span>
                        </div>

                        <button type="button" class="styledesk_paycta" :disabled="busy" @click="takePayment(true)">
                            {{ busy ? labels.pay?.marking : labels.pay?.record }}
                        </button>
                    </template>

                    <!-- Card. The form is the design system's, and it is only
                         live where a provider is connected; with none, nothing
                         here pretends to charge anything — the terminal beside
                         the till took it, and this writes that down. -->
                    <template v-else-if="method === 'card'">
                        <p v-if="!chosenMethod?.charges" class="sd-alert sd-alert--warn text-[12.5px]">
                            {{ labels.pay?.no_card_provider }}
                        </p>

                        <fieldset :disabled="!chosenMethod?.charges" class="space-y-3"
                                  :class="{ 'opacity-55': !chosenMethod?.charges }">
                            <div>
                                <label for="cName" class="block text-[12px] font-medium text-ink mb-1">{{ labels.pay?.cardholder }}</label>
                                <input id="cName" v-model="card.name" type="text" autocomplete="cc-name" class="sd-input">
                            </div>
                            <div>
                                <label for="cNum" class="block text-[12px] font-medium text-ink mb-1">{{ labels.pay?.card_number }}</label>
                                <input id="cNum" v-model="card.number" type="text" inputmode="numeric" autocomplete="cc-number"
                                       placeholder="•••• •••• •••• ••••" class="sd-input">
                            </div>
                            <div class="grid grid-cols-3 gap-2">
                                <div class="col-span-1">
                                    <label for="cExp" class="block text-[12px] font-medium text-ink mb-1">{{ labels.pay?.expiry }}</label>
                                    <input id="cExp" v-model="card.expiry" type="text" placeholder="MM/YY" autocomplete="cc-exp" class="sd-input">
                                </div>
                                <div class="col-span-1">
                                    <label for="cCvv" class="block text-[12px] font-medium text-ink mb-1">{{ labels.pay?.cvv }}</label>
                                    <input id="cCvv" v-model="card.cvv" type="text" inputmode="numeric" autocomplete="cc-csc" class="sd-input">
                                </div>
                                <div class="col-span-1">
                                    <label for="cZip" class="block text-[12px] font-medium text-ink mb-1">{{ labels.pay?.zip }}</label>
                                    <input id="cZip" v-model="card.zip" type="text" inputmode="numeric" autocomplete="postal-code" class="sd-input">
                                </div>
                            </div>

                            <p class="text-[11.5px] text-faint">{{ labels.pay?.card_safe }}</p>

                            <button type="button" class="styledesk_paycta" :disabled="busy" @click="takePayment(false)">
                                {{ (labels.pay?.pay_amount ?? '').replace(':amount', booking.due) }}
                            </button>
                        </fieldset>

                        <div class="pt-3 border-t border-line space-y-3">
                            <div>
                                <label for="cRef" class="block text-[12px] font-medium text-ink mb-1">{{ labels.pay?.reference }}</label>
                                <input id="cRef" v-model="payment.reference" type="text" class="sd-input">
                                <p class="text-[11.5px] text-faint mt-1">{{ labels.pay?.reference_hint }}</p>
                            </div>

                            <button type="button" class="styledesk_paycta styledesk_paycta--quiet" :disabled="busy"
                                    @click="takePayment(true)">
                                {{ busy ? labels.pay?.marking : labels.pay?.terminal }}
                            </button>
                        </div>
                    </template>

                    <!-- PayPal, Zelle, Cash App, Venmo: the business's own
                         handle, read out, and then a person saying it arrived.
                         Nothing here can verify a transfer, so nothing here
                         claims to. -->
                    <template v-else>
                        <div class="styledesk_handle">
                            <p class="text-[11px] uppercase tracking-wide text-faint">{{ chosenMethod?.name }}</p>
                            <p class="text-[15px] font-semibold text-head mt-0.5 break-all">{{ chosenMethod?.handle }}</p>
                            <p class="text-[11.5px] text-sub mt-1">{{ labels.pay?.handle_hint }}</p>
                        </div>

                        <div>
                            <label for="hAmount" class="block text-[12px] font-medium text-ink mb-1">{{ labels.pay?.amount }}</label>
                            <input id="hAmount" v-model="payment.amount" type="text" inputmode="decimal" class="sd-input">
                        </div>
                        <div>
                            <label for="hRef" class="block text-[12px] font-medium text-ink mb-1">{{ labels.pay?.reference }}</label>
                            <input id="hRef" v-model="payment.reference" type="text" class="sd-input">
                        </div>

                        <button type="button" class="styledesk_paycta" :disabled="busy" @click="takePayment(true)">
                            {{ busy ? labels.pay?.marking : labels.pay?.mark_paid }}
                        </button>
                    </template>
                </div>

                <div class="px-4 py-3.5 border-t border-line space-y-2">
                    <!-- Plenty of businesses take the money on the day, or on
                         account. The booking is real either way; only the
                         balance is not. -->
                    <button type="button"
                            class="w-full h-9 rounded-lg text-[12.5px] font-semibold text-sub hover:text-ink hover:bg-hover transition-colors"
                            @click="stage = 'done'">
                        {{ labels.pay?.skip }}
                    </button>
                    <p class="text-[11.5px] text-faint text-center">{{ labels.pay?.skip_hint }}</p>
                </div>
                </div>
            </div>

            <!-- ----------------------------------------------- 3 · confirmation -->
            <div v-if="stage === 'done'" class="bg-white border border-line rounded-card overflow-hidden">
                <div class="styledesk_weekhead">
                    <span class="styledesk_bookingtick is-done" aria-hidden="true">
                        <svg width="11" height="11" viewBox="0 0 24 24" fill="none"><path d="M5 12.5l4.5 4.5L19 7.5" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </span>
                    <span class="min-w-0 flex-1 text-left text-[13px] font-semibold text-head">
                        {{ labels.confirmation?.title }}
                    </span>
                    <span class="text-[12px] font-semibold text-sub shrink-0">{{ booking.reference }}</span>
                </div>

                <div>
                <div class="p-4">
                    <!-- The card's own head already says it is confirmed. -->
                    <p class="text-[12.5px] text-sub">{{ labels.confirmation?.made }}</p>

                    <div class="styledesk_handle mt-3">
                        <p class="text-[11px] uppercase tracking-wide text-faint">{{ labels.summary?.reference }}</p>
                        <p class="text-[17px] font-bold text-head mt-0.5">{{ booking.reference }}</p>
                    </div>

                    <dl class="mt-3 space-y-2 text-[13px]">
                        <div class="flex items-baseline justify-between gap-3">
                            <dt class="text-sub">{{ labels.summary?.client }}</dt>
                            <dd class="font-semibold text-head text-right min-w-0 truncate">{{ booking.client }}</dd>
                        </div>
                        <div class="flex items-baseline justify-between gap-3">
                            <dt class="text-sub">{{ labels.summary?.services }}</dt>
                            <dd class="text-head text-right min-w-0">{{ booking.services.map((row) => row.name).join(', ') }}</dd>
                        </div>
                        <div class="flex items-baseline justify-between gap-3">
                            <dt class="text-sub">{{ labels.summary?.staff }}</dt>
                            <dd class="text-head text-right">{{ booking.staff }}</dd>
                        </div>
                        <div class="flex items-baseline justify-between gap-3">
                            <dt class="text-sub">{{ labels.summary?.when }}</dt>
                            <dd class="text-head text-right">{{ booking.date }} · {{ booking.time }}</dd>
                        </div>
                        <div v-if="booking.location" class="flex items-baseline justify-between gap-3">
                            <dt class="text-sub">{{ labels.summary?.location }}</dt>
                            <dd class="text-head text-right">{{ booking.location }}</dd>
                        </div>

                        <div class="pt-2 border-t border-line space-y-2">
                            <div class="flex items-baseline justify-between gap-3">
                                <dt class="text-sub">{{ labels.summary?.total }}</dt>
                                <dd class="font-semibold text-head">{{ booking.total }}</dd>
                            </div>
                            <div v-if="booking.paid_minor > 0" class="flex items-baseline justify-between gap-3">
                                <dt class="text-sub">{{ labels.summary?.paid }}</dt>
                                <dd class="font-semibold text-head">{{ booking.paid }}</dd>
                            </div>
                            <div v-for="row in booking.payments" :key="row.method" class="flex items-baseline justify-between gap-3">
                                <dt class="text-sub">{{ labels.confirmation?.payment }}</dt>
                                <dd class="text-head">{{ row.method_label }}</dd>
                            </div>
                            <div class="flex items-baseline justify-between gap-3">
                                <dt class="text-sub">{{ labels.pay?.title }}</dt>
                                <dd>
                                    <span class="styledesk_paystate" :class="`is-${booking.payment_status}`">
                                        {{ booking.payment_status_label }}
                                    </span>
                                </dd>
                            </div>
                        </div>
                    </dl>

                    <!-- Money still owed is the one thing on this panel that
                         somebody has to act on later, so it is said plainly
                         rather than left to be inferred from a badge. -->
                    <p v-if="booking.due_minor > 0" class="sd-alert sd-alert--warn mt-3 text-[12.5px]">
                        {{ (labels.confirmation?.due_notice ?? '').replace(':amount', booking.due) }}
                    </p>

                    <p v-if="lastPayment?.change" class="text-[12.5px] text-sub mt-3">
                        {{ labels.pay?.change }}: <span class="font-semibold text-head">{{ lastPayment.change }}</span>
                    </p>

                    <p v-if="sentTo" class="sd-alert sd-alert--info mt-3 text-[12.5px]">{{ sentTo }}</p>
                    <p v-if="failure" class="sd-alert sd-alert--danger mt-3 text-[12.5px]" role="alert">{{ failure }}</p>
                </div>

                <div class="px-4 py-3.5 border-t border-line space-y-2">
                    <a :href="booking.urls.show" class="block w-full h-11 rounded-lg bg-brand hover:bg-brand-dark text-white text-[13px] font-semibold text-center leading-[44px] transition-colors">
                        {{ labels.confirmation?.view }}
                    </a>

                    <div class="grid grid-cols-2 gap-2">
                        <button type="button" class="styledesk_action justify-center" :disabled="busy"
                                @click="sendConfirmation('email')">
                            {{ busy ? labels.confirmation?.sending : labels.confirmation?.send }}
                        </button>
                        <a :href="booking.urls.print" target="_blank" rel="noopener" class="styledesk_action justify-center">
                            {{ labels.confirmation?.print }}
                        </a>
                        <a :href="booking.urls.receipt" target="_blank" rel="noopener" class="styledesk_action justify-center">
                            {{ labels.confirmation?.receipt }}
                        </a>
                        <button type="button" class="styledesk_action justify-center" @click="startAnother">
                            {{ labels.confirmation?.another }}
                        </button>
                    </div>
                </div>
                </div>
            </div>
        </section>
    </form>
</template>
