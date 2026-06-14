@extends('addon-portal-cliente::layouts.portal')
@section('title', 'Dashboard')

@section('content')
<div class="page-header">
    <div>
        <h1>👋 Hola, {{ $cmi->name }}</h1>
        <p style="color:var(--text-muted); font-size:.875rem; margin-top:.25rem">
            Cliente #{{ $cmi->client_id }} — Resumen de tu cuenta
        </p>
    </div>
</div>

{{-- KPI Cards --}}
<div class="kpi-grid">
    {{-- Plan --}}
    <div class="kpi-card">
        <div class="kpi-icon">📡</div>
        <div class="kpi-label">Plan activo</div>
        @if($plan)
            <div class="kpi-value" style="font-size:1.2rem">{{ $plan->title }}</div>
            <div class="kpi-sub">
                ⬇ {{ number_format($plan->download_speed/1024, 0) }} Mbps /
                ⬆ {{ number_format($plan->upload_speed/1024, 0) }} Mbps
            </div>
        @else
            <div class="kpi-value" style="font-size:1.1rem; color:var(--text-muted)">Sin plan</div>
        @endif
    </div>

    {{-- Estado del servicio --}}
    <div class="kpi-card">
        <div class="kpi-icon">🔌</div>
        <div class="kpi-label">Estado del servicio</div>
        @if($plan)
            @php
                $estado = $plan->estado_servicio ?? 'Desconocido';
                $badgeClass = match($estado) {
                    'Activo'     => 'badge-success',
                    'Suspendido' => 'badge-warning',
                    default      => 'badge-secondary',
                };
            @endphp
            <div class="kpi-value" style="font-size:1.2rem">
                <span class="badge {{ $badgeClass }}">{{ $estado }}</span>
            </div>
        @else
            <div class="kpi-value" style="font-size:1rem; color:var(--text-muted)">—</div>
        @endif
        @if($fechaCorte)
            <div class="kpi-sub">Próximo pago: {{ $fechaCorte }}</div>
        @endif
    </div>

    {{-- Saldo --}}
    <div class="kpi-card">
        <div class="kpi-icon">💰</div>
        <div class="kpi-label">Saldo en cuenta</div>
        <div class="kpi-value" style="color: {{ $saldo >= 0 ? 'var(--success)' : 'var(--danger)' }}">
            ${{ number_format(abs($saldo), 2) }}
        </div>
        <div class="kpi-sub">{{ $saldo >= 0 ? 'A favor' : 'Saldo negativo' }}</div>
    </div>

    {{-- Facturas vencidas --}}
    <div class="kpi-card">
        <div class="kpi-icon">📄</div>
        <div class="kpi-label">Facturas atrasadas</div>
        <div class="kpi-value" style="color: {{ ($facturasVencidas->conteo ?? 0) > 0 ? 'var(--danger)' : 'var(--success)' }}">
            {{ $facturasVencidas->conteo ?? 0 }}
        </div>
        @if(($facturasVencidas->conteo ?? 0) > 0)
            <div class="kpi-sub">Total: ${{ number_format($facturasVencidas->monto ?? 0, 2) }}</div>
        @else
            <div class="kpi-sub">¡Al corriente!</div>
        @endif
    </div>

    {{-- Tickets abiertos --}}
    <div class="kpi-card">
        <div class="kpi-icon">🎫</div>
        <div class="kpi-label">Tickets abiertos</div>
        <div class="kpi-value">{{ $ticketsAbiertos }}</div>
        <div class="kpi-sub">
            @if($ticketsAbiertos > 0)
                <a href="{{ route('portal.tickets') }}">Ver tickets →</a>
            @else
                Sin tickets pendientes
            @endif
        </div>
    </div>
</div>

{{-- Accesos rápidos --}}
<div class="card">
    <div class="card-title">⚡ Accesos rápidos</div>
    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap:.75rem">
        <a href="{{ route('portal.facturas') }}" class="btn btn-outline">📄 Ver facturas</a>
        <a href="{{ route('portal.pagos') }}" class="btn btn-outline">💳 Pagar / CLABE</a>
        <a href="{{ route('portal.tickets') }}" class="btn btn-outline">🎫 Soporte</a>
        <a href="{{ route('portal.plan') }}" class="btn btn-outline">📡 Mi plan</a>
        <a href="{{ route('portal.consumo') }}" class="btn btn-outline">📊 Consumo</a>
    </div>
</div>

@if(($facturasVencidas->conteo ?? 0) > 0)
<div class="alert alert-warning">
    ⚠️ Tienes <strong>{{ $facturasVencidas->conteo }} factura(s) atrasada(s)</strong> por un total de
    <strong>${{ number_format($facturasVencidas->monto, 2) }}</strong>.
    <a href="{{ route('portal.pagos') }}">Ver opciones de pago →</a>
</div>
@endif
@endsection
