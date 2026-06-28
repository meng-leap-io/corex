@props(['channel' => '', 'connected' => false, 'label' => null])

<div {{ $attributes->merge(['class' => 'flex items-center gap-1.5']) }}>
    <span class="w-1.5 h-1.5 rounded-full {{ $connected ? 'bg-green-500' : 'bg-gray-500' }}"
          {{ $connected ? 'style=animation:pulse 2s infinite' : '' }}
          title="{{ $connected ? 'Connected' : 'Disconnected' }}">
    </span>
    @if($label)
        <span class="text-[10px] text-gray-500">{{ $label }}</span>
    @elseif($channel)
        <span class="text-[10px] text-gray-500">{{ $channel }}</span>
    @endif
</div>
