<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import MultiSelect from './MultiSelect.vue';
import PaymentPanel from './PaymentPanel.vue';

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
    /** Where a walk-in's number and address are checked against the book. */
    matchClientUrl: { type: String, default: '' },
    /** Where the screen saves itself as it is filled in, as a booking lead. */
    autosaveUrl: { type: String, default: '' },
    /** Whether this reader may let a booking off its payment. */
    canWaive: { type: Boolean, default: false },
    /** Which times could actually be booked, asked again as choices change. */
    availabilityUrl: { type: String, default: '' },
    /* Which rooms one service could go in, and which are free. */
    resourcesUrl: { type: String, default: '' },
    /* What the booking comes to, worked out on the server. */
    quoteUrl: { type: String, default: '' },
    /* Whether this client is already booked for these services that day. */
    duplicatesUrl: { type: String, default: '' },
    /* Where the booking-in-progress is thrown away; :id is replaced. */
    discardLeadUrlPattern: { type: String, default: '' },
    /* Where an abandoned journey lands: the leads list when it started as a
       lead, the bookings list when it started here. */
    leadsUrl: { type: String, default: '' },
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

/*
 * Which room each service is in.
 *
 * Keyed by service id, because a booking can be a massage at ten and a
 * facial at eleven — two rooms, at two times, and one answer for both would
 * be wrong about at least one of them.
 *
 * Each entry: { id, name, auto, options, message }. `auto` is the difference
 * between a room the engine picked and one a person did: the first may be
 * replaced whenever the booking moves, the second may not.
 */
const rooms = ref({});
const roomPickerFor = ref(null);
let roomRequest = 0;

/** Whether anything on this booking has no room to go in. */
const roomProblem = computed(() => Object.values(rooms.value).find((room) => room.message) ?? null);

function roomOf(serviceId) {
    return rooms.value[serviceId] ?? null;
}

/**
 * Ask the server which rooms each service could use, and which are free.
 *
 * Only once there is a day and a time to ask about — "is Single Room 02
 * free" has no answer until somebody says when.
 */
async function refreshRooms() {
    if (! props.resourcesUrl || ! date.value || ! start.value) {
        return;
    }

    const ticket = ++roomRequest;
    const next = {};

    await Promise.all(chosen.value.map(async (service) => {
        const query = new URLSearchParams({
            service_id: String(service.id),
            date: date.value,
            starts_at: start.value,
        });

        if (locationId.value) query.set('location_id', String(locationId.value));
        if (props.lead?.booking_id) query.set('ignore', String(props.lead.booking_id));

        try {
            const response = await fetch(`${props.resourcesUrl}?${query.toString()}`, {
                headers: { Accept: 'application/json' },
            });

            if (! response.ok) {
                return;
            }

            const payload = await response.json();

            /* A service mapped to no rooms needs none — that is not the same
               as one whose rooms are all taken, and only the second is a
               problem worth telling anybody about. */
            if (! payload.required) {
                return;
            }

            const previous = rooms.value[service.id];
            const options = payload.options ?? [];

            /* A room somebody chose is kept while it is still possible.
               Replacing it the moment the clock moved would quietly undo
               their decision, which is the fastest way to lose their trust
               in the whole feature. */
            const keep = previous && ! previous.auto
                && options.some((option) => option.id === previous.id && option.available);

            const id = keep ? previous.id : payload.assigned;
            const option = options.find((o) => o.id === id) ?? null;

            next[service.id] = {
                id,
                name: option?.name ?? null,
                auto: ! keep,
                options,
                /* Said only when there is genuinely nowhere to put it. */
                message: id === null ? (payload.message ?? '') : '',
                /* So the screen can say "resource updated" rather than
                   silently swapping a room under somebody. */
                changed: previous && previous.id !== null && previous.id !== id,
            };
        } catch (error) {
            /* A failed lookup leaves the last good answer on screen: emptying
               it because the network blinked would read as no rooms free. */
            if (rooms.value[service.id]) {
                next[service.id] = rooms.value[service.id];
            }
        }
    }));

    /* A slower answer to an older question must not overwrite a faster
       answer to the current one. */
    if (ticket === roomRequest) {
        rooms.value = next;
    }
}

/** A room somebody picked themselves, which the engine will not overrule. */
function chooseRoom(serviceId, option) {
    if (! option.available && option.id !== roomOf(serviceId)?.id) {
        return;
    }

    rooms.value = {
        ...rooms.value,
        [serviceId]: { ...rooms.value[serviceId], id: option.id, name: option.name, auto: false, message: '', changed: false },
    };

    roomPickerFor.value = null;
}

const source = ref(props.walkIn ? 'walk-in' : 'front-desk');
const notes = ref('');
const clientNote = ref('');
const payType = ref('none');
const deposit = ref('');
const collectionMethod = ref('later');

/*
 * How the client is paying, and so which price applies.
 *
 * Card by default rather than whichever is cheaper: the screen has to quote
 * a price before anybody has said how they are paying, and quoting the lower
 * one and then charging more is the version a client complains about.
 */
const paymentMethod = ref('card');

/** Whether any chosen service is actually priced differently for cash. */
const hasTwoPrices = computed(() => chosen.value.some((service) => service.two_prices));

/*
 * What the booking comes to, from the server.
 *
 * The arithmetic is not done here: the coupon rules alone would mean handing
 * the browser the whole promotions table, and a quote worked out twice is a
 * quote that can disagree with itself. The screen asks and renders.
 */
const quote = ref(null);
const couponCode = ref('');
const couponError = ref('');
/* The code that has actually been applied, as opposed to what is typed. */
const appliedCoupon = ref('');
const tipPercent = ref(null);
const customTip = ref('');
const quoting = ref(false);
let quoteTimer = null;
let quoteRequest = 0;

async function refreshQuote() {
    if (! props.quoteUrl || ! chosen.value.length) {
        quote.value = null;

        return;
    }

    const ticket = ++quoteRequest;

    quoting.value = true;

    try {
        const response = await fetch(props.quoteUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': props.csrf,
            },
            body: JSON.stringify({
                services: chosen.value.map((service) => service.id),
                payment_method: paymentMethod.value,
                client_id: client.value?.id ?? null,
                location_id: locationId.value || null,
                coupon: appliedCoupon.value || null,
                /* One or the other, never both: a typed amount is a decision
                   and a percentage is a share, and sending both would leave
                   the server guessing which the reader meant. */
                tip_percent: customTip.value === '' ? tipPercent.value : null,
                tip_amount: customTip.value === '' ? null : customTip.value,
            }),
        });

        if (! response.ok || ticket !== quoteRequest) {
            return;
        }

        const payload = await response.json();

        quote.value = payload;
        couponError.value = payload.coupon_error ?? '';

        /* A coupon the server refused is not applied, whatever the box
           still says. */
        if (payload.coupon_error) {
            appliedCoupon.value = '';
        }
    } catch (error) {
        /* A failed quote leaves the last good one on screen rather than
           blanking the total somebody is reading. */
    } finally {
        if (ticket === quoteRequest) {
            quoting.value = false;
        }
    }
}

function applyCoupon() {
    couponError.value = '';
    appliedCoupon.value = couponCode.value.trim().toUpperCase();
    refreshQuote();
}

function removeCoupon() {
    appliedCoupon.value = '';
    couponCode.value = '';
    couponError.value = '';
    refreshQuote();
}

/** A percentage: recalculated whenever anything it is a share of moves. */
function chooseTipPercent(percent) {
    tipPercent.value = percent;
    customTip.value = '';
    refreshQuote();
}

/** An amount somebody typed: left exactly as typed. */
function applyCustomTip() {
    tipPercent.value = null;
    refreshQuote();
}

/** What one service costs the way this booking is being paid for. */
function serviceMinor(service) {
    return paymentMethod.value === 'cash'
        ? (service.cash_price_minor ?? service.price_minor)
        : service.price_minor;
}
const waiverReason = ref('');
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

    /* Payment carries a default that is a real answer — nothing now — except
       where a service insists otherwise, which is the one thing on this card
       the reader cannot simply leave. */
    if (step === 'payment' && depositTooLittle.value) {
        return depositShortfallMessage.value;
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

    /* Nothing is posted from here. The auto-save is the only thing on this
       screen that writes the lead, and `stepsDone` is part of what it saves —
       so finishing a card is a change like any other and goes in with it. Two
       writers would be two leads for one booking. */
}

/* --------------------------------------------------------- the auto-save ---

   The booking saves itself as it is filled in.

   A booking is taken over the phone and a phone call is interrupted, so what
   this protects is the work: the moment the screen knows who the appointment
   is for it writes a booking lead and gets a reference back, and every later
   change goes into that same record. A call that drops leaves a row under
   Bookings → Leads that anybody can pick back up with Continue Booking.

   A lead is not an appointment. It holds no slot, tells nobody anything, and
   stays out of the diary until Confirm Booking turns it into a booking —
   under the same reference it has been quoted under all along.

   One record per attempt. There is exactly one writer on this screen, which
   is what stops a booking leaving two rows behind it. */
const lead = ref(null);
const autosaveState = ref('');

/* The one thing that has to be true before anything is written down. A row
   saved before anybody is named is a lead nobody could match to a caller. */
const hasClientInfo = computed(() => Boolean(client.value?.id) || guest.value.name.trim() !== '');

/* Everything the lead carries, as one string. Compared rather than watched
   field by field: it is one booking, and a watcher per answer is a dozen
   watchers that can disagree about whether anything changed.

   Without the id, which is not an answer anybody gave: including it would
   make the first save's own reply look like a change and save again. */
const draftState = computed(() => {
    const { lead_id: id, ...answers } = autosaveBody();

    return JSON.stringify(answers);
});

let autosaveTimer = null;
let autosaveInFlight = false;
let autosaveSaved = '';

function autosaveBody() {
    return {
        lead_id: lead.value?.id ?? null,
        client_id: client.value?.id ?? null,
        guest_name: client.value ? null : (guest.value.name.trim() || null),
        guest_phone: client.value ? null : (guest.value.phone || null),
        guest_email: client.value ? null : (guest.value.email || null),
        staff_id: staffId.value || null,
        location_id: locationId.value || null,
        date: date.value || null,
        starts_at: start.value || null,
        services: chosen.value.map((service) => service.id),
        /* Only the rooms somebody chose. A blank means "you decide", which
           is the usual answer and is not the same as a choice. */
        resources: Object.fromEntries(
            Object.entries(rooms.value)
                .filter(([, room]) => ! room.auto && room.id)
                .map(([serviceId, room]) => [serviceId, room.id]),
        ),
        source: source.value,
        payment_type: payType.value,
        deposit: payType.value === 'deposit' ? (depositMinor.value / 100).toFixed(2) : null,
        collection_method: payType.value === 'none' ? null : collectionMethod.value,
        payment_method: paymentMethod.value,
        waiver_reason: collectionMethod.value === 'waive' ? waiverReason.value : null,
        confirmation: confirmation.value,
        notes: notes.value,
        client_note: client.value ? clientNote.value : null,
        /* How far through the five cards this got. It is what the leads queue
           reads as "stopped at", and it is the difference between a call to
           return about a price and one to return about a time. */
        current_step: currentStep(),
    };
}

/** The first card that has not been saved yet, or that the reader is on. */
function currentStep() {
    return STEPS.find((step) => ! stepsDone.value[step]) ?? 'completed';
}

/**
 * Write the lead down.
 *
 * One save at a time, and the last state wins: a receptionist typing through
 * a debounce can put three changes in flight, and the record must end up
 * holding the newest rather than whichever reply landed last. So a save that
 * finds the screen has moved on runs again the moment it is finished.
 *
 * Never after the booking has been taken. From then on the appointment is
 * real, the lead is converted, and `update()` is what edits it.
 */
async function autosave() {
    if (! props.autosaveUrl || ! hasClientInfo.value || booking.value || autosaveInFlight) {
        return;
    }

    const sent = draftState.value;

    /* Nothing has actually changed. Reached when a queued save is overtaken
       by the answer it was going to send — a lead restored by Continue
       Booking is the ordinary case — and writing the record back with what
       it already holds would be a save nobody asked for. */
    if (sent === autosaveSaved) {
        return;
    }

    autosaveInFlight = true;
    autosaveState.value = 'saving';

    try {
        const { ok, json } = await send(props.autosaveUrl, autosaveBody());

        if (! ok) {
            autosaveState.value = 'failed';

            return;
        }

        lead.value = json.lead;
        autosaveSaved = sent;
        autosaveState.value = 'saved';
    } catch (error) {
        /* Offline, or the tab went to sleep mid-request. Said quietly and
           tried again on the next change: a receptionist mid-call cannot act
           on this, and a dialog over the booking would stop them working. */
        autosaveState.value = 'failed';
    } finally {
        autosaveInFlight = false;
    }

    /* The screen moved while that was in the air, so the record is holding
       something older than what is on it. Only after a save that worked: a
       failure retried on its own would be a request every second for as long
       as the connection is down, and the next change will try again anyway. */
    if (autosaveState.value === 'saved' && draftState.value !== autosaveSaved) {
        queueAutosave();
    }
}

/** Wait for the typing to stop. Long enough not to save a name a letter at a
    time, short enough that "Saved" is true by the time anybody looks. */
function queueAutosave() {
    clearTimeout(autosaveTimer);
    autosaveTimer = setTimeout(autosave, 800);
}

watch(draftState, (now) => {
    if (now === autosaveSaved) {
        return;
    }

    queueAutosave();
});

onBeforeUnmount(() => clearTimeout(autosaveTimer));

/** What the indicator says, or nothing at all until there is a lead. */
const autosaveLabel = computed(() => {
    if (autosaveState.value === 'saving') {
        return props.labels.autosave?.saving ?? '';
    }

    if (autosaveState.value === 'failed') {
        return props.labels.autosave?.failed ?? '';
    }

    return autosaveState.value === 'saved' ? (props.labels.autosave?.saved ?? '') : '';
});

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

