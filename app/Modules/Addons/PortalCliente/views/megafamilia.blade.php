@extends('addon-portal-cliente::layouts.portal')
@section('title', 'MegaFamilia')

@section('content')
<div class="page-header">
    <div>
        <h1>👨‍👩‍👧 MegaFamilia</h1>
        <p style="color:var(--text-muted); font-size:.875rem; margin-top:.25rem">
            Cliente #{{ $cmi->client_id }} — Control parental de tu familia
        </p>
    </div>
</div>

@if(! $active)
    {{-- Estado vacío: el cliente no tiene MegaFamilia activa --}}
    <div class="card" style="text-align:center; padding:2.5rem 1.5rem">
        <div style="font-size:3rem; margin-bottom:.5rem">🛡️</div>
        <h2 style="margin-bottom:.5rem">Aún no tienes MegaFamilia activo</h2>
        <p style="color:var(--text-muted); max-width:520px; margin:0 auto 1.25rem">
            Protege a tu familia: perfiles por hijo, control de dispositivos, reglas de
            tiempo y geocercas. Activa MegaFamilia desde tus servicios.
        </p>
        <a href="{{ route('portal.marketplace') }}" class="btn btn-primary">Activa MegaFamilia</a>
    </div>
@else
    @php
        $estados = [
            'active'    => ['Activa', 'badge-success'],
            'suspended' => ['Suspendida', 'badge-warning'],
            'cancelled' => ['Cancelada', 'badge-secondary'],
        ];
    @endphp

    {{-- KPI Cards --}}
    <div class="kpi-grid">
        <div class="kpi-card">
            <div class="kpi-icon">🏠</div>
            <div class="kpi-label">Cuentas</div>
            <div class="kpi-value">{{ (int) $standing['cuentas'] }}</div>
            <div class="kpi-sub">De tu familia</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon">🧒</div>
            <div class="kpi-label">Perfiles</div>
            <div class="kpi-value">{{ (int) $standing['perfiles'] }}</div>
            <div class="kpi-sub">Hijos registrados</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon">📱</div>
            <div class="kpi-label">Dispositivos</div>
            <div class="kpi-value">{{ (int) $standing['dispositivos'] }}</div>
            <div class="kpi-sub">Vinculados</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon">⏱️</div>
            <div class="kpi-label">Reglas</div>
            <div class="kpi-value">{{ (int) $standing['reglas'] }}</div>
            <div class="kpi-sub">Configuradas</div>
        </div>
    </div>

    {{-- Cards por cuenta --}}
    <div class="card">
        <div class="card-title">Mis cuentas ({{ (int) $standing['cuentas'] }})</div>
        <div class="kpi-grid" style="margin-top:.5rem">
            @foreach($cuentas as $cuenta)
                @php [$estLabel, $estCls] = $estados[$cuenta->status] ?? [ucfirst($cuenta->status), 'badge-secondary']; @endphp
                <div class="kpi-card">
                    <div class="kpi-label">Cuenta #{{ $cuenta->id }}</div>
                    <div class="kpi-value" style="font-size:1rem; margin:.25rem 0">
                        <span class="badge {{ $estCls }}">{{ $estLabel }}</span>
                    </div>
                    <div class="kpi-sub">
                        🧒 {{ (int) $cuenta->profiles_count }} perfiles ·
                        📱 {{ (int) $cuenta->devices_count }} dispositivos
                    </div>
                </div>
            @endforeach
        </div>
    </div>
@endif
@endsection
