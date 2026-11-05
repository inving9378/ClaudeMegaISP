@extends('addon-portal-cliente::layouts.portal')
@section('title', 'Reglas de alertas')

@section('content')
<link rel="stylesheet" href="{{ asset('assets/css/bootstrap.min.css') }}">
<link rel="stylesheet" href="{{ asset('plugins/quasar/extras/bootstrap-icons/bootstrap-icons.css') }}">
@include('addon-portal-cliente::partials.flotas_estilos')
<div class="page-header">
    <div>
        <h1>🔔 Reglas de alertas</h1>
        <p style="color:var(--text-muted); font-size:.875rem; margin-top:.25rem">
            <a href="{{ route('portal.flotas') }}">← Mis Vehículos</a>
        </p>
    </div>
</div>
<div id="portal-flotas-app">
    <fleet-rule-list base-url="{{ url('/portal/flotas') }}" modo="portal"></fleet-rule-list>
</div>
@endsection

@push('scripts')
<script src="{{ mix('js/portal-flotas.js') }}"></script>
@endpush
