@if(auth()->user()->can('voip.view'))
    <li>
        <a href="javascript: void(0);" class="has-arrow">
            <i data-feather="phone"></i>
            <span data-key="t-voip">{{ $item->sidebar_label ?? 'VoIP' }}</span>
        </a>
        <ul class="sub-menu" aria-expanded="false">
            @if(auth()->user()->can('voip.troncales.view'))
                <li>
                    <a href="{{ url('/voip/troncales') }}">
                        <span><small><i class="fa fa-fw fa-sitemap"></i></small> Troncales</span>
                    </a>
                </li>
            @endif
            @if(auth()->user()->can('voip.extensiones.view'))
                <li>
                    <a href="{{ url('/voip/extensiones') }}">
                        <span><small><i class="fa fa-fw fa-phone"></i></small> Extensiones</span>
                    </a>
                </li>
            @endif
            @if(auth()->user()->can('voip.grupos.view'))
                <li>
                    <a href="{{ url('/voip/grupos-timbrado') }}">
                        <span><small><i class="fa fa-fw fa-users"></i></small> Grupos de timbrado</span>
                    </a>
                </li>
            @endif
            @if(auth()->user()->can('voip.ia-bot.view'))
                <li>
                    <a href="{{ url('/voip/ia-bot') }}">
                        <span><small><i class="fa fa-fw fa-robot"></i></small> Asistente IA</span>
                    </a>
                </li>
            @endif

            {{-- Hijos dinámicos desde module_sidebar_config --}}
            @foreach($item->dynamic_children ?? collect() as $child)
                <li>
                    <a href="{{ $child->sidebar_url ? url($child->sidebar_url) : url('/' . $child->module_key) }}">
                        <span>
                            @if($child->sidebar_icon)<small><i class="{{ $child->sidebar_icon }}"></i></small> @endif
                            {{ $child->sidebar_label ?? $child->module_key }}
                        </span>
                    </a>
                </li>
            @endforeach
        </ul>
    </li>
@endif
