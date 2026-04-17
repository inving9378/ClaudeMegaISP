@extends('meganet.layout.master')
@section('title') @lang('translation.Dashboard') @endsection

@section('content')
    <Breadcrumb
        :list=[{title:"Ticket"},{title:"Cerrados",active:"active"}]
    ></Breadcrumb>
    <Datatable
        module="tickets"
        model="Ticket"
        add="Agregar Ticket"
        list="Listado Ticket Cerrados"
        :filters="{{ $filters }}"
    ></Datatable>
    @if(session()->has('message'))
        <Message
            message="{{ session()->get('message') }}"
            module="tickets"
        ></Message>
    @endif
@endsection
