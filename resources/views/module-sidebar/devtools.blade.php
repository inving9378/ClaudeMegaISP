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
        </ul>
    </li>
@endhasanyrole
