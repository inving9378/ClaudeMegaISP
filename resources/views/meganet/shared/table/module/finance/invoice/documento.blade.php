{{-- Columna «Documento» (item #505): mismo Invoice::documentoVigente() que el ícono único.
     Clases en resources/sass/module/finance/invoice/_documento.scss (claro y oscuro). --}}
@php($etiqueta = \App\Models\Invoice::DOCUMENTOS[$documento ?? '']['etiqueta'] ?? null)
@if($etiqueta)
    <span class="doc-factura doc-factura--{{ $documento }}">{{ $etiqueta }}</span>
@else
    <span class="text-muted">—</span>
@endif
