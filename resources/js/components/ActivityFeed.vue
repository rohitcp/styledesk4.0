<script setup>
import { computed, onMounted, ref, watch } from 'vue';

/**
 * What has been happening across the business.
 *
 * A page, opened in its own tab from the app bar. It began as a drawer over
 * whatever the reader was doing, and that was the wrong shape: this is a log
 * somebody sits and reads — scrolled, filtered, followed into a record and
 * come back from — and a panel that shuts when you click past it fights all
 * four of those. In its own tab the screen they were working on is still
 * there when they are done.
 *
 * Every row that names a record carries a link to it, so this is a way INTO
 * the app rather than a summary that replaces it.
 *
 * The icons are drawn from a sprite the page carries, because the choice of
 * icon is made from data at runtime and a Blade component cannot be called
 * from here.
 */
const props = defineProps({
    urls: { type: Object, required: true },
    /** What was new when the page was opened. */
    unread: { type: Number, default: 0 },
    labels: { type: Object, default: () => ({}) },
});

const t = (path, fallback = '') => path.split('.').reduce((carry, key) => carry?.[key], props.labels) ?? fallback;

const busy = ref(true);
const items = ref([]);
const next = ref(null);
const unread = ref(props.unread);
const group = ref('all');
const failed = ref(false);

const groups = ['all', 'bookings', 'clients', 'payments', 'staff', 'scheduling', 'communication', 'system'];

/** "99+" rather than a number nobody acts on differently. */
const badge = computed(() => (unread.value > 99 ? '99+' : String(unread.value)));

/**
 * The list, cut into the three answers a reader actually wants: what has
 * happened since they got in, what happened yesterday, and everything before.
 */
const sections = computed(() => {
    const order = ['today', 'yesterday', 'earlier'];
    const buckets = new Map();

    items.value.forEach((item) => {
        /* Anything older than yesterday keeps its own date as the heading,
           so a quiet week does not collapse into one "Earlier" of forty
           rows with no idea which day is which. */
        const key = item.day === 'earlier' ? item.day_label : item.day;

        if (! buckets.has(key)) {
            buckets.set(key, { key, label: item.day_label, items: [] });
        }

        buckets.get(key).items.push(item);
    });

    return [...buckets.values()].sort((a, b) => {
        const rank = (section) => (order.indexOf(section.key) === -1 ? 2 : order.indexOf(section.key));

        return rank(a) - rank(b);
    });
});

async function load({ more = false } = {}) {
    busy.value = true;
    failed.value = false;

    const query = new URLSearchParams({ group: group.value });

    if (more && next.value) {
        query.set('before', next.value);
    }

    try {
        const response = await fetch(`${props.urls.index}?${query}`, { headers: { Accept: 'application/json' } });

        if (! response.ok) {
            failed.value = true;

            return;
        }

        const json = await response.json();

        items.value = more ? [...items.value, ...json.items] : json.items;
        next.value = json.next;
        unread.value = json.unread;
    } catch {
        failed.value = true;
    } finally {
        busy.value = false;
    }
}

async function markRead() {
    const token = document.querySelector('meta[name="csrf-token"]')?.content;

    await fetch(props.urls.read, {
        method: 'POST',
        headers: { Accept: 'application/json', 'X-CSRF-TOKEN': token ?? '' },
    });

    unread.value = 0;
    items.value = items.value.map((item) => ({ ...item, unread: false }));
}

watch(group, () => load());

/* The page IS the feed, so it is fetched on arrival rather than waiting to be
   opened. The count came with the page, so the header is right before this
   lands. */
onMounted(() => load());
</script>

<template>
    <div>
        <!-- The one control that acts on the whole page, beside the count it
             acts on. -->
        <div v-if="unread > 0" class="flex flex-wrap items-center gap-3 mb-4">
            <span class="styledesk_badge styledesk_badge--active">{{ t('unread').replace(':count', badge) }}</span>

            <button type="button" class="text-[13px] font-semibold text-link hover:underline" @click="markRead">
                {{ t('mark_read') }}
            </button>
        </div>

        <!-- What kind of thing to look at. Wraps rather than
             scrolling sideways: a chip cut off at the panel edge
             is a filter nobody knows is there. -->
        <div class="styledesk_activity__filters">
            <button v-for="key in groups" :key="key" type="button"
                    class="styledesk_activity__chip"
                    :class="group === key ? 'is-on' : ''"
                    :aria-pressed="group === key ? 'true' : 'false'"
                    @click="group = key">
                {{ t(`groups.${key}`, key) }}
            </button>
        </div>

        <div class="styledesk_activity__list">
            <p v-if="busy && ! items.length" class="text-[13px] text-sub">{{ t('loading') }}</p>

            <p v-else-if="failed" class="text-[13px] text-danger">{{ t('empty') }}</p>

            <div v-else-if="! items.length" class="py-8 text-center">
                <p class="text-[14px] font-semibold text-head">
                    {{ group === 'all' ? t('empty') : t('empty_filtered') }}
                </p>
                <p v-if="group === 'all'" class="text-[13px] text-sub mt-1">{{ t('empty_hint') }}</p>
            </div>

            <template v-else>
                <section v-for="section in sections" :key="section.key" class="mb-4 last:mb-0">
                    <h3 class="styledesk_activity__day">{{ section.label }}</h3>

                    <ul>
                        <li v-for="item in section.items" :key="item.id"
                            class="styledesk_activity__row" :class="item.unread ? 'is-new' : ''">
                            <span class="styledesk_activity__icon" :class="`is-${item.group}`" aria-hidden="true">
                                <svg width="14" height="14"><use :href="`#act-${item.kind}`" /></svg>
                            </span>

                            <!-- What happened. -->
                            <div class="min-w-0 styledesk_activity__body">
                                <p class="styledesk_activity__kind">{{ item.kind_label }}</p>
                                <p class="styledesk_activity__text">{{ item.description }}</p>
                            </div>

                            <!-- Who, when, and the way through to
                                 the record. Beside the sentence
                                 where there is room and under it
                                 where there is not — an activity
                                 you cannot act on is a
                                 notification, not an activity. -->
                            <div class="styledesk_activity__aside">
                                <p class="styledesk_activity__meta">
                                    <template v-if="item.actor">{{ t('by').replace(':name', item.actor) }} · </template>{{ item.time_label }}
                                </p>

                                <a v-if="item.url" :href="item.url" class="styledesk_activity__link">
                                    {{ item.link_label }}
                                    <svg width="11" height="11" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                        <path d="M5 12h13M13 6l6 6-6 6" stroke="currentColor" stroke-width="2.1"
                                              stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                </a>
                            </div>
                        </li>
                    </ul>
                </section>

                <button v-if="next" type="button" class="styledesk_action w-full mt-2"
                        :disabled="busy" @click="load({ more: true })">
                    {{ busy ? t('loading') : t('more') }}
                </button>
            </template>
        </div>
    </div>
</template>
