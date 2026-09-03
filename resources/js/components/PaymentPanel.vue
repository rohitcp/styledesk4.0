<script setup>
import { computed, ref, watch } from 'vue';

/**
 * Taking money against a booking.
 *
 * One component for both places money is taken: the third column of the New
 * Booking screen, and the Take Payment panel on a booking's own page. They
 * are the same act — a method, an amount, and a person saying it arrived —
 * and two copies of it would be two copies of the card form and two places
 * to fix the day a method is added.
 *
 * StyleDesk charges nothing. Every method except a card on a connected
 * provider is money that changed hands somewhere else and is written down
 * here, which is what `manual` means on the endpoint. Card details are never
 * posted, never stored and never logged.
 */
const props = defineProps({
    /** The booking, as the server's panel payload describes it. */
    booking: { type: Object, required: true },
    /** The ways this business can be paid, and whether each is set up. */
    methods: { type: Array, default: () => [] },
    /*
     * Which price list this booking was totalled against — 'card' or 'cash'.
     *
     * A cash booking is not a preference about the till; it is a different
     * set of prices, already added up and already quoted to the client. Take
     * it on a card and the salon collects the cash total for a card sale, and
     * is short the difference on every service that charges two prices. So a
     * cash booking is settled in cash, and the panel says so rather than
     * leaving somebody to notice.
     */
    pricedFor: { type: String, default: '' },
    csrf: { type: String, required: true },
    labels: { type: Object, default: () => ({}) },
});

const emit = defineEmits(['paid', 'draft']);

const method = ref('');

/* Cash only, and only for cash: a card booking may still be settled by
   PayPal or Venmo, because those are all the card price. */
const lockedMethod = computed(() => (props.pricedFor === 'cash' ? 'cash' : ''));
const isLocked = (key) => lockedMethod.value !== '' && key !== lockedMethod.value;
const busy = ref(false);
const failure = ref('');
const payment = ref({ amount: props.booking.collect_amount ?? props.booking.due_amount, received: '', reference: '' });
const card = ref({ name: '', number: '', expiry: '', cvv: '', zip: '' });

watch(lockedMethod, (locked) => {
    if (locked) {
        method.value = locked;

        return;
    }

    /* Unlocked again — the booking went back to card prices — so the choice
       returns to the person taking the money rather than staying on cash. */
    method.value = '';
}, { immediate: true });

const chosenMethod = computed(() => props.methods.find((row) => row.key === method.value) ?? null);

/* The tip, as its own answer.

   A tip is offered on the work, not on the bill: a booking with a massage
   and a bottle of oil on it suggests a tip on the massage, because twenty
   per cent of the oil is money the therapist did not earn. The server has
   already worked out which part of the bill that is — everything here reads
   from `booking.tips` rather than doing the arithmetic again. */
const tips = computed(() => props.booking.tips ?? { enabled: false });

/* Null means nobody has answered yet, which is not the same as nought.
   Where the business asks the client to choose, only one of those lets the
   payment through. */
const tip = ref(null);
const customTip = ref('');

/*
 * Start from what was agreed when the booking was taken.
 *
 * A receptionist who settled fifteen per cent with the client should not have
 * to remember it at the counter. A percentage is worked out again against
 * what is actually being collected; an amount somebody typed is offered
 * exactly as typed.
 */
function adoptChosenTip() {
    const chosen = props.booking.tips ?? {};

    if (! chosen.enabled) {
        return;
    }

    if (chosen.chosen_percent !== null && chosen.chosen_percent !== undefined) {
        const match = (chosen.suggested ?? []).find((option) => option.percent === chosen.chosen_percent);

        tip.value = match ? match.minor : Math.round((chosen.eligible_minor ?? 0) * chosen.chosen_percent / 100);
        customTip.value = '';

        return;
    }

    if (chosen.chosen_minor) {
        customTip.value = (chosen.chosen_minor / 100).toFixed(2);
    }
}

adoptChosenTip();

const tipMinor = computed(() => {
    if (customTip.value !== '') {
        return Math.max(0, Math.round(Number(customTip.value || 0) * 100));
    }

    return tip.value === null ? 0 : tip.value;
});

/*
 * What the till is taking, tip included.
 *
 * The bill and the tip are separate records — the tip is owed to whoever did
 * the work — but they are one handful of money, and a panel that showed the
 * bill alone would have the desk adding up in their head.
 */
const collectingMinor = computed(() => {
    const amount = Math.round(Number(payment.value.amount || 0) * 100);

    return Math.max(0, amount) + tipMinor.value;
});


