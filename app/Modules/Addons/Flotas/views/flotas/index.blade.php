@extends('core-layout::master')

@section('title')
    Flotas — Dashboard
@endsection

@section('content')
    <Breadcrumb :list='[{"title":"Inicio"},{"title":"Flotas","active":"active"}]'></Breadcrumb>
    <fleet-dashboard base-url="{{ url('/flotas') }}"></fleet-dashboard>
@endsection
