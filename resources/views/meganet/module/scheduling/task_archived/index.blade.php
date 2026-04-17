@extends('meganet.layout.master')
@section('title')
    @lang('translation.Dashboard')
@endsection

@section('content')
    <Breadcrumb :list=[{title:"Pagina"},{title:"Tareas",active:"active"}]></Breadcrumb>
    <task-listar persistent_filters='@json($persistentFilters)' module_id={{ $module_id }} filters ='@json($filters)'></task-listar>
    @if (session()->has('message'))
        <Message message="{{ session()->get('message') }}" module="Task">
        </Message>
    @endif
@endsection
