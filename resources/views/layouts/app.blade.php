<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? config('app.name', 'AMIS Payment') }}</title>

    <!-- Favicon -->
    <link rel="icon" type="image/png" href="{{ asset('images/AMIS_Logo.png') }}">
    <link rel="shortcut icon" href="{{ asset('images/AMIS_Logo.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Amiri:ital,wght@0,400;0,700;1,400;1,700&family=Inter:wght@300;400;500;600;700;800&family=Tajawal:wght@300;400;500;700;800;900&display=swap" rel="stylesheet">

    <!-- Prevent FOUC -->
    <style>
        [x-cloak] { display: none !important; }
        .page-content { opacity: 1; transition: opacity 0.2s; }
    </style>

    <!-- Scripts & Styles -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @stack('styles')
</head>
<body class="font-sans antialiased">

    @php
        $toastError = session('error') ?: ($errors?->any() ? $errors->first() : null);
    @endphp
    @if (session('success') || session('info') || session('warning') || $toastError)
        <div class="toast-stack">
            @if (session('success'))
                <x-toast type="success" :message="session('success')" />
            @endif
            @if (session('info'))
                <x-toast type="info" :message="session('info')" />
            @endif
            @if (session('warning'))
                <x-toast type="warning" :message="session('warning')" />
            @endif
            @if ($toastError)
                <x-toast type="error" :message="$toastError" />
            @endif
        </div>
    @endif

    <!-- Page Content -->
    <div class="page-content min-h-screen bg-gray-100">
        @auth
            @include('layouts.navigation')
        @endauth

        @isset($header)
            <header class="bg-white shadow">
                <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                    {{ $header }}
                </div>
            </header>
        @endisset

        <main>
            {{ $slot }}
        </main>

        <x-footer />
    </div>

    @stack('scripts')
</body>
</html>
