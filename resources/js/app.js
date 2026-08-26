import './echo';

/**
 * The prototype's shared front-end modules, imported verbatim from
 * html/assets/js. They are IIFEs that publish window.SD and window.SDA, so a
 * side-effect import is all they need.
 *
 * These carry the prototype's own caveat (styledesk.js, top of file): the
 * onboarding draft they keep in localStorage is a prototype store, not the
 * source of truth. Onboarding completion is decided server-side; these
 * modules stay for the UI behaviour they provide, not for state.
 */
import './prototype/styledesk';
import './prototype/branding';
import './prototype/account';

import { capitalizeFirst, initCapitalization } from './capitalize';
import { initPhoneFields } from './phone';
import { createApp } from 'vue';

/**
 * Hybrid Vue usage: Blade owns the page, Vue owns islands within it.
 *
 * Any element carrying `data-vue-component` is booted as its own Vue app, so a
 * Blade view can drop in an interactive widget without becoming an SPA:
 *
 *   <div data-vue-component="BookingCalendar" data-props='@json($props)'></div>
 *
 * Components live in resources/js/components and are resolved lazily, so a page
 * only downloads the islands it actually renders.
 */
const components = import.meta.glob('./components/**/*.vue');

function resolveComponent(name) {
    const path = `./components/${name}.vue`;

    if (!components[path]) {
        console.error(`[styledesk] Unknown Vue component "${name}" (looked for ${path}).`);

        return null;
    }

    return components[path]().then((module) => module.default);
}

function readProps(el) {
    if (!el.dataset.props) {
        return {};
    }

    try {
        return JSON.parse(el.dataset.props);
    } catch (error) {
        console.error('[styledesk] Invalid data-props JSON on Vue island.', el, error);

        return {};
    }
}

export function mountVueIslands(root = document) {
    root.querySelectorAll('[data-vue-component]').forEach(async (el) => {
        if (el.dataset.vueMounted === 'true') {
            return;
        }

        const component = await resolveComponent(el.dataset.vueComponent);

        if (!component) {
            return;
        }

        el.dataset.vueMounted = 'true';

        const app = createApp(component, readProps(el));

        /**
         * v-capitalize: the project capitalisation rule for Vue-bound inputs.
         *
         * The document-level data-capitalize handler cannot work here. It
         * rewrites the DOM value, but v-model has already read the raw value
         * into component state, and the next render puts the raw value back —
         * so the field appears to un-capitalise itself as you type. Dispatching
         * an input event after the change is what lets v-model see it.
         *
         * The re-dispatch cannot loop: capitalising an already-capitalised
         * value returns it unchanged and the guard below exits.
         */
        app.directive('capitalize', {
            mounted(el) {
                el.addEventListener('input', () => {
                    const next = capitalizeFirst(el.value);

                    if (next === el.value) {
                        return;
                    }

                    const start = el.selectionStart;
                    const end = el.selectionEnd;

                    el.value = next;
                    el.dispatchEvent(new Event('input'));

                    if (el.setSelectionRange && start !== null) {
                        el.setSelectionRange(start, end);
                    }
                });
            },
        });

        app.mount(el);
    });
}

document.addEventListener('DOMContentLoaded', () => {
    mountVueIslands();
    initPhoneFields();
    initCapitalization();
});

/**
 * Password visibility toggles.
 *
 * The icon reflects the field's current state rather than the action: while
 * the password is masked the eye-off icon is shown, and it becomes a plain eye
 * once the characters are readable. The accessible name says the opposite,
 * because a button's label should describe what pressing it does.
 *
 * Delegated from the document so fields added after load — a Vue island, a
 * re-rendered form — are covered without re-binding.
 */
document.addEventListener('click', (event) => {
    const button = event.target.closest('[data-password-toggle]');

    if (!button) {
        return;
    }

    const field = document.getElementById(button.getAttribute('data-password-toggle'));

    if (!field) {
        return;
    }

    const willReveal = field.type === 'password';
    field.type = willReveal ? 'text' : 'password';

    button.setAttribute('aria-pressed', String(willReveal));
    button.setAttribute('aria-label', willReveal ? 'Hide password' : 'Show password');

    const hiddenIcon = button.querySelector('[data-icon-hidden]');
    const visibleIcon = button.querySelector('[data-icon-visible]');

    if (hiddenIcon && visibleIcon) {
        // toggleAttribute, not .hidden: `hidden` is an IDL property of
        // HTMLElement, and these are SVG elements. Assigning svg.hidden only
        // creates a JS expando — it never writes the attribute the CSS keys
        // on, so the icons would silently never swap.
        hiddenIcon.toggleAttribute('hidden', willReveal);
        visibleIcon.toggleAttribute('hidden', !willReveal);
    }
});
