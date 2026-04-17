@extends('meganet.layout.master')
@section('title') @lang('translation.Dashboard') @endsection

@section('content')
    <Breadcrumb
        :list=[{title:"Planes"},{title:"Internet",active:"active"}]
    ></Breadcrumb>
    <Datatable
        module="internet"
        model="Internet"
        @can($group.'_add_'.$module)
        add="Agregar plan de Internet"
        @endcan
        list="Listado de planes de Internet"
    ></Datatable>
    @if(session()->has('message'))
        <Message
            message="{{ session()->get('message') }}"
            module="Internet"
        ></Message>
    @endif
@endsection


