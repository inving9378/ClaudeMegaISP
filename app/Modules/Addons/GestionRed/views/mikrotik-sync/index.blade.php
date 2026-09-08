@extends('core-layout::master')

@section('title')
    Gestión de Red — Sync Mikrotik
@endsection

@section('content')
    <Breadcrumb :list='[{"title":"Inicio"},{"title":"Red","url":"/red/router/listar"},{"title":"Sync Mikrotik","active":"active"}]'></Breadcrumb>
    <mikrotik-sync-dashboard base-url="{{ url('/red/mikrotik-sync') }}"></mikrotik-sync-dashboard>
@endsection
