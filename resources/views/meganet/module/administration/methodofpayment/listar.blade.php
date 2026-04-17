@extends('meganet.layout.master')

@section('content')
    <Breadcrumb
        :list=[{title:"Pagina"},{title:"Administracion"},{title:"Metodo_de_Pago",active:"active"}]
    ></Breadcrumb>
    <method-of-payment-listar></method-of-payment-listar>
    @if(session()->has('message'))
        <Message
            message="{{ session()->get('message') }}"
            module="Socio"
        ></Message>
    @endif
@endsection
