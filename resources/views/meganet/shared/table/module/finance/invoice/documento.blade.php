{{-- Columna «Documento» (item #505): mismo Invoice::documentoVigente() que el ícono único. --}}
@php
    $badge = [
        'prefactura' => ['Proforma', '#FEF6E7', '#B45309', '#F5D9A8'],
        'factura'    => ['Factura s/v fiscal', '#E9F5EF', '#14724A', '#B7E1CB'],
        'cfdi'       => ['Factura CFDI', '#E8F1F9', '#0B5A9E', '#B9D3EA'],
    ][$documento ?? null] ?? null;
@endphp
@if($badge)
    <span class="text-nowrap" style="display:inline-block;padding:2px 8px;border-radius:10px;font-size:11px;font-weight:600;background:{{ $badge[1] }};color:{{ $badge[2] }};border:1px solid {{ $badge[3] }};">{{ $badge[0] }}</span>
@else
    <span class="text-muted">—</span>
@endif
