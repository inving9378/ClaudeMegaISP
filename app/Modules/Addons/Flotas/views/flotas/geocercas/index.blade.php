@extends('core-layout::master')

@section('title')
    Flotas — Geocercas
@endsection

@section('content')
    <Breadcrumb :list='[{"title":"Inicio"},{"title":"Flotas","url":"/flotas"},{"title":"Geocercas","active":"active"}]'></Breadcrumb>
    <fleet-geofence-list base-url="{{ url('/flotas') }}"></fleet-geofence-list>
@endsection
