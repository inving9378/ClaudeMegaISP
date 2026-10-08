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
                Fuentes oficiales, destinatarios, canales de alerta y conexiones. La pantalla completa
                de configuración se habilita en la Fase 3; por ahora, el estado de las conexiones con
                los servicios de esta instancia es de solo lectura.
            </p>
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