/*
 * Report the amount and tip upward as they are typed.
 *
 * The breakdown above this panel has to add up to what the button is about to
 * take, and the only way for it to be certain of that is to be told by the
 * thing that owns the inputs. Emitting rather than lifting the state keeps
 * this component usable on the booking screen, where nothing is listening.
 */
watch(
    [() => payment.value.amount, tipMinor],
    ([amount, tip]) => emit('draft', { amount, tip }),
    { immediate: true },
);

/* Where the business insists on an answer, the button waits for one. */
const tipMissing = computed(() => tips.value.enabled
    && tips.value.require_selection
    && tip.value === null
    && customTip.value === '');

function chooseTip(minor) {
    tip.value = minor;
    customTip.value = '';
}

/* A new booking is a new question. Without this the previous client's
   twenty per cent would sit there pre-selected for the next one. */
watch(() => props.booking.id, () => {
    tip.value = null;
    customTip.value = '';
    adoptChosenTip();
});

/* What is still owed, worked the way the panel shows it: the amount typed
   against what the booking says is due. */
const changeDue = computed(() => {
    const received = Math.round(Number(payment.value.received || 0) * 100);
    const due = Math.round(Number(payment.value.amount || 0) * 100);

    /* The tip comes out of the same handful of notes, so what goes back is
       what is left after both. */
    return money(Math.max(0, received - due - tipMinor.value));
});

function money(minor) {
    return (props.labels.currency_symbol ?? '') + (minor / 100).toFixed(2);
}

/* The booking is re-read from the server after every payment, so the amount
   offered is always what is left rather than what was left when the panel
   opened. */
watch(() => props.booking.collect_amount ?? props.booking.due_amount, (amount) => {
    payment.value.amount = amount;
});

/**
 * Write down a payment.
 *
 * `manual` is the difference between money StyleDesk took and money somebody
 * says arrived. Everything but a card on a connected provider is the second
 * kind, and the button that records it says so.
 */
async function takePayment(manual) {
    if (busy.value) {
        return;
    }

    busy.value = true;
    failure.value = '';

    try {
        const response = await fetch(props.booking.urls.pay, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': props.csrf,
            },
            body: JSON.stringify({
                method: method.value,
                amount: payment.value.amount || props.booking.collect_amount || props.booking.due_amount,
                received: method.value === 'cash' ? (payment.value.received || null) : null,
                reference: payment.value.reference || null,
                /* Sent whenever tipping is on, including as nought: the
                   server can tell "declined" from "never asked" only by
                   whether the field arrives at all. */
                ...(tips.value.enabled ? { tip: (tipMinor.value / 100).toFixed(2) } : {}),
                manual,
            }),
        });

        const json = await response.json().catch(() => ({}));

        if (! response.ok) {
            failure.value = Object.values(json?.errors ?? {}).flat()[0]
                ?? json?.message
                ?? props.labels.pay?.failed;

            return;
        }

        payment.value = { amount: json.booking.collect_amount ?? json.booking.due_amount, received: '', reference: '' };
        tip.value = null;
        customTip.value = '';
        /* Never kept a moment longer than the request. */
        card.value = { name: '', number: '', expiry: '', cvv: '', zip: '' };

        if (json.booking.due_minor === 0) {
            method.value = '';
        }

        emit('paid', json.booking, json.payment);
    } catch (error) {
        failure.value = props.labels.pay?.failed;
    } finally {
        busy.value = false;
    }
}

defineExpose({ reset: () => { method.value = ''; failure.value = ''; tip.value = null; customTip.value = ''; } });
</script>

