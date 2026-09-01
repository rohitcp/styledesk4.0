<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import PaymentPanel from './PaymentPanel.vue';

/**
 * Taking the balance on a booking's own page.
 *
 * A booking is very often paid for somewhere other than the screen it was
 * taken on: a deposit on the phone in March, the rest at the desk in April.
 * Until now the money could only be recorded while the booking was being
 * made, so the second half of that had nowhere to go.
 *
 * The summary beside the button is re-rendered from the server's own answer
 * after every payment, so what is paid and what is owed are never this
 * page's arithmetic — and the page never reloads under somebody mid-task.
 */
const props = defineProps({
    /** The booking, as the server's panel payload describes it. */
    booking: { type: Object, required: true },
    /** The ways this business can be paid, and whether each is set up. */
    methods: { type: Array, default: () => [] },
    csrf: { type: String, required: true },
    currencySymbol: { type: String, default: '' },
    labels: { type: Object, default: () => ({}) },
});

/* The booking as it now stands. Replaced wholesale by the server's answer
   after each payment rather than adjusted here: what is paid and what is
   owed are its numbers, and a page doing its own sums about money is a
   second opinion nobody asked for. */
const booking = ref({ ...props.booking });
const open = ref(false);
const panel = ref(null);

const settled = computed(() => booking.value.due_minor <= 0);

/* The word beside a tip on a past payment. Read from the booking's own tip
   payload so it is translated once, on the server, like everything else. */
const tipLabel = computed(() => booking.value.tips?.labels?.selected ?? '');

function openPanel() {
    if (settled.value) {
        return;
    }

    open.value = true;
    panel.value?.reset();
}

function closePanel() {
    open.value = false;
}

function onPaid(updated) {
    booking.value = updated;

    /* Settled in full closes the panel: there is nothing left to ask for,
       and a form offering to take zero is a form that invites a mistake.
       Part paid stays open with the rest still owing. */
    if (updated.due_minor <= 0) {
        open.value = false;
    }
}

function closeOnEscape(event) {
    if (event.key === 'Escape' && open.value) {
        closePanel();
    }
}

onMounted(() => document.addEventListener('keydown', closeOnEscape));
onBeforeUnmount(() => document.removeEventListener('keydown', closeOnEscape));
</script>

