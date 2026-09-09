<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { onboarding } from '../stores/onboarding';
import ConfirmDialog from './ConfirmDialog.vue';
import MultiSelect from './MultiSelect.vue';

/**
 * Invite colleagues, and watch the invitations you have sent.
 *
 * Unlike the repeater this replaced, adding someone here is not deferred to
 * the Continue button: the invitation is created and the email queued the
 * moment Send is pressed. That is what the list below is showing — people who
 * have actually been invited, not rows waiting to be submitted.
 */
const props = defineProps({
    invitations: { type: Array, default: () => [] },
    locations: { type: Array, default: () => [] },
    services: { type: Array, default: () => [] },
    tenantId: { type: String, default: '' },
    endpoint: { type: String, required: true },
    csrf: { type: String, required: true },
});

const ROLES = {
    'administrator': 'Administrator',
    'manager': 'Manager',
    'front-desk': 'Front desk',
    'service-provider': 'Service provider',
};

const SERVICE_OPTIONS = computed(() => Object.fromEntries(
    props.services.map((s) => [String(s.id), s.name])
));

const LOCATION_OPTIONS = computed(() => Object.fromEntries([
    ['', 'All locations'],
    ...props.locations.map((l) => [String(l.id), l.name]),
]));

const list = ref([...props.invitations]);

// Published for the preview rail in the other column.
watch(list, (value) => { onboarding.team = value; }, { deep: true, immediate: true });

const form = reactive({
    first_name: '',
    last_name: '',
    email: '',
    role: 'service-provider',
    job_title: '',
    location_id: '',
    message: '',
    service_ids: [],
});

const errors = ref({});
const sending = ref(false);
const notice = ref(null);

/**
 * The invitation a duplicate collided with.
 *
 * Held separately from `errors` because it is not really an error: the spec
 * asks for Resend and Cancel to be offered against the existing invitation,
 * which needs the invitation itself, not just a message.
 */
const duplicate = ref(null);

function reset() {
    Object.assign(form, {
        first_name: '', last_name: '', email: '', role: 'service-provider',
        job_title: '', location_id: '', message: '', service_ids: [],
    });
    errors.value = {};
    duplicate.value = null;
}

async function post(url, options = {}) {
    const response = await fetch(url, {
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': props.csrf,
            'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
        ...options,
    });

    const body = await response.json().catch(() => ({}));

    return { response, body };
}

async function send() {
    if (sending.value) {
        return;
    }

    sending.value = true;
    errors.value = {};
    duplicate.value = null;
    notice.value = null;

    try {
        const { response, body } = await post(props.endpoint, {
            method: 'POST',
            // '' is the "all locations" option; the server wants null there.
            body: JSON.stringify({
                ...form,
                location_id: form.location_id || null,
                // The picker works in strings because option keys are strings;
                // the server validates against integer ids.
                service_ids: form.service_ids.map(Number),
            }),
        });

        if (response.status === 409) {
            duplicate.value = body.data;
            notice.value = { tone: 'warn', text: body.message };

            return;
        }

        if (response.status === 422 || response.status === 429) {
            errors.value = body.errors ?? {};

            return;
        }

        if (!response.ok) {
            // Never the provider's words — the server keeps those internal,
            // and this is the generic fallback for anything else.
            notice.value = { tone: 'error', text: 'Something went wrong sending that invitation. Please try again.' };

            return;
        }

        list.value.push(body.data);
        reset();
        notice.value = { tone: 'success', text: `Invitation sent to ${body.data.email}.` };
    } catch {
        notice.value = { tone: 'error', text: 'We could not reach StyleDesk. Check your connection and try again.' };
    } finally {
        sending.value = false;
    }
}

const busyId = ref(null);

async function resend(invitation) {
    busyId.value = invitation.id;
    notice.value = null;

    const { response, body } = await post(`${props.endpoint}/${invitation.id}/resend`, { method: 'POST' });

    busyId.value = null;

    if (!response.ok) {
        notice.value = { tone: 'error', text: body.message ?? 'That invitation could not be sent again.' };

        return;
    }

    replace(body.data);
    duplicate.value = null;
    notice.value = { tone: 'success', text: `A new invitation is on its way to ${body.data.email}.` };
}

const pendingRevoke = ref(null);

const revokeMessage = computed(() => (pendingRevoke.value
    ? `${pendingRevoke.value.name}'s invitation link will stop working immediately. They will not be told.`
    : ''));

async function revokeConfirmed() {
    const invitation = pendingRevoke.value;

    pendingRevoke.value = null;

    if (!invitation) {
        return;
    }

    busyId.value = invitation.id;

    const { response, body } = await post(`${props.endpoint}/${invitation.id}`, { method: 'DELETE' });

    busyId.value = null;

    if (!response.ok) {
        notice.value = { tone: 'error', text: body.message ?? 'That invitation could not be cancelled.' };

        return;
    }

    replace(body.data);
    duplicate.value = null;
    notice.value = { tone: 'success', text: `${invitation.name}'s invitation was cancelled.` };
}

