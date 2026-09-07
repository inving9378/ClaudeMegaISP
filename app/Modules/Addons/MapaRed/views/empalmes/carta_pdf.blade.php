{{-- MR-19 Fase 1 (item #9990565) — carta de empalme, MVP: bandeja → hilo_a → tipo → extremo_b.
     Colores tal cual el catálogo en BD (mapared_hilos.color/buffer_color), sin JS (DomPDF no lo ejecuta). --}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        @page  { size: landscape; margin: 18px; }
        body   { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #222; }
        h1     { font-size: 16px; margin: 0 0 2px; }
        h2     { font-size: 13px; margin: 16px 0 4px; border-bottom: 1px solid #ccc; padding-bottom: 3px; }
        .sub   { color: #666; font-size: 10px; margin-bottom: 12px; }
        table  { width: 100%; border-collapse: collapse; margin-top: 4px; }
        th, td { border: 1px solid #ccc; padding: 4px 6px; text-align: left; vertical-align: top; }
        th     { background: #f2f2f2; font-weight: bold; }
        .swatch { display: inline-block; width: 10px; height: 10px; border: 1px solid #999; margin-right: 4px; }
        .pie   { margin-top: 18px; color: #888; font-size: 9px; border-top: 1px solid #ddd; padding-top: 6px; }
    </style>
</head>
<body>
    <h1>Carta de empalme — {{ $contenedorNombre }}</h1>
    <div class="sub">
        {{ count($grupos) }} bandeja{{ count($grupos) === 1 ? '' : 's' }}
    </div>

    @forelse($grupos as $grupo)
        <h2>Bandeja {{ $grupo['bandeja'] }}</h2>
        <table>
            <thead>
                <tr>
                    <th>Hilo A</th>
                    <th>Buffer</th>
                    <th>Tipo</th>
                    <th>Pérdida (dB)</th>
                    <th>Extremo B</th>
                </tr>
            </thead>
            <tbody>
                @foreach($grupo['filas'] as $fila)
                    <tr>
                        <td>
                            @if($fila['hilo_a'])
                                <span class="swatch" style="background-color: {{ $fila['hilo_a']['color'] ?? '#fff' }};"></span>
                                #{{ $fila['hilo_a']['numero'] }}
                            @else
                                —
                            @endif
                        </td>
                        <td>
                            @if($fila['hilo_a'] && $fila['hilo_a']['buffer'])
                                <span class="swatch" style="background-color: {{ $fila['hilo_a']['buffer_color'] ?? '#fff' }};"></span>
                                {{ $fila['hilo_a']['buffer'] }}
                            @else
                                —
                            @endif
                        </td>
                        <td>{{ $fila['tipo'] }}</td>
                        <td>{{ $fila['perdida_db'] ?? '—' }}</td>
                        <td>
                            @if($fila['extremo_b'] && $fila['extremo_b']['tipo'] === 'hilo')
                                <span class="swatch" style="background-color: {{ $fila['extremo_b']['color'] ?? '#fff' }};"></span>
                                Hilo #{{ $fila['extremo_b']['numero'] }} (cable {{ $fila['extremo_b']['cable_id'] }})
                            @elseif($fila['extremo_b'] && $fila['extremo_b']['tipo'] === 'puerto')
                                Puerto {{ $fila['extremo_b']['numero'] }} ({{ $fila['extremo_b']['rol'] }})
                            @else
                                —
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @empty
        <p><em>Sin empalmes registrados en este elemento.</em></p>
    @endforelse

    <div class="pie">
        Generado el {{ $generado->format('d/m/Y H:i') }} · Mapa de Red · carta de empalme MVP (item #9990565).
    </div>
</body>
</html>
