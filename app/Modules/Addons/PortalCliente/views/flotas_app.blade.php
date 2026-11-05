@extends('addon-portal-cliente::layouts.portal')
@section('title', 'Mis Vehículos')

@section('content')
{{-- Flotas Portal=Panel (#657): mismas pantallas Vue del panel, paquete ligero portal-flotas.js --}}
<link rel="stylesheet" href="/assets/css/bootstrap.min.css">
<link rel="stylesheet" href="/plugins/quasar/extras/bootstrap-icons/bootstrap-icons.css">
@include('addon-portal-cliente::partials.flotas_estilos')

@if(! $privacidad['aceptada'])
    <div class="card" id="flotas-privacidad" style="padding:1.5rem">
        <div class="card-title">Aviso de privacidad del rastreo</div>
        <p>{{ $privacidad['texto'] }}</p>
        <button type="button" class="btn btn-primary" id="flotas-privacidad-ok">Acepto</button>
    </div>
@endif

<div id="portal-flotas-app" @if(! $privacidad['aceptada']) style="display:none" @endif>
    @if($vista === 'nuevo')
        <fleet-vehicle-form base-url="/portal/flotas"></fleet-vehicle-form>
    @elseif($vista === 'ficha')
        <fleet-vehicle-show :vehicle-id="{{ (int) $vehicleId }}" base-url="/portal/flotas" modo="portal"></fleet-vehicle-show>
    @else
        <fleet-dashboard base-url="/portal/flotas" modo="portal"></fleet-dashboard>
    @endif
</div>
@endsection

@push('scripts')
<script src="{{ mix('js/portal-flotas.js') }}"></script>
@if(! $privacidad['aceptada'])
<script>
document.getElementById('flotas-privacidad-ok').addEventListener('click', function () {
    var csrf = document.querySelector('meta[name="csrf-token"]');
    fetch('{{ route('portal.flotas.privacidad') }}', { method: 'POST', headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf ? csrf.content : '' } })
        .then(function (r) { if (r.ok) { window.location.reload(); } });
});
</script>
@endif
@endpush
