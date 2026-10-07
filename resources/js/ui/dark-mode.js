const STORAGE_KEY = 'theme';

function store(value) {
    try {
        localStorage.setItem(STORAGE_KEY, value);
    } catch {
        // storage blocked: the choice just won't persist
    }
}

/**
 * Navbar moon/sun button. The initial class is set by the inline script at the top of <body>.
 */
export function initDarkMode() {
    const $body = $('body');
    const $navbar = $('.main-header');

    const apply = (dark) => {
        $body.toggleClass('dark-mode', dark);
        $navbar.toggleClass('navbar-white navbar-light', !dark).toggleClass('navbar-dark', dark);
        $('[data-dark-mode-icon]').toggleClass('fa-moon', !dark).toggleClass('fa-sun', dark);
    };

    apply($body.hasClass('dark-mode'));

    $('[data-toggle-dark-mode]').on('click', (event) => {
        event.preventDefault();
        const dark = !$body.hasClass('dark-mode');
        apply(dark);
        store(dark ? 'dark' : 'light');
    });
}
