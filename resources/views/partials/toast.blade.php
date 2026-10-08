@php
    $icons = ['success' => 'fa-check-circle', 'info' => 'fa-info-circle', 'warning' => 'fa-exclamation-triangle', 'error' => 'fa-times-circle'];
    $titles = ['success' => __('Success'), 'info' => __('Notice'), 'warning' => __('Warning'), 'error' => __('Error')];
@endphp
{{-- Same markup as toast() in resources/js/ui/toasts.js builds. --}}
<div class="app-toast app-toast-{{ $type }}" role="{{ in_array($type, ['error', 'warning'], true) ? 'alert' : 'status' }}">
    <i class="fas {{ $icons[$type] }} app-toast-icon" aria-hidden="true"></i>
    <div class="app-toast-body">
        <div class="app-toast-title">{{ $titles[$type] }}</div>
        <div class="app-toast-message">{{ $message }}</div>
    </div>
    <button type="button" class="app-toast-close" aria-label="{{ __('Close') }}">&times;</button>
    <div class="app-toast-progress"></div>
</div>
