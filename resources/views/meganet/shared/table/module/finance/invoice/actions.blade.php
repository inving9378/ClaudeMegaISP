<td class="text-nowrap">
    {{-- Orden (item #497): Prefactura, Imprimir, Enviar, Marcar pagada y, separado al final, Borrar. --}}
    {{-- Item #480: PDF informativo con la plantilla «Prefactura.» y los datos de hoy. --}}
    @if(auth()->user() && auth()->user()->can('finanzas_prefactura_generar') && $statusClass != 'Cancelada')
        <a class="me-2" href="javascript:void(0);" id-item="{{ $id }}" data-toggle="tooltip" data-placement="top"
           title="Prefactura actualizada"><i class="fas fa-file-invoice-dollar invoice-prefactura"></i></a>
    @endif

    @if(auth()->user() && auth()->user()->can('invoice_print_invoice'))
        <a class="me-2" href="javascript:void(0);" id-item="{{ $id }}" data-toggle="tooltip" data-placement="top"
           title="Imprimir"><i class="fas fa-file-pdf invoice-print"></i></a>
    @endif

    @if(auth()->user() && auth()->user()->can('invoice_send_invoice'))
        {{-- data-pagada (items #498/#505): la confirmación avisa que se enviará como factura PAGADA. --}}
        <a class="me-2" href="javascript:void(0);" id-item="{{ $id }}" data-pagada="{{ $statusClass == 'Pagada' ? 1 : 0 }}"
           data-toggle="tooltip" data-placement="top"
           title="{{ $statusClass == 'Pagada' ? 'Enviar factura pagada' : 'Enviar' }}"><i class="fas fa-paper-plane invoice-send"></i></a>
    @endif

    @if(auth()->user() && auth()->user()->can('invoice_mark_invoice'))
        @if ($statusClass != 'Pagada')
            <a id-item="{{ $id }}" class="me-2" href="javascript:void(0);" data-toggle="tooltip"
               data-placement="top" title="marcar como pagada"><i class="fas fa-check mark-as-paid"></i></a>
        @endif
    @endif

    @if(auth()->user() && auth()->user()->can('invoice_delete_invoice'))
        {{-- Separado del resto para no borrar por error. La confirmación («¿Está seguro que desea
             eliminar?») la pone Datatable.vue sobre `.fa-trash`: conservar <a id-item><i class="fa-trash">. --}}
        <span class="d-inline-block align-middle ms-1 me-3" style="width:1px;height:14px;background:#cbd5e1;"></span>
        <a class="text-danger" href="javascript:void(0);" id-item="{{ $id }}" data-toggle="tooltip" data-placement="top"
           title="Borrar"><i class="fas fa-trash"></i></a>
    @endif
</td>
