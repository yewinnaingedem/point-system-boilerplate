<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('partials.head', ['title' => __('Sign in')])
</head>
<body class="hold-transition login-page">
@include('partials.dark-mode-init')
<div class="login-box">
    <div class="card card-outline card-primary">
        <div class="card-header text-center">
            <a href="{{ route('login') }}" class="h1">
                @if ($logo = setting()->url('logo'))
                    <img src="{{ $logo }}" alt="" style="height: 48px" class="mb-2 d-block mx-auto">
                @else
                    <i class="fas fa-cash-register brand-mark"></i>
                @endif
                <b>{{ setting('app_name') }}</b>
            </a>
            <p class="text-muted mb-0">{{ setting('company_name') }}</p>
        </div>
        <div class="card-body">
            <p class="login-box-msg">{{ __('Sign in to start your session') }}</p>

            <form method="POST" action="{{ route('login.store') }}">
                @csrf

                <div class="input-group mb-3">
                    <input type="email" name="email" value="{{ old('email') }}" @class(['form-control', 'is-invalid' => $errors->has('email')])
                           placeholder="{{ __('Email') }}" required autofocus autocomplete="username">
                    <div class="input-group-append">
                        <div class="input-group-text"><span class="fas fa-envelope"></span></div>
                    </div>
                    @error('email') <span class="invalid-feedback">{{ $message }}</span> @enderror
                </div>

                <div class="input-group mb-3">
                    <input type="password" name="password" id="password" @class(['form-control', 'is-invalid' => $errors->has('password')])
                           placeholder="{{ __('Password') }}" required autocomplete="current-password">
                    <div class="input-group-append">
                        <button type="button" class="input-group-text" data-toggle-password="#password" title="{{ __('Show password') }}">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                    @error('password') <span class="invalid-feedback">{{ $message }}</span> @enderror
                </div>

                <div class="row align-items-center">
                    <div class="col-7">
                        <div class="custom-control custom-checkbox">
                            <input type="checkbox" class="custom-control-input" id="remember" name="remember">
                            <label class="custom-control-label font-weight-normal" for="remember">{{ __('Remember me') }}</label>
                        </div>
                    </div>
                    <div class="col-5">
                        <button type="submit" class="btn btn-primary btn-block">{{ __('Sign in') }}</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    <p class="text-center text-muted small mt-3">&copy; {{ date('Y') }} {{ setting('company_name') }}</p>
</div>
</body>
</html>
