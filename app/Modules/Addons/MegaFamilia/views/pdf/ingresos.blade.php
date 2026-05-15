<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>MegaFamilia · Ingresos</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #2d3142; }
        h1 { font-size: 18px; margin: 0 0 4px 0; color: #ff8c00; }
        h2 { font-size: 14px; margin: 18px 0 6px 0; border-bottom: 1px solid #ddd; padding-bottom: 4px; }
        table { width: 100%; border-collapse: collapse; margin-top: 6px; }
        th, td { padding: 6px 8px; border-bottom: 1px solid #eee; text-align: left; }
        th { background: #f5f7fa; font-weight: 600; }
        td.num, th.num { text-align: right; font-variant-numeric: tabular-nums; }
        .total-row td { font-weight: 700; background: #fff8ef; }
        .meta { color: #6c757d; font-size: 10px; margin-bottom: 14px; }
    </style>
</head>
<body>
    <h1>MegaFamilia · Reporte de ingresos</h1>
    <div class="meta">
        Generado el {{ $generatedAt }} ·
        MRR estimado: <strong>${{ number_format($mrr, 2) }} MXN</strong>
    </div>

    <h2>Ingresos por plan (cuentas activas)</h2>
    <table>
        <thead>
            <tr>
                <th>Plan</th>
                <th class="num">Cuentas</th>
                <th class="num">Ingreso mensual</th>
            </tr>
        </thead>
        <tbody>
            @foreach($byPlan as $row)
                <tr>
                    <td>{{ $row->name }}</td>
                    <td class="num">{{ $row->accounts }}</td>
                    <td class="num">${{ number_format((float) $row->monthly_revenue, 2) }}</td>
                </tr>
            @endforeach
            <tr class="total-row">
                <td>Total</td>
                <td class="num">{{ collect($byPlan)->sum('accounts') }}</td>
                <td class="num">${{ number_format($mrr, 2) }}</td>
            </tr>
        </tbody>
    </table>

    @if(!empty($byMonth) && count($byMonth) > 0)
        <h2>Histórico (últimos 12 meses)</h2>
        <table>
            <thead><tr><th>Mes</th><th class="num">Ingreso</th></tr></thead>
            <tbody>
                @foreach($byMonth as $row)
                    <tr>
                        <td>{{ $row->month }}</td>
                        <td class="num">${{ number_format((float) $row->revenue, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</body>
</html>
