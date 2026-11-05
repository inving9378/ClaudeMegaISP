@extends('core-layout::master')

@section('title')
    Flotas — Detalle de cliente
@endsection

@section('content')
    <Breadcrumb :list='[{"title":"Inicio"},{"title":"Flotas","url":"/flotas"},{"title":"Flotas de clientes","url":"/flotas/clientes"},{"title":"Detalle","active":"active"}]'></Breadcrumb>
    <fleet-clients-admin base-url="{{ url('/flotas') }}" :client-id="{{ (int) request()->route('id') }}"></fleet-clients-admin>
@endsection
