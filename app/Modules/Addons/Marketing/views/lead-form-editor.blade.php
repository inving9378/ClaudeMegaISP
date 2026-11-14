@extends('core-layout::master')

@section('title')
    Editor de formulario — Marketing
@endsection

@section('content')
    {{-- Ruta como Clientes (§2.13): mismo componente Breadcrumb y mismo lugar. --}}
    <Breadcrumb :list='[{"title":"Página"},{"title":"Marketing","link":"/marketing"},{"title":"Leads","link":"/marketing/leads"},{"title":"Formularios de captura","link":"/marketing/lead-forms"},{"title":"{{ request()->route('id') ? 'Editar formulario' : 'Nuevo formulario' }}","active":true}]'></Breadcrumb>
    <marketing-lead-form-editor form-id="{{ request()->route('id') ?? '' }}"></marketing-lead-form-editor>
@endsection
