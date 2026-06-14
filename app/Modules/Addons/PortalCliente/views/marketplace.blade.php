@extends('addon-portal-cliente::layouts.portal')
@section('title', 'Servicios')

@section('content')
<div class="page-header">
    <h1>🛒 Servicios disponibles</h1>
    <span style="color:var(--text-muted); font-size:.875rem">Activa servicios adicionales para tu hogar</span>
</div>

<div class="service-grid">

    {{-- MegaFamilia --}}
    <div class="service-card" style="border: {{ $megafamiliaActiva ? '2px solid var(--success)' : '1px solid var(--border)' }}">
        <div class="svc-icon">🛡️</div>
        <div>
            <h3>MegaFamilia</h3>
            @if($megafamiliaActiva)
                <span class="badge badge-success">✅ Activo</span>
            @endif
        </div>
        <p>Control parental inteligente para tus hijos. Gestiona perfiles, horarios de internet, filtros de contenido, geofences y más desde la app móvil.</p>

        <ul style="font-size:.82rem; color:var(--text-muted); line-height:1.8; padding-left:1.2rem; list-style:disc">
            <li>Perfiles por hijo</li>
            <li>Bloqueo de sitios y apps</li>
            <li>Horarios de internet</li>
            <li>Geofences familiares</li>
            <li>Tareas y recompensas</li>
        </ul>

        @if($megafamiliaActiva)
            <div style="display:flex; gap:.75rem; flex-wrap:wrap; align-items:center">
                <div style="font-size:.82rem; color:var(--success); font-weight:500">
                    ✅ Servicio activo — descarga la app para configurarlo
                </div>
                <form method="POST" action="{{ route('portal.marketplace.megafamilia.desactivar') }}">
                    @csrf
                    <button type="submit" class="btn btn-outline btn-sm"
                        onclick="return confirm('¿Deseas suspender MegaFamilia? Tu configuración se preserva.')">
                        Suspender
                    </button>
                </form>
            </div>
        @else
            <form method="POST" action="{{ route('portal.marketplace.megafamilia.activar') }}">
                @csrf
                <button type="submit" class="btn btn-primary">⚡ Activar MegaFamilia</button>
            </form>
            <div style="font-size:.78rem; color:var(--text-muted); margin-top:-.5rem">
                Activación gratuita. Configuración y gestión desde la app móvil.
            </div>
        @endif
    </div>

    {{-- Flotas (gateado — "en preparación") --}}
    <div class="service-card" style="opacity:.8">
        <div class="svc-icon">🚗</div>
        <div>
            <h3>MegaFlotas</h3>
            <span class="badge badge-secondary">En preparación</span>
        </div>
        <p>Rastreo GPS de vehículos, geocercas, control de mantenimiento y combustible para tu flotilla personal o empresarial.</p>

        <ul style="font-size:.82rem; color:var(--text-muted); line-height:1.8; padding-left:1.2rem; list-style:disc">
            <li>Rastreo GPS en tiempo real</li>
            <li>Geocercas y alertas</li>
            <li>Historial de rutas</li>
            <li>Mantenimiento preventivo</li>
        </ul>

        <form method="POST" action="{{ route('portal.marketplace.interes') }}">
            @csrf
            <input type="hidden" name="servicio" value="MegaFlotas">
            <button type="submit" class="btn btn-outline">📧 Notificarme cuando esté disponible</button>
        </form>
        <div style="font-size:.78rem; color:var(--text-muted); margin-top:-.5rem">
            Próximamente disponible.
        </div>
    </div>

    {{-- VoIP (informativa) --}}
    <div class="service-card" style="opacity:.8">
        <div class="svc-icon">📞</div>
        <div>
            <h3>MegaVoz (VoIP)</h3>
            <span class="badge badge-secondary">En preparación</span>
        </div>
        <p>Telefonía IP para tu hogar u oficina. Extensiones, grupos de timbrado, buzón de voz y más.</p>

        <form method="POST" action="{{ route('portal.marketplace.interes') }}">
            @csrf
            <input type="hidden" name="servicio" value="MegaVoz">
            <button type="submit" class="btn btn-outline">📧 Notificarme cuando esté disponible</button>
        </form>
    </div>

</div>

<div class="alert alert-info" style="margin-top:1.5rem; font-size:.82rem">
    💳 El pago en línea con tarjeta estará disponible próximamente. Los servicios activados actualmente son de <strong>acceso gratuito / base</strong>.
</div>
@endsection
