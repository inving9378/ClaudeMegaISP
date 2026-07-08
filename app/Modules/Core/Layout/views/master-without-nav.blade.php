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

<body @if($config) data-layout-mode="{{ $config->color_mode }}" data-topbar="{{ $config->color_mode }}" data-sidebar="{{ $config->color_mode }}" @endif>
    <script>
        /* Tema INDEPENDIENTE POR PESTAÑA (sessionStorage, no compartido). Pestaña
           nueva → default del usuario (data-layout-mode de BD) y lo fija. Corre
           como primer elemento del <body> → sin parpadeo. */
        (function () {
            try {
                var KEY = "layout-mode";
                var def = document.body.getAttribute("data-layout-mode") || "light";
                var stored = sessionStorage.getItem(KEY);
                var mode = stored || def;
                if (!stored) sessionStorage.setItem(KEY, mode);
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
