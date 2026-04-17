<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    @include('meganet.layout.title-meta')
    @include('meganet.layout.head')
</head>


<body>
    <div id="init-vue">
        <div id="layout-wrapper">


            @yield('content')
            @include('meganet.layout.modals')
        </div>

        <!-- JAVASCRIPT -->
        @include('meganet.layout.vendor-scripts')
</body>

</html>
