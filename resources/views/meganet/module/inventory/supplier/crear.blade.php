@extends('core-layout::master')

@section('content')
    <Breadcrumb :list='[{"title":"Pagina"},{"title":"Inventario"},{"title":"Proveedores","href":"/inventory/supplier"},{"title":"Crear Proveedor","active":true}]'></Breadcrumb>
    <supplier-crear action="{{ $action }}" id="{{ $id }}"></supplier-crear>
@endsection
