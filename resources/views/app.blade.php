<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"  @class(['dark' => ($appearance ?? 'light') == 'dark'])>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        @php($portalSetting = \App\Models\PortalSetting::current())
        @php($portalIconUrl = $portalSetting->logoUrl())
        @php($customBackgroundColor = auth()->user()?->background_color)

        {{-- Inline script to detect system dark mode preference and apply it immediately --}}
        <script>
            (function() {
                const appearance = '{{ $appearance ?? "light" }}';

                if (appearance === 'system') {
                    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

                    if (prefersDark) {
                        document.documentElement.classList.add('dark');
                    }
                }
            })();
        </script>

        {{-- Inline style to set the HTML background color based on our theme in app.css --}}
        <style>
            html,
            body {
                background-color: {{ $customBackgroundColor ? "'{$customBackgroundColor}'" : 'oklch(1 0 0)' }};
            }

            html.dark,
            html.dark body {
                background-color: oklch(0.145 0 0);
            }

            @if ($customBackgroundColor)
                html.dark,
                html.dark body {
                    background-color: {{ $customBackgroundColor }};
                }
            @endif
        </style>

        @if ($portalIconUrl)
            <link rel="icon" href="{{ $portalIconUrl }}" sizes="any">
            <link rel="apple-touch-icon" href="{{ $portalIconUrl }}">
        @else
            <link rel="icon" href="/favicon.ico" sizes="any">
            <link rel="icon" href="/favicon.svg" type="image/svg+xml">
            <link rel="apple-touch-icon" href="/apple-touch-icon.png">
        @endif

        @fonts

        @vite(['resources/css/app.css', 'resources/js/app.ts', "resources/js/pages/{$page['component']}.vue"])
        <x-inertia::head>
            <title>{{ $portalSetting->companyName() }}</title>
        </x-inertia::head>
    </head>
    <body class="font-sans antialiased">
        <x-inertia::app />
    </body>
</html>
