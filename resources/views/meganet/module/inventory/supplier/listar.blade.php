@extends('core-layout::master')

@section('content')
    <Breadcrumb :list='[{"title":"Pagina"},{"title":"Inventario"},{"title":"Proveedores","active":true}]'></Breadcrumb>
    <supplier-listar id="{{ $id }}"></supplier-listar>
@endsection
