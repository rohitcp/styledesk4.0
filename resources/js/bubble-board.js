import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { forceCollide, forceSimulation, forceX, forceY } from 'd3-force';
import { scaleSqrt } from 'd3-scale';

/**
 * A cluster of circles, one per thing, sized by a percentage.
 *
 * The utilization picture: which of these is earning its keep, and which is
 * standing idle, answered in a glance rather than read row by row. Written
 * once and shared, because resource utilization and staff utilization are the
 * same question asked of different nouns — and two copies of this would
 * eventually be two pictures that disagree about what 40% looks like.
 *
 * Bubble AREA is the percentage, not its radius: a 60% room drawn with twice
 * the radius of a 30% one looks four times as busy, which is the oldest way
 * to mislead with a chart. `scaleSqrt` is the fix, and is why it is here.
 *
 * D3 computes the layout; the caller's template draws it. Letting
 * d3-selection own the DOM as well would mean two renderers on one page.
 *
 * @param {object}   options
 * @param {import('vue').ComputedRef<Array>} options.visible  the rows to draw
 * @param {(row: object) => string} options.metaOf   the line under the name
 * @param {(row: object) => string} options.tintOf   which of the nine tints
 * @param {number}  [options.boost]  size multiplier, for a board that wants
 *                                   bigger circles than the default
 */
