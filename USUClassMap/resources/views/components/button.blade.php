@props([
    'variant' => 'primary',
    'size' => 'md',
    'type' => 'button'
])

@php
    $baseClasses = 'inline-flex items-center justify-center rounded-lg font-medium transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-offset-2';
    
    $variantClasses = match($variant) {
        'primary' => 'bg-[#10A760] text-white hover:bg-[#0D8A4E] focus:ring-[#10A760]',
        'secondary' => 'border border-[#10A760] text-[#10A760] hover:bg-[#E8F5EE] focus:ring-[#10A760]',
        'danger' => 'bg-red-600 text-white hover:bg-red-700 focus:ring-red-600',
        default => 'bg-[#10A760] text-white hover:bg-[#0D8A4E] focus:ring-[#10A760]',
    };
    
    $sizeClasses = match($size) {
        'sm' => 'px-3 py-1.5 text-sm',
        'md' => 'px-4 py-2 text-sm',
        'lg' => 'px-6 py-3 text-base',
        default => 'px-4 py-2 text-sm',
    };
    
    $classes = $baseClasses . ' ' . $variantClasses . ' ' . $sizeClasses;
@endphp

<button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</button>
