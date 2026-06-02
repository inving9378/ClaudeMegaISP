@extends('core-layout::master')

@section('title')
    Catálogo de servicios contratables · Planes
@endsection

@section('content')
    <Breadcrumb :list='[{"title":"Planes"},{"title":"Servicios contratables","active":"active"}]'></Breadcrumb>
    <catalogo-servicios-panel></catalogo-servicios-panel>
@endsection
