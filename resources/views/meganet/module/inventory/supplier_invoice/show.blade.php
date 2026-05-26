@extends('core-layout::master')

@section('content')
    <breadcrumb :list='[{"title":"Pagina"},{"title":"Inventario"},{"title":"Facturas de Proveedores","href":"/inventory/supplier-invoice"},{"title":"Detalle","active":true}]'></breadcrumb>
    <supplier-invoice-show :invoice_id="{{ $id }}" :invoice="{{ $invoice }}"></supplier-invoice-show>
@endsection