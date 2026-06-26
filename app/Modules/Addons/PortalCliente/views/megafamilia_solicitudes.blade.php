@extends('addon-portal-cliente::layouts.portal')
@section('title', 'Solicitudes — MegaFamilia')

@section('content')
<div class="page-header">
    <div>
        <h1>🔔 Solicitudes de permiso</h1>
        <p style="color:var(--text-muted); font-size:.875rem; margin-top:.25rem">
            Pendientes de tus hijos — apruébalas o recházalas
        </p>
    </div>
    <a href="{{ route('portal.megafamilia') }}" class="btn btn-outline btn-sm">← MegaFamilia</a>
</div>

@include('addon-portal-cliente::partials.megafamilia_solicitudes')
@endsection