function replace(updated) {
    const index = list.value.findIndex((i) => i.id === updated.id);

    if (index === -1) {
        list.value.push(updated);
    } else {
        list.value[index] = { ...list.value[index], ...updated };
    }
}

function toneClass(tone) {
    return {
        success: 'sd-alert--success',
        warn: 'sd-alert--warn',
        error: 'sd-alert--danger',
    }[tone] ?? 'sd-alert--success';
}

function statusClass(status) {
    return {
        accepted: 'bg-green-50 text-green-700',
        expired: 'bg-amber-50 text-amber-700',
        revoked: 'bg-gray-100 text-sub',
    }[status] ?? 'bg-indigo-50 text-brand';
}

/**
 * Flip Pending to Active without a reload.
 *
 * The person who sent the invitation is usually still on this screen when it
 * is accepted, and a list that quietly goes stale is exactly what makes people
 * press Resend on an invitation that already worked.
 */
let channel = null;

onMounted(() => {
    if (!props.tenantId || !window.Echo) {
        return;
    }

    try {
        channel = window.Echo.private(`tenant.${props.tenantId}.team`)
            .listen('.invitation.accepted', (payload) => replace(payload));
    } catch {
        // No websocket is a degraded list, not a broken page: everything here
        // still works, it just needs a reload to show an acceptance.
    }
});

onBeforeUnmount(() => {
    if (channel && window.Echo) {
        window.Echo.leave(`tenant.${props.tenantId}.team`);
    }
});
</script>

