@extends('core-layout::master')

@section('title')
    Monitoreo Oficial — {{ $case->exists ? 'Editar expediente' : 'Nuevo expediente' }}
@endsection

@section('content')
    <Breadcrumb :list='[{"title":"Inicio"},{"title":"Monitoreo Oficial","active":"active"},{"title":"{{ $case->exists ? "Editar" : "Nuevo" }} expediente","active":"active"}]'></Breadcrumb>

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
            <h4 class="card-title">{{ $case->exists ? 'Editar expediente' : 'Nuevo expediente' }}</h4>

            <form method="POST" action="{{ $case->exists ? route('monitoreo-oficial.expedientes.actualizar', $case->id) : route('monitoreo-oficial.expedientes.guardar') }}">
                @csrf
                @if ($case->exists)
                    @method('PUT')
                @endif

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Autoridad *</label>
                        <input type="text" name="authority" class="form-control" value="{{ old('authority', $case->authority) }}" placeholder="Ej. IMPI" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Fuente</label>
                        <select name="source_id" class="form-select">
                            <option value="">— Sin fuente asociada —</option>
                            @foreach ($sources as $s)
                                <option value="{{ $s->id }}" @selected(old('source_id', $case->source_id) == $s->id)>{{ $s->name }} ({{ $s->authority }})</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-12">
                        <label class="form-label">Denominación *</label>
                        <input type="text" name="name" class="form-control" value="{{ old('name', $case->name) }}" placeholder="Ej. MEGANETMX TE CONECTA" required>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">N.º de expediente</label>
                        <input type="text" name="case_number" class="form-control" value="{{ old('case_number', $case->case_number) }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">N.º de registro</label>
                        <input type="text" name="registration_number" class="form-control" value="{{ old('registration_number', $case->registration_number) }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">N.º de referencia</label>
                        <input type="text" name="reference_number" class="form-control" value="{{ old('reference_number', $case->reference_number) }}">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Clase</label>
                        <input type="text" name="class_code" class="form-control" value="{{ old('class_code', $case->class_code) }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Estado de monitoreo *</label>
                        <select name="monitoring_status" class="form-select" required>
                            <option value="activo" @selected(old('monitoring_status', $case->monitoring_status) === 'activo')>Activo</option>
                            <option value="pausado" @selected(old('monitoring_status', $case->monitoring_status) === 'pausado')>Pausado</option>
                        </select>
                    </div>

                    <div class="col-12">
                        <label class="form-label">Estado legal actual</label>
                        <textarea name="current_legal_status" class="form-control" rows="2">{{ old('current_legal_status', $case->current_legal_status) }}</textarea>
                    </div>
                </div>

                <hr>
                <p class="text-muted small mb-2">
                    Avisos cuando la IA detecte un cambio de fondo en un documento de este expediente
                    (opcional — la campana interna del sistema ya avisa, sin configurar nada, a quien
                    subió el documento y a quien dio de alta el expediente).
                </p>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Correo adicional para avisos</label>
                        <input type="email" name="notify_email" class="form-control" value="{{ old('notify_email', $case->notify_email) }}" placeholder="ej. legal@meganet.mx">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">WhatsApp adicional para avisos</label>
                        <input type="text" name="notify_whatsapp" class="form-control" value="{{ old('notify_whatsapp', $case->notify_whatsapp) }}" placeholder="ej. 5512345678">
                    </div>
                </div>

                <div class="mt-3 d-flex gap-2">
                    <button type="submit" class="btn btn-primary">{{ $case->exists ? 'Guardar cambios' : 'Registrar expediente' }}</button>
                    <a href="{{ route('monitoreo-oficial.dashboard') }}" class="btn btn-outline-secondary">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
@endsection
