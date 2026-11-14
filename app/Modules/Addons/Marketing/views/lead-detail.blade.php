@extends('core-layout::master')

@section('title')
    Detalle de Lead — Marketing
@endsection

@section('content')
    {{-- Ruta como Clientes (§2.13): mismo componente Breadcrumb y mismo lugar. --}}
    <Breadcrumb :list='[{"title":"Página"},{"title":"Marketing","link":"/marketing"},{"title":"Leads","link":"/marketing/leads"},{"title":"Lead #{{ (int) request()->route('id') }}","active":true}]'></Breadcrumb>
    <marketing-lead-detail lead-id="{{ request()->route('id') }}"></marketing-lead-detail>
@endsection
