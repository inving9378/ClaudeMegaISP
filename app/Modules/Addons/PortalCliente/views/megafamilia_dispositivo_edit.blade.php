@extends('addon-portal-cliente::layouts.portal')
@section('title', 'Editar dispositivo')

@section('content')
<div class="page-header">
    <div>
        <h1>📱 Editar dispositivo</h1>
        <p style="color:var(--text-muted); font-size:.875rem; margin-top:.25rem">
            Cuenta #{{ $device->account_id }} — Dispositivo #{{ $device->id }}
        </p>
    </div>
    <a href="{{ route('portal.megafamilia') }}" class="btn btn-outline btn-sm">← Volver</a>
</div>

@php
    $devTipoLabels = [
        'smartphone' => 'Smartphone',
        'tablet'     => 'Tablet',
        'pc'         => 'PC',
        'otro'       => 'Otro',
    ];
@endphp

<div class="card" style="max-width:640px">
    <div class="card-title">Datos del dispositivo</div>
    <form method="POST" action="{{ route('portal.megafamilia.dispositivos.update', $device->id) }}">
        @csrf
        @method('PUT')

        <div class="form-group">
            <label>Nombre <span style="color:var(--danger)">*</span></label>
            <input type="text" name="nombre" class="form-control" maxlength="100" required
                   value="{{ old('nombre', $device->name) }}">
        </div>

        <div class="form-group">
            <label>Tipo</label>
            <select name="tipo" class="form-control">
                <option value="">— Sin especificar —</option>
                @foreach($devTipoLabels as $val => $lbl)
                    <option value="{{ $val }}" @selected(old('tipo', $device->model) === $val)>{{ $lbl }}</option>
                @endforeach
            </select>
        </div>

        <div class="form-group">
            <label>Perfil asignado</label>
            <select name="profile_id" class="form-control" required>
                @foreach($profiles as $p)
                    <option value="{{ $p->id }}" @selected(old('profile_id', $device->profile_id) == $p->id)>{{ $p->name }}</option>
                @endforeach
            </select>
        </div>

        <div style="display:flex; gap:.75rem; margin-top:1.5rem">
            <button type="submit" class="btn btn-primary">Guardar cambios</button>
            <a href="{{ route('portal.megafamilia') }}" class="btn btn-outline">Cancelar</a>
        </div>
    </form>
</div>
@endsection
