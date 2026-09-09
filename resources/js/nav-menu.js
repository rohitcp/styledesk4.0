/**
 * The top navigation's dropdown menus.
 *
 * Hover to open on a pointer, click to open on anything else. Both, rather
 * than either: hover alone leaves a touch device with a menu it can never
 * see, and click alone makes a reader press twice to reach a page the pointer
 * was already resting on.
 *
 * The panel sits flush against the bar with no gap for the pointer to fall
 * through, and closing is deliberately delayed — a pointer travelling from
 * the icon to the second item in the list passes over neither for a few
 * milliseconds, and a menu that shut on the first mouseleave shut in exactly
 * that moment.
 */

/** Long enough to cross a corner, short enough not to feel stuck open. */
const CLOSE_DELAY = 160;

export function initNavMenus(root = document) {
    const menus = Array.from(root.querySelectorAll('[data-menu]'));

    if (!menus.length) {
        return;
    }

    const open = (menu) => {
        menus.forEach((other) => {
            if (other !== menu) {
                close(other);
            }
        });

        const pop = menu.querySelector('[data-menu-pop]');
        const trigger = menu.querySelector('[aria-haspopup]');

        if (!pop) {
            return;
        }

        window.clearTimeout(menu.dataset.closeTimer);
        pop.hidden = false;
        menu.classList.add('is-open');
        trigger?.setAttribute('aria-expanded', 'true');
    };

    function close(menu) {
        const pop = menu.querySelector('[data-menu-pop]');
        const trigger = menu.querySelector('[aria-haspopup]');

        window.clearTimeout(menu.dataset.closeTimer);

        if (!pop) {
            return;
        }

        pop.hidden = true;
        menu.classList.remove('is-open');
        trigger?.setAttribute('aria-expanded', 'false');
    }

    /* Held off, so the pointer may leave the icon and arrive in the panel
       without the menu closing in between. Cancelled the moment it enters
       either one again. */
    const closeSoon = (menu) => {
        window.clearTimeout(menu.dataset.closeTimer);
        menu.dataset.closeTimer = window.setTimeout(() => close(menu), CLOSE_DELAY);
    };

    menus.forEach((menu) => {
        const trigger = menu.querySelector('[aria-haspopup]');

        /* The whole menu — the icon and the panel together — is one hover
           region, which is what lets the pointer travel between them. */
        menu.addEventListener('pointerenter', (event) => {
            if (event.pointerType === 'touch') {
                return;
            }

            open(menu);
        });

        menu.addEventListener('pointerleave', (event) => {
            if (event.pointerType === 'touch') {
                return;
            }

            closeSoon(menu);
        });

        /* A touch or a click opens it without following the link: the icon is
           a section rather than a page, and its menu is how the section is
           entered. */
        trigger?.addEventListener('click', (event) => {
            const isOpen = menu.classList.contains('is-open');

            event.preventDefault();
            event.stopPropagation();

            if (isOpen) {
                close(menu);
            } else {
                open(menu);
            }
        });

        /* Keyboard: the menu opens when focus reaches the icon and closes
           when focus leaves the group entirely, so it can be tabbed through
           without a pointer. */
        menu.addEventListener('focusin', () => open(menu));

        menu.addEventListener('focusout', (event) => {
            if (!menu.contains(event.relatedTarget)) {
                close(menu);
            }
        });
    });

    document.addEventListener('click', () => menus.forEach(close));

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            menus.forEach(close);
        }
    });
}
