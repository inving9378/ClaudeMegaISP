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
    <div id="init-vue">
        <div id="layout-wrapper">


            @yield('content')
            @include('core-layout::modals')
        </div>

        <!-- JAVASCRIPT -->
        @include('core-layout::vendor-scripts')
</body>

</html>
