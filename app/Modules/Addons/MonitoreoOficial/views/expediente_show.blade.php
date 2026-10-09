@extends('core-layout::master')

@section('title')
    Monitoreo Oficial — {{ $case->name }}
@endsection

@section('content')
    <Breadcrumb :list='[{"title":"Inicio"},{"title":"Monitoreo Oficial","active":"active"},{"title":"{{ $case->name }}","active":"active"}]'></Breadcrumb>

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="card">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <h4 class="card-title mb-1">
                        {{ $case->name }}
                        @if($case->is_demo)<span class="badge bg-info ms-1">Ejemplo</span>@endif
                        <span class="badge {{ $case->monitoring_status === 'activo' ? 'bg-success' : 'bg-secondary' }}">{{ ucfirst($case->monitoring_status) }}</span>
                    </h4>
                    <p class="text-muted mb-0">{{ $case->authority }} @if($case->source) · Fuente: {{ $case->source->name }} @endif</p>
                </div>
                @if(auth()->user()->can('monitoring.manage_cases'))
                    <a href="{{ route('monitoreo-oficial.expedientes.editar', $case->id) }}" class="btn btn-sm btn-outline-secondary">Editar datos</a>
                @endif
            </div>

            <hr>

            <div class="row g-3">
                <div class="col-md-4">
                    <div class="text-muted small">N.º de expediente</div>
                    <div>{{ $case->case_number ?? '—' }}</div>
                </div>
                <div class="col-md-4">
                    <div class="text-muted small">N.º de registro</div>
                    <div>{{ $case->registration_number ?? '—' }}</div>
                </div>
                <div class="col-md-4">
                    <div class="text-muted small">N.º de referencia</div>
                    <div>{{ $case->reference_number ?? '—' }}</div>
                </div>
                <div class="col-md-4">
                    <div class="text-muted small">Clase</div>
                    <div>{{ $case->class_code ?? '—' }}</div>
                </div>
                <div class="col-md-4">
                    <div class="text-muted small">Última comprobación</div>
                    <div>{{ optional($case->last_checked_at)->diffForHumans() ?? '—' }}</div>
                </div>
                <div class="col-md-4">
                    <div class="text-muted small">Último cambio detectado</div>
                    <div>{{ optional($case->last_change_at)->diffForHumans() ?? 'Sin cambios registrados' }}</div>
                </div>
                <div class="col-md-4">
                    <div class="text-muted small">Próxima revisión programada</div>
                    <div>{{ optional($case->next_check_at)->diffForHumans() ?? 'Sin programar — corre con monitoring:revisar-expedientes' }}</div>
                </div>
                <div class="col-12">
                    <div class="text-muted small">Estado legal actual</div>
                    <div>{{ $case->current_legal_status ?? '—' }}</div>
                </div>
                <div class="col-12">
                    <div class="text-muted small">Avisos de cambios</div>
                    <div class="text-muted small">
                        Campana interna: a quien subió el documento y a quien dio de alta el expediente (siempre).
                        @if($case->notify_email) · Correo: {{ $case->notify_email }} @endif
                        @if($case->notify_whatsapp) · WhatsApp: {{ $case->notify_whatsapp }} @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if(auth()->user()->can('monitoring.manage_cases'))
        <div class="card">
            <div class="card-body">
                <h4 class="card-title">Subir documento (PDF)</h4>
                <p class="text-muted">
                    Cada PDF que subas se compara por huella contra el último que ya tenías para este
                    expediente: si es idéntico, queda en el historial pero no se marca como cambio; si
                    es distinto, se marca "último cambio detectado" arriba. El análisis automático del
                    contenido (IA) se habilita en una fase posterior.
                </p>
                <form method="POST" action="{{ route('monitoreo-oficial.documentos.subir', $case->id) }}" enctype="multipart/form-data">
                    @csrf
                    <div class="row g-3 align-items-end">
                        <div class="col-md-5">
                            <label class="form-label">Archivo PDF *</label>
                            <input type="file" name="documento" class="form-control" accept="application/pdf" required>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label">Notas (opcional)</label>
                            <input type="text" name="notes" class="form-control" maxlength="500" placeholder="Ej. Comprobante de declaración presentada">
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary w-100">Subir</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <div class="card">
        <div class="card-body">
            <h4 class="card-title">Documentos ({{ $case->documents->count() }})</h4>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Archivo</th>
                            <th>Notas</th>
                            <th>Subido por</th>
                            <th>Fecha</th>
                            <th>Tamaño</th>
                            <th class="text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($case->documents as $doc)
                            <tr>
                                <td><i class="fa fa-file-pdf text-danger"></i> {{ $doc->original_filename }}</td>
                                <td class="text-muted">{{ $doc->notes ?? '—' }}</td>
                                <td class="text-muted">{{ $doc->uploader?->name ?? '—' }}</td>
                                <td class="text-muted">{{ $doc->created_at->format('d/m/Y H:i') }}</td>
                                <td class="text-muted">{{ number_format($doc->file_size / 1024, 0) }} KB</td>
                                <td class="text-end">
                                    <a href="{{ route('monitoreo-oficial.documentos.descargar', $doc->id) }}" class="btn btn-sm btn-outline-primary">Descargar</a>
                                    @if(auth()->user()->can('monitoring.manage_cases'))
                                        <form method="POST" action="{{ route('monitoreo-oficial.documentos.eliminar', $doc->id) }}" class="d-inline" onsubmit="return confirm('¿Eliminar este documento?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger">Eliminar</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <td colspan="6" class="pt-0 pb-3">
                                    <div class="border rounded p-2 small" style="background:#fafafa;">
                                        <div class="fw-semibold text-muted mb-1"><i class="fa fa-robot"></i> Análisis con IA</div>
                                        @if($doc->ai_analyzed_at)
                                            @if($doc->ai_error)
                                                <div class="text-muted">
                                                    <span class="badge bg-secondary">No disponible</span>
                                                    {{ $doc->ai_error }}
                                                </div>
                                            @else
                                                <div>{{ $doc->ai_summary }}</div>
                                                @if($doc->ai_has_relevant_change)
                                                    <div class="mt-1"><span class="badge bg-warning text-dark">Cambio relevante</span> {{ $doc->ai_change_summary }}</div>
                                                @elseif($doc->ai_change_summary !== null)
                                                    <div class="mt-1"><span class="badge bg-success">Sin cambios de fondo</span></div>
                                                @endif
                                            @endif
                                        @else
                                            <div class="text-muted">Pendiente de analizar.</div>
                                        @endif
                                        @if(auth()->user()->can('monitoring.run_manual'))
                                            <form method="POST" action="{{ route('monitoreo-oficial.documentos.reanalizar', $doc->id) }}" class="mt-1">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-outline-secondary">
                                                    {{ $doc->ai_analyzed_at ? 'Re-analizar' : 'Analizar ahora' }}
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-muted text-center">No se ha subido ningún documento todavía.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <a href="{{ route('monitoreo-oficial.dashboard') }}" class="btn btn-outline-secondary">&laquo; Volver al listado</a>
@endsection
