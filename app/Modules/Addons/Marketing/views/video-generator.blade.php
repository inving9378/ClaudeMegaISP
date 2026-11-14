@extends('core-layout::master')

@section('content')
    {{-- Ruta como Clientes (§2.13): mismo componente Breadcrumb y mismo lugar. --}}
    <Breadcrumb :list='[{"title":"Página"},{"title":"Marketing","link":"/marketing"},{"title":"Reels y videos","link":"/marketing/video-templates"},{"title":"Generar","active":true}]'></Breadcrumb>
<div>
    <marketing-video-generator-view></marketing-video-generator-view>
</div>
@endsection
