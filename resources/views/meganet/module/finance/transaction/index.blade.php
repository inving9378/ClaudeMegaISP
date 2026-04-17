@extends('meganet.layout.master')
@section('title') @lang('translation.Dashboard') @endsection

@section('content')
    <Breadcrumb
        :list=[{title:"Finanzas"},{title:"Transaccion",active:"active"}]
    ></Breadcrumb>
    <Datatable
        module="finanzas/transacciones"
        model="FinanceTransaction"
        list="Listado Transaction"
    ></Datatable>
    @if(session()->has('message'))
        <Message
            message="{{ session()->get('message') }}"
            module="FinanceTransaction"
        ></Message>
    @endif
@endsection


