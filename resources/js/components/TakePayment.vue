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

/* Nothing left to collect — which is not the same as nothing left owing on
   the work. A booking a membership covers in full has a nought balance and
   an agreed tip nobody has taken, and a card reading "Paid in full" over it
   would strand that money with no way to reach it. */
const settled = computed(() => booking.value.due_minor <= 0
    && (booking.value.tip_due_minor ?? 0) <= 0);

/* What the panel below is currently asking for, mirrored up here so the
   breakdown and the button never disagree. The panel owns the inputs; this
   only reads what it reports. */
const draft = ref({ amount: null, tip: 0 });

const additionalTipMinor = computed(() => draft.value.tip ?? 0);

/* The amount typed, or the whole balance when nothing has been typed yet —
   the same default the panel opens on. */
const collectTodayMinor = computed(() => {
    const amount = draft.value.amount;
    const base = amount === null || amount === ''
        ? (booking.value.collect_minor ?? booking.value.due_minor)
        : Math.round(parseFloat(amount) * 100);

    return (Number.isFinite(base) ? Math.max(0, base) : 0) + additionalTipMinor.value;
});

/* What is still owed after this one is taken. The tip is not a debt, so it
   is not subtracted from the balance. */
const remainingAfterMinor = computed(() => Math.max(
    0,
    booking.value.due_minor - (collectTodayMinor.value - additionalTipMinor.value),
));

function money(minor) {
    return (props.currencySymbol ?? '') + (minor / 100).toFixed(2);
}

function onDraft(next) {
    draft.value = next;
}

/* The word beside a tip on a past payment. Read from the booking's own tip
   payload so it is translated once, on the server, like everything else. */
const tipLabel = computed(() => booking.value.tips?.labels?.selected ?? '');

