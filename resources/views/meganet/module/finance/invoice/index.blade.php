@extends('meganet.layout.master')
@section('title') @lang('translation.Dashboard') @endsection

@section('content')
    <Breadcrumb
        :list=[{title:"Finanzas"},{title:"Facturas",active:"active"}]
    ></Breadcrumb>
    <Datatable
        module="finanzas/facturas"
        model="FinanceInvoice"
        list="Listado Facturas"
    ></Datatable>
    @if(session()->has('message'))
        <Message
            message="{{ session()->get('message') }}"
            module="FinanceInvoice"
        ></Message>
    @endif
@endsection


