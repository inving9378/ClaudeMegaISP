@extends('core-layout::master')

@section('title')
    Catálogo de API · Configuración
@endsection

@section('content')
    <Breadcrumb :list='[{"title":"Inicio"},{"title":"Configuración"},{"title":"Catálogo de API","active":"active"}]'></Breadcrumb>
    <catalogo-api-panel></catalogo-api-panel>
@endsection
