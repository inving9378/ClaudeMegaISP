@extends('core-layout::master')

@section('title')
    Flotas — Geocerca
@endsection

@section('content')
    <Breadcrumb :list='[{"title":"Inicio"},{"title":"Flotas","url":"/flotas"},{"title":"Geocercas","url":"/flotas/geocercas"},{"title":"Editor","active":"active"}]'></Breadcrumb>
    <fleet-geofence-form base-url="{{ url('/flotas') }}"></fleet-geofence-form>
@endsection
