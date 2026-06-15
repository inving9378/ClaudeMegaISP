@extends('addon-portal-cliente::layouts.portal')
@section('title', 'Pagos y CLABE')

@section('content')
<div class="page-header">
    <h1>💳 Pagos y CLABE</h1>
    <span style="color:var(--text-muted); font-size:.875rem">Solo lectura</span>
</div>

{{-- CLABE --}}
@if($clabes->isNotEmpty())
<div class="card">
    <div class="card-title">🏦 Cuenta CLABE para transferencia</div>
    @foreach($clabes->where('is_active', true) as $clabe)
    <div style="background:var(--surface); border-radius:10px; padding:1.25rem; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:1rem; margin-bottom:.75rem">
        <div>
            <div style="font-size:.75rem; color:var(--text-muted); margin-bottom:.25rem">CLABE interbancaria</div>
            <div style="font-size:1.4rem; font-weight:700; letter-spacing:.1em; font-family:monospace">{{ $clabe->clabe }}</div>
        </div>
        <button onclick="navigator.clipboard.writeText('{{ $clabe->clabe }}').then(()=>alert('CLABE copiada'))"
            class="btn btn-primary">📋 Copiar</button>
    </div>
    @endforeach
    <div style="font-size:.8rem; color:var(--text-muted); margin-top:.5rem">
        ℹ️ Realiza tu transferencia a esta CLABE. El pago se reflejará automáticamente en tu cuenta en 1-2 días hábiles.
    </div>
</div>
@else
<div class="card">
    <div class="card-title">🏦 CLABE de pago</div>
    <div style="color:var(--text-muted); font-size:.875rem; text-align:center; padding:1.5rem">
        No tienes una CLABE asignada aún. Contacta a soporte para obtenerla.
    </div>
</div>
@endif

{{-- Historial de pagos --}}
<div class="card">
    <div class="card-title">📋 Historial de pagos</div>
    @if($pagos->isEmpty())
        <div style="text-align:center; color:var(--text-muted); padding:2rem">
            No hay pagos registrados aún.
        </div>
    @else
        <div style="overflow-x:auto">
        <table class="portal-table">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Monto</th>
                    <th>Comentario</th>
                </tr>
            </thead>
            <tbody>
                @foreach($pagos as $p)
                <tr>
                    <td>{{ $p->date ?? '—' }}</td>
                    <td style="font-weight:600; color:var(--success)">${{ number_format($p->amount, 2) }}</td>
                    <td style="color:var(--text-muted); font-size:.85rem">{{ $p->comment ?? '—' }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        </div>
    @endif
</div>

@if(config('openpay.sandbox'))
<div class="alert alert-success" style="font-size:.82rem">
    💳 <strong>Pago con tarjeta disponible.</strong> Ingresa a cualquier factura pendiente y usa el botón <em>Pagar con tarjeta</em> para realizar tu pago en línea de forma segura con OpenPay.
    <a href="{{ route('portal.facturas') }}" style="margin-left:.5rem; font-weight:600">Ver mis facturas →</a>
</div>
@else
<div class="alert alert-info" style="font-size:.82rem">
    ℹ️ Para registrar un pago, realiza tu transferencia a la CLABE indicada arriba. El pago con tarjeta estará disponible próximamente.
</div>
@endif
@endsection
