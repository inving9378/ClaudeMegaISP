@extends('meganet.layout.master')
@section('title')
    @lang('translation.Dashboard')
@endsection

@section('content')
    <Breadcrumb :list=[{title:"Pagina"},{title:"Crm",active:"active"}]></Breadcrumb>
    <Crm-Datatable module="crm" model="Crm" @can($group . '_add_' . $module)
        add="Agregar Crm"
        @endcan
        list="Listado de Crm"></Crm-Datatable>
    @if (session()->has('message'))
        <Message message="{{ session()->get('message') }}" module="Crm"></Message>
    @endif
@endsection
