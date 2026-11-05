@extends('core-layout::master')

@section('title')
    Monitoreo de dedicados · Configuración
@endsection

@section('content')
    <Breadcrumb :list='[{"title":"Inicio"},{"title":"Configuración"},{"title":"Monitoreo de dedicados","active":"active"}]'></Breadcrumb>
    <monitoreo-dedicados-config></monitoreo-dedicados-config>
@endsection
