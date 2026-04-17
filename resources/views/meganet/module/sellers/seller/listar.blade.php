@extends('meganet.layout.master')

@section('content')
    <Breadcrumb :list=[{title:"Pagina"},{title:"Vendedores"},{title:"Vendedores",active:"active"}]></Breadcrumb>
    <seller-listar></seller-listar>
    @if (session()->has('message'))
        <Message message="{{ session()->get('message') }}" module="Seller"></Message>
    @endif
@endsection
