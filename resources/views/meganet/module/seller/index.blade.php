@extends('core-layout::master')

@section('title')
    @lang('translation.Dashboard')
@endsection

@section('content')
    <Breadcrumb
        :list=[{title:"Página"},{title:"Vendedor",active:"active"}]
    ></Breadcrumb>
    <Datatable
        module="vendedor"
        model="Seller"
        @if(auth()->user()->can('seller_add_seller'))
        add="Agregar Vendedor"
        @endif
        list="Listado de Vendedores"
    ></Datatable>
    @if(session()->has('message'))
        <Message
            message="{{ session()->get('message') }}"
            module="Vendedor"
        ></Message>
    @endif
@endsection
