@extends('core-layout::master')

@section('title')
    API Móvil · Logs de acceso
@endsection

@section('content')
    <Breadcrumb :list='[{"title":"Inicio"},{"title":"Configuración"},{"title":"API Móvil"},{"title":"Logs de acceso","active":"active"}]'></Breadcrumb>
    <api-movil-logs
        csrf-token="{{ csrf_token() }}"
        base-url="{{ url('/configuracion/api-movil') }}"
    ></api-movil-logs>
@endsection
