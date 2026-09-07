{{-- Acuse de avance con corte a una fecha (item #9990551): evidencia para la mesa directiva. --}}
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
        .kpis td { border: 1px solid #ccc; padding: 8px; text-align: center; width: 20%; }
        .kpis .valor { font-size: 15px; font-weight: bold; display: block; }
        .kpis .etiqueta { font-size: 9px; color: #666; }
        .semaforo-verde   { color: #2e7d32; }
        .semaforo-amarillo { color: #b28900; }
        .semaforo-rojo    { color: #c62828; }
        .semaforo-gris    { color: #757575; }
        .faltantes { color: #c62828; }
        .sin-faltantes { color: #2e7d32; }
        .pie   { margin-top: 18px; color: #888; font-size: 9px; border-top: 1px solid #ddd; padding-top: 6px; }
    </style>
</head>
<body>
    <h1>Acuse de avance — {{ $empresa->razon_social ?? $empresa->nombre_comercial }}</h1>
    <div class="sub">
        @if($empresa->rfc) RFC {{ $empresa->rfc }} · @endif
        Corte al {{ $fechaCorte->format('d/m/Y') }} · Documentación Corporativa · {{ count($apartados) }} apartado{{ count($apartados) === 1 ? '' : 's' }} visible{{ count($apartados) === 1 ? '' : 's' }}
    </div>

    <table class="kpis">
        <tr>
            <td>
                <span class="valor semaforo-{{ $global['semaforo'] }}">{{ $global['medible'] ? $global['porcentaje'] . '%' : '—' }}</span>
                <span class="etiqueta">Avance global</span>
            </td>
            <td>
                <span class="valor">{{ $global['al_dia'] }}/{{ $global['apartados'] }}</span>
                <span class="etiqueta">Apartados al día</span>
            </td>
            <td>
                <span class="valor">{{ $global['en_proceso'] }}</span>
                <span class="etiqueta">En proceso</span>
            </td>
            <td>
                <span class="valor">{{ $global['sin_iniciar'] }}</span>
                <span class="etiqueta">Sin iniciar</span>
            </td>
            <td>
                <span class="valor">{{ $global['resueltos'] }}/{{ $global['obligatorios'] }}</span>
                <span class="etiqueta">Obligatorios resueltos</span>
            </td>
        </tr>
    </table>

    <h2>Avance por apartado</h2>
    <table>
        <thead>
            <tr>
                <th>Apartado</th>
                <th>%</th>
                <th>Estado</th>
                <th>Obligatorios faltantes</th>
                <th>Responsables</th>
            </tr>
        </thead>
        <tbody>
            @foreach($apartados as $apartado)
                <tr>
                    <td>{{ $apartado['clave'] }} — {{ $apartado['nombre'] }}</td>
                    <td class="semaforo-{{ $apartado['semaforo'] }}">{{ $apartado['medible'] ? $apartado['porcentaje'] . '%' : '—' }}</td>
                    <td>{{ ['sin_iniciar' => 'Sin iniciar', 'en_proceso' => 'En proceso', 'al_dia' => 'Al día'][$apartado['estado']] ?? $apartado['estado'] }}</td>
                    <td>
                        @if(count($apartado['faltantes']) > 0)
                            <span class="faltantes">{{ implode(', ', $apartado['faltantes']) }}</span>
                        @else
                            <span class="sin-faltantes">Ninguno</span>
                        @endif
                    </td>
                    <td>
                        @if(count($apartado['responsables']['nombres']) > 0)
                            {{ implode(', ', $apartado['responsables']['nombres']) }}
                        @else
                            <em>Sin responsable asignado</em>
                        @endif
                        @if($apartado['responsables']['pendientes'] > 0)
                            ({{ $apartado['responsables']['pendientes'] }} pendiente{{ $apartado['responsables']['pendientes'] === 1 ? '' : 's' }})
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="pie">
        Generado el {{ $generado->format('d/m/Y H:i') }} · Expediente corporativo · esta exportación quedó registrada en bitácora.
    </div>
</body>
</html>
