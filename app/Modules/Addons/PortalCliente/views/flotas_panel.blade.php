@extends('addon-portal-cliente::layouts.portal')
@section('title', 'Mis Vehículos — ' . $titulo)

@section('content')
<link rel="stylesheet" href="/assets/css/bootstrap.min.css">
<link rel="stylesheet" href="/plugins/quasar/extras/bootstrap-icons/bootstrap-icons.css">
@include('addon-portal-cliente::partials.flotas_estilos')
<div class="page-header">
    <div>
        <h1>🚗 Mis Vehículos · {{ $titulo }}</h1>
    </div>
    <a href="{{ route('portal.flotas') }}">← Volver a Mis Vehículos</a>
</div>
<div id="portal-flotas-app">{!! $componente !!}</div>
@endsection

@push('scripts')
<script src="{{ mix('js/portal-flotas.js') }}"></script>
@endpush
