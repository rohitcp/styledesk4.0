<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue';

/**
 * The four figures at the top of a client's profile.
 *
 * Last visit, next appointment, total visits, lifetime spend. Each answers a
 * different question and each is counted differently — "when were they last
 * in" is not "how many times have they been", and neither is "what have they
 * actually paid us" — so all four are worked out on the server and this only
 * draws them.
 *
 * They go stale while somebody else works: a payment taken at the till, a
 * booking marked complete in the diary. Rather than leave the profile quietly
 * showing yesterday's numbers, the row asks for them again when the tab comes
 * back to the front. That is the moment the reader looks at it, and the only
 * moment the answer matters — a page that polled every ten seconds would ask
 * a hundred questions nobody was in the room for.
 */
const props = defineProps({
    /** Where the figures are read from again. */
    url: { type: String, required: true },
    /** What the server rendered the page with, so the row is never blank. */
    summary: { type: Object, required: true },
    labels: { type: Object, default: () => ({}) },
});

const summary = ref({ ...props.summary });

/* Which of the four this is, in the order they are read: what happened, what
   is coming, how often, how much. */
const cards = [
    { key: 'last_visit', tone: 'blue' },
    { key: 'next_appointment', tone: 'violet' },
    { key: 'total_visits', tone: 'teal' },
    { key: 'lifetime_spend', tone: 'amber' },
];

const has = (key) => Boolean(summary.value[key]?.value);

/* Long enough that 17px bold would wrap in a card this wide. Measured on the
   content rather than assumed per card, so "18" stays big and "4 Sep 2026 ·
   10:30 AM" comes down to fit. */
const isSentence = (value) => String(value ?? '').length > 12;

async function refresh() {
    if (document.visibilityState !== 'visible') {
        return;
    }

    try {
        const response = await fetch(props.url, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        });

        if (! response.ok) {
            return;
        }

        summary.value = (await response.json()).summary ?? summary.value;
    } catch (error) {
        /* The figures on the page are still the ones the server sent. Saying
           nothing is right here: a reader looking at a profile has not asked
           for this, and an error about a refresh they did not request is
           noise about something that has not gone wrong for them. */
    }
}

onMounted(() => {
    document.addEventListener('visibilitychange', refresh);
    window.addEventListener('focus', refresh);
});

onBeforeUnmount(() => {
    document.removeEventListener('visibilitychange', refresh);
    window.removeEventListener('focus', refresh);
});
</script>

<template>
    <!-- Four across when the row can hold them, two when it cannot; equal
         widths either way, so it never reads as one card mattering more than
         the others. -->
    <div class="styledesk_metrics">
        <component v-for="card in cards" :key="card.key"
                   :is="summary[card.key]?.url ? 'a' : 'div'"
                   :href="summary[card.key]?.url"
                   class="styledesk_metric"
                   :class="[
                       `styledesk_metric--${card.tone}`,
                       summary[card.key]?.url ? 'hover:border-brand/40 transition-colors' : '',
                   ]">
            <p class="styledesk_metric__label">{{ labels[card.key] }}</p>

            <template v-if="has(card.key)">
                <!-- A date with a time on it is a sentence, not a figure,
                     and has to sit on one line: broken after the date, the
                     stray "AM" underneath reads as a second answer. A count
                     or a total stays big, because those are the numbers. -->
                <p class="styledesk_metric__value"
                   :class="{ 'styledesk_metric__value--compact': isSentence(summary[card.key].value) }"
                   :title="summary[card.key].value">
                    {{ summary[card.key].value }}
                </p>

                <!-- The service and the stylist are what make a date useful:
                     "3 Sep" is a fact; "3 Sep, balayage with Mei" is the
                     thing a receptionist repeats back down the phone. -->
                <p v-if="summary[card.key].detail" class="text-[12px] text-sub mt-0.5 truncate"
                   :title="summary[card.key].detail">
                    {{ summary[card.key].detail }}
                </p>
            </template>

            <!-- Said in words rather than left as a dash. "No previous
                 visits" is a fact about this client; a blank is a card that
                 looks broken. -->
            <p v-else class="styledesk_metric__empty">{{ labels[`${card.key}_empty`] }}</p>
        </component>
    </div>
</template>
