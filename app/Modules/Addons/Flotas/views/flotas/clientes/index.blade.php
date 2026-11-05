@extends('core-layout::master')

@section('title')
    Flotas — Flotas de clientes
@endsection

@section('content')
    <Breadcrumb :list='[{"title":"Inicio"},{"title":"Flotas","url":"/flotas"},{"title":"Flotas de clientes","active":"active"}]'></Breadcrumb>
    <fleet-clients-admin base-url="{{ url('/flotas') }}"></fleet-clients-admin>
@endsection
