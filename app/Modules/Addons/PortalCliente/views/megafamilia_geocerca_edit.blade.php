@extends('addon-portal-cliente::layouts.portal')
@section('title', 'Editar geocerca')

@section('content')
<div class="page-header">
    <div>
        <h1>📍 Editar geocerca</h1>
        <p style="color:var(--text-muted); font-size:.875rem; margin-top:.25rem">
            Geocerca #{{ $geo->id }}
        </p>
    </div>
    <a href="{{ route('portal.megafamilia') }}" class="btn btn-outline btn-sm">← Volver</a>
</div>

<div class="card" style="max-width:680px">
    <div class="card-title">Datos de la geocerca</div>
    <form method="POST" action="{{ route('portal.megafamilia.geocercas.update', $geo->id) }}">
        @csrf
        @method('PUT')

        <div class="form-group">
            <label>Perfil <span style="color:var(--danger)">*</span></label>
            <select name="profile_id" class="form-control" required>
                @foreach($profiles as $p)
                    <option value="{{ $p->id }}" @selected(old('profile_id', $geo->profile_id) == $p->id)>{{ $p->name }}</option>
                @endforeach
            </select>
        </div>

        <div class="form-group">
            <label>Nombre <span style="color:var(--danger)">*</span></label>
            <input type="text" name="nombre" class="form-control" maxlength="100" required
                   value="{{ old('nombre', $geo->name) }}">
        </div>

        <div class="kpi-grid">
            <div class="form-group" style="margin-bottom:0">
                <label>Latitud <span style="color:var(--danger)">*</span></label>
                <input type="number" step="any" name="latitud" class="form-control" min="-90" max="90" required
                       value="{{ old('latitud', $geo->lat) }}">
            </div>
            <div class="form-group" style="margin-bottom:0">
                <label>Longitud <span style="color:var(--danger)">*</span></label>
                <input type="number" step="any" name="longitud" class="form-control" min="-180" max="180" required
                       value="{{ old('longitud', $geo->lng) }}">
            </div>
            <div class="form-group" style="margin-bottom:0">
                <label>Radio (metros) <span style="color:var(--danger)">*</span></label>
                <input type="number" name="radio_metros" class="form-control" min="50" max="10000" required
                       value="{{ old('radio_metros', $geo->radius_meters) }}">
            </div>
            <div class="form-group" style="margin-bottom:0">
                <label>Dirección (opcional)</label>
                <input type="text" name="direccion" class="form-control" maxlength="255"
                       value="{{ old('direccion', $geo->address) }}">
            </div>
        </div>

        <div style="display:flex; gap:1.25rem; flex-wrap:wrap; margin-top:1rem">
            <label style="display:flex; align-items:center; gap:.4rem; font-weight:500; font-size:.9rem; margin:0">
                <input type="hidden" name="alert_on_enter" value="0">
                <input type="checkbox" name="alert_on_enter" value="1" @checked(old('alert_on_enter', $geo->alert_on_enter))> Avisar al entrar
            </label>
            <label style="display:flex; align-items:center; gap:.4rem; font-weight:500; font-size:.9rem; margin:0">
                <input type="hidden" name="alert_on_exit" value="0">
                <input type="checkbox" name="alert_on_exit" value="1" @checked(old('alert_on_exit', $geo->alert_on_exit))> Avisar al salir
            </label>
            <label style="display:flex; align-items:center; gap:.4rem; font-weight:500; font-size:.9rem; margin:0">
                <input type="hidden" name="active" value="0">
                <input type="checkbox" name="active" value="1" @checked(old('active', $geo->active))> Geocerca activa
            </label>
        </div>

        <div style="display:flex; gap:.75rem; margin-top:1.5rem">
            <button type="submit" class="btn btn-primary">Guardar cambios</button>
            <a href="{{ route('portal.megafamilia') }}" class="btn btn-outline">Cancelar</a>
        </div>
    </form>
</div>
@endsection
