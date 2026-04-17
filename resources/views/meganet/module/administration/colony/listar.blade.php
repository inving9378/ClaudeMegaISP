@extends('meganet.layout.master')

@section('content')
    <Breadcrumb :list=[{title:"Pagina"},{title:"Administracion"},{title:"Colonia",active:"active"}]></Breadcrumb>
    <colony-listar filters="{{ json_encode($filters) }}"></colony-listar>
    @if (session()->has('message'))
        <Message message="{{ session()->get('message') }}" module="Socio"></Message>
    @endif
@endsection
