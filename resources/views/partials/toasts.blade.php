@php
    // Flash messages as toasts (top-right, auto-close with a progress bar; ui/toasts.js).
    // Controllers: ->with('success' | 'info' | 'warning' | 'error', $message).
    // Errors not tied to a form field (service guards such as "last administrator") use the
    // error bag keys below and show as error toasts too.
    $toasts = [];
    foreach (['success', 'info', 'warning', 'error'] as $type) {
        if ($message = session($type)) {
            $toasts[] = [$type, $message];
        }
    }
    foreach (['user', 'role'] as $key) {
        if ($errors->has($key)) {
            $toasts[] = ['error', $errors->first($key)];
        }
    }
@endphp
<div class="app-toasts" aria-live="polite" data-toast-labels="{{ json_encode([
    'success' => __('Success'), 'info' => __('Notice'), 'warning' => __('Warning'), 'error' => __('Error'), 'close' => __('Close'),
]) }}">
    @foreach ($toasts as [$type, $message])
        @include('partials.toast', ['type' => $type, 'message' => $message])
    @endforeach
</div>
