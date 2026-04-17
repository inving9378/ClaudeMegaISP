@extends('meganet.layout.master')

@section('content')
    <Breadcrumb
        :list=[{title:"Pagina"},{title:"Administracion"},{title:"Ift",active:"active"}]
    ></Breadcrumb>
    <ift-listar></ift-listar>
    @if(session()->has('message'))
        <Message
            message="{{ session()->get('message') }}"
            module="Socio"
        ></Message>
    @endif
@endsection
