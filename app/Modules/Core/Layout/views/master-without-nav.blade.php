<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    @include('core-layout::title-meta')
    @include('core-layout::head')
</head>

@php
    // `configLayout` está disponible globalmente vía View::share() registrado
    // en Core/Layout/ModuleServiceProvider. Lo usamos aquí para propagar el
    // tema (light/dark) al body, igual que master.blade.php — necesario para
    // que componentes Vue como DevtoolsPanel detecten el tema del usuario.
    $config = isset($configLayout) ? $configLayout(auth()->user()->id ?? null) : null;
@endphp

<body data-row-style="{{ $config->row_status_style ?? 'underline' }}" @if($config) data-layout-mode="{{ $config->color_mode }}" data-topbar="{{ $config->color_mode }}" data-sidebar="{{ $config->color_mode }}" @endif>
    <script>
        /* Tema INDEPENDIENTE POR PESTAÑA (sessionStorage, no compartido). Pestaña
           nueva → default del usuario (data-layout-mode de BD) y lo fija. Corre
           como primer elemento del <body> → sin parpadeo.

           FIX (2026-09-17): esta vista también renderiza páginas de INVITADO
           (/login) sin `$config` → sin atributo `data-layout-mode` en el body
           → `def` caía a "light" y ese "light" se GUARDABA en sessionStorage
           igual. Como sessionStorage sobrevive la navegación dentro de la misma
           pestaña, un login legítimo con color_mode="dark" en BD llegaba a la
           siguiente página ya "envenenado" a light por el paso previo del
           login. Ahora solo se persiste a sessionStorage cuando el servidor sí
           mandó una preferencia real (usuario autenticado con config); la
           página de invitado se sigue pintando en light, pero sin ensuciar la
           pestaña para la página autenticada que viene después. */
        (function () {
            try {
                var KEY = "layout-mode";
                var hasServerConfig = document.body.hasAttribute("data-layout-mode");
                var def = document.body.getAttribute("data-layout-mode") || "light";
                var stored = sessionStorage.getItem(KEY);
                var mode = stored || def;
                if (!stored && hasServerConfig) sessionStorage.setItem(KEY, mode);
                document.body.setAttribute("data-layout-mode", mode);
                document.body.setAttribute("data-topbar", mode);
                document.body.setAttribute("data-sidebar", mode);
            } catch (e) {}
        })();
    </script>
    <div id="init-vue">
        <div id="layout-wrapper">


            @yield('content')
            @include('core-layout::modals')
        </div>

        <!-- JAVASCRIPT -->
        @include('core-layout::vendor-scripts')
</body>

</html>
