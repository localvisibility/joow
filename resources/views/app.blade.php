<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title inertia>{{ config('app.name', 'Laravel') }}</title>

        <!-- Identité -->
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="icon" href="/favicon.ico" sizes="32x32">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">
        <link rel="manifest" href="/manifest.json">
        <meta name="theme-color" content="#08080c">
        <meta name="description" content="Joow crée le site professionnel de votre établissement en 30 secondes : textes, photos, avis, réservation. Design premium, sans compte.">
        <meta property="og:type" content="website">
        <meta property="og:site_name" content="Joow">
        <meta property="og:title" content="Joow — Votre site pro en 30 secondes">
        <meta property="og:description" content="Cherchez votre établissement : Joow génère un site complet et premium, prêt à publier.">
        <meta property="og:image" content="{{ url('/og.png') }}">
        <meta name="twitter:card" content="summary_large_image">

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @routes
        @vite(['resources/js/app.js', "resources/js/Pages/{$page['component']}.vue"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
