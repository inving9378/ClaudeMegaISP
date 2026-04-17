<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    @include('meganet.layout.title-meta')
    @include('meganet.layout.head')
    @yield('styles')
</head>

@php
    $config = $configLayout(auth()->user()->id);
@endphp

<body
    @if ($config) class="pace-done" data-layout-mode="{{ $config->color_mode }}" data-topbar="{{ $config->color_mode }}" data-sidebar="{{ $config->color_mode }}" @endif>
    <div id="init-vue">
        <div>
            @include('meganet.layout.topbar')
            @include('meganet.layout.sidebar')
        </div>
        <div id="layout-wrapper" class="main-content">
            <div class="page-content">
                <div class="container-fluid">
                    @if (session()->has('success'))
                        <Message-Response message="{{ session()->get('success') }}">
                        </Message-Response>
                    @endif

                    @if (session()->has('error'))
                        <Message-Response message="{{ session()->get('error') }}" type="error"></Message-Response>
                    @endif
                    @yield('content')
                    @include('meganet.layout.modals')
                </div>
                <!-- container-fluid -->
            </div>
            <!-- End Page-content -->
            @include('meganet.layout.footer')
        </div>

        @include('meganet.layout.right-sidebar')
        <!-- /Right-bar -->
    </div>
    <!-- JAVASCRIPT -->
    @include('meganet.layout.vendor-scripts')

    @stack('scripts')
</body>

</html>
