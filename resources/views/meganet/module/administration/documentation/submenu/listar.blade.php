@extends('meganet.layout.master')

@section('title')
    Documentación
@endsection

@section('content')
    
    <Breadcrumb :list="[
        {title: 'Inicio', link: '/home'},
        {title: 'Administración', link: '/administracion'},
        {title: 'Documentación', link: '/administracion/documentation/documentation_menu'},
        {title: 'Subtítulos', active: true},
    ]"></Breadcrumb>
    
    <documentation-submenu-listar></documentation-submenu-listar>
    
    @if (session()->has('message'))
        <Message message="{{ session()->get('message') }}" module="DocumentationSubmenu"></Message>
    @endif

@endsection