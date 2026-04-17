@extends('meganet.layout.master')

@section('content')
    <Breadcrumb :list=[{title:"Pagina"},{title:"Setting"},{title:"TemplateTask",active:"active"}]></Breadcrumb>
    <template-task-listar></template-task-listar>
    @if (session()->has('message'))
        <Message message="{{ session()->get('message') }}" module="TemplateTask"></Message>
    @endif
@endsection
