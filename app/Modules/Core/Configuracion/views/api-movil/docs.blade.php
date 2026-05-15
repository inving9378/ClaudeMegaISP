@extends('core-layout::master')

@section('title')
    API Móvil · Documentación API
@endsection

@section('content')
    <Breadcrumb :list='[{"title":"Inicio"},{"title":"Configuración"},{"title":"API Móvil"},{"title":"Documentación API","active":"active"}]'></Breadcrumb>
    <api-movil-docs
        csrf-token="{{ csrf_token() }}"
        base-url="{{ url('/configuracion/api-movil') }}"
    ></api-movil-docs>
@endsection
