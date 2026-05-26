@extends('core-layout::master')

@section('title')
    MegaFamilia · Geofences
@endsection

@section('content')
    <Breadcrumb :list='[{"title":"Inicio"},{"title":"MegaFamilia"},{"title":"Geofences","active":"active"}]'></Breadcrumb>
    <mega-familia-geofences
        csrf-token="{{ csrf_token() }}"
        base-url="{{ url('/megafamilia') }}"
    ></mega-familia-geofences>
@endsection
