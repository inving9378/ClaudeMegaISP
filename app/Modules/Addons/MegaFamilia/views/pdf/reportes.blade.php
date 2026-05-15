<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>MegaFamilia · Reporte de uso</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #2d3142; }
        h1 { font-size: 18px; margin: 0 0 4px 0; color: #ff8c00; }
        h2 { font-size: 14px; margin: 18px 0 6px 0; border-bottom: 1px solid #ddd; padding-bottom: 4px; }
        table { width: 100%; border-collapse: collapse; margin-top: 6px; }
        th, td { padding: 6px 8px; border-bottom: 1px solid #eee; text-align: left; }
        th { background: #f5f7fa; font-weight: 600; }
        td.num, th.num { text-align: right; font-variant-numeric: tabular-nums; }
        .meta { color: #6c757d; font-size: 10px; margin-bottom: 14px; }
        .empty { color: #6c757d; font-style: italic; padding: 10px 0; }
    </style>
</head>
<body>
    <h1>MegaFamilia · Reporte de uso</h1>
    <div class="meta">
        Generado el {{ $generatedAt }}
        @if(! empty($profileName))
            · Perfil: <strong>{{ $profileName }}</strong>
        @endif
    </div>

    <h2>Top apps (proxy por bloqueos registrados)</h2>
    @if(count($topApps) === 0)
        <div class="empty">Sin datos suficientes.</div>
    @else
        <table>
            <thead><tr><th>Aplicación</th><th class="num">Eventos</th></tr></thead>
            <tbody>
                @foreach($topApps as $row)
                    <tr>
                        <td>{{ $row->app_name }}</td>
                        <td class="num">{{ $row->total }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <h2>Tiempo de pantalla por día (últimos 14 días)</h2>
    @if(count($screenByDay) === 0)
        <div class="empty">Sin eventos screen_time_log registrados.</div>
    @else
        <table>
            <thead><tr><th>Día</th><th class="num">Muestras</th></tr></thead>
            <tbody>
                @foreach($screenByDay as $row)
                    <tr>
                        <td>{{ $row->day }}</td>
                        <td class="num">{{ $row->samples }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</body>
</html>
