@extends('meganet.layout.master')

@section('content')
    <Breadcrumb :list=[{title:"Pagina"},{title:"Setting"},{title:"WorkFlow",active:"active"}]></Breadcrumb>
    <list-template-verification-listar></list-template-verification-listar>
    @if (session()->has('message'))
        <Message message="{{ session()->get('message') }}" module="ListTemplateVerification"></Message>
    @endif
@endsection
