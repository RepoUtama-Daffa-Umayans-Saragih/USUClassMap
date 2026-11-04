@props([
    'label',
    'value',
    'icon' => null,
    'color' => 'green'
])

@php
    $iconColorClasses = match($color) {
        'green' => 'bg-green-100 text-green-600',
        'gold' => 'bg-yellow-100 text-yellow-600',
        'gray' => 'bg-gray-100 text-gray-600',
        'red' => 'bg-red-100 text-red-600',
        default => 'bg-green-100 text-green-600',
    };
@endphp

<div {{ $attributes->merge(['class' => 'bg-white shadow-sm rounded-lg border border-gray-200 p-6 flex items-center']) }}>
    @if($icon)
        <div class="p-3 rounded-lg {{ $iconColorClasses }} mr-4">
            {{ $icon }}
        </div>
    @endif
    <div>
        <div class="text-sm text-gray-500 font-medium">{{ $label }}</div>
        <div class="text-2xl font-bold text-gray-900 mt-1">{{ $value }}</div>
    </div>
</div>