<template>
    <div class="space-y-4">
        <div v-if="notice" class="sd-alert" :class="toneClass(notice.tone)" role="status">
            <p class="min-w-0">{{ notice.text }}</p>

            <!-- The spec's duplicate case: not a dead end, but a choice
                 between sending again and cancelling what already exists. -->
            <div v-if="duplicate" class="flex flex-wrap items-center gap-2 mt-2.5">
                <button type="button" :disabled="busyId === duplicate.id" @click="resend(duplicate)"
                        class="h-8 px-3 rounded-md bg-white border border-stroke text-ink text-[13px] font-semibold hover:bg-hover transition-colors disabled:opacity-45">
                    Resend invite
                </button>
                <button type="button" :disabled="busyId === duplicate.id" @click="pendingRevoke = duplicate"
                        class="h-8 px-3 rounded-md text-[13px] font-semibold text-sub hover:text-danger hover:bg-white transition-colors disabled:opacity-45">
                    Cancel invite
                </button>
            </div>
        </div>

        <!-- ------------------------------------------------------ the list -->
        <div v-if="list.length" class="rounded-card border border-line bg-white divide-y divide-line">
            <div v-for="invitation in list" :key="invitation.id" class="flex items-center gap-3 p-4">
                <span class="sd-avatar sd-avatar--md shrink-0" aria-hidden="true">
                    {{ (invitation.first_name[0] + invitation.last_name[0]).toUpperCase() }}
                </span>

                <span class="min-w-0 flex-1">
                    <span class="block text-[13px] font-semibold text-head truncate">{{ invitation.name }}</span>
                    <span class="block text-[12px] text-sub truncate">
                        {{ invitation.email }} &middot; {{ invitation.role_label }}<template v-if="invitation.service_ids?.length"> &middot; {{ invitation.service_ids.length }} service{{ invitation.service_ids.length === 1 ? '' : 's' }}</template>
                    </span>
                </span>

                <span class="shrink-0 px-2 h-6 inline-flex items-center rounded-full text-[11px] font-semibold"
                      :class="statusClass(invitation.status)">
                    {{ invitation.status_label }}
                </span>

                <span v-if="invitation.can_resend" class="shrink-0 flex items-center gap-1">
                    <button type="button" :disabled="busyId === invitation.id" @click="resend(invitation)"
                            class="h-8 px-2.5 rounded-md text-[13px] font-semibold text-sub hover:text-ink hover:bg-hover transition-colors disabled:opacity-45">
                        Resend
                    </button>
                    <button type="button" :disabled="busyId === invitation.id" @click="pendingRevoke = invitation"
                            class="h-8 px-2.5 rounded-md text-[13px] font-semibold text-sub hover:text-danger hover:bg-hover transition-colors disabled:opacity-45">
                        Cancel
                    </button>
                </span>
            </div>
        </div>

        <p v-else class="text-[13px] text-sub">
            No one invited yet. You can invite the rest of your team whenever you like.
        </p>

        <!-- ----------------------------------------------------- the form -->
        <div class="rounded-card border border-line bg-white p-4">
            <p class="text-[13px] font-semibold text-head mb-3">Invite someone</p>

            <div class="grid sm:grid-cols-2 gap-x-4 gap-y-4">
                <div>
                    <label for="invite-first" class="block text-[13px] font-medium text-ink mb-1.5">First name</label>
                    <input id="invite-first" v-model="form.first_name" type="text" class="sd-input" v-capitalize>
                    <p v-if="errors.first_name" class="mt-1.5 text-[12px] text-danger">{{ errors.first_name[0] }}</p>
                </div>
                <div>
                    <label for="invite-last" class="block text-[13px] font-medium text-ink mb-1.5">Last name</label>
                    <input id="invite-last" v-model="form.last_name" type="text" class="sd-input" v-capitalize>
                    <p v-if="errors.last_name" class="mt-1.5 text-[12px] text-danger">{{ errors.last_name[0] }}</p>
                </div>
                <div>
                    <label for="invite-email" class="block text-[13px] font-medium text-ink mb-1.5">Email</label>
                    <input id="invite-email" v-model="form.email" type="email" class="sd-input"
                           placeholder="amelia@example.com">
                    <p v-if="errors.email" class="mt-1.5 text-[12px] text-danger">{{ errors.email[0] }}</p>
                    <p v-else class="mt-1.5 text-[12px] text-sub">The invitation is sent here as soon as you press Send.</p>
                </div>
                <div>
                    <label for="invite-title" class="block text-[13px] font-medium text-ink mb-1.5">
                        Job title <span class="text-faint font-normal">(optional)</span>
                    </label>
                    <input id="invite-title" v-model="form.job_title" type="text" class="sd-input" v-capitalize
                           placeholder="Senior Stylist">
                </div>
                <div>
                    <span class="block text-[13px] font-medium text-ink mb-1.5">Role</span>
                    <MultiSelect :options="ROLES" :model-value="[form.role]" single
                                 placeholder="Select a role" search-placeholder="Search roles…" aria-label="Role"
                                 @update:model-value="(v) => { form.role = v[0] ?? 'service-provider'; }" />
                    <p v-if="errors.role" class="mt-1.5 text-[12px] text-danger">{{ errors.role[0] }}</p>
                </div>
                <div v-if="locations.length">
                    <span class="block text-[13px] font-medium text-ink mb-1.5">Location</span>
                    <MultiSelect :options="LOCATION_OPTIONS" :model-value="[form.location_id]" single
                                 placeholder="All locations" search-placeholder="Search locations…" aria-label="Location"
                                 @update:model-value="(v) => { form.location_id = v[0] ?? ''; }" />
                </div>
                <div v-if="services.length" class="sm:col-span-2">
                    <span class="block text-[13px] font-medium text-ink mb-1.5">
                        Services they provide <span class="text-faint font-normal">(optional)</span>
                    </span>
                    <!-- Multi, so this keeps its checkboxes: more than one can
                         genuinely be ticked. showPrimary is off because no
                         service outranks another. -->
                    <MultiSelect :options="SERVICE_OPTIONS" v-model="form.service_ids" :show-primary="false"
                                 placeholder="Not bookable for anything yet"
                                 search-placeholder="Search services…" aria-label="Services they provide" />
                    <p class="mt-1.5 text-[12px] text-sub">
                        Anyone assigned services becomes bookable, so clients can pick them by name.
                    </p>
                </div>

                <div class="sm:col-span-2">
                    <label for="invite-message" class="block text-[13px] font-medium text-ink mb-1.5">
                        Message <span class="text-faint font-normal">(optional)</span>
                    </label>
                    <textarea id="invite-message" v-model="form.message" rows="2" class="sd-input"
                              placeholder="Looking forward to having you on the team."></textarea>
                    <p v-if="errors.message" class="mt-1.5 text-[12px] text-danger">{{ errors.message[0] }}</p>
                </div>
            </div>

            <!-- type="button": this island sits inside the step's form, and a
                 default submit button would post the step instead of sending
                 the invitation. -->
            <div class="mt-4 flex justify-end">
                <button type="button" :disabled="sending" @click="send"
                        class="h-10 px-4 rounded-lg bg-brand hover:bg-brand-dark text-white text-[13px] font-semibold transition-colors disabled:opacity-45 disabled:pointer-events-none">
                    {{ sending ? 'Sending…' : 'Send invitation' }}
                </button>
            </div>
        </div>

        <ConfirmDialog :open="pendingRevoke !== null" title="Cancel this invitation?"
                       :message="revokeMessage" confirm-label="Cancel invite" cancel-label="Keep it"
                       @confirm="revokeConfirmed" @cancel="pendingRevoke = null" />
    </div>
</template>
