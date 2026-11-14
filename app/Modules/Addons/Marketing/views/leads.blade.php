@extends('core-layout::master')

@section('title')
    Leads — Marketing
@endsection

@section('content')
    {{-- Ruta como Clientes (§2.13): mismo componente Breadcrumb y mismo lugar. --}}
    <Breadcrumb :list='[{"title":"Página"},{"title":"Marketing","link":"/marketing"},{"title":"Leads","active":true}]'></Breadcrumb>
    <marketing-leads-view></marketing-leads-view>
@endsection
