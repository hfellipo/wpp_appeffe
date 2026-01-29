@props(['active'])

@php
$classes = ($active ?? false)
            ? 'no-underline hover:no-underline block w-full ps-3 pe-4 py-2 border-l-4 border-golden-400 text-start text-base font-medium text-white bg-brand-700 focus:outline-none focus:text-white focus:bg-brand-600 focus:border-golden-500 transition duration-150 ease-in-out'
            : 'no-underline hover:no-underline block w-full ps-3 pe-4 py-2 border-l-4 border-transparent text-start text-base font-medium text-brand-100 hover:text-white hover:bg-brand-700 hover:border-brand-400 focus:outline-none focus:text-white focus:bg-brand-700 focus:border-brand-400 transition duration-150 ease-in-out';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
