{{-- Exportación PDF de un concepto. dompdf: HTML simple, sin flex ni grid. --}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        body  { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #222; }
        h1    { font-size: 15px; margin: 0 0 2px; }
        .sub  { color: #666; font-size: 10px; margin-bottom: 12px; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { border: 1px solid #ccc; padding: 4px 6px; text-align: left; vertical-align: top; }
        th    { background: #f2f2f2; font-weight: bold; }
        .aviso { background: #fff8e1; border: 1px solid #e8d18a; padding: 8px; margin-top: 10px; }
        .pie  { margin-top: 18px; color: #888; font-size: 9px; border-top: 1px solid #ddd; padding-top: 6px; }
    </style>
</head>
<body>
    <h1>{{ $concepto->nombre }}</h1>
    <div class="sub">
        Apartado {{ $concepto->apartado->clave ?? '—' }} — {{ $concepto->apartado->nombre ?? '' }}
        · Confidencialidad: {{ $concepto->confidencialidad }}
        @if($concepto->base_legal) · Base legal: {{ $concepto->base_legal }} @endif
    </div>

    @if($resultado->mensaje)
        <div class="aviso">{{ $resultado->mensaje }}</div>
    @endif

    @if(count($resultado->datos) > 0)
        @php $columnas = array_keys($resultado->datos[0]); @endphp
        <table>
            <thead>
                <tr>@foreach($columnas as $col)<th>{{ str_replace('_', ' ', $col) }}</th>@endforeach</tr>
            </thead>
            <tbody>
                @foreach($resultado->datos as $fila)
                    <tr>
                        @foreach($columnas as $col)
                            <td>{{ is_scalar($fila[$col] ?? null) || ($fila[$col] ?? null) === null
                                ? ($fila[$col] ?? '')
                                : json_encode($fila[$col], JSON_UNESCAPED_UNICODE) }}</td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <p><em>Sin registros en este entorno.</em></p>
    @endif

    <div class="pie">
        Generado el {{ $generado->format('d/m/Y H:i') }} · Estado del concepto: {{ $resultado->estado }}
        · Este documento forma parte del expediente corporativo y su descarga quedó registrada en bitácora.
    </div>
</body>
</html>
