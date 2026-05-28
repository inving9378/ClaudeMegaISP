@extends('core-layout::master')

@section('content')
    <Breadcrumb :list='[{"title":"Pagina"},{"title":"Inventario"},{"title":"Proveedores","href":"/inventory/supplier"},{"title":"Proveedor"}, {"title":"Crear Vendedor"}]'></Breadcrumb>
    <supplier-vendor-crear action="{{ $action }}" :id="{{ $id }}" :supplier_id="{{ $supplier_id }}"></supplier-vendor-crear>
@endsection
