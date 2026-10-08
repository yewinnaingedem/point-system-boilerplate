/**
 * Remembers layout choices in localStorage (this browser only):
 *  - sidebar collapsed or expanded (header ☰ button), desktop widths only
 *  - which sidebar groups are open           <li data-menu-group="key">
 *  - which cards are collapsed               <div class="card" data-remember-card="key">
 * The inline scripts partials/sidebar-state-init and partials/ui-state-restore apply them
 * before the page paints; this file only saves changes.
 */
const DESKTOP_MIN_WIDTH = 992; // AdminLTE's autoCollapseSize: below it the sidebar is an overlay

function read(key) {
    try {
        return JSON.parse(localStorage.getItem(key) || '{}') || {};
    } catch {
        return {};
    }
}

function write(key, value) {
    try {
        localStorage.setItem(key, typeof value === 'string' ? value : JSON.stringify(value));
    } catch {
        // storage blocked (private mode, policy): the choice just won't persist
    }
}

export function initUiState() {
    $(document).on('collapsed.lte.pushmenu shown.lte.pushmenu', (event) => {
        if (window.innerWidth >= DESKTOP_MIN_WIDTH) {
            write('sidebar', event.type === 'collapsed' ? 'collapsed' : 'expanded');
        }
    });

    // Fired on the menu after a group opens or closes; record every group's current state.
    $(document).on('expanded.lte.treeview collapsed.lte.treeview', () => {
        const groups = read('sidebar.groups');
        $('[data-menu-group]').each(function () {
            groups[this.dataset.menuGroup] = this.classList.contains('menu-open');
        });
        write('sidebar.groups', groups);
    });

    // Fired by the collapse button as the animation starts, so use the event, not the class.
    $(document).on('collapsed.lte.cardwidget expanded.lte.cardwidget', (event) => {
        const card = $(event.target).closest('[data-remember-card]')[0];
        if (card) {
            const cards = read('cards');
            cards[card.dataset.rememberCard] = event.type === 'collapsed';
            write('cards', cards);
        }
    });
}
