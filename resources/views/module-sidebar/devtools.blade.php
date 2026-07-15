@hasanyrole('DESARROLLADOR|super-administrator')
    <li class="menu-item-desarrollador">
        <a href="javascript: void(0);" class="has-arrow link-desarrollador">
            <i data-feather="code"></i>
            <span data-key="t-desarrollador">{{ $item->sidebar_label ?? 'Desarrollador' }}</span>
        </a>
        <ul class="sub-menu" aria-expanded="false">
            <li>
                <a href="{{ url('/devtools') }}">
                    <span data-key="t-devtools"><small><i class="fa fa-fw fa-terminal"></i></small> DevTools</span>
                </a>
            </li>
            @can('release_view_release')
                <li>
                    <a href="{{ url('/releases') }}">
                        <span data-key="t-torre-control"><small><i class="fa fa-fw fa-broadcast-tower"></i></small> Torre de Control</span>
                    </a>
                </li>
            @endcan

            {{-- Hijos dinámicos desde module_sidebar_config (Fase 2.3/3.5) --}}
            @foreach($item->dynamic_children ?? collect() as $child)
                <li><a href="{{ $child->sidebar_url ? url($child->sidebar_url) : url('/' . $child->module_key) }}"><span>@if($child->sidebar_icon)<small><i class="{{ $child->sidebar_icon }}"></i></small> @endif{{ $child->sidebar_label ?? $child->module_key }}</span></a></li>
            @endforeach
        </ul>
    </li>
@endhasanyrole
