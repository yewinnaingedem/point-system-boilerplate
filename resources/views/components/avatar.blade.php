@props(['user', 'size' => 34])

@php($size = (int) $size)

{{-- Uploaded photo, or the user's initials in a brand-coloured circle. --}}
@if ($url = $user->avatarUrl())
    <img src="{{ $url }}" alt="{{ $user->name }}" width="{{ $size }}" height="{{ $size }}"
         {{ $attributes->merge(['class' => 'img-circle', 'style' => "width: {$size}px; height: {$size}px; object-fit: cover;"]) }}>
@else
    <span {{ $attributes->merge(['class' => 'avatar-initials', 'style' => "width: {$size}px; height: {$size}px; font-size: ".max(11, (int) round($size / 2.6)).'px;']) }}>
        {{ $user->initials() }}
    </span>
@endif
