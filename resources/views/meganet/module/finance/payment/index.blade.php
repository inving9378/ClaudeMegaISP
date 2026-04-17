@extends('meganet.layout.master')
@section('title') @lang('translation.Dashboard') @endsection

@section('content')
    <Breadcrumb
        :list=[{title:"Finanzas"},{title:"Pagos",active:"active"}]
    ></Breadcrumb>
    <payment-listar></payment-listar>
    @if(session()->has('message'))
        <Message
            message="{{ session()->get('message') }}"
            module="FinancePayment"
        ></Message>
    @endif
@endsection


