@extends('meganet.layout.master')

@section('content')
    <Breadcrumb :list=[{title:"Pagina"},{title:"Inventario"},{title:"Articulos",active:"active"}]></Breadcrumb>
    <inventory-item-stock-listar :module_id={{ $module_id }} url_base={{ asset('storage/') }}></inventory-item-stock-listar>
    @if (session()->has('message'))
        <Message message="{{ session()->get('message') }}" module="InventoryItemStock"></Message>
    @endif
@endsection
