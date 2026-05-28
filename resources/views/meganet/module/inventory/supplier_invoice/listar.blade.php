@extends('core-layout::master')

@section('content')
    <Breadcrumb :list='[{"title":"Pagina"},{"title":"Inventario"},{"title":"Facturas de Proveedores","active":true}]'></Breadcrumb>
    <supplier-invoice-listar></supplier-invoice-listar>
@endsection
