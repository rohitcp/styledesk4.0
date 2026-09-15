<script setup>
/**
 * Two sets of photographs, taken either side of a treatment.
 *
 * One component rather than two upload fields, because the pair IS the
 * record: a business that had to add "Before" and "After" separately would
 * end up with forms where one side is named differently from the other, and
 * nothing downstream could tell which photograph belonged to which side.
 *
 * Nothing is uploaded from here. The files are held in the browser and shown
 * back, which is all a preview can honestly do — there is no submission
 * endpoint yet, and inventing one that threw the files away would be worse
 * than saying so.
 *
 * Object URLs are revoked when a file is dropped or the component goes away.
 * A page where somebody adds and removes twenty photographs otherwise holds
 * every one of them in memory until it is closed.
 */
import { computed, onBeforeUnmount, ref } from 'vue';

const props = defineProps({
    field: { type: Object, required: true },
    labels: { type: Object, default: () => ({}) },
    types: { type: Array, default: () => ['jpg', 'jpeg', 'png', 'webp'] },
    maxKb: { type: Number, default: 5120 },
    maxFiles: { type: Number, default: 10 },
    controlStyle: { type: Object, default: () => ({}) },
    /** { before: 'message', after: 'message' } from the form's own check. */
    errors: { type: Object, default: () => ({}) },
});

const emit = defineEmits(['update:files']);

const t = (path, fallback = '') =>
    path.split('.').reduce((carry, key) => carry?.[key], props.labels) ?? fallback;

/** Each side holds its own list, because each side is its own answer. */
const held = ref({ before: [], after: [] });
const complaint = ref({ before: '', after: '' });
const dragging = ref('');

const allowed = computed(() => (props.field.file_types?.length ? props.field.file_types : props.types));
const accept = computed(() => allowed.value.map((type) => `.${type}`).join(','));

const capOf = (side) => (side === 'before' ? props.field.max_before : props.field.max_after) || props.maxFiles;
const kbCap = computed(() => props.field.max_kb || props.maxKb);

const labelOf = (side) => (side === 'before'
    ? (props.field.before_label || t('builder.before'))
    : (props.field.after_label || t('builder.after')));

const helpOf = (side) => (side === 'before' ? props.field.before_help : props.field.after_help);

const say = (side, message) => { complaint.value = { ...complaint.value, [side]: message }; };

/**
 * Take what fits, and say what did not.
 *
 * Refused one at a time rather than rejecting the whole drop: somebody who
 * drags in twelve photographs when ten are allowed should end up with ten,
 * not with none and a message.
 */
function add(side, files) {
    const cap = capOf(side);
    const room = cap - held.value[side].length;

    if (room <= 0) {
        say(side, t('builder.too_many').replace(':max', String(cap)));

        return;
    }

    let refusedType = false;
    let refusedSize = false;
    const taking = [];

    for (const file of files) {
        if (taking.length >= room) {
            say(side, t('builder.too_many').replace(':max', String(cap)));
            break;
        }

        const extension = file.name.split('.').pop()?.toLowerCase() ?? '';

        if (!allowed.value.includes(extension)) {
            refusedType = true;
            continue;
        }

        if (file.size > kbCap.value * 1024) {
            refusedSize = true;
            continue;
        }

        taking.push({
            name: file.name,
            size: file.size,
            /* An image can be shown back; a PDF is a name and an icon. */
            url: file.type.startsWith('image/') ? URL.createObjectURL(file) : null,
            file,
        });
    }

    if (refusedType) say(side, t('builder.wrong_type').replace(':list', allowed.value.join(', ')));
    else if (refusedSize) {
        say(side, t('builder.too_large').replace(':size', String(Math.round(kbCap.value / 1024))));
    } else if (taking.length) say(side, '');

    held.value = { ...held.value, [side]: [...held.value[side], ...taking] };
    emit('update:files', held.value);
}

function remove(side, index) {
    const [gone] = held.value[side].splice(index, 1);

    if (gone?.url) URL.revokeObjectURL(gone.url);

    held.value = { ...held.value };
    say(side, '');
    emit('update:files', held.value);
}

/** Reordered, because "first" and "last" mean something in a set of photos. */
function move(side, index, by) {
    const list = held.value[side];
    const to = index + by;

    if (to < 0 || to >= list.length) return;

    const [item] = list.splice(index, 1);
    list.splice(to, 0, item);
    held.value = { ...held.value };
    emit('update:files', held.value);
}

