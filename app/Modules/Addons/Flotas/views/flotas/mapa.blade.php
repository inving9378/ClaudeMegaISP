@extends('core-layout::master')

@section('title')
    Flotas — Mapa
@endsection

@section('content')
    <Breadcrumb :list='[{"title":"Inicio"},{"title":"Flotas","url":"/flotas"},{"title":"Mapa","active":"active"}]'></Breadcrumb>
    <fleet-map base-url="{{ url('/flotas') }}"></fleet-map>
@endsection
