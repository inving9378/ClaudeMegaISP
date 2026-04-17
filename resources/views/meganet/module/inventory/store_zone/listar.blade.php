@extends('meganet.layout.master')

@section('content')
    <Breadcrumb :list=[{title:"Pagina"},{title:"Inventario"},{title:"Zonas",active:"active"}]></Breadcrumb>
    <store-zone-listar persistent_filters='@json($persistentFilters)'></store-zone-listar>
    @if (session()->has('message'))
        <Message message="{{ session()->get('message') }}" module="StoreZone"></Message>
    @endif
@endsection
