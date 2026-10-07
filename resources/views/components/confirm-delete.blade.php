@props(['action', 'title' => null])

{{-- Opens the shared confirmation modal in layouts/admin, which submits DELETE to $action. --}}
<button type="button" class="btn btn-danger btn-sm" data-confirm-delete="{{ $action }}" data-title="{{ $title }}" title="{{ __('Delete') }}">
    <i class="fas fa-trash"></i>
</button>
