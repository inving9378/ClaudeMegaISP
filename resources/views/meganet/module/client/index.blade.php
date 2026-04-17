@extends('meganet.layout.master')
@section('title')
    @lang('translation.Dashboard')
@endsection

@section('content')
    <Breadcrumb :list=[{title:"Pagina"},{title:"Cliente",active:"active"}]></Breadcrumb>
    <Datatable-Client module="cliente" model="Client" list="Listado de Clientes" status="{{ json_encode($status) }}"
        color_datatable ="{{ $color_datatable }}" all_columns_by_module="{{ json_encode($allColumnsByModule) }}"
        header_columns_by_module ="{{ json_encode($columnsByUserAuthAndModule) }}"
        @if (isset($filters)) filters="{{ $filters }}" @endif
        @can($group . '_add_' . $module)
        add="Agregar Cliente"
        @endcan
        array_all_status="{{ json_encode($allStatusToFilter) }}">
    </Datatable-Client>
    @if (session()->has('message'))
        <Message message="{{ session()->get('message') }}" module="Cliente"></Message>
    @endif
@endsection
