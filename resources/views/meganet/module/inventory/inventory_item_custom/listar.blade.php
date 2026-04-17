@extends('meganet.layout.master')

@section('content')
    <Breadcrumb :list=[{title:"Pagina"},{title:"Inventario"},{title:"Articulos",active:"active"}]></Breadcrumb>
    <inventory-item-custom-listar :custom_model_id= {{$custom_model_id}} :module_id={{ $module_id }} url_base={{ asset('storage/') }} persistent_filters='@json($persistentFilters)'></inventory-item-custom-listar>
    @if (session()->has('message'))
        <Message message="{{ session()->get('message') }}" module="InventoryItemStock"></Message>
    @endif
@endsection