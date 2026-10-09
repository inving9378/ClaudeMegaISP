@extends('core-layout::master')

@section('title')
    Monitoreo Oficial
@endsection

@php
    $estadoBadge = [
        'disponible'   => ['class' => 'bg-success',   'txt' => 'Disponible'],
        'sin_config'   => ['class' => 'bg-warning',   'txt' => 'Sin configurar'],
        'sin_conexion' => ['class' => 'bg-warning',   'txt' => 'Sin conexión'],
        'no_instalado' => ['class' => 'bg-secondary', 'txt' => 'No instalado'],
        'error'        => ['class' => 'bg-danger',    'txt' => 'Error'],
    ];
    $provLabel = [
        'core-mail'            => 'Correo electrónico',
        'core-bell'            => 'Notificaciones internas',
        'addon-whatsapp-agent' => 'WhatsApp',
        'addon-ia'             => 'Análisis con IA / OpenAI',
    ];
@endphp

@section('content')
    <Breadcrumb :list='[{"title":"Inicio"},{"title":"Monitoreo Oficial","active":"active"}]'></Breadcrumb>

    <div class="card">
        <div class="card-body">
            <h4 class="card-title">Monitoreo Oficial y Alertas Inteligentes</h4>
            <p class="text-muted mb-0">
                Seguimiento de expedientes y publicaciones oficiales, detección de cambios y alertas.
                Módulo instalado desde <b>Gestión de Add-ons</b>. Opera con el núcleo del sistema y
                enciende los canales opcionales (WhatsApp, IA) solo si están disponibles en esta instancia.
                <span class="text-muted">(En construcción por fases — Fase 5: análisis de documentos con IA.)</span>
            </p>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <h4 class="card-title">Conexiones con servicios de esta instancia</h4>
            <p class="text-muted">Detección automática. Las capacidades ausentes no bloquean el módulo; quedan deshabilitadas.</p>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Servicio</th>
                            <th>Capacidad</th>
                            <th>Estado</th>
                            <th>Última comprobación</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($connections as $c)
                            @php $b = $estadoBadge[$c->status] ?? ['class' => 'bg-secondary', 'txt' => $c->status]; @endphp
                            <tr>
                                <td>{{ $provLabel[$c->provider_module] ?? $c->provider_module }}</td>
                                <td><code>{{ $c->capability }}</code></td>
                                <td><span class="badge {{ $b['class'] }}">{{ $b['txt'] }}</span></td>
                                <td class="text-muted">{{ optional($c->last_checked_at)->diffForHumans() ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-muted text-center">Sin conexiones registradas todavía.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h4 class="card-title mb-0">Expedientes monitoreados</h4>
                @if(auth()->user()->can('monitoring.manage_cases'))
                    <a href="{{ route('monitoreo-oficial.expedientes.crear') }}" class="btn btn-sm btn-primary">
                        <i class="fa fa-plus"></i> Nuevo expediente
                    </a>
                @endif
            </div>

            @if (session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif

            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Autoridad</th>
                            <th>Denominación</th>
                            <th>Expediente</th>
                            <th>Registro</th>
                            <th>Fuente</th>
                            <th>Estado legal</th>
                            <th>Monitoreo</th>
                            <th class="text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($cases as $case)
                            <tr>
                                <td>{{ $case->authority }}</td>
                                <td>{{ $case->name }} @if($case->is_demo)<span class="badge bg-info ms-1">Ejemplo</span>@endif</td>
                                <td>{{ $case->case_number ?? '—' }}</td>
                                <td>{{ $case->registration_number ?? '—' }}</td>
                                <td class="text-muted">{{ $case->source?->name ?? '—' }}</td>
                                <td class="text-muted">{{ $case->current_legal_status ?? '—' }}</td>
                                <td><span class="badge {{ $case->monitoring_status === 'activo' ? 'bg-success' : 'bg-secondary' }}">{{ ucfirst($case->monitoring_status) }}</span></td>
                                <td class="text-end">
                                    <a href="{{ route('monitoreo-oficial.expedientes.ver', $case->id) }}" class="btn btn-sm btn-outline-primary">Ver</a>
                                    @if(auth()->user()->can('monitoring.manage_cases'))
                                        <a href="{{ route('monitoreo-oficial.expedientes.editar', $case->id) }}" class="btn btn-sm btn-outline-secondary">Editar</a>
                                        @unless($case->is_demo)
                                            <form method="POST" action="{{ route('monitoreo-oficial.expedientes.eliminar', $case->id) }}" class="d-inline" onsubmit="return confirm('¿Eliminar este expediente?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-outline-danger">Eliminar</button>
                                            </form>
                                        @endunless
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="text-muted text-center">No hay expedientes registrados en esta instancia.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
