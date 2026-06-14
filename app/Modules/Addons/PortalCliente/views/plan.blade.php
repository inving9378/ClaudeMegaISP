@extends('addon-portal-cliente::layouts.portal')
@section('title', 'Mi Plan')

@section('content')
<div class="page-header">
    <h1>📡 Mi Plan de Servicio</h1>
</div>

@if($fechaCorte)
    <div class="alert alert-info">
        📅 Próxima fecha de pago: <strong>{{ $fechaCorte }}</strong>
    </div>
@endif

@forelse($servicios as $svc)
    @php
        $badgeClass = match($svc->estado ?? '') {
            'Activo'     => 'badge-success',
            'Suspendido' => 'badge-warning',
            'Eliminado'  => 'badge-secondary',
            default      => 'badge-info',
        };
        $dl = round($svc->download_speed / 1024, 0);
        $ul = round($svc->upload_speed / 1024, 0);
    @endphp
    <div class="card">
        <div class="card-title">
            {{ $svc->title }}
            <span class="badge {{ $badgeClass }}">{{ $svc->estado }}</span>
        </div>
        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:1rem">
            <div>
                <div style="font-size:.75rem; color:var(--text-muted); font-weight:600; text-transform:uppercase; letter-spacing:.05em">Descarga</div>
                <div style="font-size:1.75rem; font-weight:700; color:var(--pcolor)">{{ $dl }} <span style="font-size:.9rem; font-weight:400">Mbps</span></div>
            </div>
            <div>
                <div style="font-size:.75rem; color:var(--text-muted); font-weight:600; text-transform:uppercase; letter-spacing:.05em">Subida</div>
                <div style="font-size:1.75rem; font-weight:700; color:var(--pcolor)">{{ $ul }} <span style="font-size:.9rem; font-weight:400">Mbps</span></div>
            </div>
            @if($svc->ipv4)
            <div>
                <div style="font-size:.75rem; color:var(--text-muted); font-weight:600; text-transform:uppercase; letter-spacing:.05em">IP Asignada</div>
                <div style="font-size:1.1rem; font-weight:600">{{ $svc->ipv4 }}</div>
            </div>
            @endif
            @if($svc->start_date)
            <div>
                <div style="font-size:.75rem; color:var(--text-muted); font-weight:600; text-transform:uppercase; letter-spacing:.05em">Inicio de servicio</div>
                <div style="font-size:1rem">{{ \Carbon\Carbon::parse($svc->start_date)->format('d/m/Y') }}</div>
            </div>
            @endif
        </div>
    </div>
@empty
    <div class="card" style="text-align:center; padding:3rem">
        <div style="font-size:3rem; margin-bottom:1rem">📡</div>
        <h3>Sin servicios registrados</h3>
        <p style="color:var(--text-muted); margin-top:.5rem">Contacta a soporte si crees que esto es un error.</p>
        <a href="{{ route('portal.tickets') }}" class="btn btn-primary" style="margin-top:1rem">Crear ticket de soporte</a>
    </div>
@endforelse
@endsection
