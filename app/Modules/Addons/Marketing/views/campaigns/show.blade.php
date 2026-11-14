@extends('core-layout::master')

@section('title')
    Campaña: {{ $campaign->title }}
@endsection

@section('content')
    {{-- Ruta como Clientes (§2.13): mismo componente Breadcrumb y mismo lugar. --}}
    <Breadcrumb :list='[{"title":"Página"},{"title":"Marketing","link":"/marketing"},{"title":"Campañas","active":true}]'></Breadcrumb>
    <marketing-campaign-show :campaign-id="{{ $campaign->id }}"></marketing-campaign-show>
@endsection
