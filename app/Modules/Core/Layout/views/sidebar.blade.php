<div class="vertical-menu">

    <div data-simplebar class="h-100">

        <!--- Sidemenu -->
        <div id="sidebar-menu">
            <ul class="metismenu list-unstyled" id="side-menu">
                <li class="menu-title" data-key="t-menu">Menu</li>

                {{-- Fallback global de seguridad (Fase 2.3): si el composer falla y no
                     devuelve items, no rompemos el render; avisamos sólo en consola JS. --}}
                @if(($sidebarItems ?? collect())->isEmpty())
                    <script>console.warn('Sidebar composer returned empty. Check ModuleSidebarConfig.');</script>
                @endif

                {{-- 1. Dashboard — Fase 2.2 dinámico --}}
                @if(isset($sidebarItems['dashboard']))
                    @include('module-sidebar.dashboard', ['item' => $sidebarItems['dashboard']->first()])
                @endif

                {{-- 2. Planes — Fase 2.2 dinámico --}}
                @if(isset($sidebarItems['planes']))
                    @include('module-sidebar.planes', ['item' => $sidebarItems['planes']->first()])
                @endif

                {{-- 3. Clientes potenciales (CRM) — Fase 2.2 dinámico --}}
                @if(isset($sidebarItems['crm']))
                    @include('module-sidebar.crm', ['item' => $sidebarItems['crm']->first()])
                @endif

                {{-- 4. Clientes — Fase 2.2 dinámico --}}
                @if(isset($sidebarItems['clientes']))
                    @include('module-sidebar.clientes', ['item' => $sidebarItems['clientes']->first()])
                @endif

                {{-- 5. Gestión de red — Fase 2.2 dinámico --}}
                @if(isset($sidebarItems['gestion-red']))
                    @include('module-sidebar.gestion-red', ['item' => $sidebarItems['gestion-red']->first()])
                @endif

                {{-- 6. Finanzas (incluye Marketing como sub_item) — Fase 2.2 dinámico --}}
                @if(isset($sidebarItems['finanzas']))
                    @include('module-sidebar.finanzas', ['item' => $sidebarItems['finanzas']->first()])
                @endif

                {{-- 7. Inventario — Fase 2.2 dinámico --}}
                @if(isset($sidebarItems['inventario']))
                    @include('module-sidebar.inventario', ['item' => $sidebarItems['inventario']->first()])
                @endif

                {{-- 8. OLTs — Fase 2.2 dinámico --}}
                @if(isset($sidebarItems['olts']))
                    @include('module-sidebar.olts', ['item' => $sidebarItems['olts']->first()])
                @endif

                {{-- 9. Mapas — renderizado dinámico (Fase 2.1) --}}
                @if(isset($sidebarItems['mapas']))
                    @include('module-sidebar.mapas', ['item' => $sidebarItems['mapas']->first()])
                @endif

                {{-- 10. Cobranza — Fase 2.2 dinámico --}}
                @if(isset($sidebarItems['cobranza-blaster']))
                    @include('module-sidebar.cobranza-blaster', ['item' => $sidebarItems['cobranza-blaster']->first()])
                @endif

                {{-- 11. MegaFamilia — Fase 2.2 dinámico --}}
                @if(isset($sidebarItems['megafamilia']))
                    @include('module-sidebar.megafamilia', ['item' => $sidebarItems['megafamilia']->first()])
                @endif

                {{-- 11.9 Scheduling — Fase 2.2 dinámico --}}
                @if(isset($sidebarItems['scheduling']))
                    @include('module-sidebar.scheduling', ['item' => $sidebarItems['scheduling']->first()])
                @endif

                {{-- 12. Embajadores — Fase 2.2 dinámico --}}
                @if(isset($sidebarItems['embajadores']))
                    @include('module-sidebar.embajadores', ['item' => $sidebarItems['embajadores']->first()])
                @endif

                {{-- 12.5 Flotas — Fase 2.2 dinámico --}}
                @if(isset($sidebarItems['flotas']))
                    @include('module-sidebar.flotas', ['item' => $sidebarItems['flotas']->first()])
                @endif

                {{-- 12.8 Talento Meganet — Fase 2.2 dinámico --}}
                @if(isset($sidebarItems['talento']))
                    @include('module-sidebar.talento', ['item' => $sidebarItems['talento']->first()])
                @endif

                {{-- Marketing — Fase 2.2b: liberado de Finanzas, ahora top-level con partial propio --}}
                @if(isset($sidebarItems['marketing']))
                    @include('module-sidebar.marketing', ['item' => $sidebarItems['marketing']->first()])
                @endif

                {{-- 13. War Room — accesible desde el panel de Administración (/administracion), no como ítem suelto del sidebar. --}}

                {{--
                    13.5 Módulos dinámicos desde ModuleRegistry::getMenu() (item #44 → #68).
                    $addonMenuItems lo inyecta SidebarComposer. Renderiza los módulos
                    activos que NO están hardcodeados arriba (lista $sidebarHardcoded),
                    evitando duplicados. Cualquier addon nuevo aparece aquí solo, sin
                    tocar este blade. Cuando un módulo migre a 100% registry, borrar su
                    bloque hardcodeado y quitar su slug de $sidebarHardcoded.
                --}}
                @php
                    $sidebarHardcoded = [
                        'addon-planes', 'addon-finanzas', 'addon-marketing', 'addon-payments',
                        'addon-gestion-red', 'addon-inventario', 'addon-mapas',
                        'addon-cobranza-blaster', 'addon-megafamilia', 'addon-embajadores',
                        'addon-flotas', 'addon-talento', 'addon-warroom', 'addon-devtools', 'core-configuracion',
                    ];
                    // Módulos accesibles solo desde los paneles Configuración / Administración:
                    // no se muestran como ítem suelto en el sidebar (evita duplicar la navegación).
                    $sidebarSuppressed = [
                        'addon-whatsapp-agent', 'addon-smart-import-export', 'addon-hub',
                        'addon-evaluador-empresarial', 'addon-manual',
                    ];
                    $authUser = auth()->user();
                    $canSee = fn ($perm) => empty($perm) || ($authUser && $authUser->can($perm));
                    $addonDynamic = collect($addonMenuItems ?? [])
                        ->reject(fn ($m) => in_array($m['_module'] ?? '', $sidebarHardcoded, true))
                        ->reject(fn ($m) => in_array($m['_module'] ?? '', $sidebarSuppressed, true))
                        ->filter(fn ($m) => $canSee($m['permission'] ?? null))
                        ->values();
                @endphp
                @if($addonDynamic->isNotEmpty())
                    <li class="menu-title" data-key="t-modulos">Módulos</li>
                    @foreach($addonDynamic as $mod)
                        @php
                            $children = collect($mod['children'] ?? [])
                                ->filter(fn ($c) => $canSee($c['permission'] ?? null))
                                ->values();
                        @endphp
                        <li>
                            @if($children->count() > 1)
                                <a href="javascript: void(0);" class="has-arrow">
                                    <i data-feather="{{ $mod['icon'] ?? 'box' }}"></i>
                                    <span>{{ $mod['label'] ?? $mod['_module'] }}</span>
                                </a>
                                <ul class="sub-menu" aria-expanded="false">
                                    @foreach($children as $child)
                                        <li>
                                            <a href="{{ url($child['url'] ?? '#') }}">
                                                <span><small><i class="fa fa-fw fa-angle-right"></i></small> {{ $child['label'] ?? '' }}</span>
                                            </a>
                                        </li>
                                    @endforeach
                                </ul>
                            @else
                                <a href="{{ url($mod['url'] ?? '#') }}">
                                    <i data-feather="{{ $mod['icon'] ?? 'box' }}"></i>
                                    <span>{{ $mod['label'] ?? $mod['_module'] }}</span>
                                </a>
                            @endif
                        </li>
                    @endforeach
                @endif

                {{-- 14. Administración — Fase 2.2 dinámico --}}
                @if(isset($sidebarItems['administracion']))
                    @include('module-sidebar.administracion', ['item' => $sidebarItems['administracion']->first()])
                @endif

                {{-- 15. Configuración — Fase 2.2 dinámico --}}
                @if(isset($sidebarItems['configuracion']))
                    @include('module-sidebar.configuracion', ['item' => $sidebarItems['configuracion']->first()])
                @endif

                {{-- Desarrollador — Fase 2.2 dinámico --}}
                @if(isset($sidebarItems['devtools']))
                    @include('module-sidebar.devtools', ['item' => $sidebarItems['devtools']->first()])
                @endif

            </ul>
        </div>
        <!-- Sidebar -->
    </div>
</div>

<style>
    /* Separador de sección dentro de un sub-menu (ej. "Marketing" dentro de Finanzas) */
    #side-menu .sidebar-section-divider {
        padding: 10px 20px 4px;
        pointer-events: none;
        list-style: none;
    }
    #side-menu .sidebar-section-label {
        font-size: 10px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.8px;
        color: var(--bs-secondary-color, #6c757d);
        opacity: 0.75;
    }
    [data-layout-mode="dark"] #side-menu .sidebar-section-label,
    [data-topbar="dark"] #side-menu .sidebar-section-label {
        color: rgba(255, 255, 255, 0.4);
        opacity: 1;
    }

    /* Desarrollador — acento naranja, solo visible con role:DESARROLLADOR */
    #side-menu .menu-item-desarrollador > a.link-desarrollador {
        color: #ff8c00 !important;
        font-weight: 600;
        border-left: 3px solid #ff8c00;
        background: rgba(255, 140, 0, 0.06);
    }
    #side-menu .menu-item-desarrollador > a.link-desarrollador:hover {
        color: #ffffff !important;
        background: #ff8c00;
    }
    #side-menu .menu-item-desarrollador > a.link-desarrollador svg {
        color: #ff8c00;
    }
    #side-menu .menu-item-desarrollador > a.link-desarrollador:hover svg {
        color: #ffffff;
    }
    [data-layout-mode="dark"] #side-menu .menu-item-desarrollador > a.link-desarrollador {
        background: rgba(255, 140, 0, 0.10);
    }
</style>
