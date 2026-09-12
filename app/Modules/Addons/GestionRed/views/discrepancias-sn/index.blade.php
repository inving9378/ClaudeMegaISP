@extends('core-layout::master')

@section('title')
    Gestión de Red — Discrepancias SN
@endsection

@section('content')
    <Breadcrumb :list='[{"title":"Inicio"},{"title":"Red","url":"/red/router/listar"},{"title":"Discrepancias SN","active":"active"}]'></Breadcrumb>
    <discrepancias-sn-report base-url="{{ url('/red/discrepancias-sn') }}"></discrepancias-sn-report>
@endsection
