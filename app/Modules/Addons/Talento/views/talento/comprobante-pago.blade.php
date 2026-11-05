<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Comprobante de pago {{ $folio }}</title>
    {{-- Mismo formato que el ticket de mostrador (resources/views/billing/ticket.blade.php): 80 mm. --}}
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
        .bold   { font-weight: bold; }
        .sep    { border-top: 1px dashed #000; margin: 4px 0; }
        .sep2   { border-top: 2px solid #000; margin: 4px 0; }
        .row    { display: table; width: 100%; }
        .row .l { display: table-cell; }
        .row .r { display: table-cell; text-align: right; }
        .aviso {
            font-size: 9px; font-weight: bold;
            border: 1px solid #000; padding: 2px 4px;
            display: inline-block; margin: 3px 0;
        }
        .firma { border-top: 1px solid #000; margin: 34px 6mm 2px; }
    </style>
</head>
<body>

    {{-- Empresa --}}
    @if($company)
        <div class="center bold" style="font-size:10px">{{ strtoupper($company->company_name) }}</div>
        <div class="center" style="font-size:8px">
            RFC: {{ $company->rfc }}<br>
            @if($company->company_street){{ $company->company_street }} {{ $company->company_external_number }},@endif
            {{ $company->colony_name }}, {{ $company->municipality_name }}
        </div>
        <div class="sep2"></div>
    @endif

    <div class="center bold" style="font-size:10px">COMPROBANTE DE PAGO</div>
    <div class="sep"></div>
    <div class="row"><span class="l">Folio:</span><span class="r bold">{{ $folio }}</span></div>
    <div class="row"><span class="l">Semana:</span><span class="r">{{ $desde }} al {{ $hasta }}</span></div>
    <div class="sep"></div>

    <div class="bold">COLABORADOR</div>
    <div>{{ mb_strtoupper($nombre) }}</div>
    @if($puesto)<div>Puesto: {{ $puesto }}</div>@endif
    <div class="sep"></div>

    <div class="bold">DE QUÉ SE COMPONE</div>
    <div class="row"><span class="l">Unidades de la semana</span><span class="r">{{ $pago['unidades'] }}</span></div>
    @if($faltas > 0)
        <div class="row"><span class="l">Faltas</span><span class="r">{{ $faltas }}</span></div>
    @endif
    <div class="row"><span class="l">Sueldo base{{ $faltas > 0 ? ' (con faltas)' : '' }}</span><span class="r">${{ number_format($pago['sueldo_base'], 2) }}</span></div>
    <div class="row"><span class="l">Sobreproducción</span><span class="r">${{ number_format($pago['sobreproduccion'], 2) }}</span></div>
    @if($pago['bonos'] > 0)
        <div class="row"><span class="l">Bonos</span><span class="r">+${{ number_format($pago['bonos'], 2) }}</span></div>
    @endif
    @if($pago['descuentos'] > 0)
        <div class="row"><span class="l">Penalizaciones y descuentos</span><span class="r">-${{ number_format($pago['descuentos'], 2) }}</span></div>
    @endif
    @if($pago['prestamo_y_fondo'] > 0)
        <div class="row"><span class="l">Préstamo y fondo de ahorro</span><span class="r">-${{ number_format($pago['prestamo_y_fondo'], 2) }}</span></div>
    @endif
    <div class="sep2"></div>

    <div class="row bold" style="font-size:11px">
        <span class="l">A PAGAR:</span>
        <span class="r">{{ $pago['a_pagar'] < 0 ? '-' : '' }}${{ number_format(abs($pago['a_pagar']), 2) }} MXN</span>
    </div>
    @if($pago['a_pagar'] > 0)
        <div class="center" style="font-size:8px">({{ $enLetra }})</div>
    @endif
    <div class="sep2"></div>

    <div class="center">
        @if($pago['cerrada'])
            <span class="aviso">SEMANA CERRADA</span>
        @elseif($enCurso)
            <span class="aviso">SEMANA EN CURSO: NO ES EL PAGO FINAL</span>
        @else
            <div style="font-size:8px">Calculado el {{ $impreso }}. La semana todavía no se cierra.</div>
        @endif
    </div>
    <div class="sep"></div>

    @if($pago['a_pagar'] > 0)
        <div style="font-size:8px">
            Recibí la cantidad de ${{ number_format($pago['a_pagar'], 2) }} por mi trabajo de la semana
            del {{ $desde }} al {{ $hasta }}.
        </div>
    @else
        <div style="font-size:8px">Esta semana no hay pago: los descuentos son iguales o mayores a lo ganado.</div>
    @endif

    <div class="firma"></div>
    <div class="center">{{ mb_strtoupper($nombre) }}</div>
    <div class="center" style="font-size:8px">Nombre y firma de quien recibe</div>
    <div class="sep"></div>

    <div class="row"><span class="l">Entregó:</span><span class="r">{{ $entrego }}</span></div>
    <div class="row"><span class="l">Impreso:</span><span class="r">{{ $impreso }}</span></div>
    @if($company)
        <div class="sep"></div>
        <div class="center" style="font-size:7px">{{ $company->company_name }} · {{ $company->rfc }}</div>
    @endif

</body>
</html>
