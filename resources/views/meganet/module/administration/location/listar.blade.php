@extends('meganet.layout.master')

@section('content')
    <Breadcrumb
        :list=[{title:"Pagina"},{title:"Administracion"},{title:"Ubicacion",active:"active"}]
    ></Breadcrumb>
    <location-listar></location-listar>
    @if(session()->has('message'))
        <Message
            message="{{ session()->get('message') }}"
            module="Socio"
        ></Message>
    @endif
@endsection
