@extends('core-layout::master')

@section('title')
    Ticket de caja {{ $folio }}
@endsection

@section('content')
    <Breadcrumb :list='[{"title":"Inicio"},{"title":"Clientes"},{"title":"Pagos"},{"title":"Ticket de caja","active":"active"}]'></Breadcrumb>
    <ticket-caja :pago-id="{{ $pagoId }}" :cliente-id="{{ $clienteId }}" folio="{{ $folio }}" correo="{{ $correo }}"
                 :puede-enviar="{{ $puedeEnviar ? 'true' : 'false' }}"></ticket-caja>
@endsection
