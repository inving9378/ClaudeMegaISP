@extends('meganet.layout.master')

@section('content')
    <Breadcrumb :list=[{title:"Pagina"},{title:"Setting"},{title:"Nomenclaturas",active:"active"}]></Breadcrumb>
    <nomenclature-listar></nomenclature-listar>
    @if (session()->has('message'))
        <Message message="{{ session()->get('message') }}" module="Socio"></Message>
    @endif
@endsection
