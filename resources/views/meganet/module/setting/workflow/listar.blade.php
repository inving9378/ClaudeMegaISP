@extends('meganet.layout.master')

@section('content')
    <Breadcrumb :list=[{title:"Pagina"},{title:"Setting"},{title:"WorkFlow",active:"active"}]></Breadcrumb>
    <work-flow-listar></work-flow-listar>
    @if (session()->has('message'))
        <Message message="{{ session()->get('message') }}" module="WorkFlow"></Message>
    @endif
@endsection
