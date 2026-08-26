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
        createApp(component, readProps(el)).mount(el);
    });
}

document.addEventListener('DOMContentLoaded', () => mountVueIslands());
