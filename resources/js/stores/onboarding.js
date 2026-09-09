import { reactive } from 'vue';

/**
 * Shared state for the onboarding wizard's Vue islands.
 *
 * The rows live in one column and the preview that mirrors them lives in the
 * other, and they are separate Vue apps mounted on separate DOM subtrees —
 * props cannot cross between them. A module-level reactive object is the
 * simplest thing that does: the repeater writes rows into it and the preview
 * renders from it, with no event bus and no duplicated state.
 */
export const onboarding = reactive({
    services: [],
    team: [],
});
