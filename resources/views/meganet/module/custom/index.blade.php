@extends('meganet.layout.master')
@section('title') @lang('translation.Dashboard') @endsection

@section('content')
    <Breadcrumb
        :list=[{title:"Planes"},{title:"Custom",active:"active"}]
    ></Breadcrumb>
    <Datatable
        module="custom"
        model="Custom"
        @can($group.'_add_'.$module)
        add="Agregar Plan Custom"
        @endcan
        list="Listado de Planes Personalizados"
    ></Datatable>
    @if(session()->has('message'))
        <Message
            message="{{ session()->get('message') }}"
            module="Custom"
        ></Message>
    @endif
@endsection
