@extends('core-layout::master')

@section('content')
    {{-- Ruta como Clientes (§2.13): mismo componente Breadcrumb y mismo lugar. --}}
    <Breadcrumb :list='[{"title":"Página"},{"title":"Marketing","link":"/marketing"},{"title":"Publicación","link":"/marketing/publishing"},{"title":"Resumen","active":true}]'></Breadcrumb>
<div>
    <marketing-publishing-dashboard-view></marketing-publishing-dashboard-view>
</div>
@endsection
