<li>
    @canany(['plan_view_internet', 'plan_view_voz', 'plan_view_custom', 'plan_view_package', 'plan_view_catalog'])
        <a href="javascript: void(0);" class="has-arrow">
            <i data-feather="grid"></i>
            <span data-key="t-planes">{{ $item->sidebar_label ?? 'Planes' }}</span>
        </a>
    @endcanany
    <ul class="sub-menu" aria-expanded="false">
        @can('plan_view_internet')
            <li>
                <a href="{{ url('/internet') }}">
                    <span data-key="t-internet"><small><i class="fa fa-fw fa-wifi"></i></small> Internet</span>
                </a>
            </li>
        @endcan
        @can('plan_view_voz')
            <li>
                <a href="{{ url('/voz') }}">
                    <span data-key="t-voz"><small><i class="fa fa-fw fa-phone"></i></small> Voz</span>
                </a>
            </li>
        @endcan
        @can('plan_view_custom')
            <li>
                <a href="{{ url('/custom') }}">
                    <span data-key="t-custom"><small><i class="fa fa-fw fa-sitemap"></i></small> Personalizado</span>
                </a>
            </li>
        @endcan
        @can('plan_view_package')
            <li>
                <a href="{{ url('/paquetes') }}">
                    <span data-key="t-paquetes"><small><i class="fa fa-fw fa-object-group"></i></small> Paquetes</span>
                </a>
            </li>
        @endcan
        @can('plan_view_catalog')
            <li>
                <a href="{{ url('/planes/catalogo') }}">
                    <span data-key="t-catalogo-servicios"><small><i class="fa fa-fw fa-cubes"></i></small> Servicios contratables</span>
                </a>
            </li>
        @endcan
    </ul>
</li>
