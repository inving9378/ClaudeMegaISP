@extends('core-layout::master')

@section('title')
    Formularios web — Marketing
@endsection

@section('content')
    {{-- Ruta como Clientes (§2.13): mismo componente Breadcrumb y mismo lugar. --}}
    <Breadcrumb :list='[{"title":"Página"},{"title":"Marketing","link":"/marketing"},{"title":"Leads","link":"/marketing/leads"},{"title":"Formularios de captura","active":true}]'></Breadcrumb>
    <marketing-lead-forms-view></marketing-lead-forms-view>
@endsection
