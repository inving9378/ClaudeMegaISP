{{-- Acuse de avance del catálogo de plantillas (item roadmap #9990572, seguimiento de #9990551). --}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        body   { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #222; }
        h1     { font-size: 16px; margin: 0 0 2px; }
        h2     { font-size: 13px; margin: 18px 0 4px; border-bottom: 1px solid #ccc; padding-bottom: 3px; }
        .sub   { color: #666; font-size: 10px; margin-bottom: 12px; }
        table  { width: 100%; border-collapse: collapse; margin-top: 4px; }
        th, td { border: 1px solid #ccc; padding: 4px 6px; text-align: left; vertical-align: top; }
        th     { background: #f2f2f2; font-weight: bold; }
        .kpis  { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        .kpis td { border: 1px solid #ccc; padding: 8px; text-align: center; width: 25%; }
        .kpis .valor { font-size: 15px; font-weight: bold; display: block; }
        .kpis .etiqueta { font-size: 9px; color: #666; }
        .estado-publicada { color: #2e7d32; }
        .estado-borrador  { color: #b28900; }
        .pie   { margin-top: 18px; color: #888; font-size: 9px; border-top: 1px solid #ddd; padding-top: 6px; }
    </style>
</head>
<body>
    <h1>Acuse de avance — Catálogo de plantillas de documentos</h1>
    <div class="sub">
        Corte al {{ $fechaCorte->format('d/m/Y') }} · {{ $global['total'] }} plantilla{{ $global['total'] === 1 ? '' : 's' }} registrada{{ $global['total'] === 1 ? '' : 's' }}
    </div>

    <table class="kpis">
        <tr>
            <td>
                <span class="valor">{{ $global['porcentaje'] }}%</span>
                <span class="etiqueta">Avance global (publicadas)</span>
            </td>
            <td>
                <span class="valor">{{ $global['total'] }}</span>
                <span class="etiqueta">Plantillas totales</span>
            </td>
            <td>
                <span class="valor estado-publicada">{{ $global['publicadas'] }}</span>
                <span class="etiqueta">Publicadas</span>
            </td>
            <td>
                <span class="valor estado-borrador">{{ $global['borrador'] }}</span>
                <span class="etiqueta">Borrador</span>
            </td>
        </tr>
    </table>

    <h2>Detalle por plantilla</h2>
    <table>
        <thead>
            <tr>
                <th>Nombre</th>
                <th>Tipo</th>
                <th>Estado</th>
                <th>Autor</th>
                <th>Última edición</th>
            </tr>
        </thead>
        <tbody>
            @forelse($plantillas as $p)
                <tr>
                    <td>{{ $p['nombre'] }}</td>
                    <td>{{ $p['tipo'] }}</td>
                    <td class="estado-{{ $p['publicada'] ? 'publicada' : 'borrador' }}">{{ $p['estado'] }}</td>
                    <td>{{ $p['autor'] }}</td>
                    <td>{{ $p['actualizado']?->format('d/m/Y H:i') ?? '—' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5">Sin plantillas registradas.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="pie">
        Generado el {{ $generado->format('d/m/Y H:i') }} · Catálogo de plantillas de documentos · "Publicada" = plantilla con contenido; "Borrador" = sin contenido.
    </div>
</body>
</html>
