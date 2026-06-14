@extends('addon-portal-cliente::layouts.portal')
@section('title', 'Mi Perfil')

@section('content')
<div class="page-header">
    <h1>👤 Mi Perfil</h1>
</div>

{{-- Datos del cliente (solo lectura) --}}
<div class="card">
    <div class="card-title">📋 Datos de mi cuenta</div>
    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px,1fr)); gap:1.25rem">
        <div>
            <div style="font-size:.75rem; color:var(--text-muted); text-transform:uppercase; font-weight:600">Nombre</div>
            <div style="font-size:1rem; font-weight:500; margin-top:.25rem">{{ $cmi->nombre_completo }}</div>
        </div>
        <div>
            <div style="font-size:.75rem; color:var(--text-muted); text-transform:uppercase; font-weight:600">Número de cliente</div>
            <div style="font-size:1rem; font-weight:500; margin-top:.25rem; font-family:monospace">#{{ $cmi->client_id }}</div>
        </div>
        <div>
            <div style="font-size:.75rem; color:var(--text-muted); text-transform:uppercase; font-weight:600">Email</div>
            <div style="font-size:1rem; font-weight:500; margin-top:.25rem">{{ $cmi->email ?? '—' }}</div>
        </div>
        <div>
            <div style="font-size:.75rem; color:var(--text-muted); text-transform:uppercase; font-weight:600">Teléfono</div>
            <div style="font-size:1rem; font-weight:500; margin-top:.25rem">{{ $cmi->phone ?? '—' }}</div>
        </div>
        <div>
            <div style="font-size:.75rem; color:var(--text-muted); text-transform:uppercase; font-weight:600">Estado</div>
            @php
                $estBadge = match($cmi->estado ?? '') {
                    'Activo' => 'badge-success', 'Bloqueado' => 'badge-warning', default => 'badge-secondary'
                };
            @endphp
            <div style="margin-top:.5rem"><span class="badge {{ $estBadge }}">{{ $cmi->estado ?? '—' }}</span></div>
        </div>
        <div>
            <div style="font-size:.75rem; color:var(--text-muted); text-transform:uppercase; font-weight:600">Miembro desde</div>
            <div style="font-size:1rem; font-weight:500; margin-top:.25rem">
                {{ $cmi->portal_registered_at ? $cmi->portal_registered_at->format('d/m/Y') : '—' }}
            </div>
        </div>
    </div>

    <div style="margin-top:1.5rem; padding:.75rem; background:var(--surface); border-radius:8px; font-size:.82rem; color:var(--text-muted)">
        ✏️ La edición de datos personales estará disponible próximamente. Si necesitas actualizar tu información, contacta a nuestro equipo.
    </div>
</div>

{{-- Cambiar contraseña --}}
<div class="card">
    <div class="card-title">🔒 Cambiar contraseña del portal</div>
    <form method="POST" action="{{ route('portal.perfil.password') }}" style="max-width:400px">
        @csrf
        <div class="form-group">
            <label>Contraseña actual</label>
            <input type="password" name="contrasena_actual" class="form-control" required>
        </div>
        <div class="form-group">
            <label>Nueva contraseña</label>
            <input type="password" name="nueva_contrasena" class="form-control"
                placeholder="Mínimo 8 caracteres" required>
        </div>
        <div class="form-group">
            <label>Confirmar nueva contraseña</label>
            <input type="password" name="nueva_contrasena_confirmation" class="form-control" required>
        </div>
        <button type="submit" class="btn btn-primary">Cambiar contraseña</button>
    </form>
</div>
@endsection
