@extends('meganet.layout.master')
@section('title')
    @lang('translation.Dashboard')
@endsection

@section('content')
    <Breadcrumb :list=[{title:"Pagina"},{title:"DocumentTemplate",active:"active"}]></Breadcrumb>
    <Document-Template-Listar filters="{{ json_encode($filters) }}"></Document-Template-Listar>
    @if (session()->has('message'))
        <Message message="{{ session()->get('message') }}" module="DocumentTemplate"></Message>
    @endif
@endsection
