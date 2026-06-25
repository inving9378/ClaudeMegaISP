@extends('addon-portal-cliente::layouts.portal')
@section('title', 'Mi Flota')

@section('content')
<div class="page-header">
    <div>
        <h1>🚗 Mi Flota</h1>
        <p style="color:var(--text-muted); font-size:.875rem; margin-top:.25rem">
            Cliente #{{ $cmi->client_id }} — Tus vehículos y mantenimientos
        </p>
    </div>
</div>

@if(! $active)
    {{-- Estado vacío: el cliente no tiene flota --}}
    <div class="card" style="text-align:center; padding:2.5rem 1.5rem">
        <div style="font-size:3rem; margin-bottom:.5rem">🚚</div>
        <h2 style="margin-bottom:.5rem">Aún no tienes Flotas activo</h2>
        <p style="color:var(--text-muted); max-width:520px; margin:0 auto 1.25rem">
            Administra tus vehículos, mantenimientos, documentos y rastreo GPS desde un
            solo lugar. Activa Flotas para tu negocio.
        </p>
        <a href="{{ route('portal.marketplace') }}" class="btn btn-primary">Activa Flotas para tu negocio</a>
    </div>
@else
    @php
        $tipos = [
            'car'        => 'Automóvil',
            'pickup'     => 'Pick-up',
            'truck'      => 'Camión',
            'motorcycle' => 'Motocicleta',
            'other'      => 'Otro',
        ];
        $estados = [
            'active'      => ['Activo', 'badge-success'],
            'in_workshop' => ['En taller', 'badge-warning'],
            'inactive'    => ['Inactivo', 'badge-secondary'],
        ];
    @endphp

    {{-- KPI Cards --}}
    <div class="kpi-grid">
        <div class="kpi-card">
            <div class="kpi-icon">🚗</div>
            <div class="kpi-label">Vehículos</div>
            <div class="kpi-value">{{ (int) $standing['total'] }}</div>
            <div class="kpi-sub">En tu flota</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon">✅</div>
            <div class="kpi-label">Activos</div>
            <div class="kpi-value" style="color:var(--success)">{{ (int) $standing['activos'] }}</div>
            <div class="kpi-sub">Operando</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon">🔧</div>
            <div class="kpi-label">Mantenimientos próximos</div>
            <div class="kpi-value" style="color: {{ $standing['mant_proximos'] > 0 ? 'var(--warning)' : 'var(--success)' }}">
                {{ (int) $standing['mant_proximos'] }}
            </div>
            <div class="kpi-sub">En los próximos 30 días</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon">📍</div>
            <div class="kpi-label">Geocercas activas</div>
            <div class="kpi-value">{{ (int) $standing['geocercas'] }}</div>
            <div class="kpi-sub">Zonas monitoreadas</div>
        </div>
    </div>

    @if($subscription)
        <div class="card">
            <div class="card-title">Tu suscripción</div>
            <p style="color:var(--text-muted)">
                Plan: <strong>{{ $subscription->plan ?? '—' }}</strong>
                · Estado:
                <span class="badge badge-info">{{ ucfirst($subscription->status ?? '—') }}</span>
            </p>
        </div>
    @endif

    {{-- Vehículos --}}
    <div class="card">
        <div class="card-title">Mis vehículos ({{ (int) $standing['total'] }})</div>
        @if($vehicles->isEmpty())
            <p style="color:var(--text-muted)">Todavía no tienes vehículos registrados.</p>
        @else
            <div class="table-responsive">
                <table class="portal-table">
                    <thead>
                        <tr><th>Vehículo</th><th>Placa</th><th>Tipo</th><th>Año</th><th>Estado</th></tr>
                    </thead>
                    <tbody>
                        @foreach($vehicles as $v)
                            @php [$estLabel, $estCls] = $estados[$v->status] ?? [ucfirst($v->status), 'badge-secondary']; @endphp
                            <tr>
                                <td>{{ trim($v->brand.' '.$v->model) ?: 'Vehículo' }}</td>
                                <td>{{ $v->plates }}</td>
                                <td>{{ $tipos[$v->vehicle_type] ?? 'Otro' }}</td>
                                <td>{{ $v->year ?: '—' }}</td>
                                <td><span class="badge {{ $estCls }}">{{ $estLabel }}</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
@endif
@endsection
