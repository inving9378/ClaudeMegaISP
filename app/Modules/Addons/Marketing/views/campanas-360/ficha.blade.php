@extends('core-layout::master')

@section('title')
    Campaña 360
@endsection

@section('content')
    {{-- Ruta como Clientes (§2.13): mismo componente Breadcrumb y mismo lugar. --}}
    <Breadcrumb :list='[{"title":"Página"},{"title":"Marketing","link":"/marketing"},{"title":"Campañas 360","link":"/marketing/campanas-360"},{"title":"Campaña #{{ (int) $campanaId }}","active":true}]'></Breadcrumb>
    <marketing-campana-360-ficha :campana-id="{{ (int) $campanaId }}"></marketing-campana-360-ficha>
@endsection
