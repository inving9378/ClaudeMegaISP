@extends('core-layout::master')

@section('title')
    Monitoreo Oficial — Configuración
@endsection

@section('content')
    <Breadcrumb :list='[{"title":"Inicio"},{"title":"Monitoreo Oficial","active":"active"},{"title":"Configuración","active":"active"}]'></Breadcrumb>

    <div class="card">
        <div class="card-body">
            <h4 class="card-title">Configuración del módulo</h4>
            <p class="text-muted mb-0">
                Fuentes oficiales (Fase 2: alta/edición/baja ya disponible). Destinatarios y canales de
                alerta por expediente, y la descarga automática por fuente (scraping/PDF), se habilitan
                en la Fase 3. El estado de las conexiones con los servicios de esta instancia es de
                solo lectura (se detecta automático).
            </p>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h4 class="card-title mb-0">Fuentes oficiales</h4>
                @if(auth()->user()->can('monitoring.manage_sources'))
                    <a href="{{ route('monitoreo-oficial.fuentes.crear') }}" class="btn btn-sm btn-primary">
                        <i class="fa fa-plus"></i> Nueva fuente
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
                            <th>Nombre</th>
                            <th>Tipo</th>
                            <th>Estado</th>
                            @if(auth()->user()->can('monitoring.manage_sources'))
                                <th class="text-end">Acciones</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($sources as $s)
                            <tr>
                                <td>{{ $s->authority }}</td>
                                <td>{{ $s->name }}</td>
                                <td>{{ $s->source_type }}</td>
                                <td><span class="badge {{ $s->status === 'disponible' ? 'bg-success' : 'bg-secondary' }}">{{ ucfirst($s->status) }}</span></td>
                                @if(auth()->user()->can('monitoring.manage_sources'))
                                    <td class="text-end">
                                        <a href="{{ route('monitoreo-oficial.fuentes.editar', $s->id) }}" class="btn btn-sm btn-outline-secondary">Editar</a>
                                        <form method="POST" action="{{ route('monitoreo-oficial.fuentes.eliminar', $s->id) }}" class="d-inline" onsubmit="return confirm('¿Eliminar esta fuente? Los expedientes que la usan quedarán sin fuente asociada.');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger">Eliminar</button>
                                        </form>
                                    </td>
                                @endif
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-muted text-center">No hay fuentes registradas todavía.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <h4 class="card-title">Integraciones detectadas</h4>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                        <tr><th>Servicio</th><th>Capacidad</th><th>Estado</th></tr>
                    </thead>
                    <tbody>
                        @forelse($connections as $c)
                            <tr>
                                <td>{{ $c->provider_module }}</td>
                                <td><code>{{ $c->capability }}</code></td>
                                <td>{{ $c->status }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="text-muted text-center">Sin conexiones registradas.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
