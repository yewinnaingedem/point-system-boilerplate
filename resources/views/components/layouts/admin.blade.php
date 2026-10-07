@props([
    'title' => null,
    // [label => url] between Home and the current page.
    'breadcrumbs' => [],
])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('partials.head', ['title' => $title])
</head>
<body class="hold-transition sidebar-mini layout-fixed layout-navbar-fixed">
@include('partials.dark-mode-init')
<div class="wrapper">
    @include('partials.navbar')
    @include('partials.sidebar')

    <div class="content-wrapper">
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2 align-items-center">
                    <div class="col-sm-6">
                        <h1 class="m-0">{{ $title }}</h1>
                    </div>
                    <div class="col-sm-6">
                        @unless (request()->routeIs('admin.dashboard'))
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">{{ __('Home') }}</a></li>
                            @foreach ($breadcrumbs as $label => $url)
                                <li class="breadcrumb-item"><a href="{{ $url }}">{{ $label }}</a></li>
                            @endforeach
                            <li class="breadcrumb-item active">{{ $title }}</li>
                        </ol>
                        @endunless
                    </div>
                </div>
            </div>
        </div>

        <section class="content">
            <div class="container-fluid">
                @if ($impersonatorName ?? null)
                    {{-- "Login as" is active (set by the Access module). --}}
                    <div class="alert alert-warning d-flex align-items-center justify-content-between">
                        <span>
                            <i class="fas fa-user-secret mr-2"></i>
                            {!! __('You are signed in as <b>:name</b>. Everything you do now is done as this user.', ['name' => e(auth()->user()->name)]) !!}
                        </span>
                        <form method="POST" action="{{ route('admin.access.impersonate.leave') }}" class="ml-3 mb-0">
                            @csrf
                            <button type="submit" class="btn btn-dark btn-sm text-nowrap"><i class="fas fa-undo mr-1"></i>{{ __('Return to :name', ['name' => $impersonatorName]) }}</button>
                        </form>
                    </div>
                @endif
                @include('partials.flash')
                {{ $slot }}
            </div>
        </section>
    </div>

    <footer class="main-footer">
        <strong>&copy; {{ date('Y') }} {{ setting('company_name') }}.</strong> {{ __('All rights reserved.') }}
        <div class="float-right d-none d-sm-inline-block">
            <b>{{ setting('app_name') }}</b>
        </div>
    </footer>
</div>

<div class="modal fade" id="confirm-delete-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" class="modal-content">
            @csrf
            @method('DELETE')
            <div class="modal-header bg-danger">
                <h5 class="modal-title" data-title data-default="{{ __('Delete this item?') }}">{{ __('Delete this item?') }}</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="{{ __('Close') }}">&times;</button>
            </div>
            <div class="modal-body">{{ __('This cannot be undone.') }}</div>
            <div class="modal-footer justify-content-between">
                <button type="button" class="btn btn-default" data-dismiss="modal">{{ __('Cancel') }}</button>
                <button type="submit" class="btn btn-danger"><i class="fas fa-trash mr-1"></i>{{ __('Delete') }}</button>
            </div>
        </form>
    </div>
</div>
</body>
</html>
