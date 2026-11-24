<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Ritu School - Installation</title>
    <link rel="shortcut icon" href="{{ asset(config('installer.icon')) }}">
    <link href="{{ asset('vendor/wizard-installer/styles.css') }}" rel="stylesheet">
    <link href="{{ asset('assets/css/installer-branding.css') }}" rel="stylesheet">
</head>
<body class="installer-page">
<main class="installer-page__container">
    <div class="installer-page__card">
        <header class="installer-page__header">
            <img class="installer-page__logo" alt="Ritu School logo" src="{{ asset(config('installer.icon')) }}">
            <div class="installer-page__heading">
                <p class="installer-page__eyebrow">Ritu School Installation Wizard</p>
                <h1 class="installer-page__title">RITU SCHOOL - SCHOOL MANAGEMENT SYSTEM INSTALLATION</h1>
            </div>
        </header>
        <section class="installer-page__content">
            @yield('step')
        </section>
        <footer class="installer-page__footer">
            <span>Ritu School</span> | School Management System
        </footer>
    </div>
</main>
</body>
</html>