function onDrop(side, event) {
    event.preventDefault();
    dragging.value = '';
    add(side, [...(event.dataTransfer?.files ?? [])]);
}

onBeforeUnmount(() => {
    Object.values(held.value).flat().forEach((item) => {
        if (item.url) URL.revokeObjectURL(item.url);
    });
});
</script>

<template>
  <div class="space-y-4">
    <section v-for="side in ['before', 'after']" :key="side">
      <div class="flex items-baseline justify-between gap-2">
        <p class="text-[12px] font-semibold uppercase tracking-wide text-sub">
          {{ labelOf(side) }}
          <span v-if="side === 'before' ? field.before_required : field.after_required" class="text-danger">*</span>
        </p>
        <p class="text-[12px] text-faint shrink-0">
          {{ t('builder.files_counted').replace(':count', String(held[side].length)).replace(':max', String(capOf(side))) }}
        </p>
      </div>

      <p v-if="helpOf(side)" class="text-[12px] text-sub mt-0.5">{{ helpOf(side) }}</p>

      <!-- Dropped, or chosen. Both, because a member of staff on a tablet
           cannot drag anything and somebody at a desk would rather not open
           a file dialog. -->
      <div class="mt-1.5 rounded-card border border-dashed p-3 transition-colors"
           :class="dragging === side ? 'border-brand bg-brand/5' : 'border-line'"
           :style="{ borderRadius: controlStyle.borderRadius }"
           @dragover.prevent="dragging = side" @dragleave="dragging = ''" @drop="onDrop(side, $event)">
        <p class="text-[12px] text-sub">{{ t('builder.upload_hint_drag') }}</p>

        <div class="mt-2 flex flex-wrap gap-2">
          <!-- `capture` asks the phone for its camera. Its own input, because
               a single control cannot both open the camera and offer the
               library — and staff photographing a treatment want the camera
               in one press. -->
          <label class="styledesk_action !h-8 !px-2.5 !text-[12px] cursor-pointer sm:hidden">
            <input type="file" class="sr-only" multiple capture="environment" :accept="accept"
                   @change="add(side, [...$event.target.files]); $event.target.value = ''">
            {{ t('builder.take_photo') }}
          </label>

          <label class="styledesk_action !h-8 !px-2.5 !text-[12px] cursor-pointer">
            <input type="file" class="sr-only" multiple :accept="accept"
                   @change="add(side, [...$event.target.files]); $event.target.value = ''">
            {{ side === 'before' ? t('builder.upload_before') : t('builder.upload_after') }}
          </label>
        </div>

        <!-- What was added, in the order it will be read. -->
        <div v-if="held[side].length" class="mt-3 flex flex-wrap gap-2">
          <figure v-for="(item, index) in held[side]" :key="item.name + index"
                  class="w-24 rounded-card border border-line bg-white overflow-hidden">
            <img v-if="item.url" :src="item.url" :alt="item.name" class="block w-full h-20 object-cover">
            <div v-else class="h-20 grid place-items-center text-[11px] text-sub px-1 text-center">
              {{ item.name.split('.').pop().toUpperCase() }}
            </div>

            <figcaption class="px-1.5 py-1">
              <p class="text-[11px] text-sub truncate" :title="item.name">{{ item.name }}</p>

              <div class="flex items-center gap-0.5 mt-0.5">
                <button type="button" class="text-[11px] text-sub hover:text-ink px-1 disabled:opacity-40"
                        :disabled="index === 0" :title="t('builder.move_left')"
                        :aria-label="t('builder.move_left')" @click="move(side, index, -1)">‹</button>
                <button type="button" class="text-[11px] text-sub hover:text-ink px-1 disabled:opacity-40"
                        :disabled="index === held[side].length - 1" :title="t('builder.move_right')"
                        :aria-label="t('builder.move_right')" @click="move(side, index, 1)">›</button>
                <button type="button" class="ml-auto text-[11px] text-danger hover:underline"
                        :title="t('builder.remove_file')" @click="remove(side, index)">
                  {{ t('builder.remove_file') }}
                </button>
              </div>
            </figcaption>
          </figure>
        </div>
      </div>

      <p v-if="complaint[side] || errors[side]" class="text-[12px] text-danger mt-1" role="alert">
        {{ complaint[side] || errors[side] }}
      </p>
    </section>
  </div>
</template>
