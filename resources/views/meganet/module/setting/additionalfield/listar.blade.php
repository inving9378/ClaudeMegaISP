@extends('meganet.layout.master')

@section('content')
    <Breadcrumb :list=[{title:"Pagina"},{title:"Setting"},{title:"Campos_adicionales",active:"active"}]></Breadcrumb>
    <field-module-listar modules={{ json_encode($modules) }} module={{ $module }}></field-module-listar>
    @if (session()->has('message'))
        <Message message="{{ session()->get('message') }}" module="Socio"></Message>
    @endif
@endsection
