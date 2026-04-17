@extends('meganet.layout.master')

@section('content')
    <Breadcrumb :list=[{title:"Pagina"},{title:"Administracion"},{title:"Tipo_Plantillas",active:"active"}]></Breadcrumb>
    <type-template-listar></type-template-listar>
    @if (session()->has('message'))
        <Message message="{{ session()->get('message') }}" module="Socio"></Message>
    @endif
@endsection
