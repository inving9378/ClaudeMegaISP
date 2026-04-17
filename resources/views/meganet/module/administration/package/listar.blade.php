@extends('meganet.layout.master')

@section('content')
    <Breadcrumb
        :list=[{title:"Pagina"},{title:"Administracion"},{title:"Paquete",active:"active"}]
    ></Breadcrumb>
    <package-list></package-list>
    @if(session()->has('message'))
        <Message
            message="{{ session()->get('message') }}"
            module="Paquete"
        ></Message>
    @endif
@endsection
