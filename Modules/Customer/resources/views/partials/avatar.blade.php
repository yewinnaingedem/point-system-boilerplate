{{-- Customers have no photo: initials in a circle, same size options as <x-avatar>. --}}
@php($size = $size ?? 32)
<span class="customer-avatar {{ $class ?? '' }}" style="width: {{ $size }}px; height: {{ $size }}px; font-size: {{ round($size * 0.38) }}px">{{ $customer->initials() }}</span>
