@extends('meganet.layout.master')

@section('content')
    <Breadcrumb :list=[{title:"Pagina"},{title:"Imports"},{title:"Elementos_importados",active:"active"}]></Breadcrumb>
    <import-listar module={{ $module }}></import-listar>
    @if (session()->has('message'))
        <Message message="{{ session()->get('message') }}" module="Socio"></Message>
    @endif
@endsection