export function useBubbleBoard({ visible, metaOf, tintOf, boost = 1 }) {
    const board = ref(null);
    const width = ref(920);
    const height = ref(520);
    const nodes = ref([]);

    /* Below this the cluster stops being readable however it is scaled, so
       the caller replaces the board rather than shrinking it. */
    const narrow = ref(false);

    /* The one under the pointer. */
    const hovered = ref(null);

    let simulation = null;
    let observer = null;

    /**
     * Painting order.
     *
     * SVG has no z-index: what is drawn last is on top. The hovered bubble is
     * moved to the end so its lift and shadow sit above its neighbours
     * instead of being clipped by whichever circle happened to come after it.
     */
    const painted = computed(() => {
        if (hovered.value === null) {
            return nodes.value;
        }

        return [
            ...nodes.value.filter((node) => node.id !== hovered.value),
            ...nodes.value.filter((node) => node.id === hovered.value),
        ];
    });

    /**
     * How big each circle is drawn.
     *
     * The three bands the design asks for — roughly 110px, 180px and 250px
     * across — with a square-root scale between them so the AREA carries the
     * percentage.
     *
     * Both ends are clamped on purpose. Something at 95% must not swamp
     * something at 40%, and one at 4% still has a name to show: a dot nobody
     * can read is a row the owner cannot click.
     *
     * The ceiling comes down as the board fills or narrows, so twenty
     * circles on a tablet still make a cluster rather than a pile.
     */
    function scaleFor(boardWidth, count) {
        /* 145 is a 290px bubble — the top of the design's large band with a
           little room over it; 88 keeps a crowded board from needing a
           scroll. */
        const ceiling = Math.max(88, Math.min(145, Math.sqrt((boardWidth * 620) / (count * 4.6)))) * boost;

        /* The floor, not the ceiling, is what usually decides how big this
           board reads: real numbers cluster low, so most circles sit at the
           bottom of the scale and a mean floor makes the whole picture
           small. */
        const floor = Math.max(80 * boost, ceiling * 0.56);

        return scaleSqrt().domain([0, 100]).range([floor, ceiling]);
    }

    /**
     * Type sized from the circle, within the bands the design sets.
     *
     * Derived rather than fixed because the circles are responsive: a large
     * bubble on a tablet is a medium bubble on a desktop, and the percentage
     * should stay the hero at either size.
     */
    const typeFor = (r) => ({
        value: Math.round(Math.min(64, Math.max(28, r * 0.46))),
        name: Math.round(Math.min(24, Math.max(14, r * 0.17))),
        meta: Math.round(Math.min(16, Math.max(13, r * 0.115))),
        chip: Math.round(Math.min(12, Math.max(9, r * 0.085))),
    });

    /**
     * Where each line sits, measured down from the circle's middle.
     *
     * The gap between the percentage and the name is a share of the
     * percentage's own size, so it grows with the circle instead of being a
     * fixed number that is generous on a large bubble and cramped on a small
     * one. At less than a full percentage-height the two read as one block of
     * text rather than as a headline and the thing it names.
     */
    function linesFor(node) {
        const { value, name, meta, chip } = node.type;
        const room = roomFor(node);

        const valueY = room.name ? -value * 0.36 : value * 0.02;
        const nameY = valueY + value * 1.0;
        const metaY = nameY + (node.lines.length - 1) * name * 1.18 + meta * 1.9;
        const chipY = (room.hours ? metaY + meta * 0.9 : nameY + (node.lines.length - 1) * name * 1.18 + chip * 1.2);

        return { valueY, nameY, metaY, chipY };
    }

    /**
     * What a circle of this size can hold, in the order the design sets.
     *
     * Priority is percentage, then name, then the meta line, then the chip —
     * and a bubble that runs out of room drops them in reverse. What it never
     * drops is the percentage, which is the answer.
     */
    const roomFor = (node) => ({
        name: node.r >= 46,
        hours: node.r >= 72,
        chip: node.r >= 86,
    });

    /**
     * How many characters fit across the circle at a given height.
     *
     * Not the diameter but the chord: a line two thirds of the way down has
     * noticeably less room than one through the middle.
     */
    function charsFor(node, fontSize, offset) {
        const halfChord = node.r * Math.sqrt(Math.max(0.04, 1 - offset * offset));

        /* 0.55em is about the average character in this face; the 0.86 is the
           breathing room that keeps a label off the circle's edge. */
        return Math.floor((halfChord * 2 * 0.86) / (fontSize * 0.55));
    }

    const clip = (text, room) => (text.length <= room
        ? text
        : (room <= 1 ? '' : `${text.slice(0, room - 1).trimEnd()}…`));

    /**
     * The name over at most two lines.
     *
     * SVG text neither wraps nor clips, so a long name in a circle that fits
     * nine characters simply draws across its neighbour. Broken on a space
     * where that gets both halves inside the chord, cut with an ellipsis
     * where it does not — and never shrunk below the design's floor, because
     * a label too small to read is worse than a shortened one.
     */
    function nameLines(node, fontSize) {
        const full = String(node.name ?? '').trim();
        const room = charsFor(node, fontSize, 0.3);

        if (full.length <= room) {
            return [full];
        }

        /* Broken as late as the first line allows, so "Single Room 01" splits
           after "Room" rather than after "Single". */
        let first = '';

        for (const word of full.split(/\s+/)) {
            const next = first ? `${first} ${word}` : word;

            if (next.length > room) {
                break;
            }

            first = next;
        }

        if (! first) {
            return [clip(full, room)];
        }

        const rest = full.slice(first.length).trim();

        return rest ? [first, clip(rest, charsFor(node, fontSize, 0.52))] : [first];
    }

    /**
     * The chip: a rounded rect sized to the label it carries.
     *
     * Cut to the chord at the height it sits, like every other line in the
     * circle — a twenty-character category would otherwise draw a pill wider
     * than the bubble holding it.
     */
    function pillFor(node, fontSize, y) {
        const room = charsFor(node, fontSize, Math.min(0.94, y / node.r));
        const label = clip(String(node.category ?? '').toUpperCase(), Math.max(0, room - 2));

        return {
            label,
            width: label.length * fontSize * 0.68 + fontSize * 1.6,
            height: fontSize * 2.1,
        };
    }

    function layout() {
        const rows = visible.value ?? [];
        const count = Math.max(rows.length, 1);
        const scale = scaleFor(width.value, count);

        /* The board is sized to the circles rather than the circles to the
           board. A fixed ratio of the width left a business whose numbers are
           all low — so every bubble at the floor size — sitting in half a
           screen of white space, with the table pushed below the fold.

           Circles packed loosely fill a little over half the square they sit
           in, so the cluster's likely diameter is twice the root of the
           summed squared radii over that fraction. Clamped: never so short
           the bubbles are cramped, never so tall the list underneath needs
           hunting for. */
        const packed = rows.reduce((total, row) => total + scale(row.utilization) ** 2, 0);

        const needed = 2 * Math.sqrt(packed / 0.55) + 72;

        height.value = Math.round(Math.min(560 * boost, Math.max(360, needed)));

        /* And where the cluster wants more room than the panel will give it,
           the circles come down to fit rather than being squeezed into each
           other.

           Without this the containment clamp — which holds every circle
           inside the board on every tick — wins the argument with the
           collision force, and the result is a board of overlapping discs.
           That is the one thing this picture must never do: two circles of
           the same tint touching read as one shape, and a percentage drawn
           over another percentage is unreadable. */
        const shrink = needed > height.value ? height.value / needed : 1;

        /* Kept by id across a re-layout, so a circle that survives a filter
           change glides from where it was rather than being born at the
           centre and flying out. That continuity is the whole reason the
           chips feel like filtering one picture rather than loading
           another. */
        const previous = new Map(nodes.value.map((node) => [node.id, node]));

        /* Everything a circle needs to draw itself, worked out once here
           rather than in the template: the simulation re-renders on every
           tick, and measuring text sixty times a second is sixty times too
           often. */
        nodes.value = rows.map((row) => {
            const was = previous.get(row.id);
            const r = scale(row.utilization) * shrink;
            const type = typeFor(r);
            const node = { ...row, r, type, tint: tintOf(row) };

            node.lines = roomFor(node).name ? nameLines(node, type.name) : [];
            node.hoursLabel = metaOf(row);
            node.at = linesFor(node);
            /* After the positions, because the pill is cut to the chord at
               the height it ends up sitting. */
            node.pill = pillFor(node, type.chip, node.at.chipY + type.chip);

            return {
                ...node,
                x: was?.x ?? width.value / 2 + (Math.random() - 0.5) * 80,
                y: was?.y ?? height.value / 2 + (Math.random() - 0.5) * 80,
            };
        });

        /**
         * Solved here and now, not over sixty frames.
         *
         * d3's own runner is driven by requestAnimationFrame, which a browser
         * does not call in a background tab — a page opened in one came up
         * with every circle piled at the centre and stayed that way until the
         * tab was focused. Ticking it synchronously puts the circles in their
         * final places immediately, and the CSS transition on the group does
         * the gliding when there is a screen to glide on.
         *
         * It is also cheaper: the animated version re-rendered every node on
         * every frame to move them a pixel each.
         */
        simulation?.stop();

        simulation = forceSimulation(nodes.value)
            /* Pulled gently to the middle and pushed apart harder: that ratio
               is what makes a cluster rather than a grid or a scatter. */
            .force('x', forceX(width.value / 2).strength(0.05))
            .force('y', forceY(height.value / 2).strength(0.07))
            /* The padding is the gap between circles, and it is what keeps
               them legible rather than merely apart: touching discs of the
               same tint read as one shape. */
            .force('collide', forceCollide((node) => node.r + 11).strength(0.95).iterations(4))
            .stop();

        /* Held inside the board on every pass, not squeezed in at the end.
           Clamping once after the simulation had settled pushed circles off
           the edge and straight into their neighbours, and forty tidy-up
           ticks could not take the overlap back out — the board is wider than
           it is tall, so the cluster has to be given the chance to spread
           sideways WHILE the collision force is still working. */
        const contain = () => nodes.value.forEach((node) => {
            node.x = Math.max(node.r + 4, Math.min(width.value - node.r - 4, node.x));
            node.y = Math.max(node.r + 4, Math.min(height.value - node.r - 4, node.y));
        });

        for (let step = 0; step < 360; step++) {
            simulation.tick();
            contain();
        }

        nodes.value = [...nodes.value];
    }

    function measure() {
        if (! board.value) {
            return;
        }

        const box = board.value.getBoundingClientRect();

        width.value = Math.max(320, Math.round(box.width));
        narrow.value = box.width < 560;

        /* The height follows from the bubbles, so it is layout()'s to set. */
        layout();
    }

    /* A filter only ever removes or restores circles, so it re-lays out in
       the browser: asking the server again would blank the board for a round
       trip to answer a question it has already answered. */
    watch(visible, () => layout());

    onMounted(() => {
        measure();

        /* Re-laid out on resize rather than on a breakpoint: the board's
           width depends on the nav drawer as well as the window. */
        observer = new ResizeObserver(() => measure());
        observer.observe(board.value);
    });

    onBeforeUnmount(() => {
        simulation?.stop();
        observer?.disconnect();
    });

    return { board, width, height, nodes, painted, hovered, narrow, measure, layout, roomFor };
}