/**
 * Open on this client's own answers.
 *
 * A booking started from somebody's profile should arrive where they usually
 * have it, with who they usually have it with. Only where nothing has been
 * chosen yet: a receptionist who has already picked a branch is not
 * overruled by a preference set months ago.
 */
function applyClientPreferences(found) {
    if (found?.preferred_location_id && props.locations.some((place) => place.id === found.preferred_location_id)) {
        locationId.value = found.preferred_location_id;
    }

    if (found?.preferred_staff_id && ! staffId.value
        && props.staff.some((member) => member.id === found.preferred_staff_id)) {
        staffId.value = found.preferred_staff_id;
    }
}

async function chooseClient(found) {
    applyClientPreferences(found);

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
    /* A note written against a client is not a note about the walk-in who
       replaces them. Cleared with the client rather than left in a hidden
       field, where it would be posted against whoever came next. */
    clientNote.value = '';

    client.value = null;
    context.value = null;
}

// ------------------------------------------------------------------ walk-in

/* A walk-in is a booking with a name and no record behind it, which is right
   for somebody who came in once and wrong for a regular whose name the
   receptionist typed instead of searching for. The second is easy to do and
   expensive to undo: the history, the preferences and the preferred stylist
   all stay on the record nobody used, and the desk ends up holding two of the
   same person.

   So the number and the address are checked against the book as they are
   typed. A warning, never a block — two people share a phone, and a wrongly
   merged history is not something a receptionist can unpick. */
const guestMatches = ref([]);
const guestSaved = ref(false);
const guestChecking = ref(false);

let guestTimer = null;

/* What the check runs against. A name alone is not enough to claim two people
   are one: this business has more than one Sarah. */
const guestContact = computed(() => `${guest.value.phone.trim()}|${guest.value.email.trim()}`);

async function findExistingClient() {
    if (! props.matchClientUrl || guestContact.value === '|') {
        guestMatches.value = [];

        return;
    }

    guestChecking.value = true;

    try {
        const { ok, json } = await send(props.matchClientUrl, {
            name: guest.value.name.trim() || null,
            mobile: guest.value.phone.trim() || null,
            email: guest.value.email.trim() || null,
        });

        guestMatches.value = ok ? (json.matches ?? []) : [];
    } catch (error) {
        /* The check is an assist, not a gate. A receptionist mid-call is not
           helped by an error about a lookup they did not ask for. */
        guestMatches.value = [];
    } finally {
        guestChecking.value = false;
    }
}

/* Checked as it is typed, on a debounce: a number is worth checking the
   moment it is whole, and not once per keystroke. */
watch(guestContact, () => {
    guestSaved.value = false;
    clearTimeout(guestTimer);
    guestTimer = setTimeout(findExistingClient, 500);
});

watch(() => guest.value.name, () => {
    guestSaved.value = false;
});

onBeforeUnmount(() => clearTimeout(guestTimer));

/**
 * Save the walk-in's details.
 *
 * The screen has been saving as it goes, so this writes nothing the auto-save
 * would not have. What it is for is the other half: it runs the check against
 * the book before the receptionist moves on, and it says out loud that the
 * details are in — which is what somebody who has just typed three fields is
 * looking for, and what was missing here.
 */
async function saveGuest() {
    if (! guest.value.name.trim()) {
        return;
    }

    await findExistingClient();

    /* Straight in rather than on the debounce: the reader asked for it. */
    clearTimeout(autosaveTimer);
    await autosave();

    guestSaved.value = true;
}

/** Use the record instead of the walk-in. Their history is the whole point. */
function useExistingClient(match) {
    guestMatches.value = [];
    guestSaved.value = false;
    guest.value = { name: '', phone: '', email: '' };
    chooseClient(match);
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

/* ------------------------------------------- the service selector ---------

   Choosing services is its own screen.

   It used to be a list inside the Service card, which works for a salon with
   twelve services and falls apart at a hundred: a 260px scroller inside a
   form field, with the categories behind a combo and the booking's own
   summary pushed below the fold. So the card now says only what has been
   chosen, and choosing happens on a full page — categories down one side,
   services down the other, one Save at the bottom.

   The selection is drafted rather than applied as it is made. Ticking a
   service is not a decision until the reader says so at the bottom of the
   page, which is what makes Cancel mean anything — and what stops each tick
   re-asking the server which times are still free. */
const sheetOpen = ref(false);
const sheetPicked = ref([]);
const sheetCategory = ref('');
const serviceQuery = ref('');

/* The services this client is known to want, said by somebody at the desk.
   Shown first in the selector, because a repeat booking is the commonest
   thing a desk does and searching a hundred services for the same balayage
   every time is the work this saves. */
const favoriteServiceIds = computed(() => client.value?.favorite_service_ids ?? []);

const favoriteServices = computed(() => favoriteServiceIds.value
    .map((id) => props.services.find((service) => service.id === id))
    .filter(Boolean));

/** Only what this branch offers, which is the list every count is taken from. */
const offeredHere = computed(() => props.services.filter((service) => ! locationId.value
    /* An empty list means everywhere — Service::isOfferedAt() reads it the
       same way — so a service that names no locations is offered at all. */
    || ! service.location_ids?.length
    || service.location_ids.map(String).includes(String(locationId.value))));

/* All Services first, then the categories something is actually offered in,
   each with what is in it. A count is what tells a receptionist whether the
   category is worth opening. */
const sheetCategories = computed(() => {
    const counts = new Map();

    offeredHere.value.forEach((service) => {
        const key = String(service.category_id ?? '');

        counts.set(key, (counts.get(key) ?? 0) + 1);
    });

    return [
        { id: '', name: props.labels.service?.all_services, count: offeredHere.value.length },
        ...Object.entries(props.categories)
            .filter(([id]) => counts.has(String(id)))
            .map(([id, name]) => ({ id: String(id), name, count: counts.get(String(id)) })),
    ];
});

/* What the second column shows. The search reaches across every category,
   because somebody typing "balayage" is naming a service and not asking
   where it is filed. */
const sheetServices = computed(() => {
    const term = serviceQuery.value.trim().toLowerCase();

    return offeredHere.value
        .filter((service) => term
            || ! sheetCategory.value
            || String(service.category_id ?? '') === sheetCategory.value)
        .filter((service) => ! term || service.name.toLowerCase().includes(term));
});

const sheetPickedCount = computed(() => sheetPicked.value.length);

const sheetMinutes = computed(() => sheetPicked.value
    .reduce((sum, id) => sum + (props.services.find((one) => one.id === id)?.minutes ?? 0), 0));

const sheetTotalMinor = computed(() => sheetPicked.value
    .reduce((sum, id) => sum + (props.services.find((one) => one.id === id)?.price_minor ?? 0), 0));

function openServiceSheet() {
    /* Seeded from what the booking already holds, so the page opens on the
       booking as it stands rather than on an empty one. */
    sheetPicked.value = chosen.value.map((service) => service.id);
    sheetCategory.value = '';
    serviceQuery.value = '';
    sheetOpen.value = true;
}

function toggleSheetService(service) {
    sheetPicked.value = sheetPicked.value.includes(service.id)
        ? sheetPicked.value.filter((id) => id !== service.id)
        : [...sheetPicked.value, service.id];
}

const isPicked = (service) => sheetPicked.value.includes(service.id);

/**
 * Take the selection.
 *
 * In the order they were ticked, which is the order they will be worked and
 * the order the summary shows — not the order of the catalogue.
 *
 * Everything downstream follows from `chosen`: the duration, the bill, the
 * chair or room it needs, and the times still free with all of that in it.
 * Setting it here is what recalculates them, which is why nothing else has
 * to be told.
 */
function saveServiceSheet() {
    chosen.value = sheetPicked.value
        .map((id) => props.services.find((service) => service.id === id))
        .filter(Boolean);

    sheetOpen.value = false;
}

/** Back to the booking, with the selection as it was. */
function cancelServiceSheet() {
    sheetOpen.value = false;
}

/* Escape leaves without applying, the way every other overlay on the screen
   behaves — and the way a reader who opened it by accident expects. */
function closeSheetOnEscape(event) {
    if (event.key === 'Escape' && sheetOpen.value) {
        cancelServiceSheet();
    }
}

/* The page behind must not scroll under a full-screen selector: two
   scrollbars, and a reader who closes the sheet to find the booking somewhere
   else than they left it. */
watch(sheetOpen, (isOpen) => {
    document.body.style.overflow = isOpen ? 'hidden' : '';
});

onMounted(() => document.addEventListener('keydown', closeSheetOnEscape));
onBeforeUnmount(() => {
    document.removeEventListener('keydown', closeSheetOnEscape);
    document.body.style.overflow = '';
});

function removeService(service) {
    chosen.value = chosen.value.filter((one) => one.id !== service.id);
}

const minutes = computed(() => chosen.value.reduce((sum, service) => sum + service.minutes, 0));

const totalMinor = computed(() => chosen.value.reduce((sum, service) => sum + serviceMinor(service), 0));

/** "45 min", "2 hr", "2 hr 30 min" — as a person says a length out loud. */
function durationLabel(count) {
    const hours = Math.floor(count / 60);
    const rest = count % 60;
    const short = (labels) => (labels ?? ':count min').replace(':count', rest);

    if (! hours) {
        return short(props.labels.service?.minutes);
    }

    if (! rest) {
        return (props.labels.service?.hours ?? ':count hr').replace(':count', hours);
    }

    return (props.labels.service?.hours_minutes ?? ':hours hr :minutes min')
        .replace(':hours', hours)
        .replace(':minutes', rest);
}

/** "1 service" / "4 services", so the card's summary reads as a sentence. */
function serviceCountLabel(count) {
    return count === 1
        ? (props.labels.service?.count_one ?? '1 service')
        : (props.labels.service?.count ?? ':count services').replace(':count', count);
}

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
    lead.value = { id: from.id, reference: from.reference, status_label: from.status_label };

    if (from.date) {
        date.value = from.date;
        dateMode.value = from.date === props.today ? 'today' : 'custom';
    }

    chosen.value = from.service_ids
        .map((id) => props.services.find((service) => service.id === id))
        /* A service withdrawn since the call cannot be rebooked, and a lead
           that silently restored it would be a price nobody can charge. */
        .filter(Boolean);

    if (from.location_id) {
        locationId.value = from.location_id;
    }

    if (from.staff_id) {
        staffId.value = from.staff_id;
    }

    if (from.starts_at) {
        start.value = from.starts_at;
    }

    source.value = from.source ?? source.value;
    payType.value = from.payment_type ?? payType.value;
    deposit.value = from.deposit ?? '';
    collectionMethod.value = from.collection_method ?? collectionMethod.value;
    waiverReason.value = from.waiver_reason ?? '';
    confirmation.value = from.confirmation ?? confirmation.value;
    notes.value = from.notes ?? '';
    clientNote.value = from.client_note ?? '';

    if (from.client) {
        chooseClient(from.client);
    } else if (from.guest_name) {
        mode.value = 'walkin';
        guest.value = {
            name: from.guest_name,
            phone: from.guest_phone ?? '',
            email: from.guest_email ?? '',
        };
    }

    /* Every card that was answered before, ticked. The screen is the one
       they left, not a fresh one wearing their answers — a receptionist
       picking this up should be looking at the first question still open. */
    STEPS.forEach((step) => {
        if (STEPS.indexOf(step) < STEPS.indexOf(from.current_step ?? 'service')) {
            stepsDone.value = { ...stepsDone.value, [step]: true };
        }
    });

    if (chosen.value.length) {
        stepsDone.value = { ...stepsDone.value, service: true };
    }

    if (start.value) {
        stepsDone.value = { ...stepsDone.value, when: true };
    }

    open.value = STEPS.find((step) => ! stepsDone.value[step]) ?? '';

    /* Restored, not changed. Everything above set a ref the auto-save
       watches, and letting it fire would write the lead straight back with
       what it already holds — and show "Saving…" over a screen where nobody
       has touched anything yet. */
    nextTick(() => {
        autosaveSaved = draftState.value;
        autosaveState.value = 'saved';
    });
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

/**
 * The team this branch can be booked with.
 *
 * Somebody with no location works across all of them — that is what the null
 * means on a staff record — so they stay on the list wherever the booking is
 * being taken. Hiding them would empty the picker for most businesses, which
 * have one branch and never fill the column in.
 */
const bookableStaff = computed(() => props.staff.filter((member) => ! locationId.value
    || ! member.location_id
    || String(member.location_id) === String(locationId.value)));

/* A stylist who does not work at the branch just chosen cannot stay chosen:
   the booking would post a person who is not there. */
watch(locationId, () => {
    if (staffId.value && ! bookableStaff.value.some((member) => String(member.id) === String(staffId.value))) {
        staffId.value = '';
    }

    /* And a service that branch does not offer goes with it, for the same
       reason — it would be posted against a location that cannot do it. */
    chosen.value = chosen.value.filter((service) => ! service.location_ids?.length
        || service.location_ids.map(String).includes(String(locationId.value)));
});

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

    /* A deposit larger than the bill is money the desk would have to give
       back before the appointment has been worked. */
    if (depositTooMuch.value) {
        return props.labels.payment?.too_much;
    }

    /* And one smaller than the services insist on is a booking the business
       said it would not take. */
    if (depositTooLittle.value) {
        return depositShortfallMessage.value;
    }

    if (waiverMissing.value) {
        return props.labels.payment?.waiver_needed;
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

/* Reaching the confirmation takes the reader to the top of the page.
   The panel is the first thing on it, and a screen that put the one fact
   somebody wants — did that go through? — above a viewport still scrolled to
   the payment card would be hiding it just as thoroughly as before. */
watch(stage, (now) => {
    /* The page turns grey behind the finished booking, so the document sits
       on something rather than floating in the same white the form used. It
       is the clearest signal that the workflow is over: nothing on this
       screen is still waiting to be saved. */
    document.body.classList.toggle('bg-hover', now === 'done');
    document.getElementById('bookingFormHeader')?.classList.toggle('hidden', now === 'done');

    if (now === 'done') {
        nextTick(() => window.scrollTo({ top: 0, behavior: 'smooth' }));
    }
});

onBeforeUnmount(() => {
    document.body.classList.remove('bg-hover');
    document.getElementById('bookingFormHeader')?.classList.remove('hidden');
});
const booking = ref(null);
const busy = ref(false);
const failure = ref('');
const lastPayment = ref(null);
const sentTo = ref('');
/* Said on the confirmation rather than thrown: the appointment is real
   whether or not the email got out, and the desk needs to know to ring. */
const linkFailure = ref('');

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

/*
 * The rooms this booking is actually in.
 *
 * Not every room the services *could* use — on a spa with six of them that
 * read as though one client had been given the whole building. These are the
 * ones assigned, one per service.
 */
const assignedRooms = computed(() => [...new Set(
    Object.values(rooms.value).map((room) => room.name).filter(Boolean),
)]);

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
 * The times that could actually be booked, from the server.
 *
 * Seeded with what the page arrived holding, so the list is never empty while
 * the first request is in flight, and replaced as soon as an answer comes
 * back. The browser is deliberately not the thing that works this out: it
 * would need every rota, closure, resource block and existing booking to do
 * it, which is both a slower page and a description of the salon's whole day
 * handed to anyone who opens the console.
 */
const availableTimes = ref([...props.times]);
const availabilityMessage = ref('');
const checkingTimes = ref(false);
let availabilityTimer = null;
let availabilityRequest = 0;

async function refreshAvailability() {
    if (! props.availabilityUrl) {
        return;
    }

    const query = new URLSearchParams({ date: date.value });

    if (locationId.value) query.set('location_id', String(locationId.value));
    if (staffId.value) query.set('staff_id', String(staffId.value));
    chosen.value.forEach((service) => query.append('service_ids[]', String(service.id)));

    /* Numbered, because the answers can arrive out of order: a slow reply to
       last week's date must not overwrite the times for the one on screen. */
    const ticket = ++availabilityRequest;

    checkingTimes.value = true;

    try {
        const response = await fetch(`${props.availabilityUrl}?${query.toString()}`, {
            headers: { Accept: 'application/json' },
        });

        if (! response.ok || ticket !== availabilityRequest) {
            return;
        }

        const payload = await response.json();

        availableTimes.value = payload.slots ?? [];
        availabilityMessage.value = payload.message ?? '';

        /* A time that is no longer offered cannot stay chosen: it would post
           a start the server has just said is unavailable. */
        if (start.value && ! availableTimes.value.includes(start.value)) {
            start.value = '';
        }
    } catch (error) {
        /* A failed lookup leaves the last good answer on screen. Emptying the
           list because the network blinked would read as a fully booked day. */
    } finally {
        if (ticket === availabilityRequest) {
            checkingTimes.value = false;
        }
    }
}

/* Every one of these can empty the list, so every one of them asks again.
   Debounced together: choosing three services is three changes and one
   question. */
watch([locationId, staffId, date, chosen], () => {
    window.clearTimeout(availabilityTimer);
    availabilityTimer = window.setTimeout(refreshAvailability, 200);
}, { deep: true });

/* The rooms are worked out again whenever anything that decides them moves:
   the day, the time, the branch or the services. A room that is still free
   keeps its booking; one that is not is replaced, and the card says so. */
let roomTimer = null;

watch([locationId, date, start, chosen], () => {
    window.clearTimeout(roomTimer);
    roomTimer = window.setTimeout(refreshRooms, 200);
}, { deep: true });

/* The total is asked for again whenever anything that decides it moves: the
   services, how it is being paid, the client the coupon is checked against.
   The tip and the coupon ask for themselves, because they are pressed. */
watch([chosen, paymentMethod, client], () => {
    window.clearTimeout(quoteTimer);
    quoteTimer = window.setTimeout(refreshQuote, 200);
}, { deep: true });

onMounted(() => {
    refreshAvailability();
    refreshRooms();
});

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

    availableTimes.value.forEach((slot) => {
        const hour = Number(slot.slice(0, 2));

        windows[hour < 12 ? 'morning' : hour < 17 ? 'afternoon' : 'evening'].push(slot);
    });

    /* All three are returned, empty ones included: the segmented control has
       to draw a button for a part of the day with nothing in it — greyed, so
       "nothing on Tuesday morning" is something the screen says rather than
       something the reader infers from a missing button. Which of them is
       shown is `period` below. */
    return Object.entries(windows).map(([key, slots]) => ({ key, slots }));
});

