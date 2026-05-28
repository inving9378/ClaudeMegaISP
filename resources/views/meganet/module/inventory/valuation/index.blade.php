@extends('core-layout::master')

@section('content')
    <Breadcrumb :list='[{"title":"Pagina"},{"title":"Inventario"},{"title":"Valuación de Inventario","active":true}]'></Breadcrumb>
    <inventory-valuation></inventory-valuation>
@endsection
