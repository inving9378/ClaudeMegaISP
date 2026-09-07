{{-- Acuse de avance en PDF con corte a fecha — evidencia para la mesa directiva (item #9990551). --}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        body    { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #222; }
        h1      { font-size: 16px; margin: 0 0 2px; }
        h2      { font-size: 13px; margin: 18px 0 4px; border-bottom: 1px solid #ccc; padding-bottom: 3px; }
        .sub    { color: #666; font-size: 10px; margin-bottom: 4px; }
        table   { width: 100%; border-collapse: collapse; margin-top: 4px; }
        th, td  { border: 1px solid #ccc; padding: 4px 6px; text-align: left; vertical-align: top; }
        th      { background: #f2f2f2; font-weight: bold; }
        .kpis   { width: 100%; margin-top: 10px; }
        .kpis td { border: 1px solid #ccc; padding: 8px; text-align: center; width: 20%; }
        .kpi-num { font-size: 18px; font-weight: bold; display: block; }
        .kpi-lbl { font-size: 9px; color: #666; }
        .badge  { display: inline-block; padding: 1px 6px; border-radius: 3px; font-size: 9px; color: #fff; }
        .badge-verde    { background: #2e7d32; }
        .badge-amarillo { background: #b8860b; }
        .badge-rojo     { background: #c62828; }
        .badge-gris     { background: #757575; }
        .aviso  { background: #fff8e1; border: 1px solid #e8d18a; padding: 8px; margin: 10px 0; font-size: 10px; }
        .faltantes ul { margin: 2px 0 0 16px; padding: 0; }
        .pie    { margin-top: 18px; color: #888; font-size: 9px; border-top: 1px solid #ddd; padding-top: 6px; }
    </style>
</head>
<body>
    <h1>Acuse de Avance — Documentación Corporativa</h1>
    <div class="sub">
        {{ $empresa->razon_social }} @if($empresa->rfc) ({{ $empresa->rfc }}) @endif
    </div>
    <div class="sub">
        Corte al {{ $fechaCorte->format('d/m/Y') }} · Generado el {{ $generado->format('d/m/Y H:i') }}
    </div>

    @unless($esCorteHoy)
        <div class="aviso">
            El módulo no conserva un histórico del expediente por fecha: no es posible reconstruir
            el estado exacto en que se encontraba el {{ $fechaCorte->format('d/m/Y') }}. Los valores
            de este acuse reflejan el estado ACTUAL del expediente al momento de generación
            ({{ $generado->format('d/m/Y H:i') }}), etiquetados con la fecha de corte solicitada.
        </div>
    @endunless

    <h2>Resumen global</h2>
    <table class="kpis">
        <tr>
            <td>
                <span class="kpi-num">{{ $global['medible'] ? $global['porcentaje'] . '%' : '—' }}</span>
                <span class="kpi-lbl">Avance global</span>
            </td>
            <td>
                <span class="kpi-num">{{ $global['resueltos'] }}/{{ $global['obligatorios'] }}</span>
                <span class="kpi-lbl">Obligatorios resueltos</span>
            </td>
            <td>
                <span class="kpi-num">{{ $global['al_dia'] }}</span>
                <span class="kpi-lbl">Apartados al día</span>
            </td>
            <td>
                <span class="kpi-num">{{ $global['en_proceso'] }}</span>
                <span class="kpi-lbl">En proceso</span>
            </td>
            <td>
                <span class="kpi-num">{{ $global['sin_iniciar'] }}</span>
                <span class="kpi-lbl">Sin iniciar</span>
            </td>
        </tr>
    </table>

    <h2>Avance por apartado</h2>
    <table>
        <thead>
            <tr>
                <th>Clave</th>
                <th>Apartado</th>
                <th>%</th>
                <th>Estado</th>
                <th>Obligatorios</th>
                <th>Faltantes</th>
                <th>Responsables</th>
            </tr>
        </thead>
        <tbody>
            @foreach($apartados as $a)
                <tr>
                    <td>{{ $a['clave'] }}</td>
                    <td>{{ $a['nombre'] }}</td>
                    <td>
                        {{ $a['medible'] ? $a['porcentaje'] . '%' : '—' }}
                        <span class="badge badge-{{ $a['semaforo'] }}">&nbsp;</span>
                    </td>
                    <td>{{ str_replace('_', ' ', $a['estado']) }}</td>
                    <td>{{ $a['resueltos'] }}/{{ $a['obligatorios'] }}</td>
                    <td>{{ count($a['faltantes']) }}</td>
                    <td>
                        {{ $a['responsables']['nombres'] ? implode(', ', $a['responsables']['nombres']) : '—' }}
                        @if($a['responsables']['sin_responsable'] > 0)
                            ({{ $a['responsables']['sin_responsable'] }} pendiente(s) sin responsable)
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    @php $conFaltantes = array_values(array_filter($apartados, fn ($a) => count($a['faltantes']) > 0)); @endphp

    @if(count($conFaltantes) > 0)
        <h2>Obligatorios faltantes por apartado</h2>
        <div class="faltantes">
            @foreach($conFaltantes as $a)
                <strong>{{ $a['clave'] }} — {{ $a['nombre'] }}</strong>
                <ul>
                    @foreach($a['faltantes'] as $nombre)
                        <li>{{ $nombre }}</li>
                    @endforeach
                </ul>
            @endforeach
        </div>
    @endif

    <div class="pie">
        Generado el {{ $generado->format('d/m/Y H:i') }} · Expediente corporativo · esta exportación quedó registrada en bitácora.
    </div>
</body>
</html>
