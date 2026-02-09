<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        
        <link rel="stylesheet" href="{{ asset('admin/assets/vendor/css/core.css') }}" class="template-customizer-core-css" />
        <link rel="stylesheet" href="{{ asset('admin/assets/vendor/fonts/boxicons.css') }}" />
        <link rel="stylesheet" href="{{ asset('admin/assets/vendor/css/theme-default.css') }}" class="template-customizer-theme-css" />
        <link rel="stylesheet" href="{{ asset('admin/assets/css/demo.css') }}" />


        <title inertia>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @routes
        <script src="/admin/assets/vendor/js/helpers.js"></script>
        <script src="/admin/assets/js/config.js"></script>

        @vite(['resources/js/app.js', "resources/js/Pages/{$page['component']}.vue"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia

        <script src="/admin/assets/vendor/libs/jquery/jquery.js"></script>
        <script src="/admin/assets/vendor/libs/popper/popper.js"></script>
        <script src="/admin/assets/vendor/js/bootstrap.js"></script>
        <script src="/admin/assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js"></script>
        <script src="/admin/assets/vendor/js/menu.js"></script>
        <script src="/admin/assets/js/main.js"></script>


    </body>
</html>
