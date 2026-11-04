@props(['color' => 'green'])

@php
    $colorClasses = match($color) {
        'green' => 'bg-green-100 text-green-800',
        'gold' => 'bg-yellow-100 text-yellow-800',
        'gray' => 'bg-gray-100 text-gray-800',
        'red' => 'bg-red-100 text-red-800',
        default => 'bg-green-100 text-green-800',
    };
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ' . $colorClasses]) }}>
    {{ $slot }}
</span>
