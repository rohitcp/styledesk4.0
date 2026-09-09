/**
 * The dashboard's side column.
 *
 * Three panels, one at a time. All three are already on the page — they are
 * a glance rather than a page load, and fetching one on each press would put
 * a spinner where a receptionist expects an answer.
 */
export function initDashboardTabs(root = document) {
    root.querySelectorAll('[data-dashboard-side]').forEach((column) => {
        const tabs = Array.from(column.querySelectorAll('[data-side-tab]'));

        if (tabs.length === 0) {
            return;
        }

        function show(key) {
            tabs.forEach((tab) => {
                const on = tab.dataset.sideTab === key;

                tab.setAttribute('aria-selected', on ? 'true' : 'false');
                tab.classList.toggle('border-brand', on);
                tab.classList.toggle('text-brand', on);
                tab.classList.toggle('border-transparent', !on);
                tab.classList.toggle('text-sub', !on);
            });

            column.querySelectorAll('[data-side-panel]').forEach((panel) => {
                panel.hidden = panel.dataset.sidePanel !== key;
            });
        }

        tabs.forEach((tab) => {
            tab.addEventListener('click', () => show(tab.dataset.sideTab));
        });
    });
}
