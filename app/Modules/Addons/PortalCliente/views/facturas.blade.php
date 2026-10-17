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

{{-- FAC-10 (#518): documento vigente de cada factura, igual que en el sistema (un solo ícono). --}}
@if(isset($documentos) && $documentos->isNotEmpty())
<div class="page-header" style="margin-top:1.5rem">
    <h2 style="font-size:1.1rem">Prefacturas y facturas en PDF</h2>
    <span style="color:var(--text-muted); font-size:.82rem">Sin valor fiscal</span>
</div>
<div class="card" style="padding:0; overflow:hidden">
    <div style="overflow-x:auto">
    <table class="portal-table">
        <thead>
            <tr>
                <th>Periodo</th>
                <th>Total</th>
                <th>Saldo</th>
                <th>Documento</th>
                <th>PDF</th>
            </tr>
        </thead>
        <tbody>
            @foreach($documentos as $d)
                <tr>
                    <td>{{ \App\Services\Finance\Billing\PrefacturaDataProvider::periodoLegible($d->period) ?: $d->number }}</td>
                    <td style="font-weight:600">${{ number_format($d->total, 2) }}</td>
                    <td>${{ number_format($d->pending_balance, 2) }}</td>
                    <td><span class="doc-badge doc-badge--{{ $d->documento }}">{{ \App\Models\Invoice::DOCUMENTOS[$d->documento]['etiqueta'] ?? $d->documento }}</span></td>
                    <td>
                        <a href="{{ route('portal.documentos.ver', $d->id) }}" target="_blank" rel="noopener" class="btn btn-outline btn-sm"
                           title="{{ $d->documento === 'factura' ? 'Factura (pagada)' : 'Prefactura' }}">📄 {{ $d->documento === 'factura' ? 'Factura' : 'Prefactura' }}</a>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
    </div>
</div>
@endif
@endsection
