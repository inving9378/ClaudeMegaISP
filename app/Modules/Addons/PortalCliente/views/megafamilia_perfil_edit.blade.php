@extends('addon-portal-cliente::layouts.portal')
@section('title', 'Editar perfil')

@section('content')
<div class="page-header">
    <div>
        <h1>✏️ Editar perfil</h1>
        <p style="color:var(--text-muted); font-size:.875rem; margin-top:.25rem">
            Cuenta #{{ $profile->account_id }} — Perfil #{{ $profile->id }}
        </p>
    </div>
    <a href="{{ route('portal.megafamilia') }}" class="btn btn-outline btn-sm">← Volver</a>
</div>

@php
    $tipoLabels = [
        'nino'           => 'Niño',
        'preadolescente' => 'Preadolescente',
        'adolescente'    => 'Adolescente',
    ];
    $nivelLabels = [
        'primaria'     => 'Primaria',
        'secundaria'   => 'Secundaria',
        'preparatoria' => 'Preparatoria',
    ];
@endphp

<div class="card" style="max-width:640px">
    <div class="card-title">Datos del perfil</div>
    <form method="POST" action="{{ route('portal.megafamilia.perfiles.update', $profile->id) }}">
        @csrf
        @method('PUT')

        <div class="form-group">
            <label>Nombre <span style="color:var(--danger)">*</span></label>
            <input type="text" name="nombre" class="form-control" maxlength="100" required
                   value="{{ old('nombre', $profile->name) }}">
        </div>

        <div class="form-group">
            <label>Edad</label>
            <input type="number" name="edad" class="form-control" min="1" max="17"
                   value="{{ old('edad', $profile->age) }}" placeholder="1 a 17">
        </div>

        <div class="form-group">
            <label>Tipo de perfil</label>
            <select name="profile_type" class="form-control">
                <option value="">— Sin especificar —</option>
                @foreach($tipoLabels as $val => $lbl)
                    <option value="{{ $val }}" @selected(old('profile_type', $profile->profile_type) === $val)>{{ $lbl }}</option>
                @endforeach
            </select>
        </div>

        <div class="form-group">
            <label>Nivel escolar</label>
            <select name="school_level" class="form-control">
                <option value="">— Sin especificar —</option>
                @foreach($nivelLabels as $val => $lbl)
                    <option value="{{ $val }}" @selected(old('school_level', $profile->school_level) === $val)>{{ $lbl }}</option>
                @endforeach
            </select>
        </div>

        <div class="form-group">
            <label style="display:flex; align-items:center; gap:.5rem; font-weight:500">
                <input type="hidden" name="active" value="0">
                <input type="checkbox" name="active" value="1" @checked(old('active', $profile->active))>
                Perfil activo
            </label>
        </div>

        <div style="display:flex; gap:.75rem; margin-top:1.5rem">
            <button type="submit" class="btn btn-primary">Guardar cambios</button>
            <a href="{{ route('portal.megafamilia') }}" class="btn btn-outline">Cancelar</a>
        </div>
    </form>
</div>
@endsection
