@props([
    'id',
    'source',              // URL of the JSON endpoint (yajra)
    'columns',             // list of ['data', 'title', 'name' => sort column, 'orderable', 'class', 'priority']
    'order' => [[0, 'desc']],
    'filters' => null,     // selector of a form whose fields are sent along; a field named "search" is the search box
    'params' => [],        // fixed extra parameters (e.g. the current tab)
    'empty' => null,
    'loading' => null,     // e.g. "Loading users"; shown with a spinner while rows load
    'pageLength' => 15,
    'paging' => true,      // false for short fixed lists: all rows, no pager or "Showing …" line
])
@php
    // Only what DataTables needs; anything sortable must name its database column.
    $definitions = collect($columns)->map(fn (array $column) => array_filter([
        'data' => $column['data'],
        'name' => $column['name'] ?? null,
        'orderable' => (bool) ($column['orderable'] ?? false),
        'className' => $column['class'] ?? null,
        'responsivePriority' => $column['priority'] ?? null,
    ], fn ($value) => $value !== null))->values();

    $language = [
        'processing' => '',
        'loading' => $loading ?? __('Loading…'),
        'emptyTable' => $empty ?? __('No matching records.'),
        'zeroRecords' => $empty ?? __('No matching records.'),
        'info' => __('Showing _START_–_END_ of _TOTAL_'),
        'infoEmpty' => __('Showing 0–0 of 0'),
        'infoFiltered' => '',
        'lengthMenu' => __('_MENU_ per page'),
        'loadError' => __('The list could not be loaded. Please try again.'),
    ];
@endphp
{{-- Rows are loaded by resources/js/ui/datatables.js. --}}
<table id="{{ $id }}" {{ $attributes->class(['table table-hover table-striped mb-0 w-100']) }}
       data-datatable
       data-source="{{ $source }}"
       data-columns="{{ json_encode($definitions) }}"
       data-order="{{ json_encode($order) }}"
       data-params="{{ json_encode((object) array_filter($params, fn ($value) => $value !== null && $value !== '')) }}"
       data-page-length="{{ $pageLength }}"
       data-paging="{{ $paging ? 'true' : 'false' }}"
       data-language="{{ json_encode($language) }}"
       @if ($filters) data-filters="{{ $filters }}" @endif>
    <thead>
        <tr>
            @foreach ($columns as $column)
                <th @class([$column['class'] ?? null])>{{ $column['title'] }}</th>
            @endforeach
        </tr>
    </thead>
</table>
