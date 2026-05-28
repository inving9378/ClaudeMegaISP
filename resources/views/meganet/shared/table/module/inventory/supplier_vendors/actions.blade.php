<td class="text-center">
    <a class="me-2" 
       href="/inventory/supplier/{{ $supplier_id }}/vendors" 
       data-toggle="tooltip" 
       title="Ver Vendedores">
        <i class="fas fa-eye"></i>
    </a>

    @can('inventory_supplier_vendors_edit_supplier_vendors')
        <a class="me-2 uil-pen-modal" 
           href="javascript:void(0);" 
           id-item="{{ $id }}" 
           supplier-id="{{ $supplier_id }}"
           toggle-modal="crud-supplier-vendor"
           data-toggle="tooltip" 
           title="Editar Vendedor">
            <i class="far fa-edit"></i>
        </a>
    @endcan

    @can('inventory_supplier_vendors_delete_supplier_vendors')
        <a href="javascript:void(0);" 
           class="btn-delete-supplier-vendor"
           id-item="{{ $id }}" 
           supplier-id="{{ $supplier_id }}"
           data-toggle="tooltip" 
           title="Eliminar Vendedor">
            <i class="fas fa-trash text-danger"></i>
        </a>
    @endcan
</td>