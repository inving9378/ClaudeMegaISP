@extends('core-layout::master')

@section('title')
    Monitoreo Oficial — {{ $source->exists ? 'Editar fuente' : 'Nueva fuente' }}
@endsection

@section('content')
    <Breadcrumb :list='[{"title":"Inicio"},{"title":"Monitoreo Oficial","active":"active"},{"title":"{{ $source->exists ? "Editar" : "Nueva" }} fuente","active":"active"}]'></Breadcrumb>

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
            <h4 class="card-title">{{ $source->exists ? 'Editar fuente' : 'Nueva fuente oficial' }}</h4>
            <p class="text-muted">La descarga automática (scraping/PDF) de cada tipo de fuente se habilita en la Fase 3. Por ahora, esta ficha solo identifica la fuente para poder asociarla a un expediente.</p>

            <form method="POST" action="{{ $source->exists ? route('monitoreo-oficial.fuentes.actualizar', $source->id) : route('monitoreo-oficial.fuentes.guardar') }}">
                @csrf
                @if ($source->exists)
                    @method('PUT')
                @endif

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Autoridad *</label>
                        <input type="text" name="authority" class="form-control" value="{{ old('authority', $source->authority) }}" placeholder="Ej. IMPI" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Nombre *</label>
                        <input type="text" name="name" class="form-control" value="{{ old('name', $source->name) }}" placeholder="Ej. IMPI — Carga manual de documentos" required>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Tipo *</label>
                        <select name="source_type" class="form-select" required>
                            @foreach (['manual_pdf' => 'Carga manual de PDF', 'url' => 'URL / página web', 'siga' => 'SIGA', 'vidoc' => 'VIDOC', 'gaceta' => 'Gaceta oficial'] as $val => $label)
                                <option value="{{ $val }}" @selected(old('source_type', $source->source_type) === $val)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">URL base</label>
                        <input type="url" name="base_url" class="form-control" value="{{ old('base_url', $source->base_url) }}" placeholder="https://...">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Estado *</label>
                        <select name="status" class="form-select" required>
                            <option value="disponible" @selected(old('status', $source->status) === 'disponible')>Disponible</option>
                            <option value="inactiva" @selected(old('status', $source->status) === 'inactiva')>Inactiva</option>
                        </select>
                    </div>
                </div>

                <div class="mt-3 d-flex gap-2">
                    <button type="submit" class="btn btn-primary">{{ $source->exists ? 'Guardar cambios' : 'Registrar fuente' }}</button>
                    <a href="{{ route('monitoreo-oficial.configuracion') }}" class="btn btn-outline-secondary">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
@endsection
