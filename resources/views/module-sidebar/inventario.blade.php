<li>
    @canany(['inventory_view_inventory', 'inventory_item_view_inventory_item',
             'inventory_item_type_view_inventory_item_type', 'inventory_movement_view_inventory_movement',
             'inventory_store_view_inventory_store', 'inventory_item_custom_model_view_inventory_item_custom_model',
             'inventory_supplier_view_supplier', 'inventory_supplier_add_supplier',
             'inventory_valuation_view_inventory_valuation'])
        <a href="javascript: void(0);" class="has-arrow">
            <i data-feather="archive"></i>
            <span data-key="t-inventario">{{ $item->sidebar_label ?? 'Inventario' }}</span>
        </a>
    @endcanany
    <ul class="sub-menu" aria-expanded="false">
        <li>
            @canany(['inventory_store_view_inventory_store'])
                <a href="javascript: void(0);" class="has-arrow">
                    <i data-feather="layers"></i>
                    <span data-key="t-almacenes">Almacenes</span>
                </a>
            @endcanany
            <ul class="sub-menu" aria-expanded="false">
                @can('inventory_store_view_inventory_store')
                    <li>
                        <a href="{{ url('/inventory/inventory_store') }}">
                            <span data-key="t-almacenes-listar"><small><i class="fa fa-fw fa-list"></i></small> Listar</span>
                        </a>
                    </li>
                @endcan
            </ul>
        </li>
        <li>
            @canany(['inventory_item_view_inventory_item'])
                <a href="javascript: void(0);" class="has-arrow">
                    <i data-feather="layers"></i>
                    <span data-key="t-tipos-art">Tipo de Artículos</span>
                </a>
            @endcanany
            <ul class="sub-menu" aria-expanded="false">
                @can('inventory_item_view_inventory_item')
                    <li>
                        <a href="{{ url('/inventory/inventory_item_type') }}">
                            <span data-key="t-tipos-art-listar"><small><i class="fa fa-fw fa-list"></i></small> Listar</span>
                        </a>
                    </li>
                @endcan
            </ul>
        </li>
        <li>
            @canany(['inventory_item_view_inventory_item', 'inventory_item_custom_model_view_inventory_item_custom_model'])
                <a href="javascript: void(0);" class="has-arrow">
                    <i data-feather="package"></i>
                    <span data-key="t-articulos">Artículos</span>
                </a>
            @endcanany
            <ul class="sub-menu" aria-expanded="false">
                @can('inventory_item_view_inventory_item')
                    <li>
                        <a href="{{ url('/inventory/inventory_item_stock') }}">
                            <span data-key="t-articulos-listar"><small><i class="fa fa-fw fa-list"></i></small> Listar</span>
                        </a>
                    </li>
                @endcan
                @can('inventory_item_custom_model_view_inventory_item_custom_model')
                    <li>
                        <a href="{{ url('/inventory/inventory_item_custom_model') }}">
                            <span data-key="t-articulos-custom"><small><i class="fa fa-fw fa-list"></i></small> Artículos Custom</span>
                        </a>
                    </li>
                @endcan
            </ul>
        </li>
        <li>
            @canany(['inventory_movement_view_inventory_movement'])
                <a href="javascript: void(0);" class="has-arrow">
                    <i data-feather="shuffle"></i>
                    <span data-key="t-movimientos">Movimientos</span>
                </a>
            @endcanany
            <ul class="sub-menu" aria-expanded="false">
                @can('inventory_movement_view_inventory_movement')
                    <li>
                        <a href="{{ url('/inventory/inventory_movement') }}">
                            <span data-key="t-movimientos-listar"><small><i class="fa fa-fw fa-list"></i></small> Listar</span>
                        </a>
                    </li>
                @endcan
            </ul>
        </li>
        <li>
            @canany(['inventory_supplier_view_supplier', 'inventory_supplier_add_supplier'])
                <a href="javascript: void(0);" class="has-arrow">
                    <i data-feather="truck"></i>
                    <span data-key="t-proveedores">Proveedores</span>
                </a>
            @endcanany
            <ul class="sub-menu" aria-expanded="false">
                @can('inventory_supplier_add_supplier')
                    <li>
                        <a href="{{ url('/inventory/supplier/create') }}">
                            <span><small><i class="fa fa-fw fa-plus"></i></small> Crear</span>
                        </a>
                    </li>
                @endcan
                @can('inventory_supplier_view_supplier')
                    <li>
                        <a href="{{ url('/inventory/supplier') }}">
                            <span data-key="t-proveedores-listar"><small><i class="fa fa-fw fa-list"></i></small> Listar</span>
                        </a>
                    </li>
                @endcan
                @can('inventory_supplier_invoice_view_supplier_invoice')
                    <li>
                        <a href="javascript: void(0);" class="has-arrow">
                            <i data-feather="file-text"></i>
                            <span data-key="t-fact-prov">Facturas</span>
                        </a>
                        <ul class="sub-menu" aria-expanded="false">
                            @can('inventory_supplier_invoice_add_supplier_invoice')
                                <li>
                                    <a href="{{ url('/inventory/supplier-invoice/create') }}">
                                        <span><small><i class="fa fa-fw fa-plus"></i></small> Crear</span>
                                    </a>
                                </li>
                            @endcan
                            <li>
                                <a href="{{ url('/inventory/supplier-invoice') }}">
                                    <span><small><i class="fa fa-fw fa-list"></i></small> Listar</span>
                                </a>
                            </li>
                        </ul>
                    </li>
                @endcan
            </ul>
        </li>
        @can('inventory_valuation_view_inventory_valuation')
            <li>
                <a href="{{ url('/inventory/inventory-valuation') }}">
                    <i data-feather="bar-chart-2"></i>
                    <span data-key="t-valuacion">Valuación Inventario</span>
                </a>
            </li>
        @endcan

        {{-- Hijos dinámicos desde module_sidebar_config (Fase 2.3/3.5) --}}
        @foreach($item->dynamic_children ?? collect() as $child)
            <li>
                <a href="{{ $child->sidebar_url ? url($child->sidebar_url) : url('/' . $child->module_key) }}">
                    <span>@if($child->sidebar_icon)<small><i class="{{ $child->sidebar_icon }}"></i></small> @endif{{ $child->sidebar_label ?? $child->module_key }}</span>
                </a>
            </li>
        @endforeach
    </ul>
</li>
