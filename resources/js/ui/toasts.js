/**
 * Toast notifications, top-right, on every admin page (partials/toasts in the layout).
 * Flash messages arrive server-rendered; scripts can add more with toast(type, message)
 * or window.toast(...). Each closes itself when its progress bar runs out; hovering pauses
 * the bar, and the × closes it at once.
 */
const DURATION_MS = { success: 5000, info: 5000, warning: 8000, error: 8000 };
const ICONS = { success: 'fa-check-circle', info: 'fa-info-circle', warning: 'fa-exclamation-triangle', error: 'fa-times-circle' };

let $container = $();
let labels = {};

export function initToasts() {
    $container = $('.app-toasts');
    labels = $container.data('toast-labels') || {};

    $container.find('.app-toast').each((_, element) => start($(element)));
    $container.on('click', '.app-toast-close', function () {
        dismiss($(this).closest('.app-toast'));
    });

    window.toast = toast;
}

/** Show a toast: type is success | info | warning | error. Text only (never HTML). */
export function toast(type, message) {
    const kind = ICONS[type] ? type : 'info';
    const $toast = $('<div>', {
        class: `app-toast app-toast-${kind}`,
        role: kind === 'error' || kind === 'warning' ? 'alert' : 'status',
    }).append(
        $('<i>', { class: `fas ${ICONS[kind]} app-toast-icon`, 'aria-hidden': 'true' }),
        $('<div>', { class: 'app-toast-body' }).append(
            $('<div>', { class: 'app-toast-title', text: labels[kind] || kind }),
            $('<div>', { class: 'app-toast-message', text: message }),
        ),
        $('<button>', { type: 'button', class: 'app-toast-close', 'aria-label': labels.close || 'Close', html: '&times;' }),
        $('<div>', { class: 'app-toast-progress' }),
    );

    $container.append($toast);
    start($toast);
}

function start($toast) {
    const kind = ($toast.attr('class').match(/app-toast-(success|info|warning|error)/) || [])[1] || 'info';
    // The bar is a CSS animation (paused on hover); its end closes the toast.
    $toast.find('.app-toast-progress')
        .css('animation-duration', `${DURATION_MS[kind]}ms`)
        .one('animationend', () => dismiss($toast));
}

function dismiss($toast) {
    if ($toast.hasClass('is-leaving')) {
        return;
    }
    $toast.addClass('is-leaving');
    setTimeout(() => $toast.remove(), 250); // matches the leave transition in app.css
}
