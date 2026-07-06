<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title inertia>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @routes
        @php
            // Modül sayfaları "Module::Portal/Sub/Page" adıyla gelir; vite manifesti
            // Modules/{Module}/Resources/assets/js/Pages/{Sub/Page}.vue yolunu bekler.
            $pageComponent = $page['component'];
            $pageEntry = str_contains($pageComponent, '::')
                ? 'Modules/'.str_replace('::', '/Resources/assets/js/Pages/', $pageComponent).'.vue'
                : "resources/js/Pages/{$pageComponent}.vue";
        @endphp
        @vite(['resources/js/app.js', $pageEntry])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
