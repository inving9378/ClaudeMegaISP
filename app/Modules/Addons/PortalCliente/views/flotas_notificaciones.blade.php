@extends('addon-portal-cliente::layouts.portal')
@section('title', 'Notificaciones de flota')

@section('content')
<link rel="stylesheet" href="{{ asset('assets/css/bootstrap.min.css') }}">
<link rel="stylesheet" href="{{ asset('plugins/quasar/extras/bootstrap-icons/bootstrap-icons.css') }}">
@include('addon-portal-cliente::partials.flotas_estilos')
<div class="page-header">
    <div>
        <h1>🔔 Notificaciones de flota</h1>
        <p style="color:var(--text-muted); font-size:.875rem; margin-top:.25rem">Bitácora de alertas enviadas por tus vehículos</p>
    </div>
</div>
<div id="portal-flotas-app">
    <fleet-notification-log base-url="{{ url('/portal/flotas') }}" :portal="true"></fleet-notification-log>
</div>
@endsection

@push('scripts')
<script src="{{ mix('js/portal-flotas.js') }}"></script>
@endpush
