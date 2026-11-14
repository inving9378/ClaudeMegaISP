@extends('core-layout::master')

@section('content')
    {{-- Ruta como Clientes (§2.13): mismo componente Breadcrumb y mismo lugar. --}}
    <Breadcrumb :list='[{"title":"Página"},{"title":"Marketing","link":"/marketing"},{"title":"Publicación","link":"/marketing/publishing"},{"title":"Fila de publicación","active":true}]'></Breadcrumb>
<div>
    <marketing-publication-queue-view></marketing-publication-queue-view>
</div>
@endsection
