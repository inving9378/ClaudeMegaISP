<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    @include('core-layout::title-meta')
    @include('core-layout::head')
    <link rel="stylesheet" href="{{ asset('css/driver.min.css') }}">
    <script src="{{ asset('js/driver.min.js') }}"></script>
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
    <!-- JAVASCRIPT -->
    @include('core-layout::vendor-scripts')

    @stack('scripts')
</body>

</html>
