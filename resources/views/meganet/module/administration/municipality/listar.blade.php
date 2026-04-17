@extends('meganet.layout.master')

@section('content')
    <Breadcrumb :list=[{title:"Pagina"},{title:"Administracion"},{title:"Municipio",active:"active"}]></Breadcrumb>
    <municipality-listar filters="{{ json_encode($filters) }}"></municipality-listar>
    @if (session()->has('message'))
        <Message message="{{ session()->get('message') }}" module="Socio"></Message>
    @endif
@endsection
