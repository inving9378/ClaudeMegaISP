@extends('addon-portal-cliente::layouts.portal')
@section('title', 'Consumo')

@section('content')
<div class="page-header">
    <h1>📊 Consumo y velocidad</h1>
</div>

@if($plan || $actividad)
<div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap:1rem; margin-bottom:1rem">
    @if($plan)
    <div class="card">
        <div class="card-title">Velocidad contratada</div>
        <div style="display:flex; gap:1.5rem">
            <div>
                <div style="font-size:.75rem; color:var(--text-muted); font-weight:600; text-transform:uppercase; letter-spacing:.05em">Descarga</div>
                <div style="font-size:1.5rem; font-weight:700; color:var(--pcolor)">{{ round($plan->download_speed / 1024) }} <span style="font-size:.85rem; font-weight:400">Mbps</span></div>
            </div>
            <div>
                <div style="font-size:.75rem; color:var(--text-muted); font-weight:600; text-transform:uppercase; letter-spacing:.05em">Subida</div>
                <div style="font-size:1.5rem; font-weight:700; color:var(--pcolor)">{{ round($plan->upload_speed / 1024) }} <span style="font-size:.85rem; font-weight:400">Mbps</span></div>
            </div>
        </div>
        <div style="font-size:.8rem; color:var(--text-muted); margin-top:.5rem">{{ $plan->title }}</div>
    </div>
    @endif

    @if($actividad)
    <div class="card">
        <div class="card-title">Actividad reciente</div>
        @if($actividad->rate_in_bps !== null)
            <div style="display:flex; gap:1.5rem">
                <div>
                    <div style="font-size:.75rem; color:var(--text-muted); font-weight:600; text-transform:uppercase; letter-spacing:.05em">Bajando</div>
                    <div style="font-size:1.5rem; font-weight:700; color:var(--pcolor)">{{ round($actividad->rate_in_bps / 1000000, 1) }} <span style="font-size:.85rem; font-weight:400">Mbps</span></div>
                </div>
                <div>
                    <div style="font-size:.75rem; color:var(--text-muted); font-weight:600; text-transform:uppercase; letter-spacing:.05em">Subiendo</div>
                    <div style="font-size:1.5rem; font-weight:700; color:var(--pcolor)">{{ round($actividad->rate_out_bps / 1000000, 1) }} <span style="font-size:.85rem; font-weight:400">Mbps</span></div>
                </div>
            </div>
            <div style="font-size:.75rem; color:var(--text-muted); margin-top:.5rem">
                Promedio del intervalo · actualizado {{ \Carbon\Carbon::parse($actividad->updated_at)->diffForHumans() }}
            </div>
        @else
            <div style="color:var(--text-muted); font-size:.9rem">Aún no hay suficientes muestras para calcular la velocidad reciente.</div>
        @endif
        @if($actividad->ip_address)
            <div style="font-size:.8rem; color:var(--text-muted); margin-top:.35rem; font-family:monospace">IP: {{ $actividad->ip_address }}</div>
        @endif
    </div>
    @endif
</div>
@endif

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
<div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap:1rem; margin-bottom:1rem">
    @php
        $hoyTotGB = round((($consumoHoy->bytes_in ?? 0) + ($consumoHoy->bytes_out ?? 0)) / (1024**3), 2);
        $mesTotGB = round((($consumoMes->bytes_in ?? 0) + ($consumoMes->bytes_out ?? 0)) / (1024**3), 2);
    @endphp
    <div class="card">
        <div class="card-title">Consumo de hoy</div>
        <div style="font-size:1.75rem; font-weight:700; color:var(--pcolor)">{{ $hoyTotGB }} <span style="font-size:.9rem; font-weight:400">GB</span></div>
        <div style="font-size:.8rem; color:var(--text-muted); margin-top:.25rem">
            ⬇ {{ round(($consumoHoy->bytes_in ?? 0) / (1024**3), 2) }} GB · ⬆ {{ round(($consumoHoy->bytes_out ?? 0) / (1024**3), 2) }} GB
        </div>
    </div>
    <div class="card">
        <div class="card-title">Consumo de {{ ucfirst(now()->translatedFormat('F')) }}</div>
        <div style="font-size:1.75rem; font-weight:700; color:var(--pcolor)">{{ $mesTotGB }} <span style="font-size:.9rem; font-weight:400">GB</span></div>
        <div style="font-size:.8rem; color:var(--text-muted); margin-top:.25rem">
            ⬇ {{ round(($consumoMes->bytes_in ?? 0) / (1024**3), 2) }} GB · ⬆ {{ round(($consumoMes->bytes_out ?? 0) / (1024**3), 2) }} GB
        </div>
    </div>
</div>

@if($historico->isNotEmpty())
<div class="card">
    <div class="card-title">Histórico diario (últimos 30 días)</div>
    <div style="overflow-x:auto">
    <table class="portal-table">
        <thead>
            <tr>
                <th>Fecha</th>
                <th>Descarga</th>
                <th>Subida</th>
                <th>Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($historico as $d)
            @php
                $dlGB  = round($d->bytes_in  / (1024**3), 2);
                $ulGB  = round($d->bytes_out / (1024**3), 2);
                $totGB = round(($d->bytes_in + $d->bytes_out) / (1024**3), 2);
            @endphp
            <tr>
                <td>{{ \Carbon\Carbon::parse($d->date)->format('d/m/Y') }}</td>
                <td>{{ $dlGB }} GB</td>
                <td>{{ $ulGB }} GB</td>
                <td style="font-weight:600">{{ $totGB }} GB</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    </div>
</div>
@endif
@endif
@endsection
