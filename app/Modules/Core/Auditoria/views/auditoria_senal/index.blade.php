@extends('core-layout::master')

@section('title')
    Señales de bitácora
@endsection

@section('content')
    <Breadcrumb :list=[{title:"Auditoría"},{title:"Señales de bitácora",active:"active"}]></Breadcrumb>

    <div class="card">
        <div class="card-body">
            <p class="text-muted">
                Señales minadas automáticamente de la bitácora del sistema (<code>activity_log</code>).
                Solo lectura — cada fila es un candidato para revisar, no una acción ya ejecutada.
            </p>

            <form method="GET" class="row g-2 align-items-end mb-3" style="max-width: 400px;">
                <div class="col">
                    <label class="form-label mb-1">Tipo de señal</label>
                    <select name="tipo" class="form-select" onchange="this.form.submit()">
                        <option value="">Todas</option>
                        @foreach ($tipos as $t)
                            <option value="{{ $t }}" @selected($tipoActual === $t)>{{ $t }}</option>
                        @endforeach
                    </select>
                </div>
            </form>

            @if ($senales->isEmpty())
                <div class="text-center text-muted py-5">
                    Sin señales registradas todavía.
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-striped align-middle">
                        <thead>
                            <tr>
                                <th>Ocurrió</th>
                                <th>Tipo</th>
                                <th>Registro afectado</th>
                                <th>Usuario</th>
                                <th>Detalle</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($senales as $senal)
                                @php
                                    $payload = $senal->payload ?? [];
                                    $causante = $causantes->get($payload['causer_id'] ?? null);
                                @endphp
                                <tr>
                                    <td>{{ optional($senal->ocurrido_en)->format('Y-m-d H:i') }}</td>
                                    <td><span class="badge bg-warning-subtle text-dark">{{ $senal->tipo }}</span></td>
                                    <td>
                                        {{ class_basename($payload['subject_type'] ?? '') }}
                                        #{{ $payload['subject_id'] ?? '—' }}
                                    </td>
                                    <td>{{ $causante ? $causante->getClientNameWithFathersNamesAttribute() : ($payload['causer_id'] ?? 'Sistema') }}</td>
                                    <td>
                                        <span class="text-muted">
                                            Creado sin seguimiento en {{ $payload['ventana_minutos'] ?? '?' }} min;
                                            el usuario siguió activo en otra parte del sistema.
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{ $senales->links() }}
            @endif
        </div>
    </div>
@endsection
