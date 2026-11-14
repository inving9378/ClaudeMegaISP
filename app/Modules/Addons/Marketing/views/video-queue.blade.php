@extends('core-layout::master')

@section('content')
    {{-- Ruta como Clientes (§2.13): mismo componente Breadcrumb y mismo lugar. --}}
    <Breadcrumb :list='[{"title":"Página"},{"title":"Marketing","link":"/marketing"},{"title":"Reels y videos","link":"/marketing/video-templates"},{"title":"Fila de producción","active":true}]'></Breadcrumb>
<div>
    <marketing-video-queue-view></marketing-video-queue-view>
</div>
@endsection
