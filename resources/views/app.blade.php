<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        {{-- Temporarily disabled for mobile-responsiveness testing — this is the
             legacy Bootstrap admin theme (core.css/theme-default.css/boxicons.css).
             None of the Vue/Inertia pages (AdminLayout, TeamLayout, Dashboard,
             signals) use any Bootstrap classes; this theme's CSS may be forcing
             a min-width/layout on html/body that only shows up on real mobile
             Safari, not in Chrome DevTools' device simulator. If the mobile
             layout renders correctly with these disabled, that confirms the
             cause and these lines can be removed for good instead of
             re-enabled. --}}
        {{-- <link rel="stylesheet" href="{{ asset('admin/assets/vendor/css/core.css') }}" class="template-customizer-core-css" /> --}}
        {{-- <link rel="stylesheet" href="{{ asset('admin/assets/vendor/fonts/boxicons.css') }}" /> --}}
        {{-- <link rel="stylesheet" href="{{ asset('admin/assets/vendor/css/theme-default.css') }}" class="template-customizer-theme-css" /> --}}
        <link rel="icon" type="image/png" href="{{ asset('admin/assets/img/favicon/favicon.png') }}">
        

        <title inertia>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @routes
        <script src="/admin/assets/vendor/js/helpers.js"></script>

        @vite(['resources/js/app.js', "resources/js/Pages/{$page['component']}.vue"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia

        {{-- Same legacy admin theme's JS — jQuery/Popper/Bootstrap/menu.js/main.js.
             Disabled alongside the CSS above for the same test. Nothing in the
             Vue pages calls into jQuery/Bootstrap, so this should be safe to
             leave off if the layout test confirms the theory. --}}
        {{-- <script src="/admin/assets/vendor/libs/jquery/jquery.js"></script> --}}
        {{-- <script src="/admin/assets/vendor/libs/popper/popper.js"></script> --}}
        {{-- <script src="/admin/assets/vendor/js/bootstrap.js"></script> --}}
        {{-- <script src="/admin/assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js"></script> --}}
        {{-- <script src="/admin/assets/vendor/js/menu.js"></script> --}}
        {{-- <script src="/admin/assets/js/main.js"></script> --}}


    </body>
</html>