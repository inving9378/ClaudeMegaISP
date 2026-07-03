<!DOCTYPE html>
<html lang="es" class="{{ $theme === 'dark' ? 'portal-dark' : '' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover, maximum-scale=1.0, user-scalable=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#0d9488">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Talento Campo">
    <title>Talento Meganet — Campo</title>

    {{-- PWA --}}
    <link rel="manifest" href="/talento/portal/manifest.webmanifest">
    <link rel="apple-touch-icon" href="/talento-portal/icons/icon-192.png">

    {{-- Quasar UMD + Vue 3 (mismos assets que el resto del sistema) --}}
    <link rel="stylesheet" href="/plugins/quasar/css/material-icons.css">
    <link rel="stylesheet" href="/plugins/quasar/css/quasar.prod.css">
    <link rel="stylesheet" href="/talento-portal/portal.css?v={{ $assetVer }}">
</head>
{{-- data-spa-skip: este shell es independiente del interceptor spa-nav del admin --}}
<body data-spa-skip>

    <div id="q-app"></div>

    {{-- Configuración inyectada por el servidor (verdad de autenticación y tema) --}}
    @php
        $portalConfig = [
            'csrf'        => csrf_token(),
            'theme'       => $theme,
            'colaborador' => $colaborador,
            'sections'    => $sections ?? [],
            'assetVer'    => $assetVer,
            'endpoints'   => [
                'saveTheme' => '/talento/portal/preferencias/tema',
                'logout'    => url('/logout'),
                'sw'        => '/talento/portal/sw.js',
            ],
        ];
    @endphp
    <script>
        window.__PORTAL__ = {!! json_encode($portalConfig, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!};
    </script>

    <script src="/plugins/quasar/js/vue.global.prod.js"></script>
    <script src="/plugins/quasar/js/quasar.umd.prod.js"></script>
    <script src="/talento-portal/app.js?v={{ $assetVer }}"></script>
</body>
</html>
