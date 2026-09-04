{{-- Exportación PDF agregada de un apartado: todos sus conceptos ya resueltos en un solo archivo. --}}
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
        .aviso { background: #fff8e1; border: 1px solid #e8d18a; padding: 6px; margin-top: 4px; font-size: 10px; }
        .pie   { margin-top: 18px; color: #888; font-size: 9px; border-top: 1px solid #ddd; padding-top: 6px; }
    </style>
</head>
<body>
    <h1>Apartado {{ $apartado->clave }} — {{ $apartado->nombre }}</h1>
    <div class="sub">
        @if($apartado->descripcion) {{ $apartado->descripcion }} · @endif
        {{ count($conceptos) }} concepto{{ count($conceptos) === 1 ? '' : 's' }}
    </div>

    @foreach($conceptos as $concepto)
        <h2>{{ $concepto['nombre'] }}</h2>

        @if($concepto['mensaje'] ?? null)
            <div class="aviso">{{ $concepto['mensaje'] }}</div>
        @endif

        @php $filas = array_values(array_filter($concepto['datos'] ?? [], 'is_array')); @endphp

        @if(count($filas) > 0)
            @php $columnas = array_keys($filas[0]); @endphp
            <table>
                <thead>
                    <tr>@foreach($columnas as $col)<th>{{ str_replace('_', ' ', $col) }}</th>@endforeach</tr>
                </thead>
                <tbody>
                    @foreach($filas as $fila)
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
    @endforeach

    <div class="pie">
        Generado el {{ $generado->format('d/m/Y H:i') }} · Expediente corporativo · esta exportación quedó registrada en bitácora.
    </div>
</body>
</html>
