/**
 * Server-side tables (yajra DataTables). <x-datatable> renders <table data-datatable ...>;
 * DataTables itself is a separate chunk, loaded only on pages that have such a table.
 *
 * data-filters="#form": every named field of that form is sent with each request and a change
 * reloads the table. Its field named "search" is the search box (debounced). The form never
 * submits normally, and the current filters are mirrored in the URL so refresh keeps them.
 */
const SEARCH_DELAY_MS = 350;
const RELOAD_STATUSES = [401, 403, 419]; // signed out, lost access or expired CSRF session

export async function initDataTables() {
    const tables = document.querySelectorAll('table[data-datatable]');
    if (!tables.length) {
        return;
    }

    const [{ default: DataTable }] = await Promise.all([
        import('datatables.net-bs4'),
        import('datatables.net-responsive-bs4'),
    ]);
    DataTable.ext.errMode = 'none'; // no alert() boxes; errors are shown in the table

    tables.forEach((element) => create(DataTable, element));
}

function create(DataTable, element) {
    const data = element.dataset;
    const language = JSON.parse(data.language);
    const params = JSON.parse(data.params);
    const $form = data.filters ? $(data.filters) : $();
    const $search = $form.find('[name="search"]');
    const paging = data.paging !== 'false';

    const filterValues = () => $form.serializeArray().filter(({ name, value }) => name !== 'search' && value !== '');

    // AWS-console style loading: the rows give way to one centred "spinner + Loading …" line.
    // Bound before DataTables is created: the first request starts inside the constructor.
    $(element).on('processing.dt', (event, settings, show) => {
        if (show) {
            showLoading(element, language.loading);
        }
    });

    const table = new DataTable(element, {
        serverSide: true,
        processing: true,
        responsive: true,
        autoWidth: false,
        searching: true,
        columns: JSON.parse(data.columns).map((column) => ({ searchable: false, ...column })),
        order: JSON.parse(data.order),
        paging,
        pageLength: Number(data.pageLength),
        lengthMenu: [10, 15, 25, 50, 100],
        search: { search: $search.val() || '' },
        language: { ...language, loadingRecords: '' },
        layout: {
            topStart: null,
            topEnd: null,
            bottomStart: paging ? ['pageLength', 'info'] : null,
            bottomEnd: paging ? 'paging' : null,
        },
        ajax: {
            url: data.source,
            data: (request) => {
                if (!paging) {
                    // Unpaged DataTables asks for length -1 ("all"), which the server refuses;
                    // ask for the largest allowed page instead (DataTableRequest::MAX_PAGE_LENGTH).
                    request.start = 0;
                    request.length = 100;
                }
                Object.assign(request, params);
                filterValues().forEach(({ name, value }) => {
                    request[name] = value;
                });
            },
        },
    });

    table.on('xhr.dt', (event, settings, json, xhr) => {
        if (xhr && RELOAD_STATUSES.includes(xhr.status)) {
            window.location.reload();
        }
    });
    table.on('error.dt', () => {
        const $cell = $('<td class="text-center text-danger py-4">').attr('colspan', visibleColumns(element)).text(language.loadError);
        $(element).find('tbody').empty().append($('<tr>').append($cell));
    });
    table.on('draw.dt', () => syncUrl($form));

    let timer;
    $search.on('input', () => {
        clearTimeout(timer);
        timer = setTimeout(() => table.search($search.val()).draw(), SEARCH_DELAY_MS);
    });
    $form.on('submit', (event) => {
        event.preventDefault();
        clearTimeout(timer);
        table.search($search.val()).draw();
    });
    $form.on('change', ':input:not([name="search"])', () => table.ajax.reload());
}

function showLoading(element, text) {
    const $status = $('<span class="dt-loading" role="status">')
        .append('<span class="dt-loading-spinner" aria-hidden="true"></span>')
        .append($('<span>').text(text));
    const $cell = $('<td class="dt-loading-cell">').attr('colspan', visibleColumns(element)).append($status);

    $(element).find('tbody').empty().append($('<tr>').append($cell));
}

/** Columns currently shown (Responsive hides some on small screens). */
function visibleColumns(element) {
    return $(element).find('thead th').filter(':visible').length || 1;
}

/**
 * Mirror the filter form in the address bar and in links marked data-keep-filters (the tabs),
 * so a refresh, a bookmark or switching tab keeps what was typed and chosen.
 */
function syncUrl($form) {
    const fields = $form.serializeArray();
    const apply = (url) => {
        fields.forEach(({ name, value }) => (value === '' ? url.searchParams.delete(name) : url.searchParams.set(name, value)));
        return url.toString();
    };

    window.history.replaceState(null, '', apply(new URL(window.location.href)));
    $('a[data-keep-filters]').each(function () {
        this.href = apply(new URL(this.href));
    });
}
