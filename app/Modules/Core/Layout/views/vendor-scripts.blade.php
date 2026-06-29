<script src="{{ URL::asset('plugins/quasar/js/vue.global.prod.js') }}"></script>
<script src="{{ URL::asset('plugins/quasar/js/quasar.umd.prod.js') }}"></script>
<script src="{{ URL::asset('plugins/quasar/icon-set/fontawesome-v5.umd.prod.js') }}"></script>
<script src="{{ mix('js/app.js') }}"></script>
{{-- OpenPay public credentials — usadas por DomiciliacionClientTab.vue (public key es segura en JS) --}}
<script>
    window.__OPENPAY_ID__      = {{ json_encode(config('openpay.id', '')) }};
    window.__OPENPAY_PK__      = {{ json_encode(config('openpay.public_key', '')) }};
    window.__OPENPAY_SANDBOX__ = {{ config('openpay.sandbox', true) ? 'true' : 'false' }};
</script>

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

        // Restaurar estado ANTES de metisMenu() para evitar flash.
        // sessionStorage: persiste el F5 dentro de la misma pestaña pero se borra
        // al cerrar pestaña/navegador o al hacer logout (ver __clearSidebarState).
        function restoreSidebarState() {
            try {
                var state = JSON.parse(sessionStorage.getItem(SIDEBAR_STATE_KEY) || '{}');
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
        // reutilizable (persiste el estado abierto/cerrado de los submenús del sidebar).
        window.__attachSidebarPersistence = function() {
            $('#side-menu').off('shown.metisMenu hidden.metisMenu')
                .on('shown.metisMenu hidden.metisMenu', 'ul', function (e) {
                    var $li = $(this).parent('li');
                    var key = getSidebarItemKey($li);
                    if (!key) return;
                    try {
                        var state = JSON.parse(sessionStorage.getItem(SIDEBAR_STATE_KEY) || '{}');
                        if (e.type === 'shown') { state[key] = 1; } else { delete state[key]; }
                        sessionStorage.setItem(SIDEBAR_STATE_KEY, JSON.stringify(state));
                    } catch (ex) {}
                });
        };

        // Limpia el estado del sidebar (llamado en logout para que la siguiente
        // sesión arranque con todos los grupos cerrados).
        window.__clearSidebarState = function () {
            try { sessionStorage.removeItem(SIDEBAR_STATE_KEY); } catch (e) {}
        };

        window.__attachSidebarPersistence();

        // Toggle colapso sidebar (botón hamburguesa)
        // Event delegation: Vue re-crea el nodo #vertical-menu-btn al montar
        // el topbar mini-app, rompiendo el binding directo. document escucha siempre.
        var _sidebarSize = document.body.getAttribute("data-sidebar-size");
        $(document).on("click", "#vertical-menu-btn", function (e) {
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

        // Marcar link activo en sidebar — re-llamable para SPA navigation.
        // No expande grupos: el usuario controla apertura/cierre manualmente.
        // "active" en el header del grupo cerrado indica visualmente en qué sección está.
        window.__updateSidebarActiveLink = function (url) {
            var cur = (url || window.location.href).split(/[?#]/)[0];
            // Limpiar marcas previas (necesario en navegación SPA)
            $('#sidebar-menu a.active').removeClass('active');
            $('#sidebar-menu li.active').removeClass('active');
            $('#sidebar-menu a').each(function () {
                if (this.href === cur) {
                    $(this).addClass('active');                            // el link
                    $(this).parent().addClass('active');                   // su <li>
                    $(this).parent().parent().prev().addClass('active');   // header del grupo padre
                    $(this).parent().parent().parent().parent().prev()
                           .addClass('active');                            // header del grupo abuelo (2 niveles)
                }
            });
        };
        window.__updateSidebarActiveLink();

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
