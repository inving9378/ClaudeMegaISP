@extends('core-layout::master')

@section('title')
    Flotas — Geocerca
@endsection

@section('content')
    <Breadcrumb :list='[{"title":"Inicio"},{"title":"Flotas","url":"/flotas"},{"title":"Geocercas","url":"/flotas/geocercas"},{"title":"Detalle","active":"active"}]'></Breadcrumb>
    <fleet-geofence-show base-url="{{ url('/flotas') }}"></fleet-geofence-show>
@endsection