/** Which part of the day is being looked at. */
const period = ref('morning');

const currentGroup = computed(() => timeGroups.value.find((group) => group.key === period.value) ?? null);

/**
 * Land on a part of the day that has something in it.
 *
 * A salon that opens at noon has no morning, and opening on an empty segment
 * reads as a day with no availability at all. The first one with slots is
 * chosen instead — and the same rule moves the reader off a segment that has
 * just been emptied by a change of branch, staff member or date.
 *
 * A segment the reader chose themselves is left alone while it still has
 * slots: being moved off a deliberate choice is worse than an empty list.
 */
function settlePeriod() {
    const current = timeGroups.value.find((group) => group.key === period.value);

    if (current?.slots.length) {
        return;
    }

    period.value = (timeGroups.value.find((group) => group.slots.length) ?? timeGroups.value[0]).key;
}

watch(availableTimes, settlePeriod, { immediate: true });

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

/**
 * The branch picker behind the card in the page header.
 *
 * A popover rather than a select: it carries a search box, which a salon with
 * a dozen branches needs and a native select cannot have — and it is the same
 * shape as every other searchable list in the app.
 */
const locationPickerOpen = ref(false);
const locationQuery = ref('');

const locationMatches = computed(() => {
    const term = locationQuery.value.trim().toLowerCase();

    return props.locations.filter((place) => ! term || place.name.toLowerCase().includes(term));
});

function openLocationPicker() {
    locationQuery.value = '';
    locationPickerOpen.value = true;
}

function chooseLocation(place) {
    locationId.value = place.id;
    locationPickerOpen.value = false;

    /* Everything downstream is watched — the times, the team, the price list
       — so nothing else has to be told. What does have to go is a start time
       chosen against the branch being left: it may not be open then. */
    start.value = '';
}

/* Clicking away closes it, like every other popover on the page. */
function closeLocationPicker(event) {
    if (! event.target.closest?.('[data-location-picker]')) {
        locationPickerOpen.value = false;
    }
}

onMounted(() => document.addEventListener('click', closeLocationPicker));
onBeforeUnmount(() => document.removeEventListener('click', closeLocationPicker));

const dueMinor = computed(() => booking.value?.due_minor ?? estimate.value.total);

/* ------------------------------------------------------- the deposit -----

   A deposit is part of the bill taken now against a booking worked later.
   The rest is still owed, which is why the balance is stated beside it: a
   number typed into a box with no balance under it is one nobody checks. */
/*
 * What the booking actually comes to, tip and discount included.
 *
 * The quote is the server's answer and the estimate is the screen's own
 * arithmetic while one is still in flight. The deposit is worked out against
 * this rather than the bare service total: a client leaving a deposit on a
 * hundred-and-fifteen-pound visit is leaving it on a hundred and fifteen.
 */
const payableMinor = computed(() => quote.value?.total_minor ?? estimate.value.total);

/*
 * The deposit, as a percentage where one was chosen.
 *
 * Kept as the percentage rather than as the figure it worked out to, which
 * is the whole point: a fifteen per cent deposit on a bill that then grows a
 * tip is fifteen per cent of the new bill. Storing the amount would freeze it
 * at whatever the bill happened to be when the button was pressed, and the
 * desk would collect the wrong number without anything looking wrong.
 *
 * Null means somebody typed an amount instead, and a typed amount is a
 * decision — it stays exactly as typed. Same rule as the tip.
 */
const depositPercent = ref(null);

const depositMinor = computed(() => (depositPercent.value === null
    ? Math.round(Number(deposit.value || 0) * 100)
    : Math.round(payableMinor.value * depositPercent.value / 100)));

/** What is still owed after the deposit — the number the desk chases later. */
const remainingMinor = computed(() => Math.max(0, payableMinor.value - depositMinor.value));

/* Refused rather than silently clamped: a receptionist who typed 500 against
   a $150 bill has made a mistake worth seeing, and a box that quietly
   rewrote it to 150 would hide it. The server refuses it too. */
const depositTooMuch = computed(() => payType.value === 'deposit'
    && depositMinor.value > payableMinor.value);

/* ------------------------------------------- a deposit that is not asked --

   Some services are not booked without money up front, and that is a decision
   the service already carries: "Deposit required" on the service form, as a
   percentage of its price or as a flat sum.

   Added up across the lines rather than read off one of them — a booking of
   three services where two take a deposit owes both — and worked out from the
   rule rather than from a figure copied at the moment of choosing, so removing
   a service or switching to the cash price moves it on its own. */
const depositRules = computed(() => chosen.value.map((service) => service.deposit).filter(Boolean));

const requiredDepositMinor = computed(() => Math.min(
    chosen.value.reduce((sum, service) => {
        const rule = service.deposit;

        if (!rule) {
            return sum;
        }

        return sum + (rule.type === 'percent'
            ? Math.round(serviceMinor(service) * rule.percent / 100)
            : rule.minor);
    }, 0),
    payableMinor.value,
));

const depositIsRequired = computed(() => requiredDepositMinor.value > 0);

/*
 * The percentage to say it in, where there is one to say.
 *
 * Only when every chosen service asks for the same percentage: 20% of one
 * service and nothing on the one beside it is not 20% of the booking, and a
 * screen that said so would be telling the client a number that is wrong.
 */
const requiredDepositPercent = computed(() => {
    const rules = depositRules.value;

    if (!rules.length || rules.length !== chosen.value.length) {
        return null;
    }

    return rules.every((rule) => rule.type === 'percent' && rule.percent === rules[0].percent)
        ? rules[0].percent
        : null;
});

/* Raised, never lowered: the desk may ask for more than the service insists
   on, and may not ask for less. */
const depositTooLittle = computed(() => depositIsRequired.value
    && payType.value === 'deposit'
    && depositMinor.value < requiredDepositMinor.value);

const depositShortfallMessage = computed(() => (props.labels.payment?.too_little ?? '')
    .replace(':amount', money(requiredDepositMinor.value)));

/*
 * The required deposit, applied the moment it applies.
 *
 * Watched rather than computed into the field: what is in the box is the
 * reader's answer, and this only steps in where that answer would collect
 * less than the services insist on — adding a service, removing one, or
 * switching to the cash price all pass through here.
 */
watch([requiredDepositMinor, requiredDepositPercent, payType], () => {
    if (!depositIsRequired.value || payType.value === 'full') {
        return;
    }

    if (payType.value === 'none') {
        payType.value = 'deposit';

        return;
    }

    if (depositMinor.value >= requiredDepositMinor.value) {
        return;
    }

    /* Kept as the percentage where every service agrees on one, so it follows
       the bill the way the presets below it do. */
    depositPercent.value = requiredDepositPercent.value;
    deposit.value = requiredDepositPercent.value === null
        ? (requiredDepositMinor.value / 100).toFixed(2)
        : '';
}, { immediate: true });

/** Nothing now is not on offer while a service insists on a deposit. */
function choosePayType(option) {
    if (option === 'none' && depositIsRequired.value) {
        return;
    }

    payType.value = option;
}

function setDepositPercent(percent) {
    depositPercent.value = percent;
    deposit.value = '';
}

/** Typing an amount is choosing an amount, so the percentage stops. */
function typeDepositAmount() {
    depositPercent.value = null;
}

const depositIsPercent = (percent) => depositPercent.value === percent;

/* ------------------------------------------- the collection method -------

   How the money is actually collected, which is a different question from
   how much. "Take a deposit" says what is owed now; this says whether it is
   charged here, taken at the till, asked for by link, collected on arrival,
   or written off. */
const collectionMethods = ['collect-now', 'desk', 'link', 'later', 'waive'];

const canWaive = computed(() => props.canWaive);

/* Waiving needs a reason, because it is the one method that is a decision
   rather than a mechanism — and a decision nobody wrote a reason for is one
   nobody can answer for three months later. */
const waiverMissing = computed(() => payType.value !== 'none'
    && collectionMethod.value === 'waive'
    && waiverReason.value.trim() === '');

/* What is handed over minus what is owed. Shown live, because the number a
   receptionist needs is the one they are counting back into somebody's hand. */
/* ---------------------------------------------- the same booking twice ----

   The same client, booked for the same service, twice on one day.

   A warning and not a rule. A client really does come back at three for the
   blow-dry they had at ten, so this says how alike the two bookings are and
   leaves the decision to the person holding the phone. What is actually
   refused — the chair being used twice — is availability's job, and stays
   there.

   Asked as the answers arrive and again as Confirm is pressed: the booking
   somebody else took while this one was being typed is the one worth
   catching, and it can only be caught at the end. */
const repeatBookings = ref([]);
const duplicateLevel = ref(null);
const duplicateOpen = ref(false);

/*
 * What was warned about, and what was answered.
 *
 * Keyed on the booking itself rather than a bare flag: "continue anyway" is
 * an answer about this client, these services, this day and this time, and
 * changing any of them asks the question again.
 */
