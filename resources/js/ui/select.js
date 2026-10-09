/**
 * Every <select> in the page content becomes a styled, searchable dropdown (Select2, Bootstrap 4
 * theme, both shipped with AdminLTE). Select2 is its own chunk, loaded only on pages with a select.
 *
 * It keeps the original <select> in the form and triggers jQuery "change" on it, so
 * data-autosubmit, DataTables filter forms and server-side validation work as before.
 * Opt out with <select data-native>.
 *
 * <select data-search-url="…"> searches the server as you type (Select2 "ajax"): the URL answers
 * {results: [{id, text, …}], pagination: {more}}. data-template="customer" shows name, contact,
 * tier and points (customer::partials.select).
 */
const SEARCH_DELAY_MS = 250;
const SELECTOR = '.content-wrapper select:not([data-native])';

export async function initSelects(root = document) {
    // DataTables' own "per page" select stays native (v1 and v3 class names).
    const selects = [...root.querySelectorAll(SELECTOR)].filter((el) => !el.closest('.dataTables_length, .dt-length'));
    if (!selects.length) {
        return;
    }

    const [{ default: install }] = await Promise.all([
        import('admin-lte/plugins/select2/js/select2.full.js'),
        import('admin-lte/plugins/select2/css/select2.css'),
        import('admin-lte/plugins/select2-bootstrap4-theme/select2-bootstrap4.css'),
    ]);
    install(window, window.jQuery); // the CommonJS build exports a factory that takes jQuery
    patchStrictMode();

    selects.forEach((el) => {
        const $el = $(el);
        const small = el.classList.contains('custom-select-sm') || el.classList.contains('form-control-sm');
        const inline = small || el.closest('.form-inline, .card-tools, .input-group') !== null;
        const empty = el.querySelector('option[value=""]');

        const options = {
            theme: 'bootstrap4',
            width: inline ? 'resolve' : '100%',
            dropdownAutoWidth: inline,
            placeholder: el.dataset.placeholder || (empty ? empty.textContent.trim() : undefined),
            // Search box always, even for short lists (the user asked for searchable dropdowns).
            minimumResultsForSearch: 0,
            dropdownParent: $el.closest('.modal').length ? $el.closest('.modal') : $(document.body),
        };
        if (el.dataset.searchUrl) {
            Object.assign(options, remote(el));
        }
        $el.select2(options);

        const $container = $el.next('.select2-container');
        $container.toggleClass('select2-sm', small);
        $container.toggleClass('is-invalid', el.classList.contains('is-invalid'));

        // Focus the search box as soon as the dropdown opens (Select2 4.1 + jQuery 3.6 don't always).
        $el.on('select2:open', () => {
            document.querySelector('.select2-container--open .select2-search__field')?.focus();
        });
    });
}

/** Options for a select that searches the server (data-search-url). */
function remote(el) {
    const render = TEMPLATES[el.dataset.template];
    const template = render ? (item) => render(item, el.dataset) : (item) => item.text;

    return {
        minimumInputLength: 1,
        ajax: {
            url: el.dataset.searchUrl,
            dataType: 'json',
            delay: SEARCH_DELAY_MS,
            data: (params) => ({ q: params.term, page: params.page || 1 }),
            cache: true,
        },
        templateResult: (item) => (item.loading || !item.id ? item.text : template(item)),
        // The chosen value: the server-rendered option carries its details in data-option.
        templateSelection: (item) => {
            const details = item.element?.dataset.option ? JSON.parse(item.element.dataset.option) : item;
            return details.id && details.detail !== undefined ? template({ ...details, compact: true }) : item.text;
        },
    };
}

/** Rendered with text nodes only (never HTML from the server). */
const TEMPLATES = {
    customer(item, labels) {
        const $row = $('<span class="select2-customer">');
        $row.append($('<span class="select2-customer-name">').text(item.text));
        if (!item.active) {
            $row.append($('<span class="badge badge-secondary ml-1">').text(labels.inactiveLabel || 'Deactivated'));
        }
        if (!item.compact && item.detail) {
            $row.append($('<span class="select2-customer-detail">').text(item.detail));
        }
        const facts = [item.tier, `${Number(item.points).toLocaleString()} pts`].filter(Boolean).join(' · ');
        $row.append($('<span class="select2-customer-facts">').text(facts));
        return $row;
    },
};

/**
 * Select2 4.1 passes `_normalizeItem` to Array.map unbound when it reads server results
 * (data/ajax: `results.results.map(AjaxAdapter.prototype._normalizeItem)`). As a classic script
 * `this` falls back to window; bundled as a module (strict mode) it is undefined and
 * `this.container` throws, so the search never shows results. Call it with a safe `this`.
 */
let patched = false;
function patchStrictMode() {
    if (patched) {
        return;
    }
    patched = true;
    const SelectAdapter = $.fn.select2.amd.require('select2/data/select');
    const normalize = SelectAdapter.prototype._normalizeItem;
    SelectAdapter.prototype._normalizeItem = function (item) {
        return normalize.call(this ?? {}, item);
    };
}
