@extends('core-layout::master')

@section('title')
    Campañas 360
@endsection

@section('content')
    {{-- Ruta como Clientes (§2.13): mismo componente Breadcrumb y mismo lugar. --}}
    <Breadcrumb :list='[{"title":"Página"},{"title":"Marketing","link":"/marketing"},{"title":"Campañas 360","active":true}]'></Breadcrumb>
    <marketing-campanas-360-lista></marketing-campanas-360-lista>
@endsection
