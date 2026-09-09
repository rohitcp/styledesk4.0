<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

/**
 * The Publish Schedule confirmation.
 *
 * Publishing is the one action on this page that leaves the building — it
 * emails the person whose week it is — so it asks first, and shows what is
 * about to be sent rather than only asking whether to send it. The summary is
 * the same period the heading names and the same numbers the email will carry;
 * they come from the server so the two cannot drift.
 *
 * An island around an ordinary form: confirming posts the range and the server
 * does the work. There is no JSON endpoint and no second copy of the rules.
 */
const props = defineProps({
    action: { type: String, required: true },
    csrf: { type: String, required: true },
    staffName: { type: String, default: '' },
    from: { type: String, required: true },
    until: { type: String, required: true },
    periodLabel: { type: String, default: '' },
    durationLabel: { type: String, default: '' },
    workingDays: { type: [String, Number], default: 0 },
    totalHoursLabel: { type: String, default: '' },
    /** Republishing an edited week says so on the button. */
    isRepublish: { type: Boolean, default: false },
    labels: { type: Object, default: () => ({}) },
});

const open = ref(false);
const sending = ref(false);

const confirmLabel = computed(() => (props.isRepublish ? props.labels.publish_changes : props.labels.publish));

const facts = computed(() => [
    { term: props.labels.confirm?.staff_member, value: props.staffName },
    { term: props.labels.confirm?.period, value: props.periodLabel },
    { term: props.labels.confirm?.duration, value: props.durationLabel },
    { term: props.labels.confirm?.working_days, value: String(props.workingDays) },
    { term: props.labels.confirm?.total_hours, value: props.totalHoursLabel },
]);

function onKey(event) {
    if (event.key === 'Escape' && open.value) {
        open.value = false;
    }
}

onMounted(() => document.addEventListener('keydown', onKey));
onBeforeUnmount(() => document.removeEventListener('keydown', onKey));
</script>

<template>
    <div>
        <button type="button"
                class="shrink-0 inline-flex items-center gap-1.5 h-9 px-3.5 rounded-lg bg-brand hover:bg-brand-dark text-white text-[13px] font-semibold transition-colors"
                @click="open = true">
            {{ confirmLabel }}
        </button>

        <!-- Fixed and full-screen behind a scrim, because the page under it
             scrolls: this button appears twice, and a panel anchored to
             whichever one was pressed would be lost the moment somebody
             moved. -->
        <div v-if="open" class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto p-4 sm:p-6"
             role="dialog" aria-modal="true" :aria-label="labels.confirm?.title">
            <div class="fixed inset-0 bg-black/40" @click="open = false"></div>

            <form :action="action" method="POST"
                  class="relative w-full max-w-[520px] bg-white rounded-card shadow-xl my-4"
                  @submit="sending = true">
                <input type="hidden" name="_token" :value="csrf">
                <input type="hidden" name="from" :value="from">
                <input type="hidden" name="until" :value="until">

                <div class="p-5 border-b border-line">
                    <h2 class="text-[16px] font-semibold text-head">{{ labels.confirm?.title }}</h2>
                    <p class="text-[13px] text-sub mt-1 leading-relaxed">{{ labels.confirm?.intro }}</p>
                </div>

                <dl class="p-5 space-y-0">
                    <div v-for="(fact, index) in facts" :key="fact.term"
                         class="flex flex-wrap items-baseline justify-between gap-3 py-2.5"
                         :class="index < facts.length - 1 ? 'border-b border-line' : ''">
                        <dt class="text-[13px] text-sub">{{ fact.term }}</dt>
                        <dd class="text-[14px] font-semibold text-head">{{ fact.value }}</dd>
                    </div>
                </dl>

                <p class="px-5 pb-1 text-[12px] text-sub leading-relaxed">{{ labels.confirm?.consequence }}</p>

                <div class="p-5 border-t border-line flex flex-wrap items-center gap-2">
                    <button type="submit" :disabled="sending"
                            class="h-9 px-4 rounded-lg bg-brand hover:bg-brand-dark text-white text-[13px] font-semibold transition-colors disabled:opacity-60">
                        {{ sending ? labels.publishing : confirmLabel }}
                    </button>
                    <button type="button" class="styledesk_action" @click="open = false">{{ labels.cancel }}</button>
                </div>
            </form>
        </div>
    </div>
</template>
