<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{{ $titulo }} — Cliente {{ $cliente_numero }}</title>
    {{-- FR-02 (#536): estado de cuenta, documento informativo SIN valor fiscal. --}}
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 11px; color: #1a1a1a; }
        .page { padding: 28px 32px; }
        .header { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        .header img { height: 40px; }
        .empresa { text-align: right; font-size: 10px; color: #444; line-height: 1.5; }
        .empresa strong { font-size: 12px; color: #1a1a1a; }
        .banda { background: #1a5faa; color: #fff; padding: 8px 14px; border-radius: 4px; margin-bottom: 14px; }
        .banda h1 { font-size: 15px; letter-spacing: .5px; display: inline-block; }
        .banda .sub { font-size: 10px; margin-left: 8px; }
        .banda .nf { float: right; font-size: 9px; background: #e5b53a; color: #1a1a1a; padding: 3px 7px; border-radius: 3px; font-weight: bold; }
        .meta { width: 100%; border-collapse: separate; border-spacing: 6px 0; margin: 0 -6px 14px; }
        .meta td { border: 1px solid #d0d7e2; border-radius: 4px; padding: 7px 10px; vertical-align: top; width: 25%; }
        .lbl { font-size: 8.5px; color: #777; text-transform: uppercase; letter-spacing: .5px; }
        .val { font-size: 12px; font-weight: bold; margin-top: 2px; }
        .deuda { color: #b42318; }
        .favor { color: #14724a; }
        h2 { font-size: 10px; text-transform: uppercase; color: #1a5faa; letter-spacing: .6px; border-bottom: 1px solid #d0d7e2; padding-bottom: 3px; margin: 6px 0 6px; }
        table.t { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        table.t th { background: #eef3fb; color: #1a1a1a; font-size: 9.5px; padding: 5px 8px; text-align: left; }
        table.t td { padding: 5px 8px; border-bottom: 1px solid #eaeef3; }
        table.t .r { text-align: right; }
        .vacio { color: #666; font-style: italic; padding: 4px 0 10px; }
        .nota { font-size: 9.5px; color: #555; line-height: 1.5; border-top: 1px solid #d0d7e2; padding-top: 8px; margin-top: 10px; }
    </style>
</head>
<body>
<div class="page">
    <table class="header">
        <tr>
            <td>
                @if($logo)
                    <img src="{{ $logo }}" alt="{{ $empresa_nombre }}">
                @else
                    <strong style="font-size:16px;color:#1a5faa">{{ $empresa_nombre }}</strong>
                @endif
            </td>
            <td class="empresa">
                <strong>{{ $empresa_nombre }}</strong><br>
                @if($empresa_rfc) RFC: {{ $empresa_rfc }}<br> @endif
                @if($empresa_tel) Tel: {{ $empresa_tel }} @endif
                @if($empresa_correo) · {{ $empresa_correo }} @endif
            </td>
        </tr>
    </table>

    <div class="banda">
        <h1>{{ $titulo }}</h1>
        @if($subtitulo)<span class="sub">{{ $subtitulo }}</span>@endif
        <span class="nf">SIN VALOR FISCAL</span>
    </div>

    <table class="meta">
        <tr>
            <td><div class="lbl">Cliente</div><div class="val">{{ $cliente_nombre }}</div><div>No. {{ $cliente_numero }}</div></td>
            <td><div class="lbl">Fecha</div><div class="val">{{ $fecha }}</div><div>Estado: {{ $estado_cliente ?: '—' }}</div></td>
            <td>
                @if($adeudo > 0)
                    <div class="lbl">Adeudo</div><div class="val deuda">${{ number_format($adeudo, 2) }}</div>
                @elseif($a_favor > 0)
                    <div class="lbl">Saldo a favor</div><div class="val favor">${{ number_format($a_favor, 2) }}</div>
                @else
                    <div class="lbl">Saldo</div><div class="val">$0.00</div>
                @endif
                <div>Saldo de la cuenta</div>
            </td>
            <td><div class="lbl">Referencia de pago</div><div class="val">{{ $referencia ?: '—' }}</div></td>
        </tr>
    </table>

    <h2>Facturas pendientes</h2>
    @if(count($facturas))
        <table class="t">
            <tr><th>Folio</th><th>Periodo</th><th>Vence</th><th class="r">Total</th><th class="r">Pendiente</th></tr>
            @foreach($facturas as $f)
                <tr>
                    <td>{{ $f['folio'] }}</td>
                    <td>{{ $f['periodo'] ?: '—' }}</td>
                    <td>{{ $f['vence'] ?: '—' }}</td>
                    <td class="r">${{ number_format($f['total'], 2) }}</td>
                    <td class="r">${{ number_format($f['saldo'], 2) }}</td>
                </tr>
            @endforeach
        </table>
    @else
        <div class="vacio">Sin facturas pendientes.</div>
    @endif

    <h2>Últimos pagos</h2>
    @if(count($pagos))
        <table class="t">
            <tr><th>Fecha</th><th>Método</th><th class="r">Monto</th></tr>
            @foreach($pagos as $p)
                <tr><td>{{ $p['fecha'] }}</td><td>{{ $p['metodo'] ?: '—' }}</td><td class="r">${{ number_format($p['monto'], 2) }}</td></tr>
            @endforeach
        </table>
    @else
        <div class="vacio">Sin pagos registrados.</div>
    @endif

    <div class="nota">
        @if($tipo === 'final')
            El servicio fue dado de baja. Desde esta fecha no se generan nuevos cargos.
        @endif
        @if($adeudo > 0 && ($banco || $cuenta))
            Para liquidar el adeudo: {{ $banco }} {{ $cuenta ? '· Cuenta ' . $cuenta : '' }}@if($referencia) · Referencia {{ $referencia }}@endif.
        @endif
        <br><strong>Documento informativo sin valor fiscal.</strong> No sustituye a una factura (CFDI).
    </div>
</div>
</body>
</html>
