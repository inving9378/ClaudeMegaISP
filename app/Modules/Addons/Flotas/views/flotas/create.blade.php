@extends('core-layout::master')

@section('title')
    Flotas — Nuevo vehículo
@endsection

@section('content')
    <Breadcrumb :list='[{"title":"Inicio"},{"title":"Flotas","url":"/flotas"},{"title":"Nuevo vehículo","active":"active"}]'></Breadcrumb>
    <fleet-vehicle-form base-url="{{ url('/flotas') }}"></fleet-vehicle-form>
@endsection
