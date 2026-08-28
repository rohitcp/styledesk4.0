/**
 * Dropdowns marked `data-combo` become the design system's combo: a styled
 * button and a popup list, matching every other dropdown in the app rather
 * than falling back to the browser's own select rendering, which differs on
 * every platform. Each field carries its own options in data-combo-options.
 */
export function initCombos(root = document) {
    if (!window.SD || typeof window.SD.combo !== 'function') {
        return;
    }

    root.querySelectorAll('select[data-combo]').forEach((select) => {
        let options = {};

        if (select.dataset.comboOptions) {
            try {
                options = JSON.parse(select.dataset.comboOptions);
            } catch (error) {
                console.error('[styledesk] Invalid data-combo-options JSON.', select, error);
            }
        }

        window.SD.combo(select, options);
    });
}
