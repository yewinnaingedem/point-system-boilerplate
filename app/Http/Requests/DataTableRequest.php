<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the parameters a server-side DataTables (yajra) request sends. A module's table
 * request extends this, adds its own filters in filterRules() and its own authorize().
 *
 * start and length are required and bounded: yajra returns every row when they are missing
 * or length is -1, which would turn one request into a full table dump.
 */
abstract class DataTableRequest extends FormRequest
{
    public const MAX_PAGE_LENGTH = 100;

    private const MAX_SEARCH_LENGTH = 100;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'draw' => ['required', 'integer', 'min:0'],
            'start' => ['required', 'integer', 'min:0'],
            'length' => ['required', 'integer', 'min:1', 'max:'.self::MAX_PAGE_LENGTH],
            'search.value' => ['nullable', 'string', 'max:'.self::MAX_SEARCH_LENGTH],
            'order' => ['nullable', 'array', 'max:3'],
            'order.*.column' => ['required', 'integer', 'min:0'],
            'order.*.dir' => ['required', 'in:asc,desc'],
            'columns' => ['required', 'array', 'max:20'],
            ...$this->filterRules(),
        ];
    }

    /** The table's global search box (prefix search, see each table class). */
    public function searchTerm(): ?string
    {
        $term = trim((string) $this->validated('search.value'));

        return $term === '' ? null : $term;
    }

    /**
     * Rules for the extra filters this table sends along (tab, role, ...).
     *
     * @return array<string, mixed>
     */
    protected function filterRules(): array
    {
        return [];
    }
}
