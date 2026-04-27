@props(['active'])

@php
$classes = ($active ?? false)
            ? 'no-underline hover:no-underline inline-flex items-center px-1 pt-1 border-b-2 border-brand-500 text-sm font-semibold leading-5 text-brand-700 focus:outline-none focus:border-brand-600 transition duration-150 ease-in-out'
            : 'no-underline hover:no-underline inline-flex items-center px-1 pt-1 border-b-2 border-transparent text-sm font-medium leading-5 text-brand-600 hover:text-brand-900 hover:border-brand-400 focus:outline-none focus:text-brand-900 focus:border-brand-400 transition duration-150 ease-in-out';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
