@extends('meganet.layout.master')

@section('content')
    <div class="container-fluid">
        <!-- Botón Volver -->
        <div class="mb-4">
            <a href="{{ route('consumption.index') }}" class="btn btn-secondary btn-sm shadow-sm">
                <i class="fas fa-arrow-left"></i> Volver al listado general
            </a>
        </div>

        <div class="card shadow mb-4">
            <div class="card-header py-3 bg-primary">
                <h6 class="m-0 font-weight-bold text-white">
                    Historial Detallado: {{ $nombreReal }} ({{ $username }})
                </h6>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm table-hover border">
                        <thead class="bg-light">
                        <tr>
                            <th>Inicio (Start)</th>
                            <th>Fin (Stop)</th>
                            <th>Duración</th>
                            <th>IP Asignada</th>
                            <th>MAC Address (Calling Station)</th>
                            <th>Descarga</th>
                            <th>Subida</th>
                            <th>Causa de Cierre</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($sesiones as $s)
                            <tr>
                                <td><small>{{ $s->acctstarttime }}</small></td>
                                <td>
                                    @if($s->acctstoptime)
                                        <small>{{ $s->acctstoptime }}</small>
                                    @else
                                        <span class="badge badge-success pulse">En línea ahora</span>
                                    @endif
                                </td>
                                <td>
                                    <small>
                                        @if($s->acctsessiontime)
                                            {{ sprintf('%02d:%02d:%02d', ($s->acctsessiontime/3600),($s->acctsessiontime/60%60), $s->acctsessiontime%60) }}
                                        @else
                                            --:--:--
                                        @endif
                                    </small>
                                </td>
                                <td><span class="badge badge-outline-secondary">{{ $s->framedipaddress }}</span></td>
                                <td><small class="text-muted">{{ $s->callingstationid }}</small></td>
                                <td class="text-success font-weight-bold">
                                    {{ round($s->acctoutputoctets / 1024 / 1024, 2) }} MB
                                </td>
                                <td class="text-info">
                                    {{ round($s->acctinputoctets / 1024 / 1024, 2) }} MB
                                </td>
                                <td>
                                    <small class="text-danger">{{ $s->acctterminatecause }}</small>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center">No hay registros de sesiones para este usuario.</td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>

                <!-- Paginación -->
                <div class="mt-4 d-flex justify-content-center">
                    {{ $sesiones->links() }}
                </div>
            </div>
        </div>
    </div>

    <style>
        .pulse {
            animation: pulse-animation 2s infinite;
        }
        @keyframes pulse-animation {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
        }
    </style>
@endsection
