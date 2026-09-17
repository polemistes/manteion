<!DOCTYPE html>
@php($theme = \App\Http\Middleware\HandleInertiaRequests::themeFor(auth()->user()))
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-style="{{ $theme['style'] }}" data-mode="{{ $theme['mode'] }}">
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
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.png" type="image/png">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

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
