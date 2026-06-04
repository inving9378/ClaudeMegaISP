<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Ticket {{ $folio }}</title>
    <style>
        @page { size: 80mm auto; margin: 4mm 3mm; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: "Courier New", Courier, monospace;
            font-size: 9px;
            color: #000;
            width: 74mm;
        }
        .center { text-align: center; }
        .right  { text-align: right; }
        .bold   { font-weight: bold; }
        .sep    { border-top: 1px dashed #000; margin: 4px 0; }
        .sep2   { border-top: 2px solid #000; margin: 4px 0; }
        .row    { display: table; width: 100%; }
        .row .l { display: table-cell; }
        .row .r { display: table-cell; text-align: right; }
        .no-fiscal {
            font-size: 8px; font-weight: bold;
            border: 1px solid #000; padding: 1px 3px;
            display: inline-block; margin: 3px 0;
        }
        .sello {
            font-size: 12px; font-weight: bold;
            border: 2px solid #000; padding: 3px 6px;
            display: inline-block; margin: 4px 0;
        }
    </style>
</head>
<body>

    {{-- Empresa --}}
    <div class="center bold" style="font-size:10px">{{ strtoupper($company->company_name) }}</div>
    <div class="center" style="font-size:8px">
        RFC: {{ $company->rfc }}<br>
        @if($company->company_street){{ $company->company_street }} {{ $company->company_external_number }},@endif
        {{ $company->colony_name }}, {{ $company->municipality_name }}<br>
        C.P. {{ $company->company_postal_code }} | Tel: {{ $company->atention_client_phone ?? '—' }}
    </div>
    <div class="sep2"></div>

    {{-- Cabecera ticket --}}
    <div class="center bold" style="font-size:10px">TICKET DE MOSTRADOR</div>
    <div class="sep"></div>
    <div class="row"><span class="l">Folio:</span><span class="r bold">{{ $folio }}</span></div>
    <div class="row"><span class="l">Fecha:</span><span class="r">{{ $fecha }}</span></div>
    <div class="row"><span class="l">Hora:</span><span class="r">{{ $hora }}</span></div>
    <div class="row"><span class="l">Cajero:</span><span class="r">{{ $cajero }}</span></div>
    <div class="sep"></div>

    {{-- Cliente --}}
    <div class="bold">CLIENTE</div>
    <div>{{ $client->client_main_information->full_name ?? $client->client_main_information->name }}</div>
    <div>ID: {{ $client->id }}</div>
    <div class="sep"></div>

    {{-- Conceptos --}}
    <div class="bold">CONCEPTOS</div>
    @foreach($items as $item)
        <div class="row">
            <span class="l" style="max-width:52mm;display:table-cell;overflow:hidden">{{ $item['name'] }}</span>
            <span class="r">${{ number_format($item['total'], 2) }}</span>
        </div>
    @endforeach
    <div class="sep"></div>

    {{-- Totales --}}
    <div class="row"><span class="l">Subtotal:</span><span class="r">${{ number_format($subtotal, 2) }}</span></div>
    <div class="row"><span class="l">IVA (16%):</span><span class="r">${{ number_format($tax, 2) }}</span></div>
    <div class="sep"></div>
    <div class="row bold" style="font-size:11px"><span class="l">TOTAL:</span><span class="r">${{ number_format($total, 2) }} MXN</span></div>
    <div class="sep2"></div>

    {{-- Método de pago --}}
    <div class="row"><span class="l">Método:</span><span class="r bold">{{ strtoupper($metodoPago) }}</span></div>
    @if($referencia)
        <div class="row"><span class="l">Referencia:</span><span class="r">{{ $referencia }}</span></div>
    @endif
    <div class="row"><span class="l">Pago ID:</span><span class="r">{{ $payment->id }}</span></div>
    <div class="sep"></div>

    {{-- Leyenda fiscal --}}
    <div class="center">
        <span class="no-fiscal">*** SIN VALOR FISCAL ***</span>
    </div>
    <div class="center" style="font-size:8px">Este comprobante no sustituye a una factura CFDI.</div>
    <div class="center" style="font-size:8px">Gracias por su pago.</div>
    <div class="sep"></div>
    <div class="center" style="font-size:7px">{{ $company->company_name }} · {{ $company->rfc }}</div>

</body>
</html>
