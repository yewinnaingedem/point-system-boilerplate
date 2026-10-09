/**
 * Remembers layout choices in localStorage (this browser only):
 *  - sidebar collapsed or expanded (header ☰ button), desktop widths only
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

    // One sidebar group open at a time, across all sections (AdminLTE's own accordion only works
    // inside one list, and the sidebar has one list per section).
    $(document).on('click', '.nav-sidebar [data-menu-group] > .nav-link', function () {
        const group = this.parentElement;
        $('.nav-sidebar [data-menu-group].menu-open').not(group).each(function () {
            $(this).removeClass('menu-open menu-is-opening');
            $(this).children('.nav-treeview').stop(true, true).slideUp(300);
        });
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
