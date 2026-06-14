@extends('addon-portal-cliente::layouts.portal')
@section('title', 'Tickets')

@section('content')
<div class="page-header">
    <h1>🎫 Mis Tickets de Soporte</h1>
    <button onclick="document.getElementById('form-nuevo').style.display='block'; this.style.display='none'"
        class="btn btn-primary">+ Nuevo ticket</button>
</div>

{{-- Formulario nuevo ticket (oculto por defecto) --}}
<div id="form-nuevo" class="card" style="display:none">
    <div class="card-title">🆕 Crear nuevo ticket</div>
    <form method="POST" action="{{ route('portal.tickets.store') }}">
        @csrf
        <div class="form-group">
            <label>Asunto</label>
            <input type="text" name="topic" class="form-control" placeholder="Describe brevemente tu problema" required maxlength="255">
        </div>
        <div class="form-group">
            <label>Tipo</label>
            <select name="tipo" class="form-control">
                <option value="Pregunta">Pregunta</option>
                <option value="Incidente">Incidente (servicio caído)</option>
                <option value="Problema">Problema técnico</option>
                <option value="Solicitud de función">Solicitud</option>
            </select>
        </div>
        <div class="form-group">
            <label>Descripción (opcional)</label>
            <textarea name="descripcion" class="form-control" rows="4" placeholder="Describe con más detalle tu situación..."></textarea>
        </div>
        <div style="display:flex; gap:.75rem">
            <button type="submit" class="btn btn-primary">Enviar ticket</button>
            <button type="button" onclick="document.getElementById('form-nuevo').style.display='none'; document.querySelector('.btn-primary').style.display='inline-flex'"
                class="btn btn-outline">Cancelar</button>
        </div>
    </form>
</div>

{{-- Listado --}}
@if($tickets->isEmpty())
<div class="card" style="text-align:center; padding:3rem">
    <div style="font-size:3rem; margin-bottom:1rem">🎫</div>
    <h3>Sin tickets aún</h3>
    <p style="color:var(--text-muted); margin-top:.5rem">¿Tienes algún problema? Crea tu primer ticket de soporte.</p>
</div>
@else
<div class="card" style="padding:0; overflow:hidden">
    <div style="overflow-x:auto">
    <table class="portal-table">
        <thead>
            <tr>
                <th>#</th>
                <th>Asunto</th>
                <th>Tipo</th>
                <th>Estado</th>
                <th>Fecha</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            @foreach($tickets as $t)
            @php
                $badgeClass = match($t->estado) {
                    'Nuevo'                 => 'badge-info',
                    'Trabajo en curso'      => 'badge-warning',
                    'Resuelto', 'Cerrado'   => 'badge-success',
                    'Esperando al cliente'  => 'badge-warning',
                    default                 => 'badge-secondary',
                };
            @endphp
            <tr>
                <td style="font-family:monospace">T-{{ str_pad($t->id, 6, '0', STR_PAD_LEFT) }}</td>
                <td style="font-weight:500">{{ Str::limit($t->topic, 60) }}</td>
                <td style="color:var(--text-muted); font-size:.82rem">{{ $t->type ?? '—' }}</td>
                <td><span class="badge {{ $badgeClass }}">{{ $t->estado }}</span></td>
                <td style="color:var(--text-muted); font-size:.82rem">{{ $t->created_at ? \Carbon\Carbon::parse($t->created_at)->format('d/m/Y') : '—' }}</td>
                <td>
                    <a href="{{ route('portal.tickets.show', $t->id) }}" class="btn btn-outline btn-sm">Ver</a>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
    </div>
</div>
@endif
@endsection
