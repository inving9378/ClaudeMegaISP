@extends('core-layout::master')

@section('title')
    Marketing
@endsection

@section('content')
    {{-- Ruta como Clientes (§2.13): mismo componente Breadcrumb y mismo lugar. --}}
    <Breadcrumb :list='[{"title":"Página"},{"title":"Marketing","link":"/marketing"},{"title":"Panel","active":true}]'></Breadcrumb>
    <marketing-dashboard :auth-user-id="{{ auth()->id() ?? 'null' }}"></marketing-dashboard>
@endsection
