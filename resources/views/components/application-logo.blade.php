@props(['size' => 'md'])

@php
// icon is ~50px wide in the native 148×53 image
$sizes = [
    'sm' => ['icon' => 32, 'font' => '1rem',   'imgW' => round(32 * 148 / 53)],
    'md' => ['icon' => 40, 'font' => '1.2rem',  'imgW' => round(40 * 148 / 53)],
    'lg' => ['icon' => 58, 'font' => '1.75rem', 'imgW' => round(58 * 148 / 53)],
];
$s = $sizes[$size] ?? $sizes['md'];
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-2']) }}>
    <span class="relative overflow-hidden flex-shrink-0 inline-block"
          style="width: {{ $s['icon'] }}px; height: {{ $s['icon'] }}px;">
        <img src="{{ asset('images/logo-multicap.png') }}"
             class="absolute top-0 left-0 h-full max-w-none"
             style="width: {{ $s['imgW'] }}px;"
             alt="">
    </span>
    <span class="font-bold leading-none tracking-tight select-none"
          style="font-size: {{ $s['font'] }}; color: #111111;">
        Multi<span style="color: #1F7A4D;">cap</span>
    </span>
</span>
