<script src="{{ URL::asset('assets/libs/htmx/htmx.min.js') }}" defer></script>
<script>
    // ─── HTMX integration ───────────────────────────────────────────────────
    // Problema arquitectónico: el app Vue global se monta en #init-vue que envuelve
    // TODO (topbar + sidebar + #htmx-main). No podemos unmount antes del swap porque
    // Vue limpiaría #htmx-main del DOM antes de que HTMX lo reemplace.
    //
    // Solución: dejar que HTMX haga el outerHTML swap primero (mientras Vue está
    // montado pero con vdom desincronizado), luego hacer snapshot → unmount →
    // restaurar → remount. Todo ocurre sincrónicamente en htmx:afterSwap; el
    // navegador no repinta hasta que el handler termina → sin flash visible.

    document.body.addEventListener('htmx:configRequest', function(e) {
        var meta = document.querySelector('meta[name="csrf-token"]');
        if (meta) e.detail.headers['X-CSRF-TOKEN'] = meta.content;
    });

    document.body.addEventListener('htmx:afterSwap', function(e) {
        if (!e.detail.target || e.detail.target.id !== 'htmx-main') return;
        var initVue = document.getElementById('init-vue');
        if (!initVue || !window.__megaVueApp) return;

        // 1. Capturar DOM actual: shell estático (topbar+sidebar ya renderizados)
        //    + nuevo #htmx-main del servidor (contiene tags Vue sin instanciar).
        var snapshot = initVue.innerHTML;

        // 2. Desmontar Vue — limpia los hijos de #init-vue en el vdom.
        try { window.__megaVueApp.unmount(); } catch(ex) {}

        // 3. Restaurar el HTML capturado para que Vue tenga template al remontar.
        initVue.innerHTML = snapshot;

        // 4. Remontar — Vue instancia los tags de componentes en el nuevo #htmx-main.
        //    Topbar/sidebar quedan como HTML estático (sin reactivity) pero visuales.
        try { window.__megaVueApp.mount('#init-vue'); } catch(ex) {}

        // 5. Re-inicializar MetisMenu (Vue recreó el DOM del sidebar; permierde jQuery data).
        if (typeof jQuery !== 'undefined') {
            jQuery('#side-menu').metisMenu();
            if (typeof window.__attachSidebarPersistence === 'function') {
                window.__attachSidebarPersistence();
            }
            // Re-marcar link activo
            var cur = window.location.href.split(/[?#]/)[0];
            jQuery('#sidebar-menu a').each(function() {
                if (this.href === cur) {
                    jQuery(this).addClass('active');
                    jQuery(this).parent().addClass('active');
                    jQuery(this).parent().parent().prev().addClass('active');
                    jQuery(this).parent().parent().parent().parent().prev().addClass('active');
                }
            });
        }

        // 6. Re-inicializar Feather icons en el contenido nuevo.
        if (typeof feather !== 'undefined') feather.replace();
    });
</script>
<script src="{{ URL::asset('plugins/quasar/js/vue.global.prod.js') }}"></script>
<script src="{{ URL::asset('plugins/quasar/js/quasar.umd.prod.js') }}"></script>
<script src="{{ URL::asset('plugins/quasar/icon-set/fontawesome-v5.umd.prod.js') }}"></script>
<script src="{{ mix('js/app.js') }}"></script>

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

{{--
    DEPRECATED 2026-06-04: bundle viejo del tema Minia (abr 17, 225 líneas, solo jQuery/metisMenu/sidebar).
    Pisaba el bundle de Mix en scope global causando bugs fantasma en modales Vue.
    Toda su funcionalidad (sidebar, dark mode, idiomas) ya está cubierta por assets/js/theme.js u otros.
    Si algo del UI base se rompe, descomentar y reportar.
    <script src="{{ URL::asset('assets/js/app.js') }}"></script>
--}}

<script>
    jQuery.event.special.touchstart = {
        setup: function(_, ns, handle) {
            this.addEventListener("touchstart", handle, {
                passive: !ns.includes("noPreventDefault")
            });
        }
    };

    // Sidebar init — reemplaza assets/js/app.js (comentado) sin afectar Vue
    (function ($) {
        var SIDEBAR_STATE_KEY = 'meganet_sidebar_v1';

        // Deriva una clave única por <li>: usa data-key del <span>, o slugifica el texto
        function getSidebarItemKey($li) {
            var $span = $li.children('a').find('span[data-key]').first();
            if ($span.length) return $span.attr('data-key');
            var text = $li.children('a').find('span').first().text().trim();
            return text ? 'dyn-' + text.toLowerCase().replace(/[^a-z0-9]+/g, '-').substring(0, 40) : null;
        }

        // Restaurar estado ANTES de metisMenu() para evitar flash
        // MetisMenu respeta mm-active en init y abre los submenús sin animación
        function restoreSidebarState() {
            try {
                var state = JSON.parse(localStorage.getItem(SIDEBAR_STATE_KEY) || '{}');
                if (!Object.keys(state).length) return;
                $('#side-menu li').each(function () {
                    var $li = $(this);
                    if (!$li.children('ul.sub-menu').length) return;
                    var key = getSidebarItemKey($li);
                    if (key && state[key]) {
                        $li.addClass('mm-active');
                    }
                });
            } catch (e) {}
        }

        restoreSidebarState();

        // MetisMenu: acordeón del sidebar
        $("#side-menu").metisMenu();

        // Adjuntar listeners de persistencia de estado — expuesta como función global
        // para que htmx:afterSwap pueda re-adjuntarlos tras el remount de Vue.
        window.__attachSidebarPersistence = function() {
            $('#side-menu').off('shown.metisMenu hidden.metisMenu')
                .on('shown.metisMenu hidden.metisMenu', 'ul', function (e) {
                    var $li = $(this).parent('li');
                    var key = getSidebarItemKey($li);
                    if (!key) return;
                    try {
                        var state = JSON.parse(localStorage.getItem(SIDEBAR_STATE_KEY) || '{}');
                        if (e.type === 'shown') { state[key] = 1; } else { delete state[key]; }
                        localStorage.setItem(SIDEBAR_STATE_KEY, JSON.stringify(state));
                    } catch (ex) {}
                });
        };

        window.__attachSidebarPersistence();

        // Toggle colapso sidebar (botón hamburguesa)
        var _sidebarSize = document.body.getAttribute("data-sidebar-size");
        $("#vertical-menu-btn").on("click", function (e) {
            e.preventDefault();
            $("body").toggleClass("sidebar-enable");
            if ($(window).width() >= 992) {
                var cur = document.body.getAttribute("data-sidebar-size");
                if (!_sidebarSize || _sidebarSize === "lg") {
                    document.body.setAttribute("data-sidebar-size", cur === "sm" ? "lg" : "sm");
                } else if (_sidebarSize === "md") {
                    document.body.setAttribute("data-sidebar-size", cur === "md" ? "sm" : "md");
                } else {
                    document.body.setAttribute("data-sidebar-size", cur === "sm" ? "lg" : "sm");
                }
            }
        });

        // Marcar link activo en sidebar
        $("#sidebar-menu a").each(function () {
            var cur = window.location.href.split(/[?#]/)[0];
            if (this.href === cur) {
                $(this).addClass("active");
                $(this).parent().addClass("active");
                $(this).parent().parent().prev().addClass("active");
                $(this).parent().parent().parent().addClass("in");
                $(this).parent().parent().parent().parent().prev().addClass("active");
            }
        });

        // Feather icons (solo los del sidebar estático, no DOM de Vue)
        if (typeof feather !== "undefined") {
            feather.replace();
        }

        // Waves
        if (typeof Waves !== "undefined") {
            Waves.init();
        }

        // Right sidebar toggle
        $(".right-bar-toggle").on("click", function () {
            $("body").toggleClass("right-bar-enabled");
            // Sincronizar radio de layout-mode con el estado actual del body al abrir
            var curMode = document.body.getAttribute("data-layout-mode") || "light";
            var radio = document.getElementById("layout-mode-" + curMode);
            if (radio) radio.checked = true;
        });
        $(document).on("click", "body", function (e) {
            if ($(e.target).closest(".right-bar-toggle, .right-bar").length === 0) {
                $("body").removeClass("right-bar-enabled");
            }
        });
    })(jQuery);

    // Cambiar tema claro/oscuro desde el right-sidebar — global para onchange inline
    window.changeLayoutMode = function (mode) {
        document.body.setAttribute("data-layout-mode", mode);
        document.body.setAttribute("data-topbar", mode);
        document.body.setAttribute("data-sidebar", mode);
        window.axios && window.axios.post("/save-app-config-layout", { color_mode: mode })
            .catch(function (e) { console.warn("Layout mode save failed:", e); });
    };
</script>
