@extends('addon-portal-cliente::layouts.portal')
@section('title', 'Consumo')

@section('content')
<div class="page-header">
    <h1>📊 Consumo de Internet</h1>
</div>

@if(!$hayDatos)
<div class="card" style="text-align:center; padding:3rem">
    <div style="font-size:3rem; margin-bottom:1rem">📊</div>
    <h3>Sin datos de consumo disponibles</h3>
    <p style="color:var(--text-muted); margin-top:.5rem; font-size:.875rem">
        No encontramos registros de consumo para tu cuenta.<br>
        Esto puede deberse a que tu tipo de conexión no registra consumo individualizado.
    </p>
</div>
@else
<div class="card">
    <div class="card-title">Últimos 30 registros</div>
    <div style="overflow-x:auto">
    <table class="portal-table">
        <thead>
            <tr>
                <th>Fecha</th>
                <th>Descarga</th>
                <th>Subida</th>
                <th>Total</th>
                <th>IP</th>
            </tr>
        </thead>
        <tbody>
            @foreach($consumos as $c)
            @php
                $dlGB  = round($c->bytes_in  / (1024**3), 2);
                $ulGB  = round($c->bytes_out / (1024**3), 2);
                $totGB = round(($c->bytes_in + $c->bytes_out) / (1024**3), 2);
            @endphp
            <tr>
                <td>{{ $c->date_recorded }}</td>
                <td>{{ $dlGB }} GB</td>
                <td>{{ $ulGB }} GB</td>
                <td style="font-weight:600">{{ $totGB }} GB</td>
                <td style="font-family:monospace; font-size:.82rem; color:var(--text-muted)">{{ $c->ip_address ?? '—' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    </div>
</div>
@endif
@endsection