const duplicateKey = computed(() => JSON.stringify([
    client.value?.id ?? null,
    chosen.value.map((service) => service.id).sort(),
    date.value,
    start.value,
    locationId.value || null,
]));

const duplicateAcked = ref(null);
const duplicateAsked = ref(null);

let duplicateTimer = null;

async function checkDuplicates() {
    if (!client.value || !chosen.value.length || !date.value) {
        repeatBookings.value = [];
        duplicateLevel.value = null;

        return [];
    }

    const { ok, json } = await send(props.duplicatesUrl, {
        client_id: client.value.id,
        services: chosen.value.map((service) => service.id),
        date: date.value,
        starts_at: start.value || null,
        minutes: minutes.value || null,
        location_id: locationId.value || null,
        booking_id: booking.value?.id ?? null,
    });

    if (!ok) {
        /* A check that could not be run is not a duplicate found. The desk is
           not stopped by this screen failing to ask. */
        return [];
    }

    repeatBookings.value = json.matches ?? [];
    duplicateLevel.value = json.level ?? null;

    return repeatBookings.value;
}

/* Debounced, because it moves with every answer on the screen — and only
   shown once per version of the booking, so a reader who said "continue" is
   not asked again for scrolling past the same card. */
watch(duplicateKey, () => {
    clearTimeout(duplicateTimer);

    duplicateTimer = setTimeout(async () => {
        const found = await checkDuplicates();

        if (found.length && duplicateAcked.value !== duplicateKey.value && duplicateAsked.value !== duplicateKey.value) {
            duplicateAsked.value = duplicateKey.value;
            duplicateOpen.value = true;
        }
    }, 400);
}, { immediate: true });

/** Taken anyway: a decision about this booking, and only this one. */
function acceptDuplicate() {
    duplicateAcked.value = duplicateKey.value;
    duplicateOpen.value = false;

    confirmBooking();
}

function dismissDuplicate() {
    duplicateOpen.value = false;
}

/* ------------------------------------------- abandoning the whole journey --

   "Cancel this booking" is not "close this dialog". The screen has been
   saving itself as a lead since the services were settled, so walking away
   from a duplicate would otherwise leave a call to return that nobody ever
   made — and the next person to open the leads list would ring a client who
   already has the appointment.

   So it throws the journey away: the lead this screen wrote, and the events
   that belong to it. Never the booking the warning was about, which is
   somebody's appointment and not this screen's to touch.

   Confirmed first, because it deletes. */
const discardOpen = ref(false);
const discarding = ref(false);

/* Where the journey started, read once at mount: a lead reopened from the
   leads list belongs back there, and a booking started here belongs on the
   bookings list. */
const startedFromLead = props.lead !== null;

function askToDiscard() {
    duplicateOpen.value = false;
    discardOpen.value = true;
}

function keepBooking() {
    discardOpen.value = false;
}

async function discardBooking() {
    if (discarding.value) {
        return;
    }

    discarding.value = true;

    /* Nothing more is written from here: a save still queued would put back
       the lead that is being thrown away. */
    clearTimeout(autosaveTimer);
    autosaveState.value = '';

    const held = lead.value;

    if (held && props.discardLeadUrlPattern) {
        await send(props.discardLeadUrlPattern.replace(':id', held.id), {}, 'DELETE');
    }

    window.location.href = startedFromLead && props.leadsUrl ? props.leadsUrl : props.cancelUrl;
}

/* One sentence, in the strength the match deserves. */
const duplicateMessage = computed(() => {
    const first = repeatBookings.value[0];

    if (!first) {
        return '';
    }

    const key = { exact: 'message_exact', overlap: 'message_overlap' }[duplicateLevel.value] ?? 'message';

    return (props.labels.duplicate?.[key] ?? '')
        .replace(':client', who.value ?? '')
        .replace(':service', first.services.join(', '))
        .replace(':date', first.date ?? '');
});

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
        /* Only the rooms somebody chose. A blank means "you decide", which is
           the usual answer and is not the same as a choice. */
        resources: Object.fromEntries(
            Object.entries(rooms.value)
                .filter(([, room]) => ! room.auto && room.id)
                .map(([serviceId, room]) => [serviceId, room.id]),
        ),
        source: source.value,
        payment_type: payType.value,
        deposit: payType.value === 'deposit' ? (depositMinor.value / 100).toFixed(2) : null,
        collection_method: payType.value === 'none' ? null : collectionMethod.value,
        /*
         * Everything the screen settled about the money, and not only what it
         * settled about the time.
         *
         * This used to send neither the method, the coupon nor the tip, so a
         * booking quoted at cash prices with a coupon on it was written down
         * at card prices with no coupon: the screen said one number and the
         * diary held another. The quote endpoint was being told all three;
         * only the save was not.
         */
        payment_method: paymentMethod.value,
        coupon: appliedCoupon.value || null,
        tip_percent: customTip.value === '' ? tipPercent.value : null,
        tip_amount: customTip.value === '' ? null : customTip.value,
        waiver_reason: collectionMethod.value === 'waive' ? waiverReason.value : null,
        confirmation: confirmation.value,
        notes: notes.value,
        client_note: client.value ? clientNote.value : null,
        /* The booking-in-progress this finishes. Converting it is what keeps
           the reference the receptionist may already have read out. */
        lead_id: lead.value?.id ?? null,
        /* The warning was shown and answered. Without it the server refuses
           the booking, which is what makes the last check a check rather
           than a courtesy. */
        duplicate_ack: duplicateAcked.value === duplicateKey.value,
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

    /* Nothing more is written as a draft from here. A save still queued
       would be describing a booking that is about to stop being one. */
    clearTimeout(autosaveTimer);
    autosaveState.value = '';

    busy.value = true;
    failure.value = '';

    /* Asked once more, because a booking taken by somebody else while this
       one was being filled in is exactly the duplicate worth catching — and
       it can only be caught here. */
    if (duplicateAcked.value !== duplicateKey.value) {
        const found = await checkDuplicates();

        if (found.length) {
            busy.value = false;
            duplicateAsked.value = duplicateKey.value;
            duplicateOpen.value = true;

            return;
        }
    }

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
    linkFailure.value = json.link_error ?? '';

    /* Only Collect Now opens the payment card. Every other method is money
       arriving somewhere else — at the till, by link, on the day, or not at
       all — and a card demanding payment for a booking nobody is paying for
       now is the screen refusing to believe what it was told. */
    stage.value = payType.value !== 'none' && collectionMethod.value === 'collect-now'
        ? 'payment'
        : 'done';
}

/**
 * A payment came back from the panel.
 *
 * The booking is replaced wholesale with what the server answered rather than
 * adjusted here: what is paid and what is owed are its numbers, and a screen
 * that did its own arithmetic on them would be a second opinion about money.
 *
 * Part paid stays on this screen with the rest still owing; settled in full
 * moves on.
 */
