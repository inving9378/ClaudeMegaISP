<td class="text-nowrap">
    {{-- Orden (items #497/#505): Documento, Enviar, Marcar pagada y, separado al final, Borrar.
         UN solo ícono de documento según Invoice::documentoVigente() (lo calcula el helper de la
         tabla): Prefactura (pendiente), Factura sin valor fiscal (pagada) o CFDI (Fase 4). Cancelada:
         sin documento ni Enviar. «Imprimir» y «Prefactura actualizada» se fusionaron en este ícono. --}}
    @php
        $documento = $documento ?? null;
        $iconoDocumento = [
            'prefactura' => ['icono' => 'fa-file-invoice-dollar', 'titulo' => 'Prefactura', 'enviar' => 'Enviar prefactura'],
            'factura'    => ['icono' => 'fa-file-invoice', 'titulo' => 'Factura (sin valor fiscal)', 'enviar' => 'Enviar factura pagada'],
            'cfdi'       => ['icono' => 'fa-file-code', 'titulo' => 'CFDI', 'enviar' => 'Enviar CFDI'],
        ][$documento] ?? null;
    @endphp

    @if($iconoDocumento && auth()->user() && auth()->user()->can('invoice_print_invoice'))
        <a class="me-2" href="javascript:void(0);" id-item="{{ $id }}" data-toggle="tooltip" data-placement="top"
           title="{{ $iconoDocumento['titulo'] }}"><i class="fas {{ $iconoDocumento['icono'] }} invoice-documento"></i></a>
    @endif

    @if($iconoDocumento && auth()->user() && auth()->user()->can('invoice_send_invoice'))
        {{-- data-documento: la confirmación dice qué documento se enviará (Enviar manda lo mismo que el ícono). --}}
        <a class="me-2" href="javascript:void(0);" id-item="{{ $id }}" data-documento="{{ $documento }}"
           data-toggle="tooltip" data-placement="top"
           title="{{ $iconoDocumento['enviar'] }}"><i class="fas fa-paper-plane invoice-send"></i></a>
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
