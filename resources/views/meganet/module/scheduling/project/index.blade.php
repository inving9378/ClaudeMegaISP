@extends('meganet.layout.master')
@section('title')
    @lang('translation.Dashboard')
@endsection

@section('content')
    <Breadcrumb :list=[{title:"Pagina"},{title:"Proyectos",active:"active"}]></Breadcrumb>
    <project-listar></project-listar>
    @if (session()->has('message'))
        <Message message="{{ session()->get('message') }}" module="Project"></Message>
    @endif
@endsection
