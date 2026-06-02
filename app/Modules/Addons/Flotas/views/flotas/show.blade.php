@extends('core-layout::master')

@section('title')
    Flotas — Vehículo
@endsection

@section('content')
    <Breadcrumb :list='[{"title":"Inicio"},{"title":"Flotas","url":"/flotas"},{"title":"Vehículo","active":"active"}]'></Breadcrumb>
    <fleet-vehicle-show base-url="{{ url('/flotas') }}"></fleet-vehicle-show>
@endsection
