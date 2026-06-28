@props(['online' => false, 'size' => 'sm', 'label' => null])

@php
    $sizeClasses = match($size) {
        'xs' => 'w-1.5 h-1.5',
        'sm' => 'w-2 h-2',
        'md' => 'w-2.5 h-2.5',
        'lg' => 'w-3 h-3',
        default => 'w-2 h-2',
    };
@endphp

<span {{ $attributes->merge(['class' => 'inline-block rounded-full flex-shrink-0 ' . $sizeClasses . ' ' . ($online ? 'bg-green-500' : 'bg-gray-400')]) }}
      title="{{ $label ?? ($online ? 'Online' : 'Offline') }}">
</span>