function openPanel() {
    if (settled.value) {
        return;
    }

    open.value = true;
    draft.value = { amount: null, tip: 0 };
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
    if (updated.due_minor <= 0 && (updated.tip_due_minor ?? 0) <= 0) {
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
        <!-- One card, not three.
             The bill, how the money is meant to arrive, and the button that
             takes it are one thought — the desk reads down them in that order
             and acts at the bottom. As three bordered boxes they read as three
             unrelated widgets, and the button floated free of the figures it
             was about.

             The brand border is the one card on this page drawn in the primary
             colour, and deliberately so: money owed is what the column is
             opened for. The dividers inside it stay neutral — a card outlined
             and subdivided in the same strong colour reads as a warning.

             Read from this island rather than rendered in Blade because it has
             to change the moment a payment lands, and a second copy in the
             page would be the one that goes stale. -->
        <div class="bg-white border border-brand rounded-card overflow-hidden">
            <dl class="p-4 space-y-2 text-[13px]">
                <!-- Every line of the bill, zeros included. Somebody reading this
                     card is answering "what is owed and why", and a line that is
                     absent because it is nothing looks the same as a line the page
                     failed to render. -->
                <div v-for="line in booking.breakdown" :key="line.key"
                     class="flex items-baseline justify-between gap-4"
                     :class="line.strong ? 'pt-2.5 mt-0.5 border-t border-line' : ''">
                    <dt :class="line.strong ? 'font-semibold text-head' : 'text-sub'">{{ line.label }}</dt>
                    <dd :class="[
                        line.strong ? 'font-bold' : 'font-medium',
                        line.negative ? 'text-emerald-700' : '',
                        line.key === 'due' && booking.due_minor > 0 ? 'text-danger' : (line.negative ? '' : 'text-head'),
                    ]">{{ line.value }}</dd>
                </div>

                <div v-if="booking.deposit_minor > 0" class="flex items-baseline justify-between gap-4">
                    <dt class="text-sub">{{ labels.summary?.deposit }}</dt>
                    <dd class="font-medium text-head">{{ currencySymbol }}{{ (booking.deposit_minor / 100).toFixed(2) }}</dd>
                </div>

                <!-- What the till is about to take, while it is being entered.
                     Shown only with the panel open: an "additional tip" line on a
                     closed card is a figure about nothing. -->
                <template v-if="open">
                    <div v-if="additionalTipMinor > 0" class="flex items-baseline justify-between gap-4">
                        <dt class="text-sub">{{ labels.summary?.additional_tip }}</dt>
                        <dd class="font-medium text-head">{{ money(additionalTipMinor) }}</dd>
                    </div>

                    <div class="flex items-baseline justify-between gap-4 pt-2.5 mt-0.5 border-t border-line">
                        <dt class="font-semibold text-head">{{ labels.summary?.collect_today }}</dt>
                        <dd class="font-bold text-head">{{ money(collectTodayMinor) }}</dd>
                    </div>

                    <div v-if="remainingAfterMinor > 0" class="flex items-baseline justify-between gap-4">
                        <dt class="text-sub">{{ labels.pay?.remaining }}</dt>
                        <dd class="font-medium text-head">{{ money(remainingAfterMinor) }}</dd>
                    </div>
                </template>

                <div class="flex items-baseline justify-between gap-4 pt-2.5 mt-0.5 border-t border-line">
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
                 class="px-4 py-3 border-t border-line bg-[#fbfbfc] space-y-2.5 text-[13px]">
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

            <!-- The act, at the foot of the figures it is about. One button,
                 and it says which of the two states it is in: a Take Payment
                 that is merely greyed out leaves the reader wondering whether
                 they lack a permission; "Paid in full" answers it. -->
            <div class="p-3 border-t border-line">
                <button v-if="!settled" type="button"
                        class="w-full h-10 rounded-lg bg-brand hover:bg-brand-dark text-white text-[13px] font-semibold transition-colors"
                        @click="openPanel">
                    {{ labels.detail?.take_payment }}
                </button>

                <p v-else class="flex items-center justify-center gap-1.5 h-10 rounded-lg bg-brand/5 border border-brand/25 text-[13px] font-semibold text-brand">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <path d="M5 12.5l4.5 4.5L19 7.5" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    {{ labels.detail?.paid_in_full }}
                </p>
            </div>

            <!-- What actually happened, in the same card as what is owed.
                 Part of the island rather than the page around it, because a
                 payment just taken has to appear here without a reload — a
                 transactions list still saying "nothing yet" under a summary
                 saying Paid is the page contradicting itself. -->
            <section class="px-4 py-3.5 border-t border-line">
                <h2 class="text-[12px] font-semibold uppercase tracking-wide text-sub">{{ labels.detail?.transactions }}</h2>

                <p v-if="!booking.payments.length" class="mt-1.5 text-[12.5px] text-sub">
                    {{ labels.detail?.no_transactions }}
                </p>

                <ul v-else class="mt-2.5 space-y-2">
                    <li v-for="entry in booking.payments" :key="entry.id"
                        class="border border-line rounded-lg p-3">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="text-[13px] font-semibold text-head">{{ entry.method_label }}</p>
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
                                <p class="text-[13px] font-semibold text-head">{{ entry.amount }}</p>
                                <p v-if="entry.tip" class="text-[11.5px] text-sub">{{ tipLabel }} {{ entry.tip }}</p>
                                <span class="styledesk_paystate is-paid mt-1">{{ entry.status_label }}</span>
                            </div>
                        </div>
                    </li>
                </ul>
            </section>
        </div>

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
                            <!-- What is actually about to be taken, tip and
                                 all. The balance alone reads $0.00 on a
                                 booking a membership covers, over a panel
                                 that is collecting the gratuity — the two
                                 figures on one screen have to agree. -->
                            <p class="text-[26px] font-bold text-head leading-tight">{{ money(collectTodayMinor) }}</p>

                            <!-- The other half of the same sentence: $64.80
                                 now, $194.40 on the day. A deposit shown
                                 without the balance beside it reads as the
                                 whole bill, which is exactly the mistake
                                 this replaced. -->
                            <!-- The whole bill, not a summary of it. Somebody
                                 about to take money has to see that tax really
                                 is nil and that no coupon was applied, rather
                                 than guess from a line that is not there. -->
                            <dl class="mt-3 rounded-card border border-line bg-[#fbfbfc] p-3 space-y-1 text-[12px]">
                                <div v-for="line in booking.breakdown" :key="line.key"
                                     class="flex items-baseline justify-between gap-3"
                                     :class="line.strong ? 'pt-1.5 mt-1.5 border-t border-line' : ''">
                                    <dt :class="line.strong ? 'font-semibold text-head' : 'text-sub'">{{ line.label }}</dt>
                                    <dd :class="[
                                        line.strong ? 'font-bold text-head text-[13px]' : 'font-medium',
                                        line.negative ? 'text-emerald-700' : 'text-head',
                                    ]">{{ line.value }}</dd>
                                </div>

                                <!-- The two figures that move while the panel is
                                     open. Kept with the rest of the bill so the
                                     desk reads one column, not two. -->
                                <div v-if="additionalTipMinor > 0" class="flex items-baseline justify-between gap-3">
                                    <dt class="text-sub">{{ labels.summary?.additional_tip }}</dt>
                                    <dd class="font-medium text-head">{{ money(additionalTipMinor) }}</dd>
                                </div>

                                <div class="flex items-baseline justify-between gap-3 pt-1.5 mt-1.5 border-t border-line">
                                    <dt class="font-semibold text-head">{{ labels.summary?.collect_today }}</dt>
                                    <dd class="font-bold text-head text-[13px]">{{ money(collectTodayMinor) }}</dd>
                                </div>

                                <div v-if="remainingAfterMinor > 0" class="flex items-baseline justify-between gap-3">
                                    <dt class="text-sub">{{ labels.pay?.remaining }}</dt>
                                    <dd class="font-medium text-head">{{ money(remainingAfterMinor) }}</dd>
                                </div>
                            </dl>
                        </div>

                        <!-- The same panel the booking screen takes money in.
                             One component, so the card form and the method
                             list cannot drift apart between the two. -->
                        <PaymentPanel ref="panel" :booking="booking" :methods="methods" :csrf="csrf" :priced-for="booking.priced_for"
                                      :labels="{ ...labels, currency_symbol: currencySymbol }"
                                      @draft="onDraft" @paid="onPaid" />
                    </div>
                </div>
            </div>
        </Teleport>
    </div>
</template>