function onPaid(updated, entry) {
    booking.value = updated;
    lastPayment.value = entry;

    if (updated.due_minor === 0) {
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
    guestMatches.value = [];
    guestSaved.value = false;
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
    /* A fresh screen is a fresh booking, so it gets its own reference. The
       one just taken belongs to the appointment that was made with it. */
    clearTimeout(autosaveTimer);
    autosaveState.value = '';
    autosaveSaved = '';
    stepsDone.value = {};
    lastPayment.value = null;
    sentTo.value = '';
    linkFailure.value = '';
    failure.value = '';
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
        <!-- The branch this booking is being taken at, in the page header.

             Teleported: the title beside it is server rendered, and the card
             has to change the moment the branch does. A second copy of the
             chosen location in Blade would be one that goes stale.

             The whole card is the control, not just the pencil — it is one
             thing that does one thing, and a small icon is a small target. -->
        <!-- ============================================ the service selector -->
        <!-- A page rather than a dialog.

             Choosing from a hundred services is the whole task for as long as
             it lasts, so it gets the whole window: the categories fixed down
             one side, the services scrolling down the other, and the header
             and footer staying put so the count and the way out are never
             scrolled off.

             Teleported to <body> and fixed, rather than routed. The booking
             underneath is a page of unsaved answers — a client, a draft lead,
             a time somebody is holding on the phone — and a real navigation
             would have to serialise and restore all of it to come back to the
             screen the reader left. -->
        <Teleport to="body">
            <div v-if="sheetOpen" class="fixed inset-0 z-[70] bg-hover flex flex-col"
                 role="dialog" aria-modal="true" :aria-label="labels.service?.select_title">
                <!-- Header: the way back, the title, the search, the count. -->
                <header class="shrink-0 bg-white border-b border-line">
                    <div class="w-full px-4 sm:px-6 lg:px-8 py-3 flex flex-wrap items-center gap-x-4 gap-y-3">
                        <button type="button" class="sd-iconbtn grid place-items-center shrink-0"
                                :aria-label="labels.service?.close" @click="cancelServiceSheet">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                <path d="M15 5l-7 7 7 7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </button>

                        <h2 class="text-[17px] sm:text-[19px] font-bold text-head tracking-tight shrink-0">
                            {{ labels.service?.select_title }}
                        </h2>

                        <!-- Takes the room that is left on a wide screen, and
                             a line of its own below sm where a search sharing
                             a row with a title is too narrow to read. -->
                        <div class="relative order-last sm:order-none w-full sm:w-auto sm:flex-1 sm:min-w-[200px] sm:max-w-[420px]">
                            <span class="styledesk_input__prefix pointer-events-none" aria-hidden="true">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none"><circle cx="11" cy="11" r="6.5" stroke="currentColor" stroke-width="1.9"/><path d="M16 16l4.5 4.5" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"/></svg>
                            </span>
                            <input v-model="serviceQuery" type="search" class="sd-input styledesk_input--prefixed"
                                   :placeholder="labels.service?.search" :aria-label="labels.service?.search"
                                   autocomplete="off">
                        </div>

                        <!-- Counted out loud, and live: the reader is picking
                             several and the number is the thing they are
                             keeping track of. -->
                        <span class="ml-auto shrink-0 text-[13px] font-semibold"
                              :class="sheetPickedCount ? 'text-brand' : 'text-sub'" aria-live="polite">
                            {{ sheetPickedCount
                                ? (sheetPickedCount === 1
                                    ? labels.service?.selected_one
                                    : (labels.service?.selected ?? '').replace(':count', sheetPickedCount))
                                : labels.service?.selected_none }}
                        </span>
                    </div>
                </header>

                <!-- Mobile: the categories as a strip across the top rather
                     than a column. Two narrow columns on a phone gives one
                     that cannot hold a category name and one that cannot hold
                     a service. -->
                <div class="lg:hidden shrink-0 bg-white border-b border-line overflow-x-auto styledesk_scroll">
                    <div class="flex gap-2 px-4 py-2.5 w-max">
                        <button v-for="group in sheetCategories" :key="`m-${group.id}`" type="button"
                                class="shrink-0 h-8 px-3 rounded-full border text-[12.5px] font-semibold transition-colors"
                                :class="sheetCategory === group.id
                                    ? 'border-brand bg-brand text-white'
                                    : 'border-line bg-white text-sub hover:text-ink hover:bg-hover'"
                                :aria-pressed="sheetCategory === group.id"
                                @click="sheetCategory = group.id">
                            {{ group.name }} ({{ group.count }})
                        </button>
                    </div>
                </div>

                <!-- Two columns, each scrolling on its own, so the page
                     itself never does. -->
                <div class="flex-1 min-h-0 w-full px-4 sm:px-6 lg:px-8 py-4">
                    <div class="h-full min-h-0 grid grid-cols-1 lg:grid-cols-10 gap-4">
                        <!-- Column 1 — categories. Roughly 30%, fixed while
                             the services beside it scroll. -->
                        <nav class="hidden lg:flex lg:col-span-3 min-h-0 flex-col bg-white border border-line rounded-card overflow-hidden"
                             :aria-label="labels.service?.categories">
                            <p class="shrink-0 px-3.5 pt-3.5 pb-2 text-[11px] font-semibold uppercase tracking-wide text-faint">
                                {{ labels.service?.categories }}
                            </p>

                            <ul class="flex-1 min-h-0 overflow-y-auto styledesk_scroll p-2 pt-0 space-y-0.5">
                                <li v-for="group in sheetCategories" :key="group.id">
                                    <button type="button"
                                            class="w-full flex items-center gap-2 px-2.5 py-2 rounded-lg text-left text-[13px] transition-colors"
                                            :class="sheetCategory === group.id
                                                ? 'bg-sel text-brand font-semibold'
                                                : 'text-ink hover:bg-hover'"
                                            :aria-current="sheetCategory === group.id ? 'true' : null"
                                            @click="sheetCategory = group.id">
                                        <span class="min-w-0 flex-1 truncate">{{ group.name }}</span>
                                        <span class="shrink-0 text-[12px]"
                                              :class="sheetCategory === group.id ? 'text-brand' : 'text-faint'">
                                            {{ group.count }}
                                        </span>
                                    </button>
                                </li>
                            </ul>
                        </nav>

                        <!-- Column 2 — the services in it. -->
                        <div class="lg:col-span-7 min-h-0 flex flex-col bg-white border border-line rounded-card overflow-hidden">
                            <!-- What this client is known to want, first.
                                 A repeat booking is the commonest thing a
                                 desk does, and searching a hundred services
                                 for the same balayage every time is the work
                                 this saves. Only when nothing is being
                                 searched or filtered — a favourite shown
                                 under a category it is not in reads as a
                                 mistake. -->
                            <div v-if="favoriteServices.length && !serviceQuery.trim() && !sheetCategory"
                                 class="shrink-0 border-b border-line bg-brand/[0.03] px-3 py-3">
                                <p class="text-[11px] font-semibold uppercase tracking-wide text-brand">
                                    {{ labels.service?.client_favorites }}
                                </p>

                                <div class="mt-2 flex flex-wrap gap-2">
                                    <button v-for="service in favoriteServices" :key="`fav-${service.id}`"
                                            type="button"
                                            class="inline-flex items-center gap-2 h-9 pl-2.5 pr-3 rounded-lg border text-[13px] transition-colors"
                                            :class="isPicked(service)
                                                ? 'border-brand bg-brand text-white'
                                                : 'border-brand/30 bg-white text-head hover:bg-brand/5'"
                                            :aria-pressed="isPicked(service)"
                                            @click="toggleSheetService(service)">
                                        <span aria-hidden="true">★</span>
                                        <span class="font-semibold">{{ service.name }}</span>
                                        <span :class="isPicked(service) ? 'text-white/80' : 'text-sub'">
                                            {{ durationLabel(service.minutes) }} · {{ service.price }}
                                        </span>
                                    </button>
                                </div>
                            </div>

                            <ul v-if="sheetServices.length"
                                class="flex-1 min-h-0 overflow-y-auto styledesk_scroll divide-y divide-line">
                                <li v-for="service in sheetServices" :key="service.id">
                                    <!-- The whole row is the control. A tick
                                         box on its own is a small target for
                                         somebody working at a desk with a
                                         client in front of them. -->
                                    <button type="button"
                                            class="w-full flex items-start gap-3 p-3 text-left transition-colors"
                                            :class="isPicked(service) ? 'bg-brand/5' : 'hover:bg-hover'"
                                            :aria-pressed="isPicked(service)"
                                            @click="toggleSheetService(service)">
                                        <span class="mt-0.5 h-[18px] w-[18px] rounded border grid place-items-center shrink-0 transition-colors"
                                              :class="isPicked(service) ? 'bg-brand border-brand text-white' : 'border-line bg-white'"
                                              aria-hidden="true">
                                            <svg v-if="isPicked(service)" width="11" height="11" viewBox="0 0 24 24" fill="none">
                                                <path d="M5 12.5l4.5 4.5L19 7.5" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
                                            </svg>
                                        </span>

                                        <span class="min-w-0 flex-1">
                                            <span class="block text-[13.5px] font-semibold text-head">{{ service.name }}</span>
                                            <span class="block text-[12px] text-sub mt-0.5">
                                                {{ durationLabel(service.minutes) }} · {{ service.price }}
                                            </span>
                                            <!-- The chair, room or machine it
                                                 needs. Said here because it
                                                 is a reason to pick one
                                                 service over another. -->
                                            <span v-if="service.resources?.length" class="block text-[12px] text-faint mt-0.5">
                                                {{ (labels.service?.needs ?? 'Needs :names').replace(':names', service.resources.join(', ')) }}
                                            </span>
                                        </span>
                                    </button>
                                </li>
                            </ul>

                            <p v-else class="p-4 text-[13px] text-sub">
                                {{ serviceQuery.trim() ? labels.service?.none : labels.service?.nothing_here }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Sticky footer: what it adds up to, and the two ways out.
                     The running total is here because it is what the reader
                     is deciding against — a fourth service is a different
                     decision at ninety minutes than at three hours. -->
                <footer class="shrink-0 bg-white border-t border-line">
                    <div class="w-full px-4 sm:px-6 lg:px-8 py-3 flex flex-wrap items-center gap-3">
                        <p v-if="sheetPickedCount" class="text-[12.5px] text-sub order-last sm:order-none w-full sm:w-auto">
                            {{ durationLabel(sheetMinutes) }} ·
                            <span class="font-semibold text-head">{{ money(sheetTotalMinor) }}</span>
                        </p>

                        <div class="ml-auto flex items-center gap-3">
                            <button type="button" class="styledesk_action" @click="cancelServiceSheet">
                                {{ labels.cancel }}
                            </button>

                            <button type="button"
                                    class="h-9 px-4 rounded-lg bg-brand hover:bg-brand-dark text-white text-[13px] font-semibold transition-colors"
                                    @click="saveServiceSheet">
                                {{ labels.service?.save_close }}
                            </button>
                        </div>
                    </div>
                </footer>
            </div>
        </Teleport>

        <!-- The reference this booking is already saved under, and whether
             the last change is in yet.

             Teleported into the page header for the reason the location card
             is: the title beside it is server rendered, and this has to
             appear the moment the first save comes back.

             Deliberately quiet. It is not an instruction and it is not a
             result — it is the number a receptionist reaches for when the
             call drops, and a status line that announced every save would be
             the loudest thing on a screen somebody is working through while
             talking to a client. -->
        <Teleport v-if="lead && stage !== 'done'" to="#bookingRefSlot">
            <div class="flex items-center gap-2.5 sm:justify-end" aria-live="polite">
                <span class="text-[12px] font-semibold text-sub font-mono">
                    {{ (labels.autosave?.reference ?? '').replace(':reference', lead.reference) }}
                </span>

                <!-- Draft, then In progress, and gone once the booking is
                     taken. An auto-saved booking nobody finished must never
                     read as an appointment somebody has been promised. -->
                <span v-if="!booking" class="styledesk_badge styledesk_badge--setup shrink-0">
                    {{ lead.status_label || labels.autosave?.draft }}
                </span>

                <span v-if="autosaveLabel" class="text-[12px] shrink-0"
                      :class="autosaveState === 'failed' ? 'text-danger' : 'text-faint'">
                    {{ autosaveLabel }}
                </span>
            </div>
        </Teleport>

        <Teleport v-if="chosenLocation && stage !== 'done'" to="#bookingLocationSlot">
            <div class="relative w-full sm:w-auto" data-location-picker>
                <button type="button"
                        class="w-full sm:w-auto sm:min-w-[220px] flex items-center gap-3 text-left
                               bg-white border border-line rounded-card px-3.5 py-2 hover:border-brand/40
                               transition-colors cursor-pointer"
                        :aria-expanded="locationPickerOpen" aria-haspopup="listbox"
                        :title="labels.when?.change_location"
                        @click="locationPickerOpen ? locationPickerOpen = false : openLocationPicker()">
                    <span class="min-w-0 flex-1">
                        <span class="block text-[11px] font-semibold uppercase tracking-wide text-faint">
                            {{ labels.when?.location }}
                        </span>
                        <span class="block text-[14px] font-semibold text-head truncate">
                            {{ chosenLocation.name }}
                        </span>
                    </span>

                    <span class="shrink-0 text-sub" aria-hidden="true">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none">
                            <path d="M4 20h4L19 9l-4-4L4 16v4z" stroke="currentColor" stroke-width="1.7"
                                  stroke-linejoin="round"/>
                            <path d="M14.5 5.5l4 4" stroke="currentColor" stroke-width="1.7"/>
                        </svg>
                    </span>
                </button>

                <!-- One branch is not a choice, so there is nothing to open.
                     The card still says where the booking is going, which is
                     the other half of what it is for. -->
                <div v-if="locationPickerOpen && locations.length > 1"
                     class="absolute right-0 z-30 mt-1.5 w-full sm:w-[280px] bg-white border border-line
                            rounded-card shadow-lg p-2">
                    <!-- The search earns its place at a dozen branches and is
                         harmless at three. -->
                    <input v-if="locations.length > 6" v-model="locationQuery" type="search"
                           class="sd-input mb-2" :placeholder="labels.when?.search_locations"
                           :aria-label="labels.when?.search_locations">

                    <ul class="max-h-[240px] overflow-y-auto styledesk_scroll" role="listbox">
                        <li v-for="place in locationMatches" :key="place.id">
                            <button type="button" role="option"
                                    :aria-selected="String(place.id) === String(locationId)"
                                    class="w-full text-left px-2.5 py-2 rounded-lg text-[13px] hover:bg-hover
                                           transition-colors flex items-center gap-2"
                                    :class="String(place.id) === String(locationId) ? 'text-brand font-semibold' : 'text-ink'"
                                    @click="chooseLocation(place)">
                                <span class="min-w-0 flex-1 truncate">{{ place.name }}</span>
                                <svg v-if="String(place.id) === String(locationId)" width="14" height="14"
                                     viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                    <path d="M5 12.5l4.5 4.5L19 7.5" stroke="currentColor" stroke-width="2.2"
                                          stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </button>
                        </li>
                    </ul>

                    <p v-if="!locationMatches.length" class="px-2.5 py-2 text-[13px] text-sub">
                        {{ labels.when?.no_locations }}
                    </p>
                </div>
            </div>
        </Teleport>

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
        <input type="hidden" name="collection_method" :value="payType === 'none' ? '' : collectionMethod">
        <input type="hidden" name="waiver_reason" :value="collectionMethod === 'waive' ? waiverReason : ''">
        <input type="hidden" name="confirmation" :value="confirmation">
        <input type="hidden" name="notes" :value="notes">
        <input type="hidden" name="client_note" :value="client ? clientNote : ''">
        <input type="hidden" name="draft" :value="draft ? 1 : 0">

        <!-- ================================================ column 1 — client -->
        <!-- ============================================ the booking is taken -->
        <!-- A document, not a screen.

             What is left when a booking is finished is a record of it, so it
             is shaped like one: a single white sheet, centred, on a grey
             page. The form is gone rather than merely quiet, because the one
             thing a receptionist must not wonder at this point is whether
             something still needs saving.

             Held to a readable measure instead of the full window. A receipt
             stretched across 1600px is a receipt nobody can scan down. -->
        <section v-if="stage === 'done'" class="lg:col-span-12 min-w-0 flex justify-center py-2 sm:py-6"
                 :aria-label="labels.confirmation?.title">
            <article class="w-full max-w-[820px] bg-white border border-line rounded-xl shadow-sm">

                <!-- 1 · the answer, compactly. A full-width green alert says
                     the same thing far louder than a finished document
                     needs to. -->
                <header class="p-6 sm:p-8 border-b border-line">
                    <div class="flex items-start gap-3">
                        <span class="shrink-0 h-8 w-8 rounded-full bg-brand/10 text-brand grid place-items-center" aria-hidden="true">
                            <svg width="17" height="17" viewBox="0 0 24 24" fill="none">
                                <path d="M5 12.5l4.5 4.5L19 7.5" stroke="currentColor" stroke-width="2.8" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </span>

                        <div class="min-w-0">
                            <h1 class="text-[19px] sm:text-[21px] font-bold text-head tracking-tight">
                                {{ labels.confirmation?.title }}
                            </h1>
                            <p class="text-[13px] text-sub mt-1">{{ labels.confirmation?.made }}</p>
                        </div>
                    </div>

                    <div class="mt-5">
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-faint">
                            {{ labels.summary?.reference }}
                        </p>
                        <p class="text-[20px] sm:text-[22px] font-bold font-mono text-head mt-0.5">{{ booking.reference }}</p>
                    </div>
                </header>

                <!-- Anything that went wrong on the way out. The appointment
                     is real either way, so these say what to do rather than
                     pretending the booking failed. -->
                <div v-if="linkFailure || failure || sentTo" class="px-6 sm:px-8 pt-5 space-y-2">
                    <p v-if="linkFailure" class="sd-alert sd-alert--warn text-[12.5px]" role="alert">{{ linkFailure }}</p>
                    <p v-if="failure" class="sd-alert sd-alert--danger text-[12.5px]" role="alert">{{ failure }}</p>
                    <p v-if="sentTo" class="sd-alert sd-alert--success text-[12.5px]">{{ sentTo }}</p>
                </div>

                <!-- 2 · the appointment. Label left, answer right, on one
                     line each where there is room and stacked where there is
                     not — a two-column row squeezed onto a phone puts three
                     words of label against two of answer. -->
                <section class="p-6 sm:p-8">
                    <h2 class="text-[11px] font-semibold uppercase tracking-wide text-faint">
                        {{ labels.sections?.summary }}
                    </h2>

                    <dl class="mt-4 space-y-3.5 text-[13.5px]">
                        <div class="sm:flex sm:items-baseline sm:justify-between sm:gap-6">
                            <dt class="text-sub">{{ labels.summary?.client }}</dt>
                            <dd class="font-semibold text-head sm:text-right min-w-0">{{ booking.client }}</dd>
                        </div>
                        <div class="sm:flex sm:items-baseline sm:justify-between sm:gap-6">
                            <dt class="text-sub">{{ labels.summary?.services }}</dt>
                            <dd class="font-semibold text-head sm:text-right min-w-0">
                                {{ booking.services.map((row) => row.name).join(', ') }}
                            </dd>
                        </div>
                        <div class="sm:flex sm:items-baseline sm:justify-between sm:gap-6">
                            <dt class="text-sub">{{ labels.summary?.staff }}</dt>
                            <dd class="font-semibold text-head sm:text-right">{{ booking.staff }}</dd>
                        </div>
                        <div class="sm:flex sm:items-baseline sm:justify-between sm:gap-6">
                            <dt class="text-sub">{{ labels.summary?.when }}</dt>
                            <!-- The day and the hours on their own lines: two
                                 facts a reader checks separately. -->
                            <dd class="font-semibold text-head sm:text-right">
                                <span class="block">{{ booking.date }}</span>
                                <span class="block">{{ booking.time }}</span>
                            </dd>
                        </div>
                        <div v-if="booking.location" class="sm:flex sm:items-baseline sm:justify-between sm:gap-6">
                            <dt class="text-sub">{{ labels.summary?.location }}</dt>
                            <dd class="font-semibold text-head sm:text-right">{{ booking.location }}</dd>
                        </div>
                    </dl>
                </section>

                <!-- 3 · the money, laid out as a bill: the lines, then the
                     rule, then the one number somebody has to act on. -->
                <section class="px-6 sm:px-8 pb-6 sm:pb-8 pt-6 border-t border-line">
                    <h2 class="text-[11px] font-semibold uppercase tracking-wide text-faint">
                        {{ labels.detail?.payment_summary }}
                    </h2>

                    <dl class="mt-4 space-y-2.5 text-[13.5px]">
                        <div class="flex items-baseline justify-between gap-6">
                            <dt class="text-sub">{{ labels.pay?.booking_total }}</dt>
                            <dd class="font-semibold text-head tabular-nums">{{ booking.total }}</dd>
                        </div>
                        <div class="flex items-baseline justify-between gap-6">
                            <dt class="text-sub">{{ labels.summary?.paid }}</dt>
                            <dd class="font-semibold text-head tabular-nums">{{ booking.paid }}</dd>
                        </div>
                        <div v-for="row in booking.payments" :key="row.method"
                             class="flex items-baseline justify-between gap-6 text-[12.5px]">
                            <dt class="text-faint pl-3">{{ row.method_label }}</dt>
                            <dd class="text-sub tabular-nums">{{ row.amount }}</dd>
                        </div>
                    </dl>

                    <!-- The one number somebody has to do something about. -->
                    <div class="mt-4 pt-4 border-t border-line flex flex-wrap items-end justify-between gap-4">
                        <div>
                            <p class="text-[11px] font-semibold uppercase tracking-wide text-faint">
                                {{ booking.due_minor > 0 ? labels.confirmation?.amount_due : labels.summary?.due }}
                            </p>
                            <p class="text-[26px] font-bold tabular-nums leading-tight mt-0.5"
                               :class="booking.due_minor > 0 ? 'text-danger' : 'text-head'">
                                {{ booking.due }}
                            </p>
                        </div>

                        <span class="styledesk_paystate" :class="`is-${booking.payment_status}`">
                            {{ booking.payment_status_label }}
                        </span>
                    </div>

                    <!-- Where a link was sent, and where it got to. -->
                    <div v-if="booking.links?.length" class="mt-4 pt-4 border-t border-line">
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-faint">
                            {{ labels.payment?.link_status }}
                        </p>
                        <div v-for="row in booking.links" :key="row.id"
                             class="mt-2 flex flex-wrap items-center gap-2 text-[12.5px]">
                            <span class="styledesk_badge" :class="row.status_class">{{ row.status_label }}</span>
                            <span class="font-semibold text-head">{{ row.amount }}</span>
                            <span v-if="row.sent_to" class="text-sub truncate">{{ row.sent_to }}</span>
                        </div>
                    </div>

                    <!-- Waived, by whom and why: the one collection method
                         that is a decision somebody has to answer for. -->
                    <div v-if="booking.waiver" class="mt-4 pt-4 border-t border-line text-[12.5px]">
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-faint">
                            {{ labels.payment?.actions?.waive }}
                        </p>
                        <p class="text-ink mt-1">{{ booking.waiver.reason }}</p>
                        <p class="text-faint mt-0.5">{{ booking.waiver.by }} · {{ booking.waiver.at }}</p>
                    </div>

                    <p v-if="lastPayment?.change" class="mt-4 text-[12.5px] text-sub">
                        {{ labels.pay?.change }}: <span class="font-semibold text-head">{{ lastPayment.change }}</span>
                    </p>
                </section>

                <!-- 4 · what to do with it. One thing to do, three ways to
                     hand it over. -->
                <footer class="px-6 sm:px-8 py-6 border-t border-line">
                    <a :href="booking.urls.show"
                       class="w-full h-11 px-6 rounded-lg bg-brand hover:bg-brand-dark text-white
                              text-[13.5px] font-semibold flex items-center justify-center transition-colors">
                        {{ labels.confirmation?.view }}
                    </a>

                    <!-- Stacked and full width, all of them. Three buttons
                         sharing a row read as one decision split three ways;
                         these are three separate things somebody might do
                         with a finished booking, and each gets its own line
                         and its own full-width target. -->
                    <div class="mt-2 grid grid-cols-1 gap-2">
                        <button type="button" class="styledesk_action w-full h-11 justify-center" :disabled="busy"
                                @click="sendConfirmation('email')">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                <rect x="3" y="5" width="18" height="14" rx="2" stroke="currentColor" stroke-width="1.8"/>
                                <path d="M3.5 6.5l8.5 6 8.5-6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            {{ busy ? labels.confirmation?.sending : labels.confirmation?.send }}
                        </button>

                        <a :href="booking.urls.print" target="_blank" rel="noopener" class="styledesk_action w-full h-11 justify-center">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                <path d="M7 9V4h10v5" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
                                <rect x="4" y="9" width="16" height="7" rx="1.5" stroke="currentColor" stroke-width="1.8"/>
                                <path d="M7 14h10v6H7z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
                            </svg>
                            {{ labels.confirmation?.print }}
                        </a>

                        <a :href="booking.urls.receipt" target="_blank" rel="noopener" class="styledesk_action w-full h-11 justify-center">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                <path d="M12 4v11" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                                <path d="M8 11.5l4 4 4-4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                                <path d="M5 19h14" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                            </svg>
                            {{ labels.confirmation?.receipt }}
                        </a>
                    </div>
                </footer>

                <!-- 5 · set apart by a rule of its own, because it is the one
                     action that throws this document away. -->
                <div class="px-6 sm:px-8 py-5 border-t border-line bg-hover/40 rounded-b-xl">
                    <button type="button"
                            class="w-full h-11 px-5 rounded-lg border border-brand text-brand
                                   hover:bg-brand/5 text-[13.5px] font-semibold transition-colors"
                            @click="startAnother">
                        {{ labels.confirmation?.another }}
                    </button>

                    <!-- The way out of this screen. The page's own Back link
                         is hidden once the booking is taken, so without this
                         the only ways off a finished confirmation are the
                         booking itself or starting another one — and someone
                         who has finished booking usually wants neither. -->
                    <a :href="cancelUrl"
                       class="mt-2 w-full h-11 px-5 rounded-lg text-[13px] font-semibold text-sub
                              hover:text-ink hover:bg-hover flex items-center justify-center gap-1.5 transition-colors">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <path d="M14 6l-6 6 6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        {{ labels.confirmation?.to_list }}
                    </a>
                </div>
            </article>
        </section>

        <section v-show="stage !== 'done'" class="lg:col-span-3" :aria-label="labels.sections?.client">
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

                        <!-- Already on the book. A warning, never a block:
                             two people share a phone, and a wrongly merged
                             history is not something a receptionist can
                             unpick. What it offers is the useful half — the
                             record itself, one click away, because the
                             history is the reason to want it. -->
                        <div v-if="guestMatches.length" class="sd-alert sd-alert--warn" role="alert">
                            <p class="font-semibold">{{ labels.client?.guest_known }}</p>

                            <ul class="mt-1.5 space-y-1">
                                <li v-for="match in guestMatches" :key="match.id">
                                    <button type="button" class="font-semibold underline"
                                            @click="useExistingClient(match)">{{ match.name }}</button>
                                    <span class="text-[12px]"> · {{ match.mobile || match.email }}</span>
                                </li>
                            </ul>

                            <p class="text-[12px] mt-1.5">{{ labels.client?.guest_known_hint }}</p>
                        </div>

                        <p class="text-[12px] text-faint leading-relaxed">{{ labels.client?.guest_hint }}</p>

                        <div class="flex flex-wrap items-center gap-3 pt-0.5">
                            <button type="button" class="styledesk_action"
                                    :disabled="!guest.name.trim() || guestChecking"
                                    @click="saveGuest">
                                {{ guestChecking ? labels.client?.guest_checking : labels.client?.guest_save }}
                            </button>

                            <!-- Says the details are in, which is the whole
                                 job of the button beside it. Cleared the
                                 moment any of the three is edited again. -->
                            <span v-if="guestSaved && !guestChecking" class="text-[12px] font-semibold text-brand">
                                {{ labels.client?.guest_saved }}
                            </span>
                        </div>

                        <button type="button" class="text-[12.5px] font-semibold text-link"
                                @click="mode = 'booking'">{{ labels.client?.search_label }}</button>
                    </div>
                </div>
            </div>
        </section>

        <!-- =============================================== column 2 — booking -->
        <section v-show="stage !== 'done'" class="lg:col-span-6 min-w-0 space-y-3" :aria-label="labels.sections?.service">
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
                    <!-- The card says what has been chosen and nothing else.

                         It used to hold the whole catalogue behind a search
                         and a category combo, which works at twelve services
                         and fails at a hundred: a 260px scroller inside a
                         form, with the booking's own summary pushed below the
                         fold. Choosing is now its own page; this is the
                         answer it comes back with. -->
                    <div v-if="chosen.length" class="mt-2.5">
                        <p class="text-[12.5px] text-sub">
                            <span class="font-semibold text-head">{{ serviceCountLabel(chosen.length) }}</span>
                            · {{ durationLabel(minutes) }}
                            · <span class="font-semibold text-head">{{ money(totalMinor) }}</span>
                        </p>

                        <ul class="mt-2.5 space-y-1.5">
                            <li v-for="service in chosen" :key="service.id"
                                class="rounded-lg border border-brand/30 bg-brand/5 px-3 py-2">
                                <div class="flex items-center gap-2">
                                    <span class="min-w-0 flex-1">
                                        <span class="block text-[13px] font-semibold text-head truncate">{{ service.name }}</span>
                                        <span class="block text-[12px] text-sub">
                                            {{ durationLabel(service.minutes) }}
                                            <template v-if="service.price"> · {{ service.price }}</template>
                                        </span>
                                    </span>
                                    <button type="button" class="sd-iconbtn grid place-items-center shrink-0"
                                            :aria-label="(labels.service?.remove ?? '').replace(':name', service.name)"
                                            @click="removeService(service)">
                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none"><path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"/></svg>
                                    </button>
                                </div>

                                <!-- The room, worked out rather than asked
                                     for. A receptionist choosing a treatment
                                     and a time should not also have to know
                                     which of six identical rooms is free —
                                     but they can say, and then the engine
                                     leaves their choice alone. -->
                                <div v-if="roomOf(service.id)" class="mt-1.5 pt-1.5 border-t border-brand/20">
                                    <div class="flex items-center gap-2">
                                        <span class="min-w-0 flex-1">
                                            <span class="block text-[11px] font-semibold text-sub uppercase tracking-wide">
                                                {{ labels.summary?.resource }}
                                            </span>

                                            <span v-if="roomOf(service.id).name" class="flex flex-wrap items-center gap-1.5">
                                                <span class="text-[12.5px] font-medium text-head">{{ roomOf(service.id).name }}</span>
                                                <span class="styledesk_badge"
                                                      :class="roomOf(service.id).auto ? 'styledesk_badge--info' : 'styledesk_badge--note'">
                                                    {{ roomOf(service.id).auto ? labels.resources?.auto : labels.resources?.manual }}
                                                </span>
                                            </span>

                                            <!-- Nowhere to put it. Said here
                                                 rather than only on save, so
                                                 the desk finds out while the
                                                 client is still on the
                                                 telephone. -->
                                            <span v-else class="block text-[12px] text-danger">
                                                {{ roomOf(service.id).message }}
                                            </span>
                                        </span>

                                        <button type="button" class="sd-iconbtn grid place-items-center shrink-0"
                                                :aria-label="labels.resources?.change"
                                                @click="roomPickerFor = roomPickerFor === service.id ? null : service.id">
                                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none"><path d="M4 20h4l10-10-4-4L4 16v4zM14 6l4 4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                        </button>
                                    </div>

                                    <!-- Every room this service could use,
                                         with whether it is free. The taken
                                         ones stay on the list: somebody
                                         hunting for Single Room 03 and not
                                         finding it will assume the mapping is
                                         wrong, where "unavailable" answers
                                         the question they had. -->
                                    <ul v-if="roomPickerFor === service.id" class="mt-2 space-y-1">
                                        <li v-for="option in roomOf(service.id).options" :key="option.id">
                                            <button type="button"
                                                    class="w-full flex items-center gap-2 rounded-md border px-2.5 py-1.5 text-left transition-colors"
                                                    :class="[
                                                        option.id === roomOf(service.id).id ? 'border-brand bg-white' : 'border-line bg-white',
                                                        option.available || option.id === roomOf(service.id).id
                                                            ? 'hover:border-brand cursor-pointer'
                                                            : 'opacity-50 cursor-not-allowed',
                                                    ]"
                                                    :disabled="! option.available && option.id !== roomOf(service.id).id"
                                                    @click="chooseRoom(service.id, option)">
                                                <span class="min-w-0 flex-1 text-[12.5px] text-head truncate">{{ option.name }}</span>
                                                <span class="text-[11px] shrink-0"
                                                      :class="option.available ? 'text-sub' : 'text-danger'">
                                                    {{ option.id === roomOf(service.id).id
                                                        ? labels.resources?.currently
                                                        : (option.available ? '' : labels.resources?.unavailable) }}
                                                </span>
                                                <span class="text-[11px] text-faint shrink-0">{{ option.capacity }}</span>
                                            </button>
                                        </li>
                                    </ul>
                                </div>
                            </li>
                        </ul>

                        <!-- One line where nothing can be placed at all.
                             The per-service rows above say which room each
                             one is in; this is the summary that stops the
                             booking. -->
                        <p v-if="roomProblem" class="mt-2.5 text-[12.5px] text-danger">
                            {{ roomProblem.message }}
                        </p>
                    </div>

                    <p v-else class="mt-2.5 text-[12.5px] text-sub">
                        {{ services.length ? labels.service?.card_empty : labels.service?.empty }}
                    </p>

                    <button v-if="services.length" type="button"
                            class="styledesk_action w-full justify-center mt-3"
                            @click="openServiceSheet">
                        {{ chosen.length ? labels.service?.change : labels.service?.add }}
                    </button>

                    <!-- The way on. Every step ends with one, so the screen
                         has a default path through it. -->
                    <div class="mt-4 pt-3.5 border-t border-line flex flex-wrap items-center gap-3">
                        <button type="button" class="styledesk_stepcta" :disabled="stepBlocker('service') !== null"
                                @click="saveStep('service')">{{ labels.steps?.save }}</button>
                        <p v-if="stepBlocker('service')" class="text-[12px] text-danger">{{ stepBlocker('service') }}</p>
                    </div>
                </div>
            </section>

            <!-- Staff & time. The card stops clipping while it is open, or
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
                            <button v-for="member in bookableStaff" :key="member.id" type="button"
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

                    <!-- The branch used to be chosen here, in a select four
                         scrolls down. It is the card in the page header now:
                         it decides the hours, the rota, the services and the
                         chairs, so it belongs where it can be seen without
                         going looking — and two controls for one value is two
                         places to change it and one of them to forget. -->

                    <div>
                        <label class="block text-[13px] font-medium text-ink mb-1.5">{{ labels.when?.time }}</label>
                        <div class="space-y-2.5">
                            <!-- Why there is nothing to choose from.

                                 A branch that is shut, an appointment too long
                                 for the hours it would sit in, and a day that
                                 is simply full are three different answers,
                                 and the person on the phone needs the right
                                 one — "we're closed on Sundays" ends the call
                                 differently from "there's nothing left". -->
                            <p v-if="!availableTimes.length && availabilityMessage"
                               class="text-[13px] text-sub border border-line rounded-lg p-3">
                                {{ availabilityMessage }}
                            </p>

                            <p v-else-if="!availableTimes.length && checkingTimes" class="text-[13px] text-faint p-3">
                                {{ labels.when?.loading_times }}
                            </p>

                            <!-- One part of the day at a time.

                                 Sixty chips in one run is a wall, and
                                 "anything after lunch?" is a question about
                                 one of these three. Three stacked cards
                                 answered it by making the reader scroll past
                                 the two they did not ask about; a segmented
                                 control answers it by showing only the one
                                 they did.

                                 A part of the day with nothing free is
                                 disabled rather than hidden — "nothing on
                                 Tuesday morning" is a fact worth stating, and
                                 a button that vanishes is one the reader
                                 wonders about. -->
                            <div v-if="timeGroups.some((group) => group.slots.length)"
                                 class="sd-seg" role="tablist" :aria-label="labels.when?.time">
                                <!-- The active state is drawn from aria-selected rather than from a
                                     class of our own: .sd-seg__btn styles it that way already, and a
                                     class doing the same job would be a second source of one truth. -->
                                <button v-for="group in timeGroups" :key="group.key" type="button"
                                        class="sd-seg__btn" role="tab"
                                        :class="{ 'opacity-45 cursor-not-allowed': !group.slots.length }"
                                        :aria-selected="period === group.key"
                                        :disabled="!group.slots.length"
                                        :title="group.slots.length ? null : labels.when?.none_in_period"
                                        @click="period = group.key">
                                    {{ labels.when?.[group.key] }}
                                </button>
                            </div>

                            <!-- Only the chips scroll. The segmented control above
                                 stays put: a filter that scrolls out of sight with
                                 the thing it filters is one the reader loses. -->
                            <div v-if="currentGroup?.slots.length"
                                 class="max-h-[260px] overflow-y-auto styledesk_scroll flex flex-wrap gap-1.5 pr-1">
                                <button v-for="slot in currentGroup.slots" :key="slot" type="button"
                                        class="styledesk_pickchip" :class="{ 'is-on': start === slot }"
                                        @click="start = slot">{{ clock(slot) }}</button>
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
                         other for as long as they are a client.

                         Gone entirely for a walk-in, rather than shown greyed
                         out. There is no profile to keep it on, so it is not
                         a field that is temporarily unavailable — it is a
                         field that does not apply, and a disabled box with a
                         sentence explaining why is a question the reader has
                         to read before they can dismiss it. -->
                    <div v-if="client" class="mt-4 pt-4 border-t border-line">
                        <label for="bClientNote" class="block text-[13px] font-medium text-ink mb-1.5">
                            {{ labels.details?.client_note }}
                            <span class="font-normal text-sub">{{ labels.details?.client_note_aside }}</span>
                        </label>
                        <textarea id="bClientNote" v-model="clientNote" rows="2" class="sd-input h-auto py-2.5"
                                  :placeholder="labels.details?.client_note_placeholder"></textarea>
                        <p class="text-[12px] text-faint mt-1.5">{{ labels.details?.client_note_hint }}</p>
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
                    <!-- Said before the options rather than after them: the
                         reader is about to find "No Payment Now" greyed out,
                         and a control that refuses without saying why is the
                         one people ring support about. -->
                    <div v-if="depositIsRequired"
                         class="mt-[5px] rounded-lg border border-brand/30 bg-brand/5 px-4 py-3">
                        <p class="text-[13px] font-semibold text-head">
                            {{ labels.payment?.deposit_required }} —
                            <template v-if="requiredDepositPercent !== null">
                                {{ (labels.payment?.preset ?? ':percent%').replace(':percent', requiredDepositPercent) }}
                            </template>
                            {{ money(requiredDepositMinor) }}
                        </p>
                        <p class="text-[12px] text-sub mt-0.5">{{ labels.payment?.deposit_required_hint }}</p>
                    </div>

                    <fieldset class="mt-[5px]">
                        <legend class="block text-[13px] font-medium text-ink mb-2">{{ labels.payment?.type }}</legend>
                        <!-- Three answers, because they are three different
                             acts: nothing now and the whole bill owed later,
                             part of it now, or all of it now. -->
                        <div class="grid sm:grid-cols-3 gap-2">
                            <button v-for="option in ['none', 'deposit', 'full']" :key="option"
                                    type="button" class="styledesk_optioncard" :class="{ 'is-on': payType === option }"
                                    :aria-pressed="payType === option"
                                    :disabled="option === 'none' && depositIsRequired"
                                    @click="choosePayType(option)">
                                <span class="block text-[13px] font-semibold text-head">{{ labels.payment?.[option] }}</span>
                                <span class="block text-[12px] text-sub">{{ labels.payment?.[`${option}_hint`] }}</span>
                            </button>
                        </div>
                    </fieldset>

                    <!-- Nothing to enter, and said so rather than left blank:
                         an empty space under a chosen option reads as a form
                         that has not finished loading. -->
                    <p v-if="payType === 'none'" class="mt-3 text-[12.5px] text-sub">
                        {{ labels.payment?.nothing_collected }}
                    </p>

                    <!-- Which price applies.

                         Only where the services actually cost different
                         amounts either way: on a business with one price
                         this is a control that changes nothing, and asking
                         a question with one answer wastes the reader's
                         attention. -->
                    <div v-if="hasTwoPrices" class="mt-3">
                        <p class="text-[12px] font-medium text-ink mb-1.5">{{ labels.pay?.paying_by }}</p>

                        <div class="flex flex-wrap gap-2">
                            <button v-for="method in ['card', 'cash']" :key="method" type="button"
                                    class="rounded-lg border px-3 py-1.5 text-[12.5px] font-semibold transition-colors"
                                    :class="paymentMethod === method
                                        ? 'border-brand text-brand bg-brand/5'
                                        : 'border-line text-sub hover:border-brand'"
                                    @click="paymentMethod = method">
                                {{ labels.methods?.[method]?.name ?? method }}
                            </button>
                        </div>

                        <p class="mt-1.5 text-[11.5px] text-faint">{{ labels.pay?.paying_by_hint }}</p>
                    </div>


                    <!-- Full payment needs no amount typed: it is the bill,
                         and a receptionist should never be made to work out a
                         number the screen already knows. -->
                    <div v-if="payType === 'full'"
                         class="mt-3 rounded-lg border border-brand/30 bg-brand/5 px-4 py-3 text-center">
                        <p class="text-[12px] font-semibold text-sub">{{ labels.payment?.collecting }}</p>
                        <p class="text-[20px] font-bold text-head leading-tight mt-0.5">{{ money(payableMinor) }}</p>
                        <p class="text-[12px] text-sub mt-0.5">{{ labels.payment?.balance }} {{ money(0) }}</p>
                    </div>

                    <!-- What is actually being charged now, stated before the
                         booking exists. The total is the booking's worth and
                         never changes; this is the number the till sees. -->
                    <!-- The deposit's own summary lives with the deposit
                         controls below. There used to be a second one here,
                         worked out from the bare service total — so a booking
                         with a tip on it showed two different balances, and
                         only one of them was right. -->

                    <div v-if="payType === 'deposit'" class="mt-4">
                        <!-- A deposit policy is written as a percentage, so
                             that is what is kept. The figure follows the
                             bill: fifteen per cent of a booking that then
                             grows a tip is fifteen per cent of the new
                             total, and storing the amount instead would
                             freeze it at whatever the bill was when the
                             button was pressed. -->
                        <p class="text-[12px] font-medium text-ink mb-1.5">{{ labels.payment?.deposit_percent }}</p>

                        <div class="flex flex-wrap gap-1.5">
                            <button v-for="percent in [10, 15, 20, 25, 50]" :key="percent" type="button"
                                    class="h-7 px-2.5 rounded-full border text-[12px] font-semibold transition-colors"
                                    :class="depositIsPercent(percent)
                                        ? 'border-brand bg-brand text-white'
                                        : 'border-line bg-white text-sub hover:text-ink hover:bg-hover'"
                                    @click="setDepositPercent(percent)">
                                {{ (labels.payment?.preset ?? ':percent%').replace(':percent', percent) }}
                            </button>
                        </div>

                        <div class="mt-3 sm:max-w-[280px]">
                            <label for="bDeposit" class="block text-[12px] text-sub mb-1">{{ labels.payment?.amount }}</label>
                            <!-- Typing an amount is choosing an amount, so
                                 the percentage stops applying — the same
                                 rule the tip follows. -->
                            <input id="bDeposit" v-model="deposit" type="number" :min="requiredDepositMinor / 100"
                                   step="0.01" class="sd-input"
                                   :placeholder="depositPercent === null ? '' : money(depositMinor)"
                                   @input="typeDepositAmount">
                        </div>

                        <p v-if="depositTooMuch" class="mt-1.5 text-[12px] text-danger">
                            {{ labels.payment?.too_much }}
                        </p>

                        <p v-else-if="depositTooLittle" class="mt-1.5 text-[12px] text-danger">
                            {{ depositShortfallMessage }}
                        </p>

                        <!-- What is being taken now and what stays owing.
                             A deposit stated without the balance beside it
                             is a number nobody checks. -->
                        <dl v-if="payableMinor > 0" class="mt-3 rounded-lg border border-brand/30 bg-brand/5 px-4 py-3 space-y-1 text-[12.5px]">
                            <div class="flex items-baseline justify-between gap-3">
                                <dt class="text-sub">{{ labels.pay?.total_due }}</dt>
                                <dd class="font-medium text-head">{{ money(payableMinor) }}</dd>
                            </div>

                            <div class="flex items-baseline justify-between gap-3">
                                <dt class="font-semibold text-head">
                                    {{ depositPercent === null
                                        ? labels.payment?.deposit_now
                                        : (labels.payment?.deposit_now_percent ?? ':percent% deposit due now').replace(':percent', depositPercent) }}
                                </dt>
                                <dd class="text-[15px] font-bold text-head">{{ money(depositMinor) }}</dd>
                            </div>

                            <div class="flex items-baseline justify-between gap-3">
                                <dt class="text-sub">{{ labels.payment?.remaining }}</dt>
                                <dd class="font-medium text-head">{{ money(remainingMinor) }}</dd>
                            </div>
                        </dl>
                    </div>

                    <!-- Payment collection method.

                         Asked only once there is something to collect: with
                         "No Payment Now" chosen there is no money and so no
                         method, and a control asking how to collect nothing
                         is a question with no right answer. -->
                    <fieldset v-if="payType !== 'none'" class="mt-4 pt-4 border-t border-line">
                        <legend class="block text-[13px] font-medium text-ink mb-2">{{ labels.payment?.action }}</legend>

                        <div class="grid sm:grid-cols-2 gap-2">
                            <button v-for="how in collectionMethods" :key="how"
                                    type="button" class="styledesk_optioncard"
                                    :class="{ 'is-on': collectionMethod === how, 'opacity-55 cursor-not-allowed': how === 'waive' && !canWaive }"
                                    :aria-pressed="collectionMethod === how"
                                    :disabled="how === 'waive' && !canWaive"
                                    :title="how === 'waive' && !canWaive ? labels.payment?.no_waive_permission : null"
                                    @click="collectionMethod = how">
                                <span class="block text-[13px] font-semibold text-head">{{ labels.payment?.actions?.[how] }}</span>
                                <span class="block text-[12px] text-sub">{{ labels.payment?.action_hints?.[how] }}</span>
                            </button>
                        </div>

                        <!-- Waiving is a decision somebody has to answer for,
                             so it is the one method that asks why and keeps
                             the name of whoever chose it. -->
                        <div v-if="collectionMethod === 'waive'" class="mt-3">
                            <label for="bWaiver" class="block text-[13px] font-medium text-ink mb-1.5">
                                {{ labels.payment?.waiver_reason }}
                            </label>
                            <input id="bWaiver" v-model="waiverReason" type="text" maxlength="300" class="sd-input"
                                   :placeholder="labels.payment?.waiver_placeholder">
                            <p class="text-[12px] text-faint mt-1.5">{{ labels.payment?.waiver_hint }}</p>
                            <p v-if="waiverMissing" class="text-[12px] text-danger mt-1.5">
                                {{ labels.payment?.waiver_needed }}
                            </p>
                        </div>

                        <p v-else-if="collectionMethod === 'collect-now'" class="mt-3 text-[12px] text-sub">
                            {{ labels.payment?.collect_now_hint }}
                        </p>

                        <p v-else-if="collectionMethod === 'link'" class="mt-3 text-[12px] text-sub">
                            {{ emailTo ? labels.payment?.action_hints?.link : labels.payment?.link_no_email }}
                        </p>
                    </fieldset>

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

        <!-- The same client, booked for the same service, twice on one day.

             Three strengths of the same warning, because they are three
             different mistakes: another appointment the same day is usually
             meant, one that overlaps usually is not, and one at the same
             time in the same branch almost never is. Only the last makes
             abandoning the new booking the obvious button. -->
        <div v-if="duplicateOpen" class="styledesk_modal" role="dialog" aria-modal="true"
             aria-labelledby="duplicateTitle">
            <div class="styledesk_modal__scrim" @click="dismissDuplicate"></div>

            <div class="styledesk_modal__panel">
                <div class="styledesk_modal__head">
                    <h2 id="duplicateTitle" class="text-[15px] font-semibold text-head">
                        {{ duplicateLevel === 'exact' ? labels.duplicate?.title_exact : labels.duplicate?.title }}
                    </h2>

                    <button type="button" class="styledesk_modal__close" :aria-label="labels.cancel"
                            @click="dismissDuplicate">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                        </svg>
                    </button>
                </div>

                <div class="styledesk_modal__body">
                    <p class="text-[13px] text-ink leading-relaxed"
                       :class="duplicateLevel === 'same_day' ? '' : 'font-semibold'">
                        {{ duplicateMessage }}
                    </p>

                    <p class="text-[12px] font-semibold text-sub mt-4 mb-1.5">{{ labels.duplicate?.existing }}</p>

                    <ul class="space-y-2">
                        <li v-for="match in repeatBookings" :key="match.id"
                            class="rounded-lg border border-line px-3 py-2.5">
                            <p class="text-[13px] font-semibold text-head">{{ match.date }}</p>
                            <p class="text-[13px] text-ink">{{ match.time }}</p>
                            <p class="text-[12px] text-sub mt-0.5">{{ match.services.join(', ') }}</p>
                            <p class="text-[12px] text-sub">
                                <span v-if="match.staff">{{ match.staff }}</span>
                                <span v-if="match.staff && match.location"> · </span>
                                <span v-if="match.location">{{ match.location }}</span>
                            </p>

                            <a :href="match.url" target="_blank" rel="noopener"
                               class="inline-block mt-1.5 text-[12px] font-semibold text-link">
                                {{ labels.duplicate?.view }}
                            </a>
                        </li>
                    </ul>

                    <p class="text-[12.5px] text-sub mt-4">{{ labels.duplicate?.ask }}</p>
                </div>

                <!-- On an exact duplicate the safe answer leads: the reader is
                     one click from writing the same appointment down twice. -->
                <div class="styledesk_modalfoot">
                    <button v-if="duplicateLevel === 'exact'" type="button"
                            class="h-9 px-4 rounded-lg bg-brand hover:bg-brand-dark text-white text-[13px] font-semibold transition-colors"
                            @click="askToDiscard">
                        {{ labels.duplicate?.cancel }}
                    </button>

                    <button v-else type="button"
                            class="h-9 px-4 rounded-lg bg-brand hover:bg-brand-dark text-white text-[13px] font-semibold transition-colors"
                            @click="acceptDuplicate">
                        {{ labels.duplicate?.continue }}
                    </button>

                    <button v-if="duplicateLevel === 'exact'" type="button" class="styledesk_action"
                            @click="acceptDuplicate">
                        {{ labels.duplicate?.create_anyway }}
                    </button>

                    <button v-else type="button" class="styledesk_action" @click="askToDiscard">
                        {{ labels.duplicate?.cancel }}
                    </button>
                </div>
            </div>
        </div>

        <!-- Abandoning the journey deletes it, so it is confirmed first.
             The scrim keeps the booking rather than deleting it: a stray
             click outside a dialog must never be the thing that throws work
             away. -->
        <div v-if="discardOpen" class="styledesk_modal" role="dialog" aria-modal="true"
             aria-labelledby="discardTitle">
            <div class="styledesk_modal__scrim" @click="keepBooking"></div>

            <div class="styledesk_modal__panel">
                <div class="styledesk_modal__head">
                    <h2 id="discardTitle" class="text-[15px] font-semibold text-head">
                        {{ labels.duplicate?.discard_title }}
                    </h2>

                    <button type="button" class="styledesk_modal__close" :aria-label="labels.duplicate?.keep"
                            @click="keepBooking">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                        </svg>
                    </button>
                </div>

                <div class="styledesk_modal__body">
                    <p class="text-[13px] text-ink leading-relaxed">{{ labels.duplicate?.discard_body }}</p>
                    <p class="text-[12.5px] text-sub mt-2">{{ labels.duplicate?.discard_keeps }}</p>
                </div>

                <!-- Keeping the booking leads: the destructive answer is the
                     one being confirmed, not the one being offered. -->
                <div class="styledesk_modalfoot">
                    <button type="button"
                            class="h-9 px-4 rounded-lg bg-brand hover:bg-brand-dark text-white text-[13px] font-semibold transition-colors"
                            @click="keepBooking">
                        {{ labels.duplicate?.keep }}
                    </button>

                    <button type="button" class="styledesk_action styledesk_action--danger"
                            :disabled="discarding" @click="discardBooking">
                        {{ labels.duplicate?.discard_confirm }}
                    </button>
                </div>
            </div>
        </div>

        <!-- =================================== column 3 — summary → payment → done
             Three cards in one place, opening in turn. A modal or a second
             page for the money would take the receptionist off the screen
             holding everything they might still be asked about, and the shut
             heads keep the whole workflow readable while one step is open. -->
        <section v-show="stage !== 'done'" class="lg:col-span-3 lg:sticky lg:top-[73px] space-y-3" :aria-label="labels.sections?.summary">

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
                    <span v-if="chosen.length" class="text-[12px] text-brand/75 shrink-0">{{ money(payableMinor) }}</span>
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

                    <div v-if="assignedRooms.length" class="flex items-baseline justify-between gap-3">
                        <dt class="text-sub">{{ labels.summary?.resource }}</dt>
                        <dd class="font-semibold text-head text-right">{{ assignedRooms.join(', ') }}</dd>
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
                    </div>
                </dl>

                <!-- What it comes to, and the two things that move it.

                     The coupon and the tip belong beside the total they
                     change, not a card away from it: this is the panel the
                     desk reads the number off, and a discount applied
                     somewhere the reader cannot see it is a discount they
                     have to take on trust. Both are still settled on the
                     server; the card only asks. -->
                <div v-if="chosen.length" class="px-4 pb-1 space-y-0">
                <!-- The coupon.

                     Validated on the server, because the rules are the
                     business's own and the browser cannot be handed the
                     promotions table to check them against. -->
                <div v-if="chosen.length" class="mt-4 pt-3.5 border-t border-line">
                    <p class="text-[12px] font-medium text-ink mb-1.5">{{ labels.pay?.coupon }}</p>

                    <div v-if="quote?.coupon" class="flex items-center gap-2 rounded-lg border border-brand/30 bg-brand/5 px-3 py-2">
                        <span class="min-w-0 flex-1">
                            <span class="block text-[12.5px] font-semibold text-head font-mono">{{ quote.coupon.code }}</span>
                            <span class="block text-[11.5px] text-sub">{{ quote.coupon.name }} · {{ quote.coupon.label }}</span>
                        </span>
                        <span class="text-[13px] font-semibold text-head shrink-0">−{{ quote.discount }}</span>
                        <button type="button" class="text-[12px] font-semibold text-link hover:underline shrink-0"
                                @click="removeCoupon">{{ labels.pay?.remove_coupon }}</button>
                    </div>

                    <div v-else class="flex gap-2">
                        <input v-model="couponCode" type="text" class="sd-input font-mono uppercase"
                               :placeholder="labels.pay?.coupon_placeholder"
                               @keydown.enter.prevent="applyCoupon">
                        <button type="button" class="styledesk_action shrink-0" :disabled="! couponCode.trim()"
                                @click="applyCoupon">{{ labels.pay?.apply }}</button>
                    </div>

                    <!-- Why, rather than "invalid": the desk has to tell
                         the client something they can act on. -->
                    <p v-if="couponError" class="mt-1.5 text-[12px] text-danger">{{ couponError }}</p>
                </div>

                <!-- The tip. Percentages come from App settings → Tips,
                     so a business that offers 15/18/20/25 gets those. -->
                <div v-if="chosen.length && quote?.tips_enabled" class="mt-4 pt-3.5 border-t border-line">
                    <p class="text-[12px] font-medium text-ink mb-1.5">{{ labels.pay?.add_tip }}</p>

                    <div class="flex flex-wrap gap-1.5">
                        <button v-if="quote.allow_no_tip" type="button" class="styledesk_tipchip"
                                :class="{ 'styledesk_tipchip--on': tipPercent === 0 && customTip === '' }"
                                @click="chooseTipPercent(0)">
                            <span class="font-semibold">{{ labels.pay?.no_tip }}</span>
                        </button>

                        <button v-for="percent in quote.tip_percentages" :key="percent" type="button"
                                class="styledesk_tipchip"
                                :class="{ 'styledesk_tipchip--on': tipPercent === percent && customTip === '' }"
                                @click="chooseTipPercent(percent)">
                            <span class="font-semibold">{{ percent }}%</span>
                        </button>

                        <button type="button" class="styledesk_tipchip"
                                :class="{ 'styledesk_tipchip--on': customTip !== '' }"
                                @click="tipPercent = null">
                            <span class="font-semibold">{{ labels.pay?.custom_tip }}</span>
                        </button>
                    </div>

                    <div v-if="tipPercent === null" class="mt-2 w-[150px]">
                        <input v-model="customTip" type="text" inputmode="decimal" class="sd-input !h-9"
                               :placeholder="money(0)" @input="applyCustomTip">
                    </div>
                </div>

                    <dl class="mt-4 pt-3.5 border-t border-line space-y-1.5 text-[13px]">
                        <div class="flex items-baseline justify-between gap-3">
                            <dt class="text-sub">{{ labels.summary?.subtotal }}</dt>
                            <dd class="text-head">{{ quote?.subtotal ?? money(estimate.subtotal) }}</dd>
                        </div>

                        <div v-if="quote?.discount_minor > 0" class="flex items-baseline justify-between gap-3">
                            <dt class="min-w-0 text-sub truncate">
                                {{ labels.pay?.discount }}<template v-if="quote.coupon"> — {{ quote.coupon.code }}</template>
                            </dt>
                            <dd class="text-head shrink-0">−{{ quote.discount }}</dd>
                        </div>

                        <div v-if="quote?.tip_minor > 0" class="flex items-baseline justify-between gap-3">
                            <dt class="text-sub">
                                {{ labels.pay?.tip }}<template v-if="quote.tip_percent"> — {{ quote.tip_percent }}%</template>
                            </dt>
                            <dd class="text-head">+{{ quote.tip }}</dd>
                        </div>

                        <div v-if="quote ? quote.tax_minor > 0 : estimate.tax" class="flex items-baseline justify-between gap-3">
                            <dt class="text-sub">{{ taxLabel }}</dt>
                            <dd class="text-head">{{ quote?.tax ?? money(estimate.tax) }}</dd>
                        </div>

                        <div class="flex items-baseline justify-between gap-3 pt-1.5 border-t border-line">
                            <dt class="font-semibold text-head">{{ labels.summary?.total }}</dt>
                            <dd class="font-semibold text-head">{{ money(payableMinor) }}</dd>
                        </div>

                        <div v-if="payType === 'deposit' && depositMinor > 0" class="flex items-baseline justify-between gap-3">
                            <dt class="text-sub">{{ labels.summary?.deposit }}</dt>
                            <dd class="text-head">{{ money(depositMinor) }}</dd>
                        </div>
                    </dl>
                </div>

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
                        <p class="text-[12px] text-sub">{{ labels.pay?.collect_now }}</p>
                        <p class="text-[26px] font-bold text-head leading-tight">{{ booking.collect ?? booking.due }}</p>

                        <!-- The other half of the same sentence: $64.80
                             now, $194.40 on the day. A deposit shown
                             without the balance beside it reads as the
                             whole bill, which is exactly the mistake
                             this replaced. -->
                        <dl class="mt-2 space-y-0.5 text-[12px]">
                            <div class="flex items-baseline justify-between gap-3">
                                <dt class="text-sub">{{ labels.pay?.booking_total }}</dt>
                                <dd class="font-medium text-head">{{ booking.total }}</dd>
                            </div>
                            <div v-if="booking.paid_minor > 0" class="flex items-baseline justify-between gap-3">
                                <dt class="text-sub">{{ labels.summary?.paid }}</dt>
                                <dd class="font-medium text-head">{{ booking.paid }}</dd>
                            </div>
                            <div v-if="booking.remaining_minor > 0" class="flex items-baseline justify-between gap-3">
                                <dt class="text-sub">{{ labels.pay?.remaining }}</dt>
                                <dd class="font-medium text-head">{{ booking.remaining }}</dd>
                            </div>
                        </dl>
                    </div>

                <p v-if="failure" class="sd-alert sd-alert--danger mx-4 mt-3 text-[12.5px]" role="alert">{{ failure }}</p>

                <!-- The same panel the booking's own page takes money in.
                     One component rather than two copies of the card form:
                     this is the money path, and two of it would be two
                     places to fix the day a method is added. -->
                <PaymentPanel :booking="booking" :methods="methods" :csrf="csrf"
                              :priced-for="paymentMethod"
                              :labels="{ ...labels, currency_symbol: currencySymbol }"
                              @paid="onPaid" />

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
        </section>
    </form>
</template>
