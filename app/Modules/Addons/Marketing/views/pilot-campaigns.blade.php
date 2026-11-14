@extends('core-layout::master')

@section('content')
    {{-- Ruta como Clientes (§2.13): mismo componente Breadcrumb y mismo lugar. --}}
    <Breadcrumb :list='[{"title":"Página"},{"title":"Marketing","link":"/marketing"},{"title":"Pruebas A/B","active":true}]'></Breadcrumb>
<div>
    <marketing-pilot-campaigns-view></marketing-pilot-campaigns-view>
</div>
@endsection
