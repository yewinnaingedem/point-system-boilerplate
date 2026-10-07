<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>{{ isset($title) ? "{$title} | " : '' }}{{ setting('app_name') }}</title>
@if ($favicon = setting()->url('favicon'))
    <link rel="icon" href="{{ $favicon }}">
@endif
<link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
@vite(['resources/css/app.css', 'resources/js/app.js'])
{{-- primary_color is validated as #rrggbb, so it is safe to print here. --}}
<style>:root { --brand: {{ setting('primary_color') }}; }</style>
