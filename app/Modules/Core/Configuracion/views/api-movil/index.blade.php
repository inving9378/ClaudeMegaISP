@extends('core-layout::master')

@section('title')
    API Móvil · Configuración API
@endsection

@section('content')
    <Breadcrumb :list='[{"title":"Inicio"},{"title":"Configuración"},{"title":"API Móvil"},{"title":"Configuración API","active":"active"}]'></Breadcrumb>
    <api-movil-config
        csrf-token="{{ csrf_token() }}"
        base-url="{{ url('/configuracion/api-movil') }}"
    ></api-movil-config>
@endsection
