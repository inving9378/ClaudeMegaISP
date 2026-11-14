@extends('core-layout::master')

@section('content')
    {{-- Ruta como Clientes (§2.13): mismo componente Breadcrumb y mismo lugar. --}}
    <Breadcrumb :list='[{"title":"Página"},{"title":"Marketing","link":"/marketing"},{"title":"Configuración","link":"/marketing/brand-kit"},{"title":"Brand Kit","active":true}]'></Breadcrumb>
<div>
    <marketing-brand-kit-view></marketing-brand-kit-view>
</div>
@endsection
