<script src="{{ URL::asset('plugins/quasar/js/vue.global.prod.js') }}"></script>
<script src="{{ URL::asset('plugins/quasar/js/quasar.umd.prod.js') }}"></script>
<script src="{{ URL::asset('plugins/quasar/icon-set/fontawesome-v5.umd.prod.js') }}"></script>
<script src="{{ URL::asset('js/app.js') }}"></script>

<script src="{{ URL::asset('assets/libs/jquery/jquery.min.js') }}"></script>
<script src="{{ URL::asset('plugins/popper.js/popper.js') }}"></script>
<script src="{{ URL::asset('assets/libs/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
<script src="{{ URL::asset('assets/libs/metismenu/metisMenu.min.js') }}"></script>
<script src="{{ URL::asset('assets/libs/simplebar/simplebar.min.js') }}"></script>
<script src="{{ URL::asset('assets/libs/node-waves/waves.min.js') }}"></script>
<script src="{{ URL::asset('assets/libs/feather-icons/feather.min.js') }}"></script>


<!-- pace js -->
<script src="{{ URL::asset('assets/libs/pace-js/pace.min.js') }}"></script>

@yield('script')

@isset($packages['js'])
    @foreach ($packages['js'] as $package_js)
        <script src="{{ URL::asset($package_js->url) }}"></script>
    @endforeach
@endisset

<script src="{{ URL::asset('assets/js/app.js') }}"></script>

<script>
    jQuery.event.special.touchstart = {
        setup: function(_, ns, handle) {
            this.addEventListener("touchstart", handle, {
                passive: !ns.includes("noPreventDefault")
            });
        }
    };
</script>
