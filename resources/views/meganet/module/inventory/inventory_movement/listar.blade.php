@extends('meganet.layout.master')

@section('content')
    <Breadcrumb :list=[{title:"Pagina"},{title:"Inventario"},{title:"Listado",active:"active"}]></Breadcrumb>
    <inventory-movement-listar module_id={{ $module_id }}></inventory-movement-listar>
    @if (session()->has('message'))
        <Message message="{{ session()->get('message') }}" module="InventoryMovement"></Message>
    @endif
@endsection
