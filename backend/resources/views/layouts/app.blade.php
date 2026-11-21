<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Laravel') }}</title>
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />


    <style>
        [x-cloak] {
            display: none !important;
        }
    </style>

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="font-sans text-gray-900 antialiased">
    @php
        $user = auth()->user();
        $navItems = $user->hasRole('driver')
            ? [
                ['Dashboard', 'dashboard', 'dashboard', 'grid'],
                ['My Deliveries', 'schedules.mine', 'schedules.*', 'package'],
            ]
            : [
                ['Dashboard', 'dashboard', 'dashboard', 'grid'],
                ['Driver Management', 'drivers.index', 'drivers.*', 'users'],
                ['Truck Management', 'trucks.index', 'trucks.*', 'truck'],
                ['Schedule Management', 'schedules.index', 'schedules.*', 'calendar'],
                ['GPS Tracking', 'map.index', 'map.*', 'map'],
                ['Transaction History', 'transactions.index', 'transactions.*', 'history'],
            ];

        $section = request()->routeIs('profile.*')
            ? 'Profile'
            : data_get(collect($navItems)->first(fn($item) => request()->routeIs($item[2])), 0, 'Dashboard');
    @endphp

    <div x-data="{ sidebar: false }" class="min-h-screen bg-[#F6F7FB]">
        @include('layouts.navigation', ['items' => $navItems])

        <div class="lg:pl-64">
            @include('layouts.topbar', ['section' => $section])

            <!-- Page Heading -->
            @isset($header)
                <header class="border-b border-gray-200 bg-white">
                    <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                        {{ $header }}
                    </div>
                </header>
            @endisset

            <!-- Page Content -->
            <main>
                {{ $slot }}
            </main>
        </div>
    </div>
</body>

</html>
