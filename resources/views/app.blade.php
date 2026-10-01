<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title inertia>{{ config('app.name', 'SoundTix') }}</title>
        <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml" />
        @vite('resources/js/app.jsx')
        @viteReactRefresh
        <x-inertia::head />
    </head>
    <body class="h-full font-sans antialiased">
        <x-inertia::app />
    </body>
</html>