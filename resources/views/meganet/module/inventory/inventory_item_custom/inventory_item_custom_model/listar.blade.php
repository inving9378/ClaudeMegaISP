@extends('meganet.layout.master')
@section('title') @lang('translation.Dashboard') @endsection

@section('content')
    <Breadcrumb
        :list=[{title:"Inventario"},{title:"Inventario",active:"active"}]
    ></Breadcrumb>
   
    <inventory-item-custom-model-listar :module_id={{ $module_id }} url_base={{ asset('storage/') }}></inventory-item-custom-model-listar>
    @if(session()->has('message'))
        <Message
            message="{{ session()->get('message') }}"
            module="InventoryItemCustomModel"
        ></Message>
    @endif
@endsection
