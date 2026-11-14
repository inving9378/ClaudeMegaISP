@extends('core-layout::master')
@section('title') Empresas y equipo · Marketing @endsection
@section('content')
    {{-- Ruta como Clientes (§2.13): mismo componente Breadcrumb y mismo lugar. --}}
    <Breadcrumb :list='[{"title":"Página"},{"title":"Marketing","link":"/marketing"},{"title":"Empresas y equipo","active":true}]'></Breadcrumb>
    <marketing-empresas-equipo :auth-user-id="{{ auth()->id() }}"></marketing-empresas-equipo>
@endsection
