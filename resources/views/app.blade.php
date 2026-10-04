<!DOCTYPE html>
@php($theme = \App\Http\Middleware\HandleInertiaRequests::themeFor(auth()->user()))
{{-- The browser UI colour (address bar, installed app's title bar) per mode:
     provisional brand values, the same as themeColors in resources/js/theme.ts.
     "system" leaves each meta to its media query; a forced mode paints both. --}}
@php($themeColors = ['light' => '#FFFFFF', 'dark' => '#131211'])
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-mode="{{ $theme['mode'] }}">
    <head>
        <meta charset="utf-8">
        {{-- Resolve "system" to light/dark before first paint, so a dark-mode
             visitor never sees a white flash. Kept in sync afterwards by
             applyTheme() in resources/js/theme.ts. --}}
        <script>
            (function () {
                var root = document.documentElement;
                if (root.dataset.mode === 'system') {
                    root.dataset.mode = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
                }
            })();
        </script>
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="theme-color" media="(prefers-color-scheme: light)" content="{{ $themeColors[$theme['mode'] === 'dark' ? 'dark' : 'light'] }}">
        <meta name="theme-color" media="(prefers-color-scheme: dark)" content="{{ $themeColors[$theme['mode'] === 'light' ? 'light' : 'dark'] }}">

        {{-- Installable web app: the manifest, plus what iOS reads for Add to
             Home Screen. Icons are generated from mobile-assets/logo.png. --}}
        <link rel="manifest" href="/manifest.webmanifest">
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="default">
        <meta name="apple-mobile-web-app-title" content="{{ config('app.name', 'Manteion') }}">

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.png" type="image/png">
        <link rel="apple-touch-icon" href="/icons/apple-touch-icon-180.png">

        @fonts

        @vite(['resources/css/app.css', 'resources/js/app.ts', "resources/js/pages/{$page['component']}.vue"])
        <x-inertia::head>
            <title>{{ config('app.name', 'Laravel') }}</title>
        </x-inertia::head>
    </head>
    <body class="font-sans antialiased">
        <x-inertia::app />
    </body>
</html>
