<td>
    @can('invoice_send_invoice')
        <a class="me-2" href="javascript:void(0);" id-item="{{ $id }}" data-toggle="tooltip" data-placement="top"
           title="Enviar"><i class="fas fa-paper-plane invoice-send"></i></a>
    @endcan

    @can('invoice_print_invoice')
        <a class="me-2" href="javascript:void(0);" id-item="{{ $id }}" data-toggle="tooltip" data-placement="top"
           title="Imprimir"><i class="fas fa-file-pdf invoice-print"></i></a>
    @endcan

    @can('invoice_mark_invoice')
        @if ($statusClass != 'Pagada')
            <a id-item="{{ $id }}" class="mr-2" href="javascript:void(0);" data-toggle="tooltip"
               data-placement="top" title="marcar como pagada"><i class="fas fa-check mark-as-paid"></i></a>
        @endif
    @endcan
    @can('invoice_delete_invoice')
        <a class="" href="javascript:void(0);" id-item="{{ $id }}" data-toggle="tooltip" data-placement="top"
           title="Borrar"><i class="fas fa-trash"></i></a>
    @endcan
</td>
