@extends('core-layout::master')

@section('title')
    {{ $campanaId ? 'Editar campaña 360' : 'Nueva campaña 360' }}
@endsection

@section('content')
    {{-- Ruta como Clientes (§2.13): mismo componente Breadcrumb y mismo lugar. --}}
    <Breadcrumb :list='[{"title":"Página"},{"title":"Marketing","link":"/marketing"},{"title":"Campañas 360","link":"/marketing/campanas-360"},{"title":"{{ $campanaId ? 'Editar campaña' : 'Nueva campaña' }}","active":true}]'></Breadcrumb>
    <marketing-campana-360-asistente :campana-id="{{ $campanaId ? (int) $campanaId : 'null' }}"></marketing-campana-360-asistente>
@endsection
