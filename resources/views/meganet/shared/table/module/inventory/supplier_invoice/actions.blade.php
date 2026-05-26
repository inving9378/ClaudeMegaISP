<td>
    <a class="me-2" href="/inventory/supplier-invoice/show/{{ $id }}" data-toggle="tooltip" title="Ver Detalle">
        <i class="fas fa-eye"></i>
    </a>
    @if($status === 'pending')
        <a class="me-2" href="/inventory/supplier-invoice/receive/{{ $id }}" data-toggle="tooltip" title="Recibir Factura">
            <i class="fas fa-check-circle text-success"></i>
        </a>
        <a href="javascript:void(0);" id-item="{{ $id }}" data-toggle="tooltip" title="Eliminar">
            <i class="fas fa-trash"></i>
        </a>
    @endif
</td>
