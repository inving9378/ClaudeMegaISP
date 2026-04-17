@extends('meganet.layout.master')
@section('title') @lang('translation.Dashboard') @endsection

{{-- Comment --}}
@section('content')
    <Breadcrumb
        :list=[{title:"Pagina"},{title:"Administracion"},{title:"Roles",active:"active"}]
    ></Breadcrumb>
    {{-- <rol-listar></rol-listar> --}}
    <listar-rol></listar-rol>
    @if(session()->has('message'))
        <Message
            message="{{ session()->get('message') }}"
            module="Socio"
        ></Message>
    @endif
@endsection