<template>
    <div>
        <!-- The summary. Read from this island rather than rendered in Blade
             because it has to change the moment a payment lands, and a second
             copy in the page would be the one that goes stale. -->
        <dl class="bg-white border border-line rounded-card p-4 space-y-2.5 text-[13px]">
            <div v-for="line in booking.lines" :key="line.key" class="flex items-baseline justify-between gap-4">
                <dt :class="line.strong ? 'font-semibold text-head' : 'text-sub'">{{ line.label }}</dt>
                <dd :class="line.strong ? 'font-bold text-head' : 'font-medium text-head'">{{ line.value }}</dd>
            </div>

            <div v-if="booking.deposit_minor > 0" class="flex items-baseline justify-between gap-4">
                <dt class="text-sub">{{ labels.summary?.deposit }}</dt>
                <dd class="font-medium text-head">{{ currencySymbol }}{{ (booking.deposit_minor / 100).toFixed(2) }}</dd>
            </div>

            <div class="flex items-baseline justify-between gap-4 pt-2.5 border-t border-line">
                <dt class="text-sub">{{ labels.summary?.paid }}</dt>
                <dd class="font-medium text-head">{{ booking.paid }}</dd>
            </div>

            <!-- The line anybody opening this page is usually looking for, so
                 it is stated even when it is nothing. -->
            <div class="flex items-baseline justify-between gap-4">
                <dt class="font-semibold text-head">{{ labels.summary?.due }}</dt>
                <dd class="font-bold" :class="booking.due_minor > 0 ? 'text-danger' : 'text-head'">{{ booking.due }}</dd>
            </div>

            <div class="flex items-baseline justify-between gap-4 pt-2.5 border-t border-line">
                <dt class="text-sub">{{ labels.detail?.payment_status }}</dt>
                <dd>
                    <span class="styledesk_badge" :class="settled ? 'styledesk_badge--active' : 'styledesk_badge--setup'">
                        {{ booking.payment_status_label }}
                    </span>
                </dd>
            </div>
        </dl>

        <!-- How this booking's money is meant to arrive, and where the asking
             got to. A balance sitting unpaid means something different when a
             link went out on Tuesday than when the desk is collecting it on
             the day. -->
        <div v-if="booking.collection_label || booking.links.length || booking.waiver"
             class="mt-3 bg-white border border-line rounded-card p-4 space-y-2.5 text-[13px]">
            <div v-if="booking.collection_label" class="flex items-baseline justify-between gap-4">
                <dt class="text-sub">{{ labels.payment?.action }}</dt>
                <dd class="font-medium text-head">{{ booking.collection_label }}</dd>
            </div>

            <div v-for="row in booking.links" :key="row.id"
                 class="flex flex-wrap items-center justify-between gap-x-4 gap-y-1">
                <span class="text-sub">{{ labels.payment?.link_status }}</span>
                <span class="flex items-center gap-2">
                    <span class="styledesk_badge" :class="row.status_class">{{ row.status_label }}</span>
                    <span class="font-medium text-head">{{ row.amount }}</span>
                </span>
                <span v-if="row.sent_to" class="w-full text-[12px] text-faint truncate">
                    {{ row.sent_to }} · {{ row.sent_at }}
                </span>
            </div>

            <div v-if="booking.waiver" class="pt-2.5 border-t border-line">
                <p class="font-medium text-head">{{ labels.payment?.actions?.waive }}</p>
                <p class="text-[12.5px] text-sub mt-0.5">{{ booking.waiver.reason }}</p>
                <p class="text-[12px] text-faint mt-0.5">{{ booking.waiver.by }} · {{ booking.waiver.at }}</p>
            </div>
        </div>

        <!-- One button, and it says which of the two states it is in. A Take
             Payment that is merely greyed out leaves the reader wondering
             whether they lack a permission; "Paid in full" answers it. -->
        <button v-if="!settled" type="button"
                class="mt-3 w-full h-10 rounded-lg bg-brand hover:bg-brand-dark text-white text-[13px] font-semibold transition-colors"
                @click="openPanel">
            {{ labels.detail?.take_payment }}
        </button>

        <p v-else class="mt-3 flex items-center justify-center gap-1.5 h-10 rounded-lg bg-brand/5 border border-brand/25 text-[13px] font-semibold text-brand">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <path d="M5 12.5l4.5 4.5L19 7.5" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            {{ labels.detail?.paid_in_full }}
        </p>

        <!-- What actually happened. Part of the island rather than the page
             around it, because a payment just taken has to appear here
             without a reload — a transactions list that still says "nothing
             yet" under a summary saying Paid is the page contradicting
             itself. -->
        <section class="mt-6">
            <h2 class="styledesk_heading">{{ labels.detail?.transactions }}</h2>

            <p v-if="!booking.payments.length" class="mt-2 text-[13px] text-sub">
                {{ labels.detail?.no_transactions }}
            </p>

            <ul v-else class="mt-3 space-y-2">
                <li v-for="entry in booking.payments" :key="entry.id"
                    class="bg-white border border-line rounded-card p-4">
                    <div class="flex items-start justify-between gap-4">
                        <div class="min-w-0">
                            <p class="text-[13.5px] font-semibold text-head">{{ entry.method_label }}</p>
                            <p class="text-[12px] text-sub mt-0.5">
                                {{ entry.at }}
                                <template v-if="entry.by">
                                    · {{ (labels.detail?.recorded_by ?? '').replace(':name', entry.by) }}
                                </template>
                            </p>

                            <p v-if="entry.reference" class="text-[12px] text-faint mt-1 font-mono">{{ entry.reference }}</p>

                            <p v-if="entry.change" class="text-[12px] text-sub mt-1">
                                {{ labels.pay?.change }}: {{ entry.change }}
                            </p>
                        </div>

                        <div class="text-right shrink-0">
                            <p class="text-[13.5px] font-semibold text-head">{{ entry.amount }}</p>
                            <p v-if="entry.tip" class="text-[11.5px] text-sub">{{ tipLabel }} {{ entry.tip }}</p>
                            <span class="styledesk_paystate is-paid mt-1">{{ entry.status_label }}</span>
                        </div>
                    </div>
                </li>
            </ul>
        </section>

        <!-- A side panel rather than a page. Taking the balance is a minute's
             work against a booking somebody is already reading, and sending
             them to a second screen to do it loses the thing they were
             checking it against. -->
        <Teleport to="body">
            <div v-if="open" class="fixed inset-0 z-[70] flex justify-end">
                <div class="absolute inset-0 bg-black/30" aria-hidden="true" @click="closePanel"></div>

                <div class="relative w-full sm:max-w-[400px] h-full bg-white shadow-xl flex flex-col"
                     role="dialog" aria-modal="true" :aria-label="labels.detail?.take_payment">
                    <header class="shrink-0 flex items-center gap-3 px-4 py-3 border-b border-line">
                        <h2 class="min-w-0 flex-1 text-[15px] font-bold text-head">{{ labels.detail?.take_payment }}</h2>

                        <button type="button" class="sd-iconbtn grid place-items-center shrink-0"
                                :aria-label="labels.cancel" @click="closePanel">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"/></svg>
                        </button>
                    </header>

                    <div class="flex-1 min-h-0 overflow-y-auto styledesk_scroll">
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

                        <!-- The same panel the booking screen takes money in.
                             One component, so the card form and the method
                             list cannot drift apart between the two. -->
                        <PaymentPanel ref="panel" :booking="booking" :methods="methods" :csrf="csrf"
                                      :labels="{ ...labels, currency_symbol: currencySymbol }"
                                      @paid="onPaid" />
                    </div>
                </div>
            </div>
        </Teleport>
    </div>
</template>
