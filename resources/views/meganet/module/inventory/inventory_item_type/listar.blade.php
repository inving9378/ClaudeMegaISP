@extends('meganet.layout.master')

@section('content')
    <Breadcrumb :list=[{title:"Pagina"},{title:"Inventario"},{title:"Tipo_de_articulos",active:"active"}]></Breadcrumb>
    <inventory-item-type-listar></inventory-item-type-listar>
    @if (session()->has('message'))
        <Message message="{{ session()->get('message') }}" module="InventoryItemType"></Message>
    @endif
@endsection
