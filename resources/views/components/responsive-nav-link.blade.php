@props(['active'])

@php
$classes = ($active ?? false)
            ? 'no-underline hover:no-underline block w-full ps-3 pe-4 py-2 border-l-4 border-brand-500 text-start text-base font-semibold text-brand-800 bg-brand-100 focus:outline-none focus:text-brand-900 focus:bg-brand-200 focus:border-brand-600 transition duration-150 ease-in-out'
            : 'no-underline hover:no-underline block w-full ps-3 pe-4 py-2 border-l-4 border-transparent text-start text-base font-medium text-brand-700 hover:text-brand-900 hover:bg-brand-100 hover:border-brand-300 focus:outline-none focus:text-brand-900 focus:bg-brand-100 focus:border-brand-300 transition duration-150 ease-in-out';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
