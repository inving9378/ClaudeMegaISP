@extends('addon-portal-cliente::layouts.portal')
@section('title', 'Solicitudes — MegaFamilia')

@section('content')
@php
    $tipoLabels = [
        'time_extra' => 'Tiempo extra',
        'app_unlock' => 'Desbloquear app',
        'web_unlock' => 'Desbloquear sitio',
    ];
@endphp

<div class="page-header">
    <div>
        <h1>🔔 Solicitudes de permiso</h1>
        <p style="color:var(--text-muted); font-size:.875rem; margin-top:.25rem">
            Pendientes de tus hijos — apruébalas o recházalas
        </p>
    </div>
    <a href="{{ route('portal.megafamilia') }}" class="btn btn-outline btn-sm">← MegaFamilia</a>
</div>

<div class="card">
    <div class="card-title">Pendientes ({{ $solicitudes->count() }})</div>

    @if($solicitudes->isEmpty())
        <p style="color:var(--text-muted); font-size:.9rem; text-align:center; padding:1.5rem 0">
            No hay solicitudes pendientes.
        </p>
    @else
        <div class="table-responsive">
            <table class="portal-table">
                <thead>
                    <tr>
                        <th>Hijo</th>
                        <th>Solicitud</th>
                        <th>Dispositivo</th>
                        <th>Fecha</th>
                        <th style="text-align:right">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($solicitudes as $sol)
                        <tr>
                            <td><strong>{{ optional($sol->profile)->name ?? '—' }}</strong></td>
                            <td>
                                <span class="badge badge-info">{{ $tipoLabels[$sol->type] ?? $sol->type }}</span>
                                @if($sol->detail)
                                    <div style="margin-top:.2rem">{{ $sol->detail }}</div>
                                @endif
                                @if($sol->message)
                                    <div style="color:var(--text-muted); font-size:.78rem; margin-top:.15rem">“{{ $sol->message }}”</div>
                                @endif
                            </td>
                            <td>{{ optional($sol->device)->name ?? '—' }}</td>
                            <td style="white-space:nowrap; font-size:.82rem">{{ optional($sol->created_at)->format('d/m/Y H:i') }}</td>
                            <td style="text-align:right; white-space:nowrap">
                                <form method="POST" action="{{ route('portal.megafamilia.solicitudes.aprobar', $sol->id) }}" style="display:inline">
                                    @csrf
                                    <button type="submit" class="btn btn-success btn-sm">✓ Aprobar</button>
                                </form>
                                <form method="POST" action="{{ route('portal.megafamilia.solicitudes.rechazar', $sol->id) }}" style="display:inline">
                                    @csrf
                                    <button type="submit" class="btn btn-danger btn-sm">✕ Rechazar</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
@endsection
