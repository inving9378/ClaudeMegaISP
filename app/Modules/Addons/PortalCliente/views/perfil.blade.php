@extends('addon-portal-cliente::layouts.portal')
@section('title', 'Mi Perfil')

@section('content')
<div class="page-header">
    <h1>👤 Mi Perfil</h1>
</div>

{{-- Alertas globales --}}
@if(session('success'))
    <div class="alert alert-success" style="margin-bottom:1rem">✅ {{ session('success') }}</div>
@endif
@if(session('info'))
    <div class="alert alert-info" style="margin-bottom:1rem">ℹ️ {{ session('info') }}</div>
@endif

{{-- Datos del cliente --}}
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
            <div style="font-size:1rem; font-weight:500; margin-top:.25rem">{{ $cmi->email ?: '—' }}</div>
        </div>
        <div>
            <div style="font-size:.75rem; color:var(--text-muted); text-transform:uppercase; font-weight:600">Teléfono</div>
            <div style="font-size:1rem; font-weight:500; margin-top:.25rem">{{ $cmi->phone ?: '—' }}</div>
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
    @if($cmi->street || $cmi->external_number)
    <div style="margin-top:1rem; padding-top:1rem; border-top:1px solid var(--border)">
        <div style="font-size:.75rem; color:var(--text-muted); text-transform:uppercase; font-weight:600">Dirección</div>
        <div style="font-size:1rem; margin-top:.25rem">
            {{ trim(($cmi->street ?? '') . ' ' . ($cmi->external_number ?? '') . ($cmi->internal_number ? ' Int. ' . $cmi->internal_number : '')) ?: '—' }}
        </div>
    </div>
    @endif
</div>

{{-- Editar datos de contacto --}}
<div class="card">
    <div class="card-title">✏️ Editar datos de contacto</div>
    <p style="font-size:.82rem; color:var(--text-muted); margin-bottom:1.25rem">
        Puedes actualizar tu email, teléfono y dirección. Cada cambio queda registrado para tu seguridad.
        <strong>No afecta tu plan, facturación ni acceso.</strong>
    </p>

    <form method="POST" action="{{ route('portal.perfil.actualizar') }}" style="max-width:500px">
        @csrf
        <div class="form-group">
            <label>Email <span style="color:var(--text-muted); font-size:.8rem">(opcional)</span></label>
            <input type="email" name="email" class="form-control @error('email') is-invalid @enderror"
                value="{{ old('email', $cmi->email) }}" maxlength="255" placeholder="tu@correo.com">
            @error('email')
                <div class="invalid-feedback" style="color:var(--danger); font-size:.82rem; margin-top:.25rem">{{ $message }}</div>
            @enderror
        </div>
        <div class="form-group">
            <label>Teléfono <span style="color:var(--text-muted); font-size:.8rem">(7-20 dígitos)</span></label>
            <input type="tel" name="phone" class="form-control @error('phone') is-invalid @enderror"
                value="{{ old('phone', $cmi->phone) }}" maxlength="20" placeholder="5500000000">
            @error('phone')
                <div class="invalid-feedback" style="color:var(--danger); font-size:.82rem; margin-top:.25rem">{{ $message }}</div>
            @enderror
        </div>
        <div style="display:grid; grid-template-columns:1fr 90px 70px; gap:.5rem; align-items:start">
            <div class="form-group" style="margin-bottom:0">
                <label>Calle</label>
                <input type="text" name="street" class="form-control"
                    value="{{ old('street', $cmi->street) }}" maxlength="255" placeholder="Av. Reforma">
            </div>
            <div class="form-group" style="margin-bottom:0">
                <label>Núm. ext.</label>
                <input type="text" name="external_number" class="form-control" maxlength="50"
                    value="{{ old('external_number', $cmi->external_number) }}" placeholder="123">
            </div>
            <div class="form-group" style="margin-bottom:0">
                <label>Int.</label>
                <input type="text" name="internal_number" class="form-control" maxlength="50"
                    value="{{ old('internal_number', $cmi->internal_number) }}" placeholder="A">
            </div>
        </div>
        <div style="margin-top:1.25rem">
            <button type="submit" class="btn btn-primary">Guardar cambios</button>
        </div>
    </form>
</div>

{{-- Cambiar contraseña --}}
<div class="card">
    <div class="card-title">🔒 Cambiar contraseña del portal</div>
    <form method="POST" action="{{ route('portal.perfil.password') }}" style="max-width:400px">
        @csrf
        <div class="form-group">
            <label>Contraseña actual</label>
            <input type="password" name="contrasena_actual"
                class="form-control @error('contrasena_actual') is-invalid @enderror" required>
            @error('contrasena_actual')
                <div class="invalid-feedback" style="color:var(--danger); font-size:.82rem; margin-top:.25rem">{{ $message }}</div>
            @enderror
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
