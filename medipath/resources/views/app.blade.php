<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"
      data-theme="{{ $page['props']['ui']['theme'] ?? 'light' }}"
      data-bs-theme="{{ $page['props']['ui']['theme'] ?? 'light' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <meta name="description" content="MediPath — verified doctor appointments, medicine safety checks, and a rule-first emergency assistant.">
    <meta name="reverb-key" content="{{ config('broadcasting.connections.reverb.key') }}">
    <meta name="reverb-host" content="{{ config('broadcasting.connections.reverb.host', '127.0.0.1') }}">
    <meta name="reverb-port" content="{{ config('broadcasting.connections.reverb.port', 8080) }}">
    <meta name="reverb-scheme" content="{{ config('broadcasting.connections.reverb.scheme', 'http') }}">
    <title inertia>{{ config('app.name', 'MediPath') }}</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">

    {{-- Prevent a flash of the wrong theme before hydration (System → override). --}}
    <script>
        (function () {
            try {
                var pref = {!! json_encode($page['props']['ui']['themePreference'] ?? 'system') !!};
                var stored = localStorage.getItem('mp-theme');
                var resolved = pref;
                if (pref === 'system') {
                    resolved = stored || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
                }
                document.documentElement.setAttribute('data-theme', resolved);
                document.documentElement.setAttribute('data-bs-theme', resolved);
            } catch (e) { /* no-op */ }
        })();
    </script>

    @routes
    @viteReactRefresh
    @vite(['resources/sass/app.scss', 'resources/js/app.tsx'])
    @inertiaHead
</head>
<body>
    @inertia
</body>
</html>