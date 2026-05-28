<td>
    <a class="me-2" href="/inventory/supplier/show/{{ $id }}" data-toggle="tooltip" title="Ver Detalle">
        <i class="fas fa-eye"></i>
    </a>
    @can('supplier_edit_supplier')
        <a class="me-2 uil-pen-modal" href="javascript:void(0);" id-item="{{ $id }}" toggle-modal="crudsupplier"
            data-toggle="tooltip" title="Editar">
            <i class="far fa-edit"></i>
        </a>
    @endcan
    @can('supplier_delete_supplier')
        <a href="javascript:void(0);" id-item="{{ $id }}" data-toggle="tooltip" title="Eliminar">
            <i class="fas fa-trash"></i>
        </a>
    @endcan
</td>
