@extends('meganet.layout.master')

@section('title')
    Documentación
@endsection

@section('content')
    
    <Breadcrumb :list="[
        {title: 'Inicio', link: '/home'},
        {title: 'Administración', link: '/administracion'},
        {title: 'Documentación', active: true}
    ]"></Breadcrumb>
    
    <documentation-menu-listar></documentation-menu-listar>
    
    @if (session()->has('message'))
        <Message message="{{ session()->get('message') }}" module="DocumentationMenu"></Message>
    @endif

@endsection