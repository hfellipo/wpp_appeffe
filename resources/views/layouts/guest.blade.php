<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-gray-900 antialiased">
        <div class="min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0 bg-gradient-to-br from-brand-50 via-brand-100 to-brand-200">
            <div class="text-center">
                <a href="/" class="flex flex-col items-center">
                    <div class="bg-white rounded-2xl shadow-lg px-8 py-5">
                        <x-application-logo size="lg" />
                    </div>
                </a>
            </div>

            <div class="w-full sm:max-w-md mt-6 px-6 py-4 bg-white shadow-xl overflow-hidden sm:rounded-lg border-t-4 border-brand-500">
                {{ $slot }}
            </div>

            <p class="mt-6 text-sm text-brand-700">
                &copy; {{ date('Y') }} Secretário App. Todos os direitos reservados.
            </p>
        </div>
    </body>
</html>
