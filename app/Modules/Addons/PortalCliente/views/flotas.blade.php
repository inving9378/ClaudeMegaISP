@extends('addon-portal-cliente::layouts.portal')
@section('title', 'Mis Vehículos')

@section('content')
<div class="page-header">
    <div>
        <h1>🚗 Mis Vehículos</h1>
        <p style="color:var(--text-muted); font-size:.875rem; margin-top:.25rem">
            Cliente #{{ $cmi->client_id }} — Tus vehículos y mantenimientos
        </p>
    </div>
</div>

@if(! $active)
    {{-- Estado vacío: el cliente no tiene vehículos --}}
    @php
        // NOTA: la columna `cliente_dedicado` NO existe hoy en `clients`/CMI (auditado).
        // Placeholder: por defecto trato al cliente como residencial. Cuando exista la
        // columna, sustituir por: $clienteDedicado = (bool) ($cmi->cliente_dedicado ?? false);
        $clienteDedicado = false;
    @endphp
    <div class="card" style="text-align:center; padding:2.5rem 1.5rem">
        <div style="font-size:3rem; margin-bottom:.5rem">🚙</div>
        <h2 style="margin-bottom:.5rem">
            {{ $clienteDedicado ? 'Gestiona la flota de tu empresa' : 'Administra y cuida tus vehículos' }}
        </h2>
        <p style="color:var(--text-muted); max-width:520px; margin:0 auto 1.25rem">
            Lleva el control de tus vehículos, mantenimientos, documentos y rastreo GPS
            desde un solo lugar.
        </p>
        <a href="{{ route('portal.marketplace') }}" class="btn btn-primary">Activar módulo de Vehículos</a>
    </div>
@endif
@endsection