<template>
    <div>
        <p v-if="failure" class="sd-alert sd-alert--danger mx-4 mt-3 text-[12.5px]" role="alert">{{ failure }}</p>

        <!-- The ways this business can be paid. One that has not been
             set up is still shown, and says why it cannot be used:
             hiding it leaves somebody hunting for Venmo. -->
        <div v-if="!method" class="p-4 space-y-1.5">
            <p class="text-[12px] font-medium text-ink">{{ labels.pay?.method }}</p>

            <button v-for="row in methods" :key="row.key" type="button"
                    class="styledesk_paymethod" :disabled="!row.ready || isLocked(row.key)"
                    @click="method = row.key">
                <span class="min-w-0">
                    <span class="block text-[13px] font-semibold text-head">{{ row.name }}</span>
                    <span class="block text-[11.5px] text-sub">
                        <template v-if="isLocked(row.key)">{{ labels.pay?.cash_only_row }}</template>
                        <template v-else>{{ row.ready ? row.hint : labels.pay?.not_ready }}</template>
                    </span>
                </span>
                <svg v-if="row.ready" width="14" height="14" viewBox="0 0 24 24" fill="none" class="text-faint shrink-0"><path d="M9 6l6 6-6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </button>
        </div>

        <div v-else class="p-4 space-y-3">
            <div class="flex items-center gap-2">
                <p class="min-w-0 flex-1 text-[13px] font-semibold text-head">{{ chosenMethod?.name }}</p>
                <button v-if="!lockedMethod" type="button" class="text-[12px] font-semibold text-link hover:underline"
                        @click="method = ''">{{ labels.pay?.change_method }}</button>
            </div>

            <p v-if="lockedMethod" class="rounded-lg bg-hover px-3 py-2 text-[11.5px] text-sub">
                {{ labels.pay?.cash_only }}
            </p>

            <!-- The tip, asked before the amount is committed rather than
                 after. It is offered on the tipped part of the bill, which
                 is what the server sent: twenty per cent of a bottle of oil
                 is money nobody earned. -->
            <div v-if="tips.enabled" class="border border-line rounded-lg p-3 space-y-2.5">
                <div class="flex items-baseline justify-between gap-3">
                    <p class="text-[12px] font-semibold text-ink">{{ tips.labels?.title }}</p>
                    <p class="text-[11.5px] text-sub">
                        {{ tips.labels?.eligible }} {{ money(tips.eligible_minor) }}
                    </p>
                </div>

                <div class="flex flex-wrap gap-1.5">
                    <button v-for="option in tips.suggested" :key="option.percent" type="button"
                            class="styledesk_tipchip"
                            :class="{ 'styledesk_tipchip--on': customTip === '' && tip === option.minor }"
                            @click="chooseTip(option.minor)">
                        <span class="font-semibold">{{ option.percent }}%</span>
                        <span class="text-[11px] opacity-80">{{ money(option.minor) }}</span>
                    </button>

                    <!-- Declining is an answer, and only offered where the
                         business allows one. -->
                    <button v-if="tips.allow_no_tip" type="button"
                            class="styledesk_tipchip"
                            :class="{ 'styledesk_tipchip--on': customTip === '' && tip === 0 }"
                            @click="chooseTip(0)">
                        <span class="font-semibold">{{ tips.labels?.none }}</span>
                    </button>
                </div>

                <div>
                    <label for="pTip" class="block text-[11.5px] font-medium text-ink mb-1">{{ tips.labels?.custom }}</label>
                    <input id="pTip" v-model="customTip" type="text" inputmode="decimal" class="sd-input !h-9"
                           :placeholder="money(0)">
                </div>

                <div class="flex items-baseline justify-between gap-3 text-[13px] border-t border-line pt-2">
                    <span class="text-sub">{{ tips.labels?.selected }}</span>
                    <span class="font-semibold text-head">{{ money(tipMinor) }}</span>
                </div>

                <!-- The bill and the tip are one handful of money, whatever
                     the records say. A panel that showed the bill alone
                     would have the desk adding up in their head. -->
                <div class="flex items-baseline justify-between gap-3 text-[13px]">
                    <span class="font-semibold text-head">{{ labels.pay?.total_due }}</span>
                    <span class="text-[15px] font-bold text-head">{{ money(collectingMinor) }}</span>
                </div>

                <p v-if="tipMissing" class="text-[11.5px] text-danger">{{ tips.labels?.required }}</p>
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

                <button type="button" class="styledesk_paycta" :disabled="busy || tipMissing" @click="takePayment(true)">
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

                    <button type="button" class="styledesk_paycta" :disabled="busy || tipMissing" @click="takePayment(false)">
                        {{ (labels.pay?.pay_amount ?? '').replace(':amount', (labels.currency_symbol ?? '') + Number(payment.amount || 0).toFixed(2)) }}
                    </button>
                </fieldset>

                <div class="pt-3 border-t border-line space-y-3">
                    <div>
                        <label for="cRef" class="block text-[12px] font-medium text-ink mb-1">{{ labels.pay?.reference }}</label>
                        <input id="cRef" v-model="payment.reference" type="text" class="sd-input">
                        <p class="text-[11.5px] text-faint mt-1">{{ labels.pay?.reference_hint }}</p>
                    </div>

                    <button type="button" class="styledesk_paycta styledesk_paycta--quiet" :disabled="busy || tipMissing"
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

                <button type="button" class="styledesk_paycta" :disabled="busy || tipMissing" @click="takePayment(true)">
                    {{ busy ? labels.pay?.marking : labels.pay?.mark_paid }}
                </button>
            </template>
        </div>
    </div>
</template>
