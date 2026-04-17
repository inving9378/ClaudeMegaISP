@extends('meganet.layout.master')

@section('content')
    <Breadcrumb :list=[{title:"Pagina"},{title:"Setting"},{title:"Team",active:"active"}]></Breadcrumb>
    <team-listar></team-listar>
    @if (session()->has('message'))
        <Message message="{{ session()->get('message') }}" module="Team"></Message>
    @endif
@endsection
