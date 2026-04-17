@extends('meganet.layout.master')
@section('title') @lang('translation.Dashboard') @endsection

@section('content')
    <Breadcrumb
        :list=[{title:"Planes"},{title:"Voz",active:"active"}]
    ></Breadcrumb>
    <Datatable
        module="voz"
        model="Voise"
        @can($group.'_add_'.$module)
        add="Agregar Plan Voz"
        @endcan
        list="Listado de Planes de Voz"
    ></Datatable>
    @if(session()->has('message'))
        <Message
            message="{{ session()->get('message') }}"
            module="Voz"
        ></Message>
    @endif
@endsection
