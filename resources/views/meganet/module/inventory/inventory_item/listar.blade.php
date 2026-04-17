@extends('meganet.layout.master')

@section('content')
    <Breadcrumb :list=[{title:"Pagina"},{title:"Inventario"},{title:"Listado",active:"active"}]></Breadcrumb>
    <inventory-item-listar module_id={{ $module_id }}></inventory-item-listar>
    @if (session()->has('message'))
        <Message message="{{ session()->get('message') }}" module="InventoryItem"></Message>
    @endif
@endsection
