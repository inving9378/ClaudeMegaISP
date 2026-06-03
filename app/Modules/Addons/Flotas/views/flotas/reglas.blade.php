@extends('core-layout::master')

@section('title')
    Flotas — Reglas de alertas
@endsection

@section('content')
    <Breadcrumb :list='[{"title":"Inicio"},{"title":"Flotas","url":"/flotas"},{"title":"Reglas de alertas","active":"active"}]'></Breadcrumb>
    <fleet-rule-list base-url="{{ url('/flotas') }}"></fleet-rule-list>
@endsection
