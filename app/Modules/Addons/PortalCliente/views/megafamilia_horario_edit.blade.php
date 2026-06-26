@extends('addon-portal-cliente::layouts.portal')
@section('title', 'Editar horario')

@section('content')
@php
    $diasLargo = [0 => 'Domingo', 1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado'];
    $diasCorto = [0 => 'Dom', 1 => 'Lun', 2 => 'Mar', 3 => 'Mié', 4 => 'Jue', 5 => 'Vie', 6 => 'Sáb'];
    $selDays   = collect(old('days', $schedule->days ?? []))->map(fn ($d) => (int) $d)->all();
@endphp

<div class="page-header">
    <div>
        <h1>⏰ Editar horario</h1>
        <p style="color:var(--text-muted); font-size:.875rem; margin-top:.25rem">
            Perfil «{{ $perfil->name }}» — Horario #{{ $schedule->id }}
        </p>
    </div>
    <a href="{{ route('portal.megafamilia') }}" class="btn btn-outline btn-sm">← Volver</a>
</div>

<div class="card" style="max-width:680px">
    <div class="card-title">Datos del horario</div>
    <form method="POST" action="{{ route('portal.megafamilia.horarios.update', [$perfil->id, $schedule->id]) }}">
        @csrf
        @method('PUT')

        <div class="form-group">
            <label>Nombre <span style="color:var(--danger)">*</span></label>
            <input type="text" name="nombre" class="form-control" maxlength="100" required
                   value="{{ old('nombre', $schedule->name) }}">
        </div>

        <div class="form-group">
            <label>Días <span style="color:var(--danger)">*</span></label>
            <div style="display:flex; gap:.75rem; flex-wrap:wrap">
                @foreach($diasLargo as $num => $lbl)
                    <label style="display:flex; align-items:center; gap:.3rem; font-weight:400; font-size:.9rem; margin:0">
                        <input type="checkbox" name="days[]" value="{{ $num }}" @checked(in_array($num, $selDays, true))> {{ $lbl }}
                    </label>
                @endforeach
            </div>
        </div>

        <div class="kpi-grid">
            <div class="form-group" style="margin-bottom:0">
                <label>Hora inicio <span style="color:var(--danger)">*</span></label>
                <input type="time" name="hora_inicio" class="form-control" required
                       value="{{ old('hora_inicio', substr((string) $schedule->start_time, 0, 5)) }}">
            </div>
            <div class="form-group" style="margin-bottom:0">
                <label>Hora fin <span style="color:var(--danger)">*</span></label>
                <input type="time" name="hora_fin" class="form-control" required
                       value="{{ old('hora_fin', substr((string) $schedule->end_time, 0, 5)) }}">
            </div>
            <div class="form-group" style="margin-bottom:0">
                <label>Acción</label>
                <select name="action" class="form-control">
                    <option value="block" @selected(old('action', $schedule->action) === 'block')>Bloquear internet</option>
                    <option value="allow" @selected(old('action', $schedule->action) === 'allow')>Permitir internet</option>
                </select>
            </div>
        </div>

        <div class="form-group" style="margin-top:1rem">
            <label style="display:flex; align-items:center; gap:.5rem; font-weight:500">
                <input type="hidden" name="active" value="0">
                <input type="checkbox" name="active" value="1" @checked(old('active', $schedule->active))>
                Horario activo
            </label>
        </div>

        <div style="display:flex; gap:.75rem; margin-top:1.5rem">
            <button type="submit" class="btn btn-primary">Guardar cambios</button>
            <a href="{{ route('portal.megafamilia') }}" class="btn btn-outline">Cancelar</a>
        </div>
    </form>
</div>
@endsection
