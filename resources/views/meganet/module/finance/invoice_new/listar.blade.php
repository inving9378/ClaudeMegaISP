@extends('meganet.layout.master')
@section('title')
    @lang('translation.Dashboard')
@endsection

@section('content')
    <Breadcrumb :list=[{title:"Finanzas"},{title:"Facturas",active:"active"}]></Breadcrumb>
    <invoice-listar>

    </invoice-listar>
    @if (session()->has('message'))
        <Message message="{{ session()->get('message') }}" module="Invoice"></Message>
    @endif
@endsection
