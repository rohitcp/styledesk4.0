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
    csrf: { type: String, required: true },
    labels: { type: Object, default: () => ({}) },
});

const emit = defineEmits(['paid']);

const method = ref('');
const busy = ref(false);
const failure = ref('');
const payment = ref({ amount: props.booking.collect_amount ?? props.booking.due_amount, received: '', reference: '' });
const card = ref({ name: '', number: '', expiry: '', cvv: '', zip: '' });

const chosenMethod = computed(() => props.methods.find((row) => row.key === method.value) ?? null);

/* What is still owed, worked the way the panel shows it: the amount typed
   against what the booking says is due. */
const changeDue = computed(() => {
    const received = Math.round(Number(payment.value.received || 0) * 100);
    const due = Math.round(Number(payment.value.amount || 0) * 100);

    return money(Math.max(0, received - due));
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

defineExpose({ reset: () => { method.value = ''; failure.value = ''; } });
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
                        {{ (labels.pay?.pay_amount ?? '').replace(':amount', (labels.currency_symbol ?? '') + Number(payment.amount || 0).toFixed(2)) }}
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
    </div>
</template>
