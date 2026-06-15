@extends('addon-portal-cliente::layouts.portal')
@section('title', 'Factura #' . $factura->number)

@section('content')
<div class="page-header">
    <h1>📄 Factura #{{ $factura->number }}</h1>
    <a href="{{ route('portal.facturas') }}" class="btn btn-outline btn-sm">← Volver</a>
</div>

<div class="card">
    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px,1fr)); gap:1.5rem">
        <div>
            <div style="font-size:.75rem; color:var(--text-muted); text-transform:uppercase; font-weight:600">Fecha</div>
            <div style="font-size:1.1rem; font-weight:600; margin-top:.25rem">{{ $factura->document_date !== '0' ? $factura->document_date : '—' }}</div>
        </div>
        <div>
            <div style="font-size:.75rem; color:var(--text-muted); text-transform:uppercase; font-weight:600">Total</div>
            <div style="font-size:1.75rem; font-weight:700; color:var(--pcolor); margin-top:.25rem">${{ number_format($factura->total, 2) }}</div>
        </div>
        <div>
            <div style="font-size:.75rem; color:var(--text-muted); text-transform:uppercase; font-weight:600">Estado</div>
            @php
                $e = strtolower($factura->estado ?? '');
                if (str_contains($e,'pagado')) { $bc='badge-success'; $bl='Pagada'; }
                elseif (str_contains($e,'atrasado')) { $bc='badge-danger'; $bl='Atrasada'; }
                else { $bc='badge-warning'; $bl=$factura->estado; }
            @endphp
            <div style="margin-top:.5rem"><span class="badge {{ $bc }}">{{ $bl }}</span></div>
        </div>
        @if($factura->payment_date)
        <div>
            <div style="font-size:.75rem; color:var(--text-muted); text-transform:uppercase; font-weight:600">Fecha de pago</div>
            <div style="font-size:1.1rem; font-weight:600; margin-top:.25rem">{{ $factura->payment_date }}</div>
        </div>
        @endif
    </div>

    @if($factura->note)
    <div style="margin-top:1.5rem; padding-top:1.5rem; border-top:1px solid var(--border)">
        <div style="font-size:.75rem; color:var(--text-muted); text-transform:uppercase; font-weight:600; margin-bottom:.5rem">Notas</div>
        <div style="font-size:.875rem; line-height:1.6">{{ $factura->note }}</div>
    </div>
    @endif

    <div style="margin-top:1.5rem; padding:.75rem; background:var(--surface); border-radius:8px; font-size:.82rem; color:var(--text-muted)">
        💡 El timbrado CFDI estará disponible próximamente. Para solicitar tu factura fiscal, contacta a nuestro equipo de soporte.
    </div>

    {{-- Botón de pago: visible solo para facturas pendientes/atrasadas y con OpenPay habilitado --}}
    @php
        $esPendiente = ! str_contains(strtolower($factura->estado ?? ''), 'pagado');
        $openpayHabilitado = config('openpay.sandbox') === true;
    @endphp
    @if($esPendiente && $openpayHabilitado)
    <div style="margin-top:1.5rem; padding-top:1.5rem; border-top:1px solid var(--border)">
        <button onclick="opAbrirPagoModal()" class="btn btn-primary" style="font-size:1rem; padding:.65rem 1.75rem">
            💳 Pagar con tarjeta
        </button>
        <div style="font-size:.75rem; color:var(--text-muted); margin-top:.5rem">
            Pago seguro procesado por OpenPay. Tu número de tarjeta no es almacenado por nosotros.
        </div>
    </div>
    @endif
</div>

{{-- Modal de pago OpenPay (formulario de tarjeta) --}}
@if($esPendiente && $openpayHabilitado)
    @include('addon-portal-cliente::partials.openpay_modal', ['factura' => $factura])
@endif

@endsection
