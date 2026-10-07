@if (session('success'))
    <div class="alert alert-success alert-dismissible fade show">
        <button type="button" class="close" data-dismiss="alert" aria-label="{{ __('Close') }}">&times;</button>
        <i class="icon fas fa-check"></i> {{ session('success') }}
    </div>
@endif

{{-- Errors not tied to a form field (service guards such as "last administrator"). --}}
@foreach (['user', 'role'] as $key)
    @error($key)
        <div class="alert alert-danger alert-dismissible fade show">
            <button type="button" class="close" data-dismiss="alert" aria-label="{{ __('Close') }}">&times;</button>
            <i class="icon fas fa-ban"></i> {{ $message }}
        </div>
    @enderror
@endforeach
