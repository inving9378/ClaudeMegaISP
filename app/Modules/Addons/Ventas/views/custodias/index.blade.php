<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Custodia de prospectos — Ventas</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; margin: 0; background: #0f172a; color: #e2e8f0; }
        .wrap { max-width: 1100px; margin: 0 auto; padding: 24px 16px 64px; }
        h1 { font-size: 20px; margin: 0 0 4px; }
        .sub { color: #94a3b8; font-size: 13px; margin: 0 0 20px; }
        .warn { background: #422006; border: 1px solid #a16207; color: #fde68a; font-size: 12px; padding: 8px 10px; border-radius: 8px; margin-bottom: 18px; }
        table { width: 100%; border-collapse: collapse; margin-top: 6px; }
        th, td { text-align: left; padding: 8px 10px; border-bottom: 1px solid #334155; font-size: 14px; vertical-align: top; }
        th { color: #94a3b8; font-weight: 600; font-size: 12px; text-transform: uppercase; }
        .badge { display: inline-block; padding: 2px 9px; border-radius: 999px; font-size: 12px; font-weight: 600; }
        .estado-activa { background: #052e16; color: #4ade80; border: 1px solid #166534; }
        .estado-vencida { background: #422006; color: #fbbf24; border: 1px solid #a16207; }
        .estado-liberada { background: #334155; color: #cbd5e1; border: 1px solid #475569; }
        .muted { color: #64748b; font-size: 12px; }
        .empty { padding: 24px; text-align: center; color: #64748b; }
        .pagination { margin-top: 16px; }
        .pagination a, .pagination span { color: #94a3b8; margin-right: 8px; font-size: 13px; }
    </style>
</head>
<body>
<div class="wrap">
    <h1>Custodia de prospectos</h1>
    <p class="sub">Ventana de 7 días, hasta 3 renovaciones con evidencia de trabajo, retorno automático al pool general (item #9990780).</p>

    <div class="warn">
        Pantalla de solo lectura (super-administrator / DESARROLLADOR). La renovación y el criterio
        de "evidencia de trabajo" están definidos en <code>CustodiaService</code>, pendientes del
        reglamento de ventas (#0) para su regla exacta — hoy usan un mínimo provisional (≥1
        evidencia registrada).
    </div>

    @if ($custodias->isEmpty())
        <div class="empty">No hay custodias registradas todavía.</div>
    @else
        <table>
            <thead>
                <tr>
                    <th>Prospecto</th>
                    <th>Colaborador</th>
                    <th>Fecha límite</th>
                    <th>Renovaciones</th>
                    <th>Estado</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($custodias as $custodia)
                    <tr>
                        <td>
                            {{ $custodia->prospecto->nombre ?? '—' }}
                            <div class="muted">{{ $custodia->prospecto->telefono ?? $custodia->prospecto->email ?? '' }}</div>
                        </td>
                        <td>{{ $custodia->colaborador->user->name ?? ($custodia->colaborador_id ? "colaborador #{$custodia->colaborador_id}" : 'Sin asignar') }}</td>
                        <td>{{ optional($custodia->fecha_limite)->format('Y-m-d') }}</td>
                        <td>{{ $custodia->renovaciones_usadas }} / {{ $custodia->max_renovaciones }}</td>
                        <td><span class="badge estado-{{ $custodia->estado }}">{{ $custodia->estado }}</span></td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="pagination">{{ $custodias->links() }}</div>
    @endif
</div>
</body>
</html>
