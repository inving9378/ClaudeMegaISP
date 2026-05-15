@extends('core-layout::master')

@section('title')
    API Móvil · Tokens activos
@endsection

@section('content')
    <Breadcrumb :list='[{"title":"Inicio"},{"title":"Configuración"},{"title":"API Móvil"},{"title":"Tokens activos","active":"active"}]'></Breadcrumb>
    <api-movil-tokens
        csrf-token="{{ csrf_token() }}"
        base-url="{{ url('/configuracion/api-movil') }}"
    ></api-movil-tokens>
@endsection
