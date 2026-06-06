@canany(['cobranza.view', 'cobranza.configure'])
    <li>
        <a href="javascript: void(0);" class="has-arrow">
            <i data-feather="phone-call"></i>
            <span data-key="t-cobranza">{{ $item->sidebar_label ?? 'Cobranza' }}</span>
        </a>
        <ul class="sub-menu" aria-expanded="false">
            @can('cobranza.view')
                <li>
                    <a href="{{ url('/cobranza/campanas') }}">
                        <span><small><i class="fa fa-fw fa-broadcast-tower"></i></small> Campañas</span>
                    </a>
                </li>
            @endcan
            @can('cobranza.configure')
                <li>
                    <a href="{{ url('/cobranza/voip') }}">
                        <span><small><i class="fa fa-fw fa-phone-square"></i></small> Config. VoIP</span>
                    </a>
                </li>
            @endcan

            {{-- Hijos dinámicos desde module_sidebar_config (Fase 2.3/3.5) --}}
            @foreach($item->dynamic_children ?? collect() as $child)
                <li><a href="{{ $child->sidebar_url ? url($child->sidebar_url) : url('/' . $child->module_key) }}"><span>@if($child->sidebar_icon)<small><i class="{{ $child->sidebar_icon }}"></i></small> @endif{{ $child->sidebar_label ?? $child->module_key }}</span></a></li>
            @endforeach
        </ul>
    </li>
@endcanany
