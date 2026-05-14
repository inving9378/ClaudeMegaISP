<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    @include('core-layout::title-meta')
    @include('core-layout::head')
    @yield('styles')
</head>

@php
    $config = $configLayout(auth()->user()->id);
@endphp

<body
    @if ($config) class="pace-done" data-layout-mode="{{ $config->color_mode }}" data-topbar="{{ $config->color_mode }}" data-sidebar="{{ $config->color_mode }}" @endif>
    <div id="init-vue">
        <div>
            @include('core-layout::topbar')
            @include('core-layout::sidebar')
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
    <!-- JAVASCRIPT -->
    @include('core-layout::vendor-scripts')

    @stack('scripts')
</body>

</html>
