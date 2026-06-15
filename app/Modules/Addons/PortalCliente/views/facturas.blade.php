@extends('addon-portal-cliente::layouts.portal')
@section('title', 'Facturas')

@section('content')
<div class="page-header">
    <h1>📄 Mis Facturas</h1>
    @if(config('openpay.sandbox'))
    <span style="font-size:.82rem; color:var(--success); font-weight:500">💳 Pago con tarjeta disponible</span>
    @else
    <span style="color:var(--text-muted); font-size:.875rem">Solo lectura</span>
    @endif
</div>

@php
function estadoFacturaBadge($estado) {
    $e = strtolower($estado ?? '');
    if (str_contains($e,'pagado') || str_contains($e,'paid')) return ['badge-success', 'Pagada'];
    if (str_contains($e,'atrasado') || str_contains($e,'overdue')) return ['badge-danger', 'Atrasada'];
    if (str_contains($e,'pagar')) return ['badge-warning', 'Pendiente'];
    return ['badge-secondary', $estado];
}
@endphp

<div class="card" style="padding:0; overflow:hidden">
    @if($facturas->isEmpty())
        <div style="text-align:center; padding:3rem; color:var(--text-muted)">
            <div style="font-size:3rem; margin-bottom:1rem">📄</div>
            No hay facturas registradas.
        </div>
    @else
        <div style="overflow-x:auto">
        <table class="portal-table">
            <thead>
                <tr>
                    <th>N° Factura</th>
                    <th>Fecha</th>
                    <th>Total</th>
                    <th>Estado</th>
                    <th>Pago</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                @foreach($facturas as $f)
                    @php [$cls,$label] = estadoFacturaBadge($f->estado); @endphp
                    <tr>
                        <td style="font-weight:600">{{ $f->number }}</td>
                        <td>{{ $f->document_date !== '0' && $f->document_date ? $f->document_date : '—' }}</td>
                        <td style="font-weight:600">${{ number_format($f->total, 2) }}</td>
                        <td><span class="badge {{ $cls }}">{{ $label }}</span></td>
                        <td style="color:var(--text-muted); font-size:.82rem">{{ $f->payment_date ?? '—' }}</td>
                        <td style="white-space:nowrap">
                            <a href="{{ route('portal.facturas.show', $f->id) }}" class="btn btn-outline btn-sm">Ver</a>
                            @if(config('openpay.sandbox') && ! str_contains(strtolower($f->estado ?? ''), 'pagado'))
                            <a href="{{ route('portal.facturas.show', $f->id) }}" class="btn btn-primary btn-sm" style="margin-left:.35rem">
                                💳 Pagar
                            </a>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        </div>
    @endif
</div>
@endsection
