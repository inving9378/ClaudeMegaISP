@extends('meganet.layout.master')

@section('content')
    <Breadcrumb :list=[{title:"Pagina"},{title:"Inventario"},{title:"Almacen",active:true}]></Breadcrumb>
    <inventory-store-listar></inventory-store-listar>
    @if (session()->has('message'))
        <Message message="{{ session()->get('message') }}" module="InventoryItemType"></Message>
    @endif
@endsection
