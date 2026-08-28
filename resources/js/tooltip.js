/**
 * Tooltips that escape their container.
 *
 * The CSS tooltip drew itself inside the element it belonged to, which meant
 * two failures the moment a control sat near an edge: a column with
 * `overflow: hidden` clipped it away entirely, and one near the right of the
 * window pushed past the viewport.
 *
 * This puts a single node on the body and positions it against whichever
 * control is being pointed at, flipping above and clamping sideways when
 * there is no room. One node rather than one per control, because a tooltip
 * is only ever shown for one thing at a time.
 */
const GAP = 8;

let tip = null;
let current = null;

function node() {
    if (!tip) {
        tip = document.createElement('div');
        tip.className = 'styledesk_tooltip';
        /* Decoration. The control keeps its own accessible name — an
           aria-label, or its visible text — so a screen reader is told what
           the button is without also being told about a bubble it cannot
           see. */
        tip.setAttribute('aria-hidden', 'true');
        tip.hidden = true;
        document.body.appendChild(tip);
    }

    return tip;
}

function show(trigger) {
    const text = trigger.getAttribute('data-tip');

    if (!text) {
        return;
    }

    current = trigger;

    const el = node();
    el.textContent = text;
    el.hidden = false;
    el.classList.remove('is-above');

    const anchor = trigger.getBoundingClientRect();
    const box = el.getBoundingClientRect();

    /* Beside the control rather than under it, where the control is asked to
       be. The navigation icons are: their menus now open flush against the
       bottom of the bar, so a hint drawn below the icon lands on top of the
       menu it is describing. */
    if (trigger.dataset.tipPlacement === 'right') {
        placeBeside(el, anchor, box);

        return;
    }

    // Below by default, above when the space beneath is not enough.
    const below = window.innerHeight - anchor.bottom;
    const above = below < box.height + GAP && anchor.top > box.height + GAP;

    el.classList.toggle('is-above', above);
    el.style.top = `${above ? anchor.top - box.height - GAP : anchor.bottom + GAP}px`;

    /* Centred on the control, then pulled back inside the window. A tooltip
       that hangs off the edge is worse than one that is not quite centred. */
    const centred = anchor.left + anchor.width / 2 - box.width / 2;
    const clamped = Math.min(Math.max(GAP, centred), window.innerWidth - box.width - GAP);

    el.style.left = `${clamped}px`;
}

/**
 * To the right of the control, or to its left when the window has run out.
 *
 * The same flip the vertical placement makes, in the other axis: the last
 * icon in a bar is the one with nothing to its right, and it is exactly the
 * one a tooltip would otherwise disappear off the edge beside.
 */
function placeBeside(el, anchor, box) {
    const room = window.innerWidth - anchor.right;
    const flip = room < box.width + GAP && anchor.left > box.width + GAP;

    el.style.left = `${flip ? anchor.left - box.width - GAP : anchor.right + GAP}px`;

    /* Centred against the icon, then held inside the window — a hint level
       with nothing is a hint pointing at nothing. */
    const centred = anchor.top + anchor.height / 2 - box.height / 2;

    el.style.top = `${Math.min(Math.max(GAP, centred), window.innerHeight - box.height - GAP)}px`;
}

function hide() {
    current = null;

    if (tip) {
        tip.hidden = true;
    }
}

export function initTooltips(root = document) {
    // Delegated, so a control drawn later — a grid row, a cloned form row —
    // is covered without being registered.
    root.addEventListener('pointerover', (event) => {
        const trigger = event.target.closest('[data-tip]');

        if (trigger && trigger !== current) {
            show(trigger);
        }
    });

    root.addEventListener('pointerout', (event) => {
        const trigger = event.target.closest('[data-tip]');

        if (trigger && !trigger.contains(event.relatedTarget)) {
            hide();
        }
    });

    // Keyboard users get the same tooltip on focus.
    root.addEventListener('focusin', (event) => {
        const trigger = event.target.closest('[data-tip]');

        if (trigger) {
            show(trigger);
        }
    });

    root.addEventListener('focusout', hide);

    /* One hint at a time. Clicking an icon-only control usually opens
       something — a menu, a modal — and a tooltip left hovering over it
       describes a button the reader has already used. */
    root.addEventListener('click', hide, true);

    // A tooltip positioned against something that has moved is a tooltip
    // pointing at nothing.
    window.addEventListener('scroll', hide, true);
    window.addEventListener('resize', hide);
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            hide();
        }
    });
}
