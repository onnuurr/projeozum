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
            // Modül sayfaları ("Finance::Dashboard") manifest'te Modules/... yoluyla
            // anahtarlanır; app.js'teki import.meta.glob eşlemesinin sunucu tarafı karşılığı.
            $inertiaPagePath = str_contains($page['component'], '::')
                ? vsprintf('Modules/%s/Resources/assets/js/Pages/%s.vue', explode('::', $page['component'], 2))
                : "resources/js/Pages/{$page['component']}.vue";
        @endphp
        @vite(['resources/js/app.js', $inertiaPagePath])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
