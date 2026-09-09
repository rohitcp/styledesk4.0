/**
 * The row and card action menus.
 *
 * One open at a time, positioned against its button because the panel is
 * fixed, and closed by a scroll it cannot follow.
 *
 * Shared rather than repeated. Three screens carried their own copy of this —
 * the client profile, the locations directory, the staff directory — and a
 * fourth was about to. Copies of a menu drift: one of them flips above when
 * there is no room below and another does not, and nobody notices until a
 * card in the last row opens its menu into the fold.
 *
 * The panel is `position: fixed`, which is what lets it escape a card with
 * `overflow: hidden` — and what means it has to be placed in viewport
 * coordinates rather than left to the flow.
 */
export function initRowMenus(root = document) {
    const menus = Array.from(root.querySelectorAll('[data-rowmenu]'));

    if (!menus.length) {
        return;
    }

    function closeAll(except) {
        menus.forEach((menu) => {
            if (menu === except) {
                return;
            }

            const pop = menu.querySelector('[data-rowmenu-pop]');
            const button = menu.querySelector('[data-rowmenu-button]');

            if (pop) {
                pop.hidden = true;
            }

            button?.setAttribute('aria-expanded', 'false');
            menu.classList.remove('is-open');
        });
    }

    function place(button, pop) {
        const rect = button.getBoundingClientRect();

        pop.style.visibility = 'hidden';
        const height = pop.offsetHeight;
        const width = pop.offsetWidth;
        pop.style.visibility = '';

        /* Flipped above when there is no room below, so a row at the bottom
           of the page does not open its menu into the fold. */
        const below = window.innerHeight - rect.bottom;
        const top = below < height + 12 ? rect.top - height - 4 : rect.bottom + 4;

        pop.style.top = `${Math.max(8, top)}px`;
        pop.style.left = `${Math.max(8, rect.right - width)}px`;
    }

    menus.forEach((menu) => {
        const button = menu.querySelector('[data-rowmenu-button]');
        const pop = menu.querySelector('[data-rowmenu-pop]');

        if (!button || !pop) {
            return;
        }

        button.addEventListener('click', (event) => {
            /* Several of these sit inside something that is itself a link or
               a row that navigates. Without this the menu button opens the
               record it belongs to instead of its own menu. */
            event.preventDefault();
            event.stopPropagation();

            const opening = pop.hidden;

            closeAll(menu);
            pop.hidden = !opening;
            button.setAttribute('aria-expanded', opening ? 'true' : 'false');
            menu.classList.toggle('is-open', opening);

            if (opening) {
                place(button, pop);
            }
        });

        /*
         * A click on the panel itself goes no further; a click on something
         * in it does.
         *
         * The panel sits inside cards and rows that navigate, so a stray
         * click on its padding must not fall through to them. Stopping every
         * click was the obvious version and was wrong: menu items never
         * reached the document, so a page that binds them by delegation — as
         * both settings catalogues do — saw nothing happen at all, and the
         * menu looked dead.
         */
        pop.addEventListener('click', (event) => {
            if (!event.target.closest('a[href], button')) {
                event.stopPropagation();
            }
        });
    });

    document.addEventListener('click', () => closeAll(null));
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeAll(null);
        }
    });
    window.addEventListener('scroll', () => closeAll(null), true);
    window.addEventListener('resize', () => closeAll(null));
}
