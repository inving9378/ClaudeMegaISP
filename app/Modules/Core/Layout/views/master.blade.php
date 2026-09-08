<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    @include('core-layout::title-meta')
    @include('core-layout::head')
    <link rel="stylesheet" href="{{ asset('css/driver.min.css') }}">
    <script src="{{ asset('vendor/js/driver.min.js') }}"></script>
    @yield('styles')
</head>

@php
    $config = $configLayout(auth()->user()->id);
@endphp

<body
    data-row-style="{{ $config->row_status_style ?? 'underline' }}"
    @if ($config) class="pace-done" data-layout-mode="{{ $config->color_mode }}" data-topbar="{{ $config->color_mode }}" data-sidebar="{{ $config->color_mode }}" @endif>
    <script>
        /* Tema INDEPENDIENTE POR PESTAÑA. Cada pestaña conserva su tema en
           sessionStorage (NO compartido entre pestañas → sin conflicto). Una
           pestaña nueva arranca con el default del usuario (el data-layout-mode
           que ya vino renderizado desde BD) y lo fija para esta pestaña.
           Corre como primer elemento del <body> → sin parpadeo. */
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
    <div>
        <div id="topbar-vue-root">
            @include('core-layout::topbar')
        </div>
        @include('core-layout::sidebar')
        {{-- Navegación móvil (capa aditiva, solo <=992px; escritorio intacto) --}}
        @include('core-layout::mobile-nav')
        <div id="layout-wrapper" class="main-content">
            <div class="page-content">
                <div class="container-fluid" id="init-vue">
                    @if (session()->has('success'))
                        <Message-Response message="{{ session()->get('success') }}">
                        </Message-Response>
                    @endif

                    @if (session()->has('error'))
                        <Message-Response message="{{ session()->get('error') }}" type="error"></Message-Response>
                    @endif
                    @yield('content')
                    @include('core-layout::modals')
                </div>
                <!-- container-fluid -->
            </div>
            <!-- End Page-content -->
            @include('core-layout::footer')
        </div>

        @include('core-layout::right-sidebar')
        <!-- /Right-bar -->
    </div>
    <div id="help-float-root">
        {{-- Ayuda contextual por pantalla (panel flotante estilo Splynx) --}}
        <help-float url="{{ url('/') }}"></help-float>
    </div>
    @if(auth()->user() && auth()->user()->can('usar-ia-chat'))
        <div id="ia-chat-float-root">
            {{-- Chat IA flotante (#9 / #636): gateado por permiso, costo real de API por mensaje.
                 ⚠️ APAGADO desde el 2026-08-27 (#649): contestaba desde `ModuleRegistry::getAiContext()`
                 —lo REGISTRADO— y declaraba no poder medir, mientras JARVIS mide de verdad. Dos
                 asistentes con el mismo nombre se contradicen. Se apaga revocando `usar-ia-chat`;
                 el montaje se deja porque partes de este panel se reusan en la Capa 1 del chat. --}}
            <ia-chat-float url="{{ url('/') }}"></ia-chat-float>
        </div>
    @endif

    @if(auth()->user() && auth()->user()->can('torre.config.view'))
        <div id="jarvis-burbuja-root">
            {{-- LA CARA DE JARVIS (#651): presencia + estado en cualquier pantalla.
                 El anillo es el interruptor de hombre muerto — si el medidor deja de latir, lo dice
                 solo, en la esquina, sin que nadie entre a la Torre a buscarlo. --}}
            <jarvis-burbuja url="{{ url('/') }}"></jarvis-burbuja>
        </div>
    @endif
    <!-- JAVASCRIPT -->
    @include('core-layout::vendor-scripts')

    @stack('scripts')

    {{-- Item #137: stack opt-in para vistas rescatadas de SPA_BLACKLIST. Vive FUERA de
         #init-vue (spa-nav.js no lo toca en el swap) y spa-nav.js lo re-ejecuta de forma
         controlada (elimina + re-crea el <script>) tras cada navegación SPA. Vacío en
         el resto de las vistas: no-op. --}}
    <div id="__spa-scripts" style="display:none">@stack('scripts-spa')</div>
</body>

</html>
