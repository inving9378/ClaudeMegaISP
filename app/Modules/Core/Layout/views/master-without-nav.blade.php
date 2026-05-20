<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    @include('core-layout::title-meta')
    @include('core-layout::head')
</head>


<body>
    <div id="init-vue">
        <div id="layout-wrapper">


            @yield('content')
            @include('core-layout::modals')
        </div>

        <!-- JAVASCRIPT -->
        @include('core-layout::vendor-scripts')
</body>

</html>
